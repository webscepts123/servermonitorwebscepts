<?php

namespace App\Console\Commands;

use App\Mail\ServerDownAlertMail;
use App\Mail\ServerRecoveryAlertMail;
use App\Models\Server;
use App\Models\ServerCheck;
use App\Models\ServerSecurityAlert;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use phpseclib3\Net\SSH2;

class CheckServers extends Command
{
    protected $signature = 'servers:check';

    protected $description = 'Check all servers health';

    public function handle(SmsService $smsService): int
    {
        $servers = Server::where('is_active', 1)->latest()->get();

        $this->info('Checking '.$servers->count().' active servers...');

        foreach ($servers as $server) {
            $this->line('----------------------------------------');
            $this->info('Checking: '.$server->name.' - '.$server->host);

            $oldStatus = strtolower(trim($server->status ?? 'offline'));

            try {
                $startTime = microtime(true);

                $password = $this->getPassword($server);

                /*
                |--------------------------------------------------------------------------
                | Default values
                |--------------------------------------------------------------------------
                */
                $sshOnline = false;
                $websiteOnline = false;
                $cpanelOnline = false;
                $pleskOnline = false;

                $cpu = null;
                $ram = null;
                $disk = null;
                $load = null;
                $services = [];
                $firewallStatus = 'Unknown';

                /*
                |--------------------------------------------------------------------------
                | SSH Check + Server Metrics
                |--------------------------------------------------------------------------
                */
                try {
                    $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
                    $ssh->setTimeout(20);

                    $sshOnline = $ssh->login($server->username, $password);

                    if ($sshOnline) {
                        $cpu = trim($ssh->exec("
                            if command -v top >/dev/null 2>&1; then
                                top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}' | awk '{printf \"%.0f\", $1}'
                            else
                                echo ''
                            fi
                        "));

                        $ram = trim($ssh->exec("
                            if command -v free >/dev/null 2>&1; then
                                free | awk '/Mem:/ {printf(\"%.0f\", $3/$2 * 100)}'
                            else
                                echo ''
                            fi
                        "));

                        $disk = trim($ssh->exec("
                            df -h / | awk 'NR==2 {print $5}' | sed 's/%//'
                        "));

                        $load = trim($ssh->exec("
                            uptime | awk -F'load average:' '{ print $2 }' | sed 's/^ *//'
                        "));

                        $services = [
                            'apache/httpd' => trim($ssh->exec("
                                systemctl is-active apache2 2>/dev/null ||
                                systemctl is-active httpd 2>/dev/null ||
                                echo unknown
                            ")),

                            'nginx' => trim($ssh->exec("
                                systemctl is-active nginx 2>/dev/null ||
                                echo unknown
                            ")),

                            'mysql/mariadb' => trim($ssh->exec("
                                systemctl is-active mysql 2>/dev/null ||
                                systemctl is-active mariadb 2>/dev/null ||
                                echo unknown
                            ")),

                            'ssh' => trim($ssh->exec("
                                systemctl is-active sshd 2>/dev/null ||
                                systemctl is-active ssh 2>/dev/null ||
                                echo unknown
                            ")),

                            'exim' => trim($ssh->exec("
                                systemctl is-active exim 2>/dev/null ||
                                echo unknown
                            ")),

                            'dovecot' => trim($ssh->exec("
                                systemctl is-active dovecot 2>/dev/null ||
                                echo unknown
                            ")),

                            'named' => trim($ssh->exec("
                                systemctl is-active named 2>/dev/null ||
                                systemctl is-active bind9 2>/dev/null ||
                                echo unknown
                            ")),

                            /*
                            |--------------------------------------------------------------------------
                            | LiteSpeed / OpenLiteSpeed
                            |--------------------------------------------------------------------------
                            */
                            'lsws' => trim($ssh->exec("
                                systemctl is-active lsws 2>/dev/null ||
                                echo unknown
                            ")),

                            'lshttpd' => trim($ssh->exec("
                                systemctl is-active lshttpd 2>/dev/null ||
                                echo unknown
                            ")),

                            'openlitespeed' => trim($ssh->exec("
                                systemctl is-active openlitespeed 2>/dev/null ||
                                echo unknown
                            ")),

                            'litespeed' => trim($ssh->exec("
                                systemctl is-active litespeed 2>/dev/null ||
                                echo unknown
                            ")),
                        ];

                        /*
                        |--------------------------------------------------------------------------
                        | LiteSpeed lswsctrl fallback
                        |--------------------------------------------------------------------------
                        */
                        $lswsCtrlStatus = trim($ssh->exec("
                            if [ -x /usr/local/lsws/bin/lswsctrl ]; then
                                /usr/local/lsws/bin/lswsctrl status 2>&1 | head -n 5
                            else
                                echo ''
                            fi
                        "));

                        if (!empty($lswsCtrlStatus)) {
                            $services['lswsctrl'] = str_contains(strtolower($lswsCtrlStatus), 'running')
                                ? 'active'
                                : 'detected';
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Firewall
                        |--------------------------------------------------------------------------
                        */
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

                        /*
                        |--------------------------------------------------------------------------
                        | Extra Security Checks
                        |--------------------------------------------------------------------------
                        */
                        $this->runSecurityChecks($server, $ssh, $disk);
                    }
                } catch (\Throwable $e) {
                    $sshOnline = false;

                    $this->warn('SSH failed for '.$server->name.': '.$e->getMessage());
                }

                /*
                |--------------------------------------------------------------------------
                | External Port / Panel / Website Checks
                |--------------------------------------------------------------------------
                | IMPORTANT:
                | Server is ONLINE if any important service is reachable:
                | SSH OR Website OR cPanel/WHM OR Plesk.
                |--------------------------------------------------------------------------
                */
                $cpanelOnline = $this->isPortOpen($server->host, 2087, 5)
                    || $this->isPortOpen($server->host, 2083, 5);

                $pleskOnline = $this->isPortOpen($server->host, 8443, 5);

                if (!empty($server->website_url)) {
                    $websiteOnline = $this->isWebsiteOnline($server->website_url);
                } else {
                    $websiteOnline = $this->isPortOpen($server->host, 80, 5)
                        || $this->isPortOpen($server->host, 443, 5);
                }

                $newStatus = ($sshOnline || $websiteOnline || $cpanelOnline || $pleskOnline)
                    ? 'online'
                    : 'offline';

                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                /*
                |--------------------------------------------------------------------------
                | Save Server Check
                |--------------------------------------------------------------------------
                */
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

                if (Schema::hasColumn('server_checks', 'website_online')) {
                    $checkData['website_online'] = $websiteOnline;
                }

                if (Schema::hasColumn('server_checks', 'cpanel_online')) {
                    $checkData['cpanel_online'] = $cpanelOnline;
                }

                if (Schema::hasColumn('server_checks', 'plesk_online')) {
                    $checkData['plesk_online'] = $pleskOnline;
                }

                if (Schema::hasColumn('server_checks', 'response_time')) {
                    $checkData['response_time'] = $responseTime;
                }

                if (Schema::hasColumn('server_checks', 'firewall_status')) {
                    $checkData['firewall_status'] = $firewallStatus;
                }

                ServerCheck::create($checkData);

                /*
                |--------------------------------------------------------------------------
                | Update Main Server Status
                |--------------------------------------------------------------------------
                */
                $server->update([
                    'status' => $newStatus,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Alerts
                |--------------------------------------------------------------------------
                */
                $this->createIssueAlerts(
                    server: $server,
                    sshOnline: $sshOnline,
                    websiteOnline: $websiteOnline,
                    cpanelOnline: $cpanelOnline,
                    pleskOnline: $pleskOnline,
                    cpu: $cpu,
                    ram: $ram,
                    disk: $disk,
                    services: $services,
                    firewallStatus: $firewallStatus
                );

                if ($newStatus === 'offline' && $oldStatus !== 'offline') {
                    $this->sendDownAlerts($server->fresh(), $smsService);
                }

                if ($newStatus === 'online' && $oldStatus === 'offline') {
                    $this->sendRecoveryAlerts($server->fresh(), $smsService);
                }

                $this->info(
                    $server->name.
                    ' checked. Status: '.$newStatus.
                    ' | SSH: '.($sshOnline ? 'online' : 'failed').
                    ' | Website: '.($websiteOnline ? 'online' : 'offline').
                    ' | cPanel: '.($cpanelOnline ? 'online' : 'offline').
                    ' | Plesk: '.($pleskOnline ? 'online' : 'offline')
                );

            } catch (\Throwable $e) {
                /*
                |--------------------------------------------------------------------------
                | Critical check crash
                |--------------------------------------------------------------------------
                */
                $server->update([
                    'status' => 'offline',
                ]);

                $checkData = [
                    'server_id' => $server->id,
                    'online' => false,
                    'ssh_online' => false,
                    'status' => 'Offline',
                    'checked_at' => now(),
                ];

                if (Schema::hasColumn('server_checks', 'website_online')) {
                    $checkData['website_online'] = false;
                }

                if (Schema::hasColumn('server_checks', 'cpanel_online')) {
                    $checkData['cpanel_online'] = false;
                }

                if (Schema::hasColumn('server_checks', 'plesk_online')) {
                    $checkData['plesk_online'] = false;
                }

                if (Schema::hasColumn('server_checks', 'response_time')) {
                    $checkData['response_time'] = null;
                }

                ServerCheck::create($checkData);

                $this->createSecurityAlert(
                    $server,
                    'monitoring',
                    'danger',
                    'Server check failed',
                    $e->getMessage()
                );

                if ($oldStatus !== 'offline') {
                    $this->sendDownAlerts($server->fresh(), $smsService);
                }

                $this->error($server->name.' failed: '.$e->getMessage());
            }
        }

        $this->info('All server checks completed.');

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Security Checks
    |--------------------------------------------------------------------------
    */
    private function runSecurityChecks(Server $server, SSH2 $ssh, mixed $disk): void
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | Disk usage
            |--------------------------------------------------------------------------
            */
            $diskWarningPercent = (int) ($server->disk_warning_percent ?? 80);
            $diskTransferPercent = (int) ($server->disk_transfer_percent ?? 90);

            if (is_numeric($disk)) {
                $diskValue = (int) $disk;

                if ($diskValue >= $diskTransferPercent) {
                    $this->createSecurityAlert(
                        $server,
                        'disk',
                        'danger',
                        'Disk transfer threshold reached',
                        "Disk usage is {$diskValue}%. Transfer threshold is {$diskTransferPercent}%."
                    );
                } elseif ($diskValue >= $diskWarningPercent) {
                    $this->createSecurityAlert(
                        $server,
                        'disk',
                        'warning',
                        'Disk warning threshold reached',
                        "Disk usage is {$diskValue}%. Warning threshold is {$diskWarningPercent}%."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Failed SSH login attempts
            |--------------------------------------------------------------------------
            */
            $failedLogins = trim($ssh->exec("
                grep 'Failed password' /var/log/secure 2>/dev/null | tail -n 20 ||
                grep 'Failed password' /var/log/auth.log 2>/dev/null | tail -n 20 ||
                echo ''
            "));

            if (!empty($failedLogins)) {
                $this->createSecurityAlert(
                    $server,
                    'ssh',
                    'warning',
                    'Recent failed SSH login attempts detected',
                    $failedLogins
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Suspicious temp files
            |--------------------------------------------------------------------------
            */
            $suspiciousFiles = trim($ssh->exec("
                find /tmp /var/tmp -type f \\( -name '*.php' -o -name '*.sh' -o -name '*.pl' -o -name '*.py' \\) 2>/dev/null | head -n 30
            "));

            if (!empty($suspiciousFiles)) {
                $this->createSecurityAlert(
                    $server,
                    'malware',
                    'warning',
                    'Suspicious executable files found in temp folders',
                    $suspiciousFiles
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Exim queue size
            |--------------------------------------------------------------------------
            */
            $mailQueue = trim($ssh->exec("
                if command -v exim >/dev/null 2>&1; then
                    exim -bpc 2>/dev/null
                else
                    echo ''
                fi
            "));

            if (is_numeric($mailQueue) && (int) $mailQueue >= 100) {
                $this->createSecurityAlert(
                    $server,
                    'email',
                    'warning',
                    'High email queue detected',
                    "Current Exim queue size: {$mailQueue}"
                );
            }

            /*
            |--------------------------------------------------------------------------
            | LiteSpeed detection
            |--------------------------------------------------------------------------
            */
            $lsws = trim($ssh->exec("
                if [ -x /usr/local/lsws/bin/lswsctrl ]; then
                    /usr/local/lsws/bin/lswsctrl status 2>&1 | head -n 5
                else
                    echo ''
                fi
            "));

            if (!empty($lsws)) {
                $this->createSecurityAlert(
                    $server,
                    'litespeed',
                    'info',
                    'LiteSpeed status detected',
                    $lsws
                );
            }

        } catch (\Throwable $e) {
            $this->createSecurityAlert(
                $server,
                'security',
                'warning',
                'Security scan section failed',
                $e->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Issue Alerts
    |--------------------------------------------------------------------------
    */
    private function createIssueAlerts(
        Server $server,
        bool $sshOnline,
        bool $websiteOnline,
        bool $cpanelOnline,
        bool $pleskOnline,
        mixed $cpu,
        mixed $ram,
        mixed $disk,
        array $services,
        string $firewallStatus
    ): void {
        /*
        |--------------------------------------------------------------------------
        | SSH issue
        |--------------------------------------------------------------------------
        */
        if (!$sshOnline) {
            $this->createSecurityAlert(
                $server,
                'ssh',
                'warning',
                'SSH connection failed',
                'SSH login failed or port is blocked. Check username, password, SSH port, firewall, and security group.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Website issue
        |--------------------------------------------------------------------------
        */
        if (!$websiteOnline) {
            $this->createSecurityAlert(
                $server,
                'website',
                'warning',
                'Website/HTTP service not reachable',
                'Website URL or ports 80/443 are not reachable from the monitoring server.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | cPanel issue
        |--------------------------------------------------------------------------
        */
        $panelType = strtolower($server->panel_type ?? 'auto');

        if (($panelType === 'cpanel' || $panelType === 'whm' || $panelType === 'auto' || empty($panelType)) && !$cpanelOnline) {
            $this->createSecurityAlert(
                $server,
                'cpanel',
                'warning',
                'cPanel / WHM port is not reachable',
                'Ports 2087/2083 are not reachable from the monitoring server.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Plesk issue
        |--------------------------------------------------------------------------
        */
        if ($panelType === 'plesk' && !$pleskOnline) {
            $this->createSecurityAlert(
                $server,
                'plesk',
                'warning',
                'Plesk port is not reachable',
                'Port 8443 is not reachable from the monitoring server.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resource issues
        |--------------------------------------------------------------------------
        */
        if (is_numeric($cpu) && (float) $cpu >= 90) {
            $this->createSecurityAlert(
                $server,
                'cpu',
                'danger',
                'High CPU usage detected',
                'CPU usage is '.$cpu.'%.'
            );
        }

        if (is_numeric($ram) && (float) $ram >= 90) {
            $this->createSecurityAlert(
                $server,
                'ram',
                'danger',
                'High RAM usage detected',
                'RAM usage is '.$ram.'%.'
            );
        }

        if (is_numeric($disk) && (float) $disk >= 90) {
            $this->createSecurityAlert(
                $server,
                'disk',
                'danger',
                'High disk usage detected',
                'Disk usage is '.$disk.'%.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Service issues
        |--------------------------------------------------------------------------
        */
        foreach ($services as $name => $status) {
            $cleanStatus = strtolower(trim((string) $status));

            if (
                !in_array($cleanStatus, ['active', 'unknown', 'detected', ''], true)
                && !str_contains($cleanStatus, 'running')
            ) {
                $this->createSecurityAlert(
                    $server,
                    'service',
                    'warning',
                    strtoupper($name).' service issue detected',
                    "Service {$name} status: {$status}"
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Firewall info
        |--------------------------------------------------------------------------
        */
        if (
            strtolower($firewallStatus) === 'unknown'
            || empty($firewallStatus)
        ) {
            $this->createSecurityAlert(
                $server,
                'firewall',
                'warning',
                'Firewall status unknown',
                'Could not detect firewalld, CSF, UFW, or iptables status.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SMS / Email Alerts
    |--------------------------------------------------------------------------
    */
    private function sendDownAlerts(Server $server, SmsService $smsService): void
    {
        $message = "DOWN ALERT: {$server->name} is OFFLINE. Host: {$server->host}";

        if (!empty($server->email_alerts_enabled)) {
            foreach ([$server->admin_email, $server->customer_email] as $email) {
                if (!empty($email)) {
                    try {
                        Mail::to($email)->send(new ServerDownAlertMail($server));
                    } catch (\Throwable $e) {
                        $this->createSecurityAlert(
                            $server,
                            'email',
                            'warning',
                            'Failed to send down email alert',
                            $e->getMessage()
                        );
                    }
                }
            }
        }

        if (!empty($server->sms_alerts_enabled)) {
            foreach ([$server->admin_phone, $server->customer_phone] as $phone) {
                if (!empty($phone)) {
                    try {
                        $smsService->send($phone, $message);
                    } catch (\Throwable $e) {
                        $this->createSecurityAlert(
                            $server,
                            'sms',
                            'warning',
                            'Failed to send down SMS alert',
                            $e->getMessage()
                        );
                    }
                }
            }
        }
    }

    private function sendRecoveryAlerts(Server $server, SmsService $smsService): void
    {
        $message = "RECOVERED: {$server->name} is back ONLINE. Host: {$server->host}";

        if (!empty($server->email_alerts_enabled)) {
            foreach ([$server->admin_email, $server->customer_email] as $email) {
                if (!empty($email)) {
                    try {
                        Mail::to($email)->send(new ServerRecoveryAlertMail($server));
                    } catch (\Throwable $e) {
                        $this->createSecurityAlert(
                            $server,
                            'email',
                            'warning',
                            'Failed to send recovery email alert',
                            $e->getMessage()
                        );
                    }
                }
            }
        }

        if (!empty($server->sms_alerts_enabled)) {
            foreach ([$server->admin_phone, $server->customer_phone] as $phone) {
                if (!empty($phone)) {
                    try {
                        $smsService->send($phone, $message);
                    } catch (\Throwable $e) {
                        $this->createSecurityAlert(
                            $server,
                            'sms',
                            'warning',
                            'Failed to send recovery SMS alert',
                            $e->getMessage()
                        );
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
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

    private function createSecurityAlert(
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
            /*
            |--------------------------------------------------------------------------
            | Avoid too many duplicate alerts within 30 minutes
            |--------------------------------------------------------------------------
            */
            $exists = ServerSecurityAlert::where('server_id', $server->id)
                ->where('type', $type)
                ->where('level', $level)
                ->where('title', $title)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->exists();

            if ($exists) {
                return;
            }

            ServerSecurityAlert::create([
                'server_id' => $server->id,
                'type' => $type,
                'level' => $level,
                'title' => $title,
                'message' => $message,
                'detected_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Do not break monitoring if alert table/model has an issue.
        }
    }
}