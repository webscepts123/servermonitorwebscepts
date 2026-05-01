<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use phpseclib3\Net\SSH2;

class CpanelAccountController extends Controller
{
    public function index(Server $server)
    {
        $accounts = [];
        $packages = [];
        $ips = [];
        $error = null;

        try {
            $ssh = $this->ssh($server);

            $rawAccounts = $ssh->exec("whmapi1 listaccts --output=json");
            $rawPackages = $ssh->exec("whmapi1 listpkgs --output=json");
            $rawIps = $ssh->exec("whmapi1 listips --output=json");

            $accountsJson = json_decode($rawAccounts, true);
            $packagesJson = json_decode($rawPackages, true);
            $ipsJson = json_decode($rawIps, true);

            $accounts = $accountsJson['data']['acct'] ?? [];
            $packages = $packagesJson['data']['pkg'] ?? [];
            $ips = $ipsJson['data']['ip'] ?? [];

        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('cpanel.accounts.index', compact(
            'server',
            'accounts',
            'packages',
            'ips',
            'error'
        ));
    }

    public function create(Server $server)
    {
        $packages = [];
        $ips = [];
        $error = null;

        try {
            $ssh = $this->ssh($server);

            $packagesJson = json_decode($ssh->exec("whmapi1 listpkgs --output=json"), true);
            $ipsJson = json_decode($ssh->exec("whmapi1 listips --output=json"), true);

            $packages = $packagesJson['data']['pkg'] ?? [];
            $ips = $ipsJson['data']['ip'] ?? [];

        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('cpanel.accounts.create', compact('server', 'packages', 'ips', 'error'));
    }

    public function store(Request $request, Server $server)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'username' => 'required|string|max:16',
            'password' => 'required|string|min:8',
            'email' => 'required|email',
            'package' => 'required|string',
            'ip' => 'nullable|string',
        ]);

        try {
            $ssh = $this->ssh($server);

            $cmd = "whmapi1 createacct "
                . "domain=" . escapeshellarg($data['domain']) . " "
                . "username=" . escapeshellarg($data['username']) . " "
                . "password=" . escapeshellarg($data['password']) . " "
                . "contactemail=" . escapeshellarg($data['email']) . " "
                . "plan=" . escapeshellarg($data['package']);

            if (!empty($data['ip'])) {
                $cmd .= " ip=" . escapeshellarg($data['ip']);
            }

            $output = $ssh->exec($cmd . " --output=json");
            $json = json_decode($output, true);

            if (($json['metadata']['result'] ?? 0) != 1) {
                return back()->with('error', $json['metadata']['reason'] ?? $output)->withInput();
            }

            return redirect()
                ->route('servers.cpanel.index', $server)
                ->with('success', 'cPanel account created successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function edit(Server $server, string $user)
    {
        $packages = [];
        $ips = [];
        $account = null;
        $error = null;

        try {
            $ssh = $this->ssh($server);

            $accountsJson = json_decode($ssh->exec("whmapi1 listaccts search={$user} searchtype=user --output=json"), true);
            $packagesJson = json_decode($ssh->exec("whmapi1 listpkgs --output=json"), true);
            $ipsJson = json_decode($ssh->exec("whmapi1 listips --output=json"), true);

            $account = $accountsJson['data']['acct'][0] ?? null;
            $packages = $packagesJson['data']['pkg'] ?? [];
            $ips = $ipsJson['data']['ip'] ?? [];

        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('cpanel.accounts.edit', compact('server', 'user', 'account', 'packages', 'ips', 'error'));
    }

    public function updatePassword(Request $request, Server $server, string $user)
    {
        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        try {
            $ssh = $this->ssh($server);

            $cmd = "whmapi1 passwd user=" . escapeshellarg($user)
                . " password=" . escapeshellarg($request->password)
                . " --output=json";

            $json = json_decode($ssh->exec($cmd), true);

            if (($json['metadata']['result'] ?? 0) != 1) {
                return back()->with('error', $json['metadata']['reason'] ?? 'Password update failed.');
            }

            return back()->with('success', 'Password updated successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updatePackage(Request $request, Server $server, string $user)
    {
        $request->validate([
            'package' => 'required|string',
        ]);

        try {
            $ssh = $this->ssh($server);

            $cmd = "whmapi1 changepackage user=" . escapeshellarg($user)
                . " pkg=" . escapeshellarg($request->package)
                . " --output=json";

            $json = json_decode($ssh->exec($cmd), true);

            if (($json['metadata']['result'] ?? 0) != 1) {
                return back()->with('error', $json['metadata']['reason'] ?? 'Package update failed.');
            }

            return back()->with('success', 'Package updated successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updateIp(Request $request, Server $server, string $user)
    {
        $request->validate([
            'ip' => 'required|string',
        ]);

        try {
            $ssh = $this->ssh($server);

            $cmd = "whmapi1 setsiteip user=" . escapeshellarg($user)
                . " ip=" . escapeshellarg($request->ip)
                . " --output=json";

            $json = json_decode($ssh->exec($cmd), true);

            if (($json['metadata']['result'] ?? 0) != 1) {
                return back()->with('error', $json['metadata']['reason'] ?? 'IP update failed.');
            }

            return back()->with('success', 'IP updated successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function ssh(Server $server): SSH2
    {
        $password = $this->getServerPassword($server);

        if ($password === '') {
            throw new \Exception('SSH password is empty.');
        }

        $ssh = new SSH2($server->host, (int) ($server->ssh_port ?? 22));
        $ssh->setTimeout(30);

        if (!$ssh->login((string) $server->username, (string) $password)) {
            throw new \Exception('SSH login failed. Use root or reseller WHM user.');
        }

        return $ssh;
    }

    private function getServerPassword(Server $server): string
    {
        if (empty($server->password)) {
            return '';
        }

        try {
            $password = decrypt($server->password);
        } catch (\Throwable $e) {
            $password = $server->password;
        }

        return is_string($password) ? $password : '';
    }
}