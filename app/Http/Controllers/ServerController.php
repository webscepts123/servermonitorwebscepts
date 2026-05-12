<?php

namespace App\Http\Controllers;

use App\Mail\ServerDownAlertMail;
use App\Mail\ServerRecoveryAlertMail;
use App\Models\Server;
use App\Models\ServerCheck;
use App\Models\ServerSecurityAlert;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use phpseclib3\Net\SSH2;

class ServerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST SERVERS
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $servers = Server::with([
            'checks' => fn ($q) => $q->latest()->limit(1),
            'securityAlerts' => fn ($q) => $q->latest()->limit(5),
        ])->latest()->get();

        return view('servers.index', compact('servers'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        $backupServers = Server::where('is_active', 1)->latest()->get();

        return view('servers.create', compact('backupServers'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $data = $this->validateServer($request, false);

        $data = $this->prepareServerData($request, $data);

        $data['status'] = 'offline';
        $data['last_status'] = 'offline';
        $data['last_known_status'] = 'offline';

        /*
        |--------------------------------------------------------------------------
        | Encrypt SSH / Root Password
        |--------------------------------------------------------------------------
        */
        if ($request->filled('password')) {
            $data['password'] = encrypt($request->input('password'));
        } else {
            unset($data['password']);
        }

        /*
        |--------------------------------------------------------------------------
        | Encrypt WHM Token / Password
        |--------------------------------------------------------------------------
        */
        if ($request->filled('whm_token')) {
            $data['whm_token'] = Crypt::encryptString($request->input('whm_token'));
        } else {
            unset($data['whm_token']);
        }

        if ($request->filled('whm_password')) {
            $data['whm_password'] = Crypt::encryptString($request->input('whm_password'));
        } else {
            unset($data['whm_password']);
        }

        Server::create($this->filterServerColumns($data));

        return redirect()
            ->route('servers.index')
            ->with('success', 'Server created successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(Server $server)
    {
        $server->load([
            'checks' => fn ($q) => $q->latest()->limit(50),
            'securityAlerts' => fn ($q) => $q->latest()->limit(50),
        ]);

        return view('servers.show', compact('server'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    public function edit(Server $server)
    {
        $backupServers = Server::where('id', '!=', $server->id)
            ->where('is_active', 1)
            ->latest()
            ->get();

        return view('servers.edit', compact('server', 'backupServers'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, Server $server)
    {
        $data = $this->validateServer($request, true);

        $data = $this->prepareServerData($request, $data);

        /*
        |--------------------------------------------------------------------------
        | SSH Password
        |--------------------------------------------------------------------------
        | Leave blank = keep old password.
        |--------------------------------------------------------------------------
        */
        if ($request->filled('password')) {
            $data['password'] = encrypt($request->input('password'));
        } else {
            unset($data['password']);
        }

        /*
        |--------------------------------------------------------------------------
        | WHM Token
        |--------------------------------------------------------------------------
        | Leave blank = keep old token.
        |--------------------------------------------------------------------------
        */
        if ($request->filled('whm_token')) {
            $data['whm_token'] = Crypt::encryptString($request->input('whm_token'));
            $data['whm_token_status'] = null;
            $data['whm_token_error'] = null;
            $data['whm_token_last_checked_at'] = null;
        } else {
            unset($data['whm_token']);
        }

        /*
        |--------------------------------------------------------------------------
        | WHM Password Fallback
        |--------------------------------------------------------------------------
        | Leave blank = keep old fallback password.
        |--------------------------------------------------------------------------
        */
        if ($request->filled('whm_password')) {
            $data['whm_password'] = Crypt::encryptString($request->input('whm_password'));
        } else {
            unset($data['whm_password']);
        }

        $server->update($this->filterServerColumns($data));

        return redirect()
            ->route('servers.index')
            ->with('success', 'Server updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy(Server $server)
    {
        $server->delete();

        return redirect()
            ->route('servers.index')
            ->with('success', 'Server deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | QUICK SERVER CHECK
    |--------------------------------------------------------------------------
    | Checks:
    | - SSH login
    | - Response time
    | - CPU
    | - RAM
    | - Disk
    | - Load average
    | - Apache / Nginx / MySQL / SSH
    | - LiteSpeed / OpenLiteSpeed
    | - Firewall
    | - Website status
    | - cPanel / WHM ports
    | - Plesk port
    | - Sends SMS/email down + recovery alerts
    |--------------------------------------------------------------------------
    */
    public function checkNow(Server $server, SmsService $smsService)
    {
        $oldStatus = strtolower(trim($server->status ?? $server->last_known_status ?? 'offline'));

        try {
            $startTime = microtime(true);

            $password = $this->getPassword($server);

            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
            $ssh->setTimeout(20);

            $sshOnline = false;

            if (!empty($password)) {
                $sshOnline = $ssh->login($server->username, $password);
            }

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            /*
            |--------------------------------------------------------------------------
            | Default values
            |--------------------------------------------------------------------------
            */
            $cpu = null;
            $ram = null;
            $disk = null;
            $load = null;
            $services = [];
            $firewallStatus = 'Unknown';

            $websiteOnline = false;
            $cpanelOnline = false;
            $whmOnline = false;
            $pleskOnline = false;

            /*
            |--------------------------------------------------------------------------
            | Port / URL Checks
            |--------------------------------------------------------------------------
            */
            $cpanelOnline = $this->isPortOpen($server->host, 2083, 5);
            $whmOnline = $this->isPortOpen($server->host, (int) ($server->whm_port ?? 2087), 5);
            $pleskOnline = $this->isPortOpen($server->host, 8443, 5);

            if (!empty($server->website_url)) {
                $websiteOnline = $this->isWebsiteOnline($server->website_url);
            } else {
                $websiteOnline = $this->isPortOpen($server->host, 80, 5)
                    || $this->isPortOpen($server->host, 443, 5);
            }

            /*
            |--------------------------------------------------------------------------
            | SSH Server Metrics
            |--------------------------------------------------------------------------
            */
            if ($sshOnline) {
                $cpu = trim($ssh->exec("
                    if command -v top >/dev/null 2>&1; then
                        top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}' | awk '{printf \"%.0f\", $1}'
                    else
                        echo ''
                    fi
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

                    'cpanel' => trim($ssh->exec("
                        systemctl is-active cpanel 2>/dev/null ||
                        echo unknown
                    ")),

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

                $lswsCtrlStatus = trim($ssh->exec("
                    if [ -x /usr/local/lsws/bin/lswsctrl ]; then
                        /usr/local/lsws/bin/lswsctrl status 2>&1 | head -n 3
                    else
                        echo ''
                    fi
                "));

                if (!empty($lswsCtrlStatus)) {
                    $services['lswsctrl'] = str_contains(strtolower($lswsCtrlStatus), 'running')
                        ? 'active'
                        : 'detected';
                }

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

            /*
            |--------------------------------------------------------------------------
            | Server Status Logic
            |--------------------------------------------------------------------------
            */
            $newStatus = ($sshOnline || $websiteOnline || $cpanelOnline || $whmOnline || $pleskOnline)
                ? 'online'
                : 'offline';

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
                $checkData['cpanel_online'] = $cpanelOnline || $whmOnline;
            }

            if (Schema::hasColumn('server_checks', 'plesk_online')) {
                $checkData['plesk_online'] = $pleskOnline;
            }

            ServerCheck::create($checkData);

            /*
            |--------------------------------------------------------------------------
            | Update Server Main Status
            |--------------------------------------------------------------------------
            */
            $serverUpdate = [
                'status' => $newStatus,
                'last_known_status' => $newStatus,
                'last_status' => $newStatus,
                'last_checked_at' => now(),
                'last_error' => $newStatus === 'offline'
                    ? 'Server check failed: SSH, website and panel ports are unavailable.'
                    : null,
            ];

            $server->update($this->filterServerColumns($serverUpdate));

            /*
            |--------------------------------------------------------------------------
            | Disk Warning Alert
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
            | LiteSpeed Alert
            |--------------------------------------------------------------------------
            */
            $liteSpeedActive =
                ($services['lsws'] ?? null) === 'active' ||
                ($services['lshttpd'] ?? null) === 'active' ||
                ($services['openlitespeed'] ?? null) === 'active' ||
                ($services['litespeed'] ?? null) === 'active' ||
                ($services['lswsctrl'] ?? null) === 'active';

            if ($liteSpeedActive) {
                $this->createSecurityAlert(
                    $server,
                    'litespeed',
                    'info',
                    'LiteSpeed detected active',
                    'LiteSpeed/OpenLiteSpeed is active on this server.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Down / Recovery Alerts
            |--------------------------------------------------------------------------
            */
            if ($newStatus === 'offline' && $oldStatus !== 'offline') {
                $this->sendDownAlerts($server->fresh(), $smsService);
            }

            if ($newStatus === 'online' && $oldStatus === 'offline') {
                $this->sendRecoveryAlerts($server->fresh(), $smsService);
            }

            return back()->with(
                'success',
                'Server checked successfully. Status: ' . ucfirst($newStatus)
            );
        } catch (\Throwable $e) {
            $serverUpdate = [
                'status' => 'offline',
                'last_status' => 'offline',
                'last_known_status' => 'offline',
                'last_checked_at' => now(),
                'last_error' => $e->getMessage(),
            ];

            $server->update($this->filterServerColumns($serverUpdate));

            $checkData = [
                'server_id' => $server->id,
                'online' => false,
                'ssh_online' => false,
                'status' => 'Offline',
                'checked_at' => now(),
            ];

            if (Schema::hasColumn('server_checks', 'response_time')) {
                $checkData['response_time'] = null;
            }

            if (Schema::hasColumn('server_checks', 'website_online')) {
                $checkData['website_online'] = false;
            }

            if (Schema::hasColumn('server_checks', 'cpanel_online')) {
                $checkData['cpanel_online'] = false;
            }

            if (Schema::hasColumn('server_checks', 'plesk_online')) {
                $checkData['plesk_online'] = false;
            }

            ServerCheck::create($checkData);

            if ($oldStatus !== 'offline') {
                $this->sendDownAlerts($server->fresh(), $smsService);
            }

            Log::error('Server check failed.', [
                'server_id' => $server->id,
                'server_name' => $server->name,
                'host' => $server->host,
                'error' => $e->getMessage(),
            ]);

            return back()->with(
                'error',
                'Server check failed: ' . $e->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PORT CHECK
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | WEBSITE CHECK
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | SECURITY SCAN
    |--------------------------------------------------------------------------
    */
    public function securityScan(Server $server)
    {
        try {
            $password = $this->getPassword($server);

            if (!$password) {
                throw new \Exception('SSH password is missing.');
            }

            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
            $ssh->setTimeout(20);

            if (!$ssh->login($server->username, $password)) {
                throw new \Exception('SSH login failed.');
            }

            $disk = (int) trim($ssh->exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'"));

            $failedLogins = trim($ssh->exec(
                "grep 'Failed password' /var/log/secure 2>/dev/null | tail -n 20 || grep 'Failed password' /var/log/auth.log 2>/dev/null | tail -n 20"
            ));

            $suspiciousFiles = trim($ssh->exec(
                "find /tmp /var/tmp -type f \\( -name '*.php' -o -name '*.sh' \\) 2>/dev/null | head -n 20"
            ));

            if ($disk >= 90) {
                $this->createSecurityAlert(
                    $server,
                    'disk',
                    'danger',
                    'Disk usage critical',
                    "Disk usage is {$disk}%"
                );
            }

            if (!empty($failedLogins)) {
                $this->createSecurityAlert(
                    $server,
                    'ssh',
                    'warning',
                    'Recent failed SSH logins detected',
                    $failedLogins
                );
            }

            if (!empty($suspiciousFiles)) {
                $this->createSecurityAlert(
                    $server,
                    'malware',
                    'warning',
                    'Suspicious files found in temp folders',
                    $suspiciousFiles
                );
            }

            return back()->with('success', 'Security scan completed.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Security scan failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TERMINAL
    |--------------------------------------------------------------------------
    */
    public function terminal(Server $server)
    {
        return view('servers.terminal', compact('server'));
    }

    /*
    |--------------------------------------------------------------------------
    | RUN TERMINAL COMMAND
    |--------------------------------------------------------------------------
    */
    public function runCommand(Request $request, Server $server)
    {
        $request->validate([
            'command' => 'required|string|max:500',
        ]);

        $command = strtolower($request->command);

        $blocked = [
            'rm -rf',
            'shutdown',
            'reboot',
            'mkfs',
            'dd if=',
            ':(){',
            'chmod -r 777 /',
            'chmod 777 /',
            'chown -r',
            'passwd',
            'userdel',
            'drop database',
            'truncate table',
            'format',
            'del /f',
            'powershell remove-item',
        ];

        foreach ($blocked as $bad) {
            if (str_contains($command, strtolower($bad))) {
                return response()->json([
                    'success' => false,
                    'output' => 'Dangerous command blocked for customer file protection.',
                ], 422);
            }
        }

        try {
            $password = $this->getPassword($server);

            if (!$password) {
                throw new \Exception('SSH password is missing.');
            }

            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
            $ssh->setTimeout(20);

            if (!$ssh->login($server->username, $password)) {
                throw new \Exception('SSH login failed');
            }

            $output = $ssh->exec($request->command);

            return response()->json([
                'success' => true,
                'output' => $output ?: 'No output',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'output' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */
    private function validateServer(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            /*
            |--------------------------------------------------------------------------
            | Basic server data
            |--------------------------------------------------------------------------
            */
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'website_url' => 'nullable|url|max:500',
            'linked_domain' => 'nullable|string|max:255',
            'panel_type' => 'nullable|string|in:cpanel,plesk,none',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',

            /*
            |--------------------------------------------------------------------------
            | WHM / cPanel API Access
            |--------------------------------------------------------------------------
            */
            'whm_username' => 'nullable|string|max:255',
            'whm_token' => 'nullable|string|max:20000',
            'whm_password' => 'nullable|string|max:20000',
            'whm_auth_type' => 'nullable|string|in:token,password',
            'whm_port' => 'nullable|integer|min:1|max:65535',
            'whm_ssl_verify' => 'nullable|boolean',

            /*
            |--------------------------------------------------------------------------
            | Alert contact data
            |--------------------------------------------------------------------------
            */
            'admin_email' => 'nullable|email|max:255',
            'admin_phone' => 'nullable|string|max:50',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'alert_phones' => 'nullable|string|max:1000',
            'alert_emails' => 'nullable|string|max:1000',

            /*
            |--------------------------------------------------------------------------
            | Backup data
            |--------------------------------------------------------------------------
            */
            'backup_server_id' => 'nullable|exists:servers,id',
            'backup_path' => 'nullable|string|max:500',
            'local_backup_path' => 'nullable|string|max:500',
            'google_drive_remote' => 'nullable|string|max:255',
            'disk_warning_percent' => 'nullable|integer|min:1|max:100',
            'disk_transfer_percent' => 'nullable|integer|min:1|max:100',
            'daily_sync_time' => 'nullable|string|max:20',
            'sync_time' => 'nullable|string|max:20',
            'backup_selected_accounts' => 'nullable|array',

            /*
            |--------------------------------------------------------------------------
            | Toggles
            |--------------------------------------------------------------------------
            */
            'auto_transfer' => 'nullable|boolean',
            'google_drive_sync' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'email_alerts_enabled' => 'nullable|boolean',
            'sms_alerts_enabled' => 'nullable|boolean',
            'monitor_website' => 'nullable|boolean',
            'monitor_cpanel' => 'nullable|boolean',
            'monitor_frameworks' => 'nullable|boolean',
            'send_recovery_alert' => 'nullable|boolean',
            'failover_enabled' => 'nullable|boolean',
            'dns_failover_enabled' => 'nullable|boolean',

            /*
            |--------------------------------------------------------------------------
            | DNS / Failover
            |--------------------------------------------------------------------------
            */
            'original_ip' => 'nullable|string|max:255',
            'active_dns_ip' => 'nullable|string|max:255',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARE SERVER DATA
    |--------------------------------------------------------------------------
    */
    private function prepareServerData(Request $request, array $data): array
    {
        /*
        |--------------------------------------------------------------------------
        | Defaults
        |--------------------------------------------------------------------------
        */
        $data['ssh_port'] = $data['ssh_port'] ?? 22;
        $data['disk_warning_percent'] = $data['disk_warning_percent'] ?? 80;
        $data['disk_transfer_percent'] = $data['disk_transfer_percent'] ?? 90;

        $data['whm_username'] = $request->input('whm_username')
            ?: $request->input('username')
            ?: 'root';

        $data['whm_auth_type'] = $request->input('whm_auth_type', 'token') ?: 'token';
        $data['whm_port'] = $request->input('whm_port', 2087) ?: 2087;
        $data['whm_ssl_verify'] = $request->boolean('whm_ssl_verify');

        /*
        |--------------------------------------------------------------------------
        | Empty values to null
        |--------------------------------------------------------------------------
        */
        foreach ([
            'panel_type',
            'backup_server_id',
            'website_url',
            'linked_domain',
            'backup_path',
            'local_backup_path',
            'google_drive_remote',
            'daily_sync_time',
            'sync_time',
            'original_ip',
            'active_dns_ip',
        ] as $field) {
            if (($data[$field] ?? '') === '') {
                $data[$field] = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Checkbox values
        |--------------------------------------------------------------------------
        */
        $data['is_active'] = $request->has('is_active');
        $data['auto_transfer'] = $request->has('auto_transfer');
        $data['google_drive_sync'] = $request->has('google_drive_sync');
        $data['email_alerts_enabled'] = $request->has('email_alerts_enabled');
        $data['sms_alerts_enabled'] = $request->has('sms_alerts_enabled');

        $data['monitor_website'] = $request->has('monitor_website');
        $data['monitor_cpanel'] = $request->has('monitor_cpanel');
        $data['monitor_frameworks'] = $request->has('monitor_frameworks');
        $data['send_recovery_alert'] = $request->has('send_recovery_alert');

        $data['failover_enabled'] = $request->has('failover_enabled');
        $data['dns_failover_enabled'] = $request->has('dns_failover_enabled');

        /*
        |--------------------------------------------------------------------------
        | Keep backup_selected_accounts as array if available
        |--------------------------------------------------------------------------
        */
        if ($request->has('backup_selected_accounts')) {
            $data['backup_selected_accounts'] = $request->input('backup_selected_accounts', []);
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE SECURITY ALERT
    |--------------------------------------------------------------------------
    */
    private function createSecurityAlert(
        Server $server,
        string $type,
        string $level,
        string $title,
        string $message
    ): void {
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
            Log::error('Security alert create failed.', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND DOWN ALERTS
    |--------------------------------------------------------------------------
    */
    private function sendDownAlerts(Server $server, SmsService $smsService): void
    {
        $message = "DOWN ALERT: {$server->name} is OFFLINE. Host: {$server->host}";

        if (!empty($server->email_alerts_enabled)) {
            foreach ($this->serverAlertEmails($server) as $email) {
                try {
                    Mail::to($email)->send(new ServerDownAlertMail($server));
                } catch (\Throwable $e) {
                    Log::error('Server down email failed.', [
                        'server_id' => $server->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!empty($server->sms_alerts_enabled)) {
            foreach ($this->serverAlertPhones($server) as $phone) {
                try {
                    $smsService->send($phone, $message);
                } catch (\Throwable $e) {
                    Log::error('Server down SMS failed.', [
                        'server_id' => $server->id,
                        'phone' => $phone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $server->update($this->filterServerColumns([
            'last_down_alert_sent_at' => now(),
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | SEND RECOVERY ALERTS
    |--------------------------------------------------------------------------
    */
    private function sendRecoveryAlerts(Server $server, SmsService $smsService): void
    {
        if (Schema::hasColumn($server->getTable(), 'send_recovery_alert') && !$server->send_recovery_alert) {
            return;
        }

        $message = "RECOVERED: {$server->name} is back ONLINE. Host: {$server->host}";

        if (!empty($server->email_alerts_enabled)) {
            foreach ($this->serverAlertEmails($server) as $email) {
                try {
                    Mail::to($email)->send(new ServerRecoveryAlertMail($server));
                } catch (\Throwable $e) {
                    Log::error('Server recovery email failed.', [
                        'server_id' => $server->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!empty($server->sms_alerts_enabled)) {
            foreach ($this->serverAlertPhones($server) as $phone) {
                try {
                    $smsService->send($phone, $message);
                } catch (\Throwable $e) {
                    Log::error('Server recovery SMS failed.', [
                        'server_id' => $server->id,
                        'phone' => $phone,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $server->update($this->filterServerColumns([
            'last_recovery_alert_sent_at' => now(),
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | ALERT EMAIL LIST
    |--------------------------------------------------------------------------
    */
    private function serverAlertEmails(Server $server): array
    {
        $emails = [
            $server->admin_email ?? null,
            $server->customer_email ?? null,
        ];

        if (!empty($server->alert_emails)) {
            foreach (explode(',', $server->alert_emails) as $email) {
                $emails[] = trim($email);
            }
        }

        $envEmails = explode(',', (string) env('MONITOR_ALERT_EMAILS', ''));

        foreach ($envEmails as $email) {
            if (trim($email)) {
                $emails[] = trim($email);
            }
        }

        return array_values(array_filter(array_unique(array_filter($emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        }))));
    }

    /*
    |--------------------------------------------------------------------------
    | ALERT PHONE LIST
    |--------------------------------------------------------------------------
    */
    private function serverAlertPhones(Server $server): array
    {
        $phones = [
            $server->admin_phone ?? null,
            $server->customer_phone ?? null,
        ];

        if (!empty($server->alert_phones)) {
            foreach (explode(',', $server->alert_phones) as $phone) {
                $phones[] = trim($phone);
            }
        }

        $envPhones = explode(',', (string) env('MONITOR_ALERT_PHONES', ''));

        foreach ($envPhones as $phone) {
            if (trim($phone)) {
                $phones[] = trim($phone);
            }
        }

        $clean = [];

        foreach ($phones as $phone) {
            $phone = trim((string) $phone);

            if (!$phone) {
                continue;
            }

            $phone = str_replace([' ', '-', '(', ')'], '', $phone);

            if (!in_array($phone, $clean, true)) {
                $clean[] = $phone;
            }
        }

        return $clean;
    }

    /*
    |--------------------------------------------------------------------------
    | GET SSH PASSWORD
    |--------------------------------------------------------------------------
    */
    private function getPassword(Server $server): string
    {
        foreach ([
            $server->password ?? null,
            $server->whm_password ?? null,
        ] as $secret) {
            if (!$secret) {
                continue;
            }

            try {
                return decrypt($secret);
            } catch (\Throwable $e) {
                try {
                    return Crypt::decryptString($secret);
                } catch (\Throwable $e2) {
                    return (string) $secret;
                }
            }
        }

        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER SERVER COLUMNS
    |--------------------------------------------------------------------------
    | Prevents SQL errors if migration is not updated yet.
    |--------------------------------------------------------------------------
    */
    private function filterServerColumns(array $data): array
    {
        $table = (new Server())->getTable();

        return collect($data)
            ->filter(function ($value, $column) use ($table) {
                return Schema::hasColumn($table, $column);
            })
            ->toArray();
    }
}