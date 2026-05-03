<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\ServerCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use phpseclib3\Net\SSH2;

class CheckServersHourly extends Command
{
    protected $signature = 'servers:check-hourly';

    protected $description = 'Check all active servers every hour';

    public function handle(): int
    {
        $servers = Server::where('is_active', 1)->get();

        $this->info('Checking '.$servers->count().' active servers...');

        foreach ($servers as $server) {
            try {
                $oldStatus = strtolower($server->status ?? 'offline');

                $startTime = microtime(true);

                try {
                    $password = decrypt($server->password);
                } catch (\Throwable $e) {
                    $password = $server->password;
                }

                $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
                $ssh->setTimeout(20);

                $sshOnline = $ssh->login($server->username, $password);

                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $cpu = null;
                $ram = null;
                $disk = null;
                $load = null;
                $services = [];
                $firewallStatus = 'Unknown';

                if ($sshOnline) {
                    $cpu = trim($ssh->exec("
                        top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}' | awk '{printf \"%.0f\", $1}'
                    "));

                    $ram = trim($ssh->exec("
                        free | awk '/Mem:/ {printf(\"%.0f\", $3/$2 * 100)}'
                    "));

                    $disk = trim($ssh->exec("
                        df -h / | awk 'NR==2 {print $5}' | sed 's/%//'
                    "));

                    $load = trim($ssh->exec("
                        uptime | awk -F'load average:' '{ print $2 }' | sed 's/^ *//'
                    "));

                    $services = [
                        'apache/httpd' => trim($ssh->exec("systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null || echo unknown")),
                        'nginx' => trim($ssh->exec("systemctl is-active nginx 2>/dev/null || echo unknown")),
                        'mysql/mariadb' => trim($ssh->exec("systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null || echo unknown")),
                        'ssh' => trim($ssh->exec("systemctl is-active sshd 2>/dev/null || systemctl is-active ssh 2>/dev/null || echo unknown")),

                        'lsws' => trim($ssh->exec("systemctl is-active lsws 2>/dev/null || echo unknown")),
                        'lshttpd' => trim($ssh->exec("systemctl is-active lshttpd 2>/dev/null || echo unknown")),
                        'openlitespeed' => trim($ssh->exec("systemctl is-active openlitespeed 2>/dev/null || echo unknown")),
                        'litespeed' => trim($ssh->exec("systemctl is-active litespeed 2>/dev/null || echo unknown")),
                    ];

                    $firewallStatus = trim($ssh->exec("
                        if systemctl is-active firewalld >/dev/null 2>&1; then
                            echo 'firewalld active'
                        elif systemctl is-active csf >/dev/null 2>&1; then
                            echo 'csf active'
                        elif command -v ufw >/dev/null 2>&1; then
                            ufw status 2>/dev/null | head -n 1
                        elif command -v iptables >/dev/null 2>&1; then
                            echo 'iptables available'
                        else
                            echo 'Unknown'
                        fi
                    "));
                }

                $websiteOnline = false;

                if (!empty($server->website_url)) {
                    $websiteOnline = $this->isWebsiteOnline($server->website_url);
                } else {
                    $websiteOnline = $this->isPortOpen($server->host, 80) || $this->isPortOpen($server->host, 443);
                }

                $cpanelOnline = $this->isPortOpen($server->host, 2087);
                $pleskOnline = $this->isPortOpen($server->host, 8443);

                $newStatus = ($sshOnline || $websiteOnline || $cpanelOnline || $pleskOnline)
                    ? 'online'
                    : 'offline';

                $checkData = [
                    'server_id' => $server->id,
                    'online' => $newStatus === 'online',
                    'ssh_online' => $sshOnline,
                    'status' => ucfirst($newStatus),
                    'cpu_usage' => is_numeric($cpu) ? (float) $cpu : null,
                    'ram_usage' => is_numeric($ram) ? (float) $ram : null,
                    'disk_usage' => is_numeric($disk) ? (float) $disk : null,
                    'load_average' => $load,
                    'services' => json_encode($services),
                    'checked_at' => now(),
                ];

                if (Schema::hasColumn('server_checks', 'response_time')) {
                    $checkData['response_time'] = $responseTime;
                }

                if (Schema::hasColumn('server_checks', 'firewall_status')) {
                    $checkData['firewall_status'] = $firewallStatus;
                }

                if (Schema::hasColumn('server_checks', 'website_online')) {
                    $checkData['website_online'] = $websiteOnline;
                }

                if (Schema::hasColumn('server_checks', 'cpanel_online')) {
                    $checkData['cpanel_online'] = $cpanelOnline;
                }

                if (Schema::hasColumn('server_checks', 'plesk_online')) {
                    $checkData['plesk_online'] = $pleskOnline;
                }

                ServerCheck::create($checkData);

                $server->update([
                    'status' => $newStatus,
                ]);

                $this->info($server->name.' checked: '.$newStatus);

            } catch (\Throwable $e) {
                $server->update([
                    'status' => 'offline',
                ]);

                ServerCheck::create([
                    'server_id' => $server->id,
                    'online' => false,
                    'ssh_online' => false,
                    'status' => 'Offline',
                    'checked_at' => now(),
                ]);

                $this->error($server->name.' failed: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function isPortOpen(string $host, int $port, int $timeout = 5): bool
    {
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if ($connection) {
                fclose($connection);
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isWebsiteOnline(string $url): bool
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'ignore_errors' => true,
                    'method' => 'GET',
                    'header' => "User-Agent: Webscepts-Monitor/1.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $headers = @get_headers($url, true, $context);

            if (!$headers || empty($headers[0])) {
                return false;
            }

            return preg_match('/\s(200|201|202|204|301|302|303|307|308|401|403)\s/', $headers[0]) === 1;

        } catch (\Throwable $e) {
            return false;
        }
    }
}