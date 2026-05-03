<?php

namespace App\Http\Controllers;

use App\Mail\ServerDownAlertMail;
use App\Mail\ServerRecoveryAlertMail;
use App\Models\Server;
use App\Models\ServerCheck;
use App\Models\ServerSecurityAlert;
use App\Services\SmsService;
use Illuminate\Http\Request;
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
        $data['password'] = encrypt($data['password']);

        Server::create($data);

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

        if ($request->filled('password')) {
            $data['password'] = encrypt($request->password);
        } else {
            unset($data['password']);
        }

        $server->update($data);

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
    | QUICK CHECK
    |--------------------------------------------------------------------------
    */
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
| - cPanel / WHM port
| - Plesk port
| - Sends SMS/email down + recovery alerts
|--------------------------------------------------------------------------
*/
public function checkNow(Server $server, SmsService $smsService)
{
    $oldStatus = strtolower(trim($server->status ?? 'offline'));

    try {
        $startTime = microtime(true);

        $password = $this->getPassword($server);

        $ssh = new \phpseclib3\Net\SSH2($server->host, $server->ssh_port ?? 22);
        $ssh->setTimeout(20);

        $sshOnline = $ssh->login($server->username, $password);

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
        $pleskOnline = false;

        /*
        |--------------------------------------------------------------------------
        | Port / URL Checks
        |--------------------------------------------------------------------------
        */
        $cpanelOnline = $this->isPortOpen($server->host, 2087, 5);
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

            /*
            |--------------------------------------------------------------------------
            | CPU Usage
            |--------------------------------------------------------------------------
            */
            $cpu = trim($ssh->exec("
                if command -v top >/dev/null 2>&1; then
                    top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}' | awk '{printf \"%.0f\", $1}'
                else
                    echo ''
                fi
            "));

            /*
            |--------------------------------------------------------------------------
            | RAM Usage
            |--------------------------------------------------------------------------
            */
            $ram = trim($ssh->exec("
                free | awk '/Mem:/ {printf(\"%.0f\", $3/$2 * 100)}'
            "));

            /*
            |--------------------------------------------------------------------------
            | Disk Usage
            |--------------------------------------------------------------------------
            */
            $disk = trim($ssh->exec("
                df -h / | awk 'NR==2 {print $5}' | sed 's/%//'
            "));

            /*
            |--------------------------------------------------------------------------
            | Load Average
            |--------------------------------------------------------------------------
            */
            $load = trim($ssh->exec("
                uptime | awk -F'load average:' '{ print $2 }' | sed 's/^ *//'
            "));

            /*
            |--------------------------------------------------------------------------
            | Service Checks
            |--------------------------------------------------------------------------
            */
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

                /*
                |--------------------------------------------------------------------------
                | LiteSpeed Services
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
            | LiteSpeed Fallback Detection
            |--------------------------------------------------------------------------
            | Some LiteSpeed installs use lswsctrl without clean systemctl service.
            |--------------------------------------------------------------------------
            */
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

            /*
            |--------------------------------------------------------------------------
            | Firewall Status
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
        }

        /*
        |--------------------------------------------------------------------------
        | Server Status Logic
        |--------------------------------------------------------------------------
        | Server is online if SSH is online OR website is online OR panel is online.
        |--------------------------------------------------------------------------
        */
        $newStatus = ($sshOnline || $websiteOnline || $cpanelOnline || $pleskOnline)
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

        /*
        |--------------------------------------------------------------------------
        | Optional Columns
        |--------------------------------------------------------------------------
        | Prevents SQL errors if columns are missing.
        |--------------------------------------------------------------------------
        */
        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'response_time')) {
            $checkData['response_time'] = $responseTime;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'firewall_status')) {
            $checkData['firewall_status'] = $firewallStatus;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'website_online')) {
            $checkData['website_online'] = $websiteOnline;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'cpanel_online')) {
            $checkData['cpanel_online'] = $cpanelOnline;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'plesk_online')) {
            $checkData['plesk_online'] = $pleskOnline;
        }

        ServerCheck::create($checkData);

        /*
        |--------------------------------------------------------------------------
        | Update Server Main Status
        |--------------------------------------------------------------------------
        */
        $server->update([
            'status' => $newStatus,
        ]);

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

        /*
        |--------------------------------------------------------------------------
        | If check crashes, mark offline and save failed check
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

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'response_time')) {
            $checkData['response_time'] = null;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'website_online')) {
            $checkData['website_online'] = false;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'cpanel_online')) {
            $checkData['cpanel_online'] = false;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('server_checks', 'plesk_online')) {
            $checkData['plesk_online'] = false;
        }

        ServerCheck::create($checkData);

        if ($oldStatus !== 'offline') {
            $this->sendDownAlerts($server->fresh(), $smsService);
        }

        return back()->with(
            'error',
            'Server check failed: ' . $e->getMessage()
        );
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

    /*
    |--------------------------------------------------------------------------
    | SECURITY SCAN
    |--------------------------------------------------------------------------
    */
    public function securityScan(Server $server)
    {
        try {
            $password = $this->getPassword($server);

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
            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
            $ssh->setTimeout(20);

            if (!$ssh->login($server->username, $this->getPassword($server))) {
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
    | PRIVATE HELPERS
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
            'panel_type' => 'nullable|string|in:cpanel,plesk,none',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => $isUpdate ? 'nullable|string' : 'required|string',

            /*
            |--------------------------------------------------------------------------
            | Alert contact data
            |--------------------------------------------------------------------------
            */
            'admin_email' => 'nullable|email|max:255',
            'admin_phone' => 'nullable|string|max:30',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:30',

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
        ]);
    }

    private function prepareServerData(Request $request, array $data): array
    {
        $data['ssh_port'] = $data['ssh_port'] ?? 22;
        $data['disk_warning_percent'] = $data['disk_warning_percent'] ?? 80;
        $data['disk_transfer_percent'] = $data['disk_transfer_percent'] ?? 90;

        if (($data['panel_type'] ?? '') === '') {
            $data['panel_type'] = null;
        }

        if (($data['backup_server_id'] ?? '') === '') {
            $data['backup_server_id'] = null;
        }

        $data['is_active'] = $request->has('is_active');
        $data['auto_transfer'] = $request->has('auto_transfer');
        $data['google_drive_sync'] = $request->has('google_drive_sync');
        $data['email_alerts_enabled'] = $request->has('email_alerts_enabled');
        $data['sms_alerts_enabled'] = $request->has('sms_alerts_enabled');

        return $data;
    }

    private function createSecurityAlert(
        Server $server,
        string $type,
        string $level,
        string $title,
        string $message
    ): void {
        ServerSecurityAlert::create([
            'server_id' => $server->id,
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'detected_at' => now(),
        ]);
    }

    private function sendDownAlerts(Server $server, SmsService $smsService): void
    {
        $message = "DOWN ALERT: {$server->name} is OFFLINE. Host: {$server->host}";

        if (!empty($server->email_alerts_enabled)) {
            foreach ([$server->admin_email, $server->customer_email] as $email) {
                if ($email) {
                    Mail::to($email)->send(new ServerDownAlertMail($server));
                }
            }
        }

        if (!empty($server->sms_alerts_enabled)) {
            foreach ([$server->admin_phone, $server->customer_phone] as $phone) {
                if ($phone) {
                    $smsService->send($phone, $message);
                }
            }
        }
    }

    private function sendRecoveryAlerts(Server $server, SmsService $smsService): void
    {
        $message = "RECOVERED: {$server->name} is back ONLINE. Host: {$server->host}";

        if (!empty($server->email_alerts_enabled)) {
            foreach ([$server->admin_email, $server->customer_email] as $email) {
                if ($email) {
                    Mail::to($email)->send(new ServerRecoveryAlertMail($server));
                }
            }
        }

        if (!empty($server->sms_alerts_enabled)) {
            foreach ([$server->admin_phone, $server->customer_phone] as $phone) {
                if ($phone) {
                    $smsService->send($phone, $message);
                }
            }
        }
    }

    private function getPassword(Server $server): string
    {
        try {
            return decrypt($server->password);
        } catch (\Throwable $e) {
            return $server->password;
        }
    }
}