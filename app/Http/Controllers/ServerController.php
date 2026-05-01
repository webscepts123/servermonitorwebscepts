<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerCheck;
use App\Models\ServerSecurityAlert;
use Illuminate\Http\Request;
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
        $servers = Server::latest()->get();
        return view('servers.index', compact('servers'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        $backupServers = Server::where('is_active', 1)->get();
        return view('servers.create', compact('backupServers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string',

            'backup_server_id' => 'nullable|exists:servers,id',
            'backup_path' => 'nullable|string|max:500',
            'local_backup_path' => 'nullable|string|max:500',
            'google_drive_remote' => 'nullable|string|max:255',
            'disk_warning_percent' => 'nullable|integer|min:1|max:100',
            'disk_transfer_percent' => 'nullable|integer|min:1|max:100',

            'auto_transfer' => 'nullable|boolean',
            'google_drive_sync' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $data['ssh_port'] = $data['ssh_port'] ?? 22;
        $data['disk_warning_percent'] = $data['disk_warning_percent'] ?? 80;
        $data['disk_transfer_percent'] = $data['disk_transfer_percent'] ?? 90;

        $data['is_active'] = $request->has('is_active');
        $data['auto_transfer'] = $request->has('auto_transfer');
        $data['google_drive_sync'] = $request->has('google_drive_sync');

        // Encrypt password
        $data['password'] = encrypt($data['password']);

        Server::create($data);

        return redirect()->route('servers.index')->with('success', 'Server created successfully.');
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
            'securityAlerts'
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
        $backupServers = Server::where('id', '!=', $server->id)->get();
        return view('servers.edit', compact('server', 'backupServers'));
    }

    public function update(Request $request, Server $server)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'ssh_port' => 'nullable|integer',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',

            'backup_server_id' => 'nullable|exists:servers,id',
            'backup_path' => 'nullable|string',
            'local_backup_path' => 'nullable|string',
            'google_drive_remote' => 'nullable|string',
            'disk_warning_percent' => 'nullable|integer',
            'disk_transfer_percent' => 'nullable|integer',

            'auto_transfer' => 'nullable|boolean',
            'google_drive_sync' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->filled('password')) {
            $data['password'] = encrypt($request->password);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $request->has('is_active');
        $data['auto_transfer'] = $request->has('auto_transfer');
        $data['google_drive_sync'] = $request->has('google_drive_sync');

        $server->update($data);

        return redirect()->route('servers.index')->with('success', 'Server updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy(Server $server)
    {
        $server->delete();
        return redirect()->route('servers.index')->with('success', 'Server deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | QUICK CHECK
    |--------------------------------------------------------------------------
    */
    public function checkNow(Server $server)
    {
        try {
            $password = $this->getPassword($server);

            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
            $ssh->setTimeout(15);

            $sshOnline = $ssh->login($server->username, $password);

            $cpu = $ram = $disk = $load = null;
            $services = [];

            if ($sshOnline) {
                $cpu = trim($ssh->exec("top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}'"));
                $ram = trim($ssh->exec("free | awk '/Mem:/ {printf(\"%.0f\", $3/$2 * 100)}'"));
                $disk = trim($ssh->exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'"));
                $load = trim($ssh->exec("uptime | awk -F'load average:' '{ print $2 }'"));

                $services = [
                    'web' => trim($ssh->exec("systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null")),
                    'mysql' => trim($ssh->exec("systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null")),
                    'ssh' => trim($ssh->exec("systemctl is-active sshd 2>/dev/null")),
                ];
            }

            ServerCheck::create([
                'server_id' => $server->id,
                'online' => $sshOnline,
                'ssh_online' => $sshOnline,
                'status' => $sshOnline ? 'Online' : 'Offline',
                'cpu_usage' => is_numeric($cpu) ? $cpu : null,
                'ram_usage' => is_numeric($ram) ? $ram : null,
                'disk_usage' => is_numeric($disk) ? $disk : null,
                'load_average' => $load,
                'services' => json_encode($services),
                'checked_at' => now(),
            ]);

            return back()->with('success', 'Server checked successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
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
            $ssh->login($server->username, $password);

            $disk = (int) trim($ssh->exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'"));

            if ($disk > 90) {
                ServerSecurityAlert::create([
                    'server_id' => $server->id,
                    'type' => 'disk',
                    'level' => 'danger',
                    'title' => 'Disk usage critical',
                    'message' => "Disk usage {$disk}%",
                    'detected_at' => now(),
                ]);
            }

            return back()->with('success', 'Security scan completed.');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
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

        $blocked = ['rm -rf', 'shutdown', 'reboot', 'mkfs', 'dd if='];

        foreach ($blocked as $bad) {
            if (str_contains(strtolower($request->command), $bad)) {
                return response()->json([
                    'success' => false,
                    'output' => 'Dangerous command blocked.'
                ], 422);
            }
        }

        try {
            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);

            if (!$ssh->login($server->username, $this->getPassword($server))) {
                throw new \Exception('SSH login failed');
            }

            $output = $ssh->exec($request->command);

            return response()->json([
                'success' => true,
                'output' => $output ?: 'No output'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'output' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */
    private function getPassword(Server $server)
    {
        try {
            return decrypt($server->password);
        } catch (\Throwable $e) {
            return $server->password;
        }
    }
}