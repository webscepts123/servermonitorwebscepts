<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use phpseclib3\Net\SSH2;

class CpanelAccountController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST cPanel ACCOUNTS
    |--------------------------------------------------------------------------
    */
    public function index(Server $server)
    {
        $accounts = [];
        $error = null;

        try {
            $response = $this->whmRequest($server, 'listaccts');
            $accounts = $response['data']['acct'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('cpanel.accounts.index', compact('server', 'accounts', 'error'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ACCOUNT PAGE
    |--------------------------------------------------------------------------
    */
    public function create(\App\Models\Server $server)
    {
        $error = null;
        $packages = [];
        $ips = [];
    
        try {
            $whm = $this->whmRequest($server, 'listpkgs');
    
            if (!empty($whm['package'])) {
                $packages = $whm['package'];
            } elseif (!empty($whm['data']['pkg'])) {
                $packages = $whm['data']['pkg'];
            }
        } catch (\Throwable $e) {
            $error = 'Unable to load packages: ' . $e->getMessage();
        }
    
        try {
            $ipResponse = $this->whmRequest($server, 'listips');
    
            if (!empty($ipResponse['data']['ip'])) {
                $ips = $ipResponse['data']['ip'];
            } elseif (!empty($ipResponse['ip'])) {
                $ips = $ipResponse['ip'];
            } elseif (!empty($ipResponse['data']['ips'])) {
                $ips = $ipResponse['data']['ips'];
            }
        } catch (\Throwable $e) {
            // Keep page working even if WHM cannot return IP list.
            $ips = [];
        }
    
        return view('cpanel.accounts.create', compact(
            'server',
            'packages',
            'ips',
            'error'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE cPanel ACCOUNT
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, Server $server)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'username' => 'required|string|max:16',
            'password' => 'required|string|min:8',
            'email' => 'nullable|email|max:255',
            'plan' => 'nullable|string|max:255',
        ]);

        try {
            $params = [
                'domain' => $data['domain'],
                'username' => $data['username'],
                'password' => $data['password'],
                'contactemail' => $data['email'] ?? '',
            ];

            if (!empty($data['plan'])) {
                $params['plan'] = $data['plan'];
            }

            $this->whmRequest($server, 'createacct', $params);

            return redirect()
                ->route('servers.cpanel.index', $server)
                ->with('success', 'cPanel account created successfully.');

        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Create account failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGE ACCOUNT PAGE
    |--------------------------------------------------------------------------
    */
    public function edit(Server $server, string $user)
    {
        $account = [];
        $packages = [];
        $ips = [];
        $error = null;

        try {
            $account = $this->getAccount($server, $user);
            $packages = $this->getPackages($server);
            $ips = $this->getIps($server);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $remoteData = $this->getRemoteAccountData($server, $user, $account);

        return view('cpanel.accounts.edit', array_merge(
            compact('server', 'user', 'account', 'packages', 'ips', 'error'),
            $remoteData
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE cPanel ACCOUNT PASSWORD
    |--------------------------------------------------------------------------
    */
    public function updatePassword(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        try {
            $this->whmRequest($server, 'passwd', [
                'user' => $user,
                'password' => $data['password'],
            ]);

            return back()->with('success', 'Password updated successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Password update failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PACKAGE
    |--------------------------------------------------------------------------
    */
    public function updatePackage(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'package' => 'required|string|max:255',
        ]);

        try {
            $this->whmRequest($server, 'changepackage', [
                'user' => $user,
                'pkg' => $data['package'],
            ]);

            return back()->with('success', 'Package changed successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Package change failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ACCOUNT IP
    |--------------------------------------------------------------------------
    */
    public function updateIp(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'ip' => 'required|string|max:255',
        ]);

        try {
            $this->whmRequest($server, 'setsiteip', [
                'user' => $user,
                'ip' => $data['ip'],
            ]);

            return back()->with('success', 'IP changed successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', 'IP change failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MANUAL SMS
    |--------------------------------------------------------------------------
    */
    public function sendAccountSms(
        Request $request,
        Server $server,
        string $user,
        SmsService $smsService
    ) {
        $data = $request->validate([
            'phone' => 'required|string|max:30',
            'message' => 'required|string|max:500',
        ]);

        try {
            $sent = $smsService->send($data['phone'], $data['message']);

            return back()->with(
                $sent ? 'success' : 'error',
                $sent ? 'SMS sent successfully.' : 'SMS failed. Check storage/logs/laravel.log.'
            );

        } catch (\Throwable $e) {
            return back()->with('error', 'SMS failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MANUAL EMAIL
    |--------------------------------------------------------------------------
    */
    public function sendAccountEmail(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        try {
            Mail::raw($data['message'], function ($mail) use ($data) {
                $mail->to($data['email'])
                    ->subject($data['subject']);
            });

            return back()->with('success', 'Email sent successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Email failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO cPanel HOME
    |--------------------------------------------------------------------------
    */
    public function autoLogin(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            return back()->with('error', 'Auto login failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO EMAIL ACCOUNTS PAGE
    |--------------------------------------------------------------------------
    */
    public function autoLoginEmail(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld', 'Email_Accounts');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            try {
                $url = $this->createUserSession($server, $user, 'cpaneld');
                return redirect()->away($url);
            } catch (\Throwable $e2) {
                return back()->with('error', 'Email auto login failed: ' . $e2->getMessage());
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO FILE MANAGER
    |--------------------------------------------------------------------------
    */
    public function autoLoginFiles(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld', 'FileManager');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            try {
                $url = $this->createUserSession($server, $user, 'cpaneld');
                return redirect()->away($url);
            } catch (\Throwable $e2) {
                return back()->with('error', 'File Manager auto login failed: ' . $e2->getMessage());
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO WORDPRESS MANAGER
    |--------------------------------------------------------------------------
    */
    public function autoLoginWordPress(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld', 'WordPress_Manager');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            try {
                $url = $this->createUserSession($server, $user, 'cpaneld');
                return redirect()->away($url);
            } catch (\Throwable $e2) {
                return back()->with('error', 'WordPress auto login failed: ' . $e2->getMessage());
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE ACCOUNT
    |--------------------------------------------------------------------------
    */
    private function getAccount(Server $server, string $user): array
    {
        try {
            $response = $this->whmRequest($server, 'accountsummary', [
                'user' => $user,
            ]);

            $acct = $response['data']['acct'][0] ?? null;

            if ($acct) {
                return $acct;
            }
        } catch (\Throwable $e) {
            // fallback to listaccts below
        }

        $list = $this->whmRequest($server, 'listaccts');
        $accounts = $list['data']['acct'] ?? [];

        foreach ($accounts as $account) {
            if (($account['user'] ?? null) === $user) {
                return $account;
            }
        }

        throw new \Exception('Account not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET PACKAGES
    |--------------------------------------------------------------------------
    */
    private function getPackages(Server $server): array
    {
        try {
            $response = $this->whmRequest($server, 'listpkgs');

            return $response['data']['pkg'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET IPs
    |--------------------------------------------------------------------------
    */
    private function getIps(Server $server): array
    {
        try {
            $response = $this->whmRequest($server, 'listips');

            return $response['data']['ip'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REAL REMOTE ACCOUNT DATA THROUGH SSH
    |--------------------------------------------------------------------------
    */
    private function getRemoteAccountData(Server $server, string $user, array $account): array
    {
        $data = [
            'realDiskUsage' => null,
            'realDiskLimit' => null,
            'realHomePath' => "/home/{$user}",
            'realPublicHtml' => "/home/{$user}/public_html",
            'remoteServices' => [],
            'wordpressData' => [
                'detected' => false,
                'wp_cli_available' => false,
                'version' => null,
                'plugins_total' => 0,
                'plugins_active' => 0,
                'plugins_update' => 0,
                'themes_total' => 0,
                'themes_active' => 0,
                'themes_update' => 0,
                'status_message' => 'Not checked',
                'plugins' => [],
                'themes' => [],
            ],
            'emailSecurityData' => [
                'spf' => 'Unknown',
                'dkim' => 'Unknown',
                'dmarc' => 'Unknown',
            ],
        ];

        try {
            $ssh = $this->ssh($server);

            $homePath = trim($ssh->exec("eval echo ~{$user} 2>/dev/null"));

            if ($homePath && !str_contains(strtolower($homePath), 'not found')) {
                $data['realHomePath'] = $homePath;
                $data['realPublicHtml'] = $homePath . '/public_html';
            }

            $homeArg = escapeshellarg($data['realHomePath']);

            $data['realDiskUsage'] = trim(
                $ssh->exec("du -sh {$homeArg} 2>/dev/null | awk '{print $1}'")
            );

            $data['realDiskLimit'] =
                $account['disklimit']
                ?? $account['disklimit_human']
                ?? $account['diskquota']
                ?? null;

            $data['remoteServices'] = [
                'apache/httpd' => trim($ssh->exec("systemctl is-active httpd 2>/dev/null || systemctl is-active apache2 2>/dev/null || echo unknown")),
                'nginx' => trim($ssh->exec("systemctl is-active nginx 2>/dev/null || echo unknown")),
                'mysql/mariadb' => trim($ssh->exec("systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null || echo unknown")),
                'exim' => trim($ssh->exec("systemctl is-active exim 2>/dev/null || echo unknown")),
                'cpanel' => trim($ssh->exec("systemctl is-active cpanel 2>/dev/null || echo unknown")),
                'ssh' => trim($ssh->exec("systemctl is-active sshd 2>/dev/null || systemctl is-active ssh 2>/dev/null || echo unknown")),
            ];

            $data['wordpressData'] = $this->getWordPressData($ssh, $data['realPublicHtml']);

            $domain = $account['domain'] ?? null;
            $data['emailSecurityData'] = $this->getEmailSecurityData($ssh, $domain);

        } catch (\Throwable $e) {
            $data['wordpressData']['status_message'] = 'SSH check failed: ' . $e->getMessage();
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | WORDPRESS REAL DATA
    |--------------------------------------------------------------------------
    */
    private function getWordPressData(SSH2 $ssh, string $path): array
    {
        $wp = [
            'detected' => false,
            'wp_cli_available' => false,
            'version' => null,
            'plugins_total' => 0,
            'plugins_active' => 0,
            'plugins_update' => 0,
            'themes_total' => 0,
            'themes_active' => 0,
            'themes_update' => 0,
            'status_message' => 'WordPress not detected',
            'plugins' => [],
            'themes' => [],
        ];

        $pathArg = escapeshellarg($path);

        $hasConfig = trim(
            $ssh->exec("[ -f {$pathArg}/wp-config.php ] && echo yes || echo no")
        );

        $wp['detected'] = $hasConfig === 'yes';

        if (!$wp['detected']) {
            $wp['status_message'] = 'wp-config.php not found in public_html';
            return $wp;
        }

        $wpCli = trim($ssh->exec("command -v wp 2>/dev/null || echo no"));
        $wp['wp_cli_available'] = $wpCli !== 'no' && $wpCli !== '';

        if (!$wp['wp_cli_available']) {
            $versionFile = trim(
                $ssh->exec("grep \"\\\$wp_version\" {$pathArg}/wp-includes/version.php 2>/dev/null | head -n 1 | sed \"s/.*= '//\" | sed \"s/';//\"")
            );

            $wp['version'] = $versionFile ?: null;
            $wp['status_message'] = 'WordPress detected, but WP-CLI is not installed on server';

            return $wp;
        }

        $version = trim(
            $ssh->exec("wp core version --path={$pathArg} --allow-root 2>/dev/null")
        );

        $pluginsJson = trim(
            $ssh->exec("wp plugin list --format=json --path={$pathArg} --allow-root 2>/dev/null")
        );

        $themesJson = trim(
            $ssh->exec("wp theme list --format=json --path={$pathArg} --allow-root 2>/dev/null")
        );

        $plugins = json_decode($pluginsJson, true) ?: [];
        $themes = json_decode($themesJson, true) ?: [];

        $wp['version'] = $version ?: null;
        $wp['plugins'] = $plugins;
        $wp['themes'] = $themes;

        $wp['plugins_total'] = count($plugins);
        $wp['plugins_active'] = collect($plugins)->where('status', 'active')->count();
        $wp['plugins_update'] = collect($plugins)->where('update', 'available')->count();

        $wp['themes_total'] = count($themes);
        $wp['themes_active'] = collect($themes)->where('status', 'active')->count();
        $wp['themes_update'] = collect($themes)->where('update', 'available')->count();

        $wp['status_message'] = 'WordPress detected';

        return $wp;
    }

    /*
    |--------------------------------------------------------------------------
    | EMAIL DNS SECURITY DATA
    |--------------------------------------------------------------------------
    */
    private function getEmailSecurityData(SSH2 $ssh, ?string $domain): array
    {
        if (!$domain || $domain === 'Unknown domain') {
            return [
                'spf' => 'Unknown',
                'dkim' => 'Unknown',
                'dmarc' => 'Unknown',
            ];
        }

        $domainArg = escapeshellarg($domain);

        $spf = trim(
            $ssh->exec("dig TXT {$domainArg} +short 2>/dev/null | grep -i 'v=spf1' | head -n 1")
        );

        $dmarc = trim(
            $ssh->exec("dig TXT _dmarc.{$domainArg} +short 2>/dev/null | head -n 1")
        );

        $dkim = trim(
            $ssh->exec("dig TXT default._domainkey.{$domainArg} +short 2>/dev/null | head -n 1")
        );

        return [
            'spf' => $spf ? 'Configured' : 'Missing',
            'dkim' => $dkim ? 'Configured / Possible' : 'Missing / Unknown',
            'dmarc' => $dmarc ? 'Configured' : 'Missing',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE TEMPORARY cPanel USER SESSION
    |--------------------------------------------------------------------------
    */
    private function createUserSession(
        Server $server,
        string $cpanelUser,
        string $service = 'cpaneld',
        ?string $app = null
    ): string {
        $params = [
            'user' => $cpanelUser,
            'service' => $service,
        ];

        if ($app) {
            $params['app'] = $app;
        }

        $response = $this->whmRequest($server, 'create_user_session', $params);

        $url = $response['data']['url'] ?? null;

        if (!$url && !empty($response['data']['session'])) {
            $url = "https://{$server->host}:2083/login/?session=" . urlencode($response['data']['session']);
        }

        if (!$url) {
            throw new \Exception('cPanel session URL not returned.');
        }

        return $url;
    }

    /*
    |--------------------------------------------------------------------------
    | WHM REQUEST WITHOUT API TOKEN
    |--------------------------------------------------------------------------
    */
    private function whmRequest(Server $server, string $function, array $params = []): array
    {
        $host = $server->host;
        $url = "https://{$host}:2087/json-api/{$function}";

        $params = array_merge([
            'api.version' => 1,
        ], $params);

        $username = $server->username ?: 'root';
        $password = $this->getPassword($server);

        if (!$username || !$password) {
            throw new \Exception('Server username/password missing.');
        }

        $response = Http::withBasicAuth($username, $password)
            ->withoutVerifying()
            ->timeout(30)
            ->get($url, $params);

        if (!$response->successful()) {
            throw new \Exception(
                'WHM login/API failed: HTTP ' . $response->status() . ' - ' . $response->body()
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new \Exception('Invalid WHM response.');
        }

        $metadata = $json['metadata'] ?? [];

        if (isset($metadata['result']) && (int) $metadata['result'] === 0) {
            $reason = $metadata['reason'] ?? 'Unknown WHM API error.';
            throw new \Exception($reason);
        }

        return $json;
    }

    /*
    |--------------------------------------------------------------------------
    | SSH LOGIN
    |--------------------------------------------------------------------------
    */
    private function ssh(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
        $ssh->setTimeout(25);

        $password = $this->getPassword($server);

        if (!$ssh->login($server->username, $password)) {
            throw new \Exception('SSH login failed.');
        }

        return $ssh;
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD HELPER
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
}