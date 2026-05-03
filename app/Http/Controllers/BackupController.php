<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerSecurityAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class BackupController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | BACKUP DASHBOARD
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $servers = Server::latest()->get();

        /*
        |--------------------------------------------------------------------------
        | Load cPanel accounts for selectable backup transfer
        |--------------------------------------------------------------------------
        */
        $serverAccounts = [];

        foreach ($servers as $server) {
            $serverAccounts[$server->id] = [];

            try {
                $response = $this->whmRequest($server, 'listaccts');

                $accounts = $response['acct']
                    ?? $response['data']['acct']
                    ?? [];

                $serverAccounts[$server->id] = collect($accounts)
                    ->map(function ($account) {
                        return [
                            'user' => $account['user'] ?? null,
                            'domain' => $account['domain'] ?? null,
                            'ip' => $account['ip'] ?? null,
                            'diskused' => $account['diskused'] ?? null,
                            'owner' => $account['owner'] ?? null,
                            'plan' => $account['plan'] ?? null,
                            'suspended' => $account['suspended'] ?? false,
                        ];
                    })
                    ->filter(fn ($account) => !empty($account['user']))
                    ->values()
                    ->toArray();

            } catch (\Throwable $e) {
                $serverAccounts[$server->id] = [];

                Log::warning('Unable to load cPanel accounts for backup page', [
                    'server_id' => $server->id,
                    'server' => $server->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return view('backups.index', compact('servers', 'serverAccounts'));
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE BACKUP SETTINGS
    |--------------------------------------------------------------------------
    */
    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'server_id' => 'required|exists:servers,id',

            'backup_server_id' => 'nullable|exists:servers,id',
            'backup_path' => 'nullable|string|max:500',
            'local_backup_path' => 'nullable|string|max:500',
            'google_drive_remote' => 'nullable|string|max:255',
            'daily_sync_time' => 'nullable',

            'disk_warning_percent' => 'nullable|integer|min:1|max:100',
            'disk_transfer_percent' => 'nullable|integer|min:1|max:100',

            'backup_selected_accounts' => 'nullable|array',
            'backup_selected_accounts.*' => 'nullable|string|max:255',

            'auto_transfer' => 'nullable|boolean',
            'google_drive_sync' => 'nullable|boolean',
            'failover_enabled' => 'nullable|boolean',
            'dns_failover_enabled' => 'nullable|boolean',
        ]);

        $server = Server::findOrFail($data['server_id']);

        $updateData = [
            'backup_server_id' => $data['backup_server_id'] ?? null,
            'backup_path' => $data['backup_path'] ?? '/backup',
            'local_backup_path' => $data['local_backup_path'] ?? '/backup',
            'google_drive_remote' => $data['google_drive_remote'] ?? null,
            'daily_sync_time' => $data['daily_sync_time'] ?? null,
            'disk_warning_percent' => $data['disk_warning_percent'] ?? 80,
            'disk_transfer_percent' => $data['disk_transfer_percent'] ?? 90,

            /*
            |--------------------------------------------------------------------------
            | Selected cPanel accounts
            |--------------------------------------------------------------------------
            | If empty, failover command can transfer the full backup path.
            |--------------------------------------------------------------------------
            */
            'backup_selected_accounts' => array_values(array_filter(
                $request->input('backup_selected_accounts', [])
            )),

            /*
            |--------------------------------------------------------------------------
            | Toggle values
            |--------------------------------------------------------------------------
            */
            'auto_transfer' => $request->has('auto_transfer'),
            'google_drive_sync' => $request->has('google_drive_sync'),
            'failover_enabled' => $request->has('failover_enabled'),
            'dns_failover_enabled' => $request->has('dns_failover_enabled'),
        ];

        $server->update($updateData);

        $this->createAlert(
            $server,
            'backup',
            'info',
            'Backup settings updated',
            'Backup settings were updated. Selected accounts: ' . count($updateData['backup_selected_accounts'])
        );

        return back()->with('success', 'Backup settings updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | RUN GOOGLE DRIVE BACKUP PAGE/ACTION
    |--------------------------------------------------------------------------
    */
    public function runGoogleDriveBackup()
    {
        $servers = Server::where('google_drive_sync', true)->latest()->get();

        foreach ($servers as $server) {
            try {
                $this->uploadServerBackupToGoogleDrive($server);

                $this->createAlert(
                    $server,
                    'backup',
                    'info',
                    'Google Drive backup completed',
                    'Google Drive backup sync completed successfully.'
                );
            } catch (\Throwable $e) {
                $this->createAlert(
                    $server,
                    'backup',
                    'danger',
                    'Google Drive backup failed',
                    $e->getMessage()
                );
            }
        }

        return back()->with('success', 'Google Drive backup process completed.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD SINGLE SERVER TO GOOGLE DRIVE
    |--------------------------------------------------------------------------
    */
    public function uploadToGoogleDrive(Request $request)
    {
        $data = $request->validate([
            'server_id' => 'required|exists:servers,id',
        ]);

        $server = Server::findOrFail($data['server_id']);

        try {
            $output = $this->uploadServerBackupToGoogleDrive($server);

            $this->createAlert(
                $server,
                'backup',
                'info',
                'Google Drive upload completed',
                $output ?: 'Backup uploaded to Google Drive.'
            );

            return back()->with('success', 'Backup uploaded to Google Drive successfully.');

        } catch (\Throwable $e) {
            $this->createAlert(
                $server,
                'backup',
                'danger',
                'Google Drive upload failed',
                $e->getMessage()
            );

            return back()->with('error', 'Google Drive upload failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSFER TO BACKUP SERVER
    |--------------------------------------------------------------------------
    */
    public function transferToBackupServer(Request $request)
    {
        $data = $request->validate([
            'server_id' => 'required|exists:servers,id',
        ]);

        $server = Server::findOrFail($data['server_id']);

        try {
            $backupServer = $this->getBackupServer($server);

            $output = $this->transferServerBackup($server, $backupServer);

            $this->createAlert(
                $server,
                'backup',
                'info',
                'Backup transferred to backup server',
                $output ?: 'Backup transfer completed.'
            );

            return back()->with('success', 'Backup transferred to backup server successfully.');

        } catch (\Throwable $e) {
            $this->createAlert(
                $server,
                'backup',
                'danger',
                'Backup transfer failed',
                $e->getMessage()
            );

            return back()->with('error', 'Backup transfer failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PULL BACKUP TO MONITOR SYSTEM
    |--------------------------------------------------------------------------
    */
    public function pullBackupToMonitor(Request $request)
    {
        $data = $request->validate([
            'server_id' => 'required|exists:servers,id',
        ]);

        $server = Server::findOrFail($data['server_id']);

        try {
            $output = $this->pullBackupFromServerToMonitor($server);

            $this->createAlert(
                $server,
                'backup',
                'info',
                'Backup pulled to monitor system',
                $output ?: 'Backup pulled to monitoring server.'
            );

            return back()->with('success', 'Backup pulled to monitor system successfully.');

        } catch (\Throwable $e) {
            $this->createAlert(
                $server,
                'backup',
                'danger',
                'Pull backup failed',
                $e->getMessage()
            );

            return back()->with('error', 'Pull backup failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PULL + UPLOAD NOW
    |--------------------------------------------------------------------------
    */
    public function fullSync(Request $request)
    {
        $data = $request->validate([
            'server_id' => 'required|exists:servers,id',
        ]);

        $server = Server::findOrFail($data['server_id']);

        try {
            $pullOutput = $this->pullBackupFromServerToMonitor($server);
            $driveOutput = $this->uploadLocalBackupToGoogleDrive($server);

            $message = trim($pullOutput . PHP_EOL . $driveOutput);

            $this->createAlert(
                $server,
                'backup',
                'info',
                'Full backup sync completed',
                $message ?: 'Backup pulled and uploaded to Google Drive.'
            );

            return back()->with('success', 'Pull + Google Drive upload completed successfully.');

        } catch (\Throwable $e) {
            $this->createAlert(
                $server,
                'backup',
                'danger',
                'Full backup sync failed',
                $e->getMessage()
            );

            return back()->with('error', 'Full sync failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO DISK BACKUP
    |--------------------------------------------------------------------------
    */
    public function autoDiskBackup()
    {
        $servers = Server::where('is_active', true)
            ->where('auto_transfer', true)
            ->latest()
            ->get();

        $results = [];

        foreach ($servers as $server) {
            try {
                $disk = $this->getRemoteDiskUsage($server);
                $limit = (int) ($server->disk_transfer_percent ?? 90);

                if ($disk >= $limit) {
                    $backupServer = $this->getBackupServer($server);
                    $output = $this->transferServerBackup($server, $backupServer);

                    $results[] = "{$server->name}: transferred because disk is {$disk}%.";

                    $this->createAlert(
                        $server,
                        'backup',
                        'danger',
                        'Auto disk backup transfer completed',
                        "Disk usage {$disk}% reached transfer limit {$limit}%." . PHP_EOL . $output
                    );
                } else {
                    $results[] = "{$server->name}: skipped, disk is {$disk}%.";
                }

            } catch (\Throwable $e) {
                $results[] = "{$server->name}: failed - {$e->getMessage()}";

                $this->createAlert(
                    $server,
                    'backup',
                    'danger',
                    'Auto disk backup failed',
                    $e->getMessage()
                );
            }
        }

        return back()->with('success', implode(' ', $results));
    }

    /*
    |--------------------------------------------------------------------------
    | BACKUP LOGS PAGE
    |--------------------------------------------------------------------------
    */
    public function logs()
    {
        $logFiles = [
            'auto-backup-failover' => storage_path('logs/auto-backup-failover.log'),
            'server-hourly-check' => storage_path('logs/server-hourly-check.log'),
            'server-quick-check' => storage_path('logs/server-quick-check.log'),
            'laravel' => storage_path('logs/laravel.log'),
        ];

        $logs = [];

        foreach ($logFiles as $name => $path) {
            if (file_exists($path)) {
                $logs[$name] = collect(array_slice(file($path), -200))
                    ->implode('');
            } else {
                $logs[$name] = 'No log file found.';
            }
        }

        return view('backups.logs', compact('logs'));
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Upload remote server backup to Google Drive from source server
    |--------------------------------------------------------------------------
    */
    private function uploadServerBackupToGoogleDrive(Server $server): string
    {
        $ssh = $this->ssh($server);

        $sourcePath = rtrim($server->backup_path ?: '/backup', '/');
        $remote = trim($server->google_drive_remote ?: '');

        if (empty($remote)) {
            throw new \Exception('Google Drive remote name is empty.');
        }

        $selectedAccounts = $server->backup_selected_accounts ?? [];

        if (!is_array($selectedAccounts)) {
            $selectedAccounts = [];
        }

        if (count($selectedAccounts)) {
            $outputs = [];

            foreach ($selectedAccounts as $accountUser) {
                $accountUser = $this->cleanAccountUser($accountUser);

                if (!$accountUser) {
                    continue;
                }

                $cmd = "
                    command -v rclone >/dev/null 2>&1 || echo 'RCLONE_NOT_FOUND';

                    if [ -f {$sourcePath}/cpmove-{$accountUser}.tar.gz ]; then
                        rclone copy {$sourcePath}/cpmove-{$accountUser}.tar.gz {$remote}:server-backups/{$server->name}/{$accountUser}/ 2>&1;
                    elif [ -d /home/{$accountUser} ]; then
                        rclone copy /home/{$accountUser}/ {$remote}:server-backups/{$server->name}/{$accountUser}/home/ 2>&1;
                    else
                        echo 'ACCOUNT_NOT_FOUND: {$accountUser}';
                    fi
                ";

                $output = $ssh->exec($cmd);

                if (str_contains($output, 'RCLONE_NOT_FOUND')) {
                    throw new \Exception('rclone is not installed/configured on source server.');
                }

                $outputs[] = "Account {$accountUser}: " . trim($output);
            }

            return implode(PHP_EOL, $outputs);
        }

        $cmd = "
            command -v rclone >/dev/null 2>&1 || echo 'RCLONE_NOT_FOUND';
            rclone copy {$sourcePath}/ {$remote}:server-backups/{$server->name}/ --progress 2>&1
        ";

        $output = $ssh->exec($cmd);

        if (str_contains($output, 'RCLONE_NOT_FOUND')) {
            throw new \Exception('rclone is not installed/configured on source server.');
        }

        return $output;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Upload local monitor backup folder to Google Drive
    |--------------------------------------------------------------------------
    */
    private function uploadLocalBackupToGoogleDrive(Server $server): string
    {
        $localPath = rtrim($server->local_backup_path ?: '/backup', '/');
        $remote = trim($server->google_drive_remote ?: '');

        if (empty($remote)) {
            throw new \Exception('Google Drive remote name is empty.');
        }

        if (!is_dir($localPath)) {
            throw new \Exception("Local backup path does not exist: {$localPath}");
        }

        $cmd = "rclone copy " . escapeshellarg($localPath . '/') . " " . escapeshellarg($remote . ':server-backups/' . $server->name . '/') . " 2>&1";

        $output = shell_exec($cmd);

        return trim((string) $output);
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Transfer backup from source server to assigned backup server
    |--------------------------------------------------------------------------
    */
    private function transferServerBackup(Server $server, Server $backupServer): string
    {
        $ssh = $this->ssh($server);

        $sourcePath = rtrim($server->backup_path ?: '/backup', '/');
        $backupPath = rtrim($backupServer->backup_path ?: '/backup', '/');

        $backupHost = $backupServer->host;
        $backupUser = $backupServer->username ?: 'root';
        $backupPort = $backupServer->ssh_port ?: 22;

        $selectedAccounts = $server->backup_selected_accounts ?? [];

        if (!is_array($selectedAccounts)) {
            $selectedAccounts = [];
        }

        /*
        |--------------------------------------------------------------------------
        | Account-aware transfer
        |--------------------------------------------------------------------------
        | If selected accounts exist, only transfer selected cPanel accounts.
        |--------------------------------------------------------------------------
        */
        if (count($selectedAccounts)) {
            $outputs = [];

            foreach ($selectedAccounts as $accountUser) {
                $accountUser = $this->cleanAccountUser($accountUser);

                if (!$accountUser) {
                    continue;
                }

                $accountBackupFile = "{$sourcePath}/cpmove-{$accountUser}.tar.gz";
                $accountHomePath = "/home/{$accountUser}";
                $remoteTarget = "{$backupUser}@{$backupHost}:{$backupPath}/{$server->name}/{$accountUser}/";

                $cmd = "
                    command -v rsync >/dev/null 2>&1 || echo 'RSYNC_NOT_FOUND';

                    if [ -f {$accountBackupFile} ]; then
                        rsync -az -e 'ssh -p {$backupPort} -o StrictHostKeyChecking=no' {$accountBackupFile} {$remoteTarget} 2>&1;
                    elif [ -d {$accountHomePath} ]; then
                        rsync -az --delete -e 'ssh -p {$backupPort} -o StrictHostKeyChecking=no' {$accountHomePath}/ {$remoteTarget}home/ 2>&1;
                    else
                        echo 'ACCOUNT_NOT_FOUND: {$accountUser}';
                    fi
                ";

                $output = $ssh->exec($cmd);

                if (str_contains($output, 'RSYNC_NOT_FOUND')) {
                    throw new \Exception('rsync is not installed on source server.');
                }

                if (str_contains(strtolower($output), 'permission denied')) {
                    throw new \Exception("Permission denied transferring {$accountUser}: " . $output);
                }

                $outputs[] = "Account {$accountUser}: " . trim($output);

                $this->createAlert(
                    $server,
                    'backup',
                    'info',
                    "Selected account transferred: {$accountUser}",
                    $output ?: "Transferred selected account {$accountUser}."
                );
            }

            return implode(PHP_EOL, $outputs);
        }

        /*
        |--------------------------------------------------------------------------
        | Full backup path transfer fallback
        |--------------------------------------------------------------------------
        */
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

        return $output;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Pull backup from cPanel server to monitoring server
    |--------------------------------------------------------------------------
    */
    private function pullBackupFromServerToMonitor(Server $server): string
    {
        $sourcePath = rtrim($server->backup_path ?: '/backup', '/');
        $localPath = rtrim($server->local_backup_path ?: '/backup', '/');

        if (!is_dir($localPath)) {
            @mkdir($localPath, 0775, true);
        }

        $host = $server->host;
        $user = $server->username ?: 'root';
        $port = $server->ssh_port ?: 22;

        $selectedAccounts = $server->backup_selected_accounts ?? [];

        if (!is_array($selectedAccounts)) {
            $selectedAccounts = [];
        }

        if (count($selectedAccounts)) {
            $outputs = [];

            foreach ($selectedAccounts as $accountUser) {
                $accountUser = $this->cleanAccountUser($accountUser);

                if (!$accountUser) {
                    continue;
                }

                $target = "{$localPath}/{$server->name}/{$accountUser}";
                @mkdir($target, 0775, true);

                $cmd = "
                    rsync -az -e 'ssh -p {$port} -o StrictHostKeyChecking=no' 
                    {$user}@{$host}:{$sourcePath}/cpmove-{$accountUser}.tar.gz 
                    {$target}/ 2>&1
                ";

                $output = shell_exec($cmd);
                $outputs[] = "Account {$accountUser}: " . trim((string) $output);
            }

            return implode(PHP_EOL, $outputs);
        }

        $target = "{$localPath}/{$server->name}-" . now()->format('Ymd-His');
        @mkdir($target, 0775, true);

        $cmd = "
            rsync -az --delete -e 'ssh -p {$port} -o StrictHostKeyChecking=no' 
            {$user}@{$host}:{$sourcePath}/ 
            {$target}/ 2>&1
        ";

        $output = shell_exec($cmd);

        return trim((string) $output);
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Get assigned backup server
    |--------------------------------------------------------------------------
    */
    private function getBackupServer(Server $server): Server
    {
        if (empty($server->backup_server_id)) {
            throw new \Exception('No backup server assigned.');
        }

        $backupServer = Server::find($server->backup_server_id);

        if (!$backupServer) {
            throw new \Exception('Assigned backup server not found.');
        }

        return $backupServer;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Get remote disk usage
    |--------------------------------------------------------------------------
    */
    private function getRemoteDiskUsage(Server $server): int
    {
        $ssh = $this->ssh($server);

        $disk = trim($ssh->exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'"));

        return is_numeric($disk) ? (int) $disk : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: SSH helper
    |--------------------------------------------------------------------------
    */
    private function ssh(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
        $ssh->setTimeout(60);

        if (!$ssh->login($server->username, $this->getPassword($server))) {
            throw new \Exception('SSH login failed for ' . $server->name);
        }

        return $ssh;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Password helper
    |--------------------------------------------------------------------------
    */
    private function getPassword(Server $server): string
    {
        try {
            return decrypt($server->password);
        } catch (\Throwable $e) {
            return $server->password;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Safe account username
    |--------------------------------------------------------------------------
    */
    private function cleanAccountUser(?string $user): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $user);
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: WHM API Request
    |--------------------------------------------------------------------------
    | Uses WHM root username/password from saved server details.
    |--------------------------------------------------------------------------
    */
    private function whmRequest(Server $server, string $endpoint, array $params = []): array
    {
        $host = $server->host;
        $username = $server->username ?: 'root';
        $password = $this->getPassword($server);

        $query = http_build_query(array_merge([
            'api.version' => 1,
        ], $params));

        $url = "https://{$host}:2087/json-api/{$endpoint}?{$query}";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "{$username}:{$password}",
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($response === false || !empty($error)) {
            throw new \Exception('WHM API connection failed: ' . $error);
        }

        if ($status >= 400) {
            throw new \Exception("WHM API HTTP {$status}: {$response}");
        }

        $json = json_decode($response, true);

        if (!is_array($json)) {
            throw new \Exception('Invalid WHM API response.');
        }

        if (isset($json['metadata']['result']) && (int) $json['metadata']['result'] === 0) {
            throw new \Exception($json['metadata']['reason'] ?? 'WHM API request failed.');
        }

        return $json;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Create monitoring/security alert
    |--------------------------------------------------------------------------
    */
    private function createAlert(
        Server $server,
        string $type,
        string $level,
        string $title,
        string $message
    ): void {
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
            Log::warning('Unable to create backup alert', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}