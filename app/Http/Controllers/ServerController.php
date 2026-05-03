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
    public function checkNow(Server $server, SmsService $smsService)
    {
        $oldStatus = strtolower(trim($server->status ?? 'offline'));

        try {
            $startTime = microtime(true);

            $password = $this->getPassword($server);

            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
            $ssh->setTimeout(15);

            $sshOnline = $ssh->login($server->username, $password);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $cpu = null;
            $ram = null;
            $disk = null;
            $load = null;
            $services = [];
            $firewallStatus = 'Unknown';

            if ($sshOnline) {
                $cpu = trim($ssh->exec("top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}'"));
                $ram = trim($ssh->exec("free | awk '/Mem:/ {printf(\"%.0f\", $3/$2 * 100)}'"));
                $disk = trim($ssh->exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'"));
                $load = trim($ssh->exec("uptime | awk -F'load average:' '{ print $2 }'"));

                $services = [
                    'web' => trim($ssh->exec("systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null || echo unknown")),
                    'nginx' => trim($ssh->exec("systemctl is-active nginx 2>/dev/null || echo unknown")),
                    'mysql' => trim($ssh->exec("systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null || echo unknown")),
                    'ssh' => trim($ssh->exec("systemctl is-active sshd 2>/dev/null || systemctl is-active ssh 2>/dev/null || echo unknown")),
                ];

                $firewallStatus = trim($ssh->exec("systemctl is-active firewalld 2>/dev/null || systemctl is-active csf 2>/dev/null || ufw status 2>/dev/null | head -n 1 || echo Unknown"));
            }

            $newStatus = $sshOnline ? 'online' : 'offline';

            $checkData = [
                'server_id' => $server->id,
                'online' => $sshOnline,
                'ssh_online' => $sshOnline,
                'status' => ucfirst($newStatus),
                'cpu_usage' => is_numeric($cpu) ? $cpu : null,
                'ram_usage' => is_numeric($ram) ? $ram : null,
                'disk_usage' => is_numeric($disk) ? $disk : null,
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

            ServerCheck::create($checkData);

            $server->update([
                'status' => $newStatus,
            ]);

            if ($newStatus === 'offline' && $oldStatus !== 'offline') {
                $this->sendDownAlerts($server->fresh(), $smsService);
            }

            if ($newStatus === 'online' && $oldStatus === 'offline') {
                $this->sendRecoveryAlerts($server->fresh(), $smsService);
            }

            return back()->with('success', 'Server checked successfully.');

        } catch (\Throwable $e) {
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

            if (Schema::hasColumn('server_checks', 'response_time')) {
                $checkData['response_time'] = null;
            }

            ServerCheck::create($checkData);

            if ($oldStatus !== 'offline') {
                $this->sendDownAlerts($server->fresh(), $smsService);
            }

            return back()->with('error', 'Server check failed: ' . $e->getMessage());
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