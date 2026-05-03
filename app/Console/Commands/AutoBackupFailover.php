<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\ServerSecurityAlert;
use App\Services\CloudnsService;
use Illuminate\Console\Command;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

class AutoBackupFailover extends Command
{
    protected $signature = 'servers:auto-backup-failover';

    protected $description = 'Auto transfer backups and update ClouDNS records when server disk is high';

    public function handle(CloudnsService $cloudns): int
    {
        $servers = Server::query()
            ->where('is_active', 1)
            ->where('auto_transfer', 1)
            ->whereNotNull('backup_server_id')
            ->get();

        $this->info('Checking auto backup/failover for ' . $servers->count() . ' servers...');

        foreach ($servers as $server) {
            try {
                $backupServer = Server::find($server->backup_server_id);

                if (!$backupServer) {
                    $this->warn($server->name . ': backup server not found.');
                    continue;
                }

                $diskUsage = $this->getDiskUsage($server);

                $transferLimit = (int) ($server->disk_transfer_percent ?? 90);

                $this->line($server->name . ' disk usage: ' . $diskUsage . '% / limit ' . $transferLimit . '%');

                if ($diskUsage < $transferLimit) {
                    continue;
                }

                if ($server->last_failover_at && $server->last_failover_at->gt(now()->subHours(6))) {
                    $this->warn($server->name . ': failover already ran recently. Skipping.');
                    continue;
                }

                $this->transferBackup($server, $backupServer);

                if (!empty($server->google_drive_sync)) {
                    $this->syncGoogleDrive($server);
                }

                if (!empty($server->dns_failover_enabled) && !empty($server->linked_domain)) {
                    $cloudns->updateARecordsToIp($server->linked_domain, $backupServer->host);

                    $server->update([
                        'original_ip' => $server->original_ip ?: $server->host,
                        'active_dns_ip' => $backupServer->host,
                    ]);

                    $this->createAlert(
                        $server,
                        'dns',
                        'danger',
                        'ClouDNS failover activated',
                        "Disk reached {$diskUsage}%. Domain {$server->linked_domain} A record changed to backup IP {$backupServer->host}."
                    );
                }

                $server->update([
                    'last_failover_at' => now(),
                    'last_failover_reason' => "Disk usage {$diskUsage}% reached transfer limit {$transferLimit}%.",
                ]);

                $this->createAlert(
                    $server,
                    'backup',
                    'danger',
                    'Auto backup transfer completed',
                    "Disk usage {$diskUsage}%. Backup transferred to {$backupServer->name} ({$backupServer->host})."
                );

                $this->info($server->name . ': auto backup/failover completed.');

            } catch (\Throwable $e) {
                $this->error($server->name . ': ' . $e->getMessage());

                $this->createAlert(
                    $server,
                    'backup',
                    'danger',
                    'Auto backup/failover failed',
                    $e->getMessage()
                );
            }
        }

        return self::SUCCESS;
    }

    private function getDiskUsage(Server $server): int
    {
        $ssh = $this->ssh($server);

        $disk = trim($ssh->exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'"));

        return is_numeric($disk) ? (int) $disk : 0;
    }

    private function transferBackup(Server $server, Server $backupServer): void
    {
        $sourcePath = rtrim($server->backup_path ?: '/backup', '/');
        $backupPath = rtrim($backupServer->backup_path ?: '/backup', '/');

        $sourcePassword = $this->getPassword($server);
        $backupPassword = $this->getPassword($backupServer);

        $ssh = $this->ssh($server);

        $backupHost = $backupServer->host;
        $backupUser = $backupServer->username ?: 'root';
        $backupPort = $backupServer->ssh_port ?: 22;

        $remoteTarget = "{$backupUser}@{$backupHost}:{$backupPath}/{$server->name}-" . now()->format('Ymd-His') . "/";

        $cmd = "
            mkdir -p {$sourcePath} 2>/dev/null;
            command -v rsync >/dev/null 2>&1 || echo 'RSYNC_NOT_FOUND';
            rsync -az --delete -e 'ssh -p {$backupPort} -o StrictHostKeyChecking=no' {$sourcePath}/ {$remoteTarget} 2>&1
        ";

        $output = $ssh->exec($cmd);

        if (str_contains($output, 'RSYNC_NOT_FOUND')) {
            throw new \Exception('rsync is not installed on source server.');
        }

        if (str_contains(strtolower($output), 'permission denied')) {
            throw new \Exception('Backup transfer permission denied: ' . $output);
        }

        $this->createAlert(
            $server,
            'backup',
            'info',
            'Backup transfer output',
            $output ?: 'Transfer command completed.'
        );
    }

    private function syncGoogleDrive(Server $server): void
    {
        $ssh = $this->ssh($server);

        $sourcePath = rtrim($server->backup_path ?: '/backup', '/');
        $remote = trim($server->google_drive_remote ?: '');

        if (empty($remote)) {
            throw new \Exception('Google Drive remote name is empty.');
        }

        $cmd = "
            command -v rclone >/dev/null 2>&1 || echo 'RCLONE_NOT_FOUND';
            rclone copy {$sourcePath}/ {$remote}:server-backups/{$server->name}/ --progress 2>&1
        ";

        $output = $ssh->exec($cmd);

        if (str_contains($output, 'RCLONE_NOT_FOUND')) {
            throw new \Exception('rclone is not installed/configured on source server.');
        }

        $this->createAlert(
            $server,
            'backup',
            'info',
            'Google Drive backup sync output',
            $output ?: 'Google Drive sync completed.'
        );
    }

    private function ssh(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
        $ssh->setTimeout(60);

        if (!$ssh->login($server->username, $this->getPassword($server))) {
            throw new \Exception('SSH login failed for ' . $server->name);
        }

        return $ssh;
    }

    private function getPassword(Server $server): string
    {
        try {
            return decrypt($server->password);
        } catch (\Throwable $e) {
            return $server->password;
        }
    }

    private function createAlert(Server $server, string $type, string $level, string $title, string $message): void
    {
        if (!class_exists(ServerSecurityAlert::class)) {
            return;
        }

        try {
            ServerSecurityAlert::create([
                'server_id' => $server->id,
                'type' => $type,
                'level' => $level,
                'title' => $title,
                'message' => $message,
                'detected_at' => now(),
            ]);
        } catch (\Throwable $e) {
            //
        }
    }
}