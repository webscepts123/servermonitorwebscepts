<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use phpseclib3\Net\SSH2;

class DeveloperCpanelImportController extends Controller
{
    public function index(Request $request)
    {
        $servers = Server::latest()->get();

        $developers = DeveloperUser::latest()
            ->limit(300)
            ->get()
            ->keyBy('cpanel_username');

        $cpanelAccounts = session('cpanel_accounts', []);

        $frameworks = $this->frameworkOptions();

        return view('developers.cpanel-import', compact(
            'servers',
            'developers',
            'cpanelAccounts',
            'frameworks'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch cPanel Accounts From WHM
    |--------------------------------------------------------------------------
    | Updated:
    | 1. First tries SSH command: whmapi1 listaccts --output=json
    | 2. Then falls back to WHM HTTP API username/password
    |--------------------------------------------------------------------------
    */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
        ]);

        $server = Server::findOrFail($data['server_id']);

        try {
            $accounts = $this->fetchCpanelAccounts($server);

            session([
                'cpanel_accounts' => $accounts,
                'cpanel_accounts_server_id' => $server->id,
            ]);

            return back()->with(
                'success',
                count($accounts) . ' cPanel accounts loaded from ' . ($server->name ?? $server->host ?? 'server')
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to fetch cPanel accounts: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Import Selected cPanel Users To Developer Codes
    |--------------------------------------------------------------------------
    */
    public function bulkImport(Request $request)
    {
        $data = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            'selected' => ['nullable', 'array'],
            'accounts' => ['nullable', 'array'],
        ]);

        $selected = $data['selected'] ?? [];
        $accounts = $data['accounts'] ?? [];

        if (empty($selected)) {
            return back()->with('error', 'Please tick at least one cPanel account to add to Developer Codes.');
        }

        $createdLogins = [];
        $updated = 0;

        foreach ($selected as $username) {
            if (!isset($accounts[$username])) {
                continue;
            }

            $account = $accounts[$username];

            $cpanelUsername = trim($account['user'] ?? $username);
            $contactEmail = trim($account['email'] ?? '');
            $domain = trim($account['domain'] ?? '');

            if (!$cpanelUsername) {
                continue;
            }

            if (!$contactEmail || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                $contactEmail = $cpanelUsername . '@developer.local';
            }

            $framework = trim($account['framework'] ?? 'custom') ?: 'custom';
            $frameworkConfig = $this->frameworkDefaults($framework, $cpanelUsername, $domain);

            $projectRoot = trim($account['project_root'] ?? '') ?: $frameworkConfig['project_root'];
            $allowedPath = $projectRoot ?: ('/home/' . $cpanelUsername);

            /*
            |--------------------------------------------------------------------------
            | Developer Portal Access ON/OFF
            |--------------------------------------------------------------------------
            | Blade can send:
            | accounts[USERNAME][developer_portal_access] = 1
            | or:
            | accounts[USERNAME][developer_portal_access] = 0
            |--------------------------------------------------------------------------
            */
            $portalAccess = $this->boolFromArray($account, 'developer_portal_access', true);

            $temporaryPassword = Str::password(16);

            $dbType = strtolower(trim($account['db_type'] ?? 'mysql'));

            if (!in_array($dbType, ['mysql', 'postgresql', 'pgsql', 'postgres'], true)) {
                $dbType = 'mysql';
            }

            if (in_array($dbType, ['pgsql', 'postgres'], true)) {
                $dbType = 'postgresql';
            }

            $payload = [
                'server_id' => $data['server_id'],

                'name' => $account['name'] ?: $cpanelUsername,
                'email' => $contactEmail,
                'contact_email' => $contactEmail,
                'cpanel_username' => $cpanelUsername,
                'cpanel_domain' => $domain,

                'password' => bcrypt($temporaryPassword),
                'temporary_password' => Crypt::encryptString($temporaryPassword),
                'password_must_change' => true,

                'role' => 'developer',
                'ssh_username' => $cpanelUsername,
                'allowed_project_path' => $allowedPath,

                'project_type' => $account['project_type'] ?? $frameworkConfig['project_type'],
                'framework' => $framework,
                'project_root' => $projectRoot,
                'build_command' => trim($account['build_command'] ?? '') ?: $frameworkConfig['build_command'],
                'deploy_command' => trim($account['deploy_command'] ?? '') ?: $frameworkConfig['deploy_command'],
                'start_command' => trim($account['start_command'] ?? '') ?: $frameworkConfig['start_command'],

                'can_git_pull' => !empty($account['can_git_pull']),
                'can_clear_cache' => !empty($account['can_clear_cache']),
                'can_composer' => !empty($account['can_composer']),
                'can_npm' => !empty($account['can_npm']),
                'can_run_build' => !empty($account['can_run_build']),
                'can_run_python' => !empty($account['can_run_python']),
                'can_restart_app' => !empty($account['can_restart_app']),
                'can_view_files' => !empty($account['can_view_files']),
                'can_edit_files' => !empty($account['can_edit_files']),
                'can_delete_files' => !empty($account['can_delete_files']),

                'can_mysql' => !empty($account['can_mysql']) || $dbType === 'mysql',
                'can_postgresql' => !empty($account['can_postgresql']) || $dbType === 'postgresql',
                'db_host' => $account['db_host'] ?? 'localhost',
                'db_port' => $account['db_port'] ?? $this->defaultDbPort($dbType),
                'db_type' => $dbType,
                'db_username' => $account['db_username'] ?? $cpanelUsername,
                'db_name' => $account['db_name'] ?? '',
            ];

            $payload = array_merge($payload, $this->portalAccessPayload($portalAccess));

            DeveloperUser::updateOrCreate(
                [
                    'cpanel_username' => $cpanelUsername,
                ],
                $this->filterDeveloperColumns($payload)
            );

            $updated++;

            $createdLogins[] = [
                'name' => $account['name'] ?: $cpanelUsername,
                'login' => $cpanelUsername,
                'email' => $contactEmail,
                'domain' => $domain,
                'framework' => $framework,
                'project_root' => $projectRoot,
                'portal_access' => $portalAccess ? 'Enabled' : 'Disabled',
                'password' => $temporaryPassword,
                'url' => 'https://developercodes.webscepts.com/login',
            ];
        }

        return back()
            ->with('success', $updated . ' developer login(s) created/updated.')
            ->with('created_logins', $createdLogins);
    }

    /*
    |--------------------------------------------------------------------------
    | Import One Normal cPanel Login
    |--------------------------------------------------------------------------
    */
    public function importSingleCpanelLogin(Request $request)
    {
        $data = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            'cpanel_username' => ['required', 'string', 'max:100'],
            'cpanel_password' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'cpanel_domain' => ['nullable', 'string', 'max:255'],
            'framework' => ['nullable', 'string', 'max:100'],
            'project_root' => ['nullable', 'string', 'max:255'],

            'developer_portal_access' => ['nullable'],

            'db_type' => ['nullable', 'string', 'max:50'],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:20'],
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'db_name' => ['nullable', 'string', 'max:255'],

            'can_git_pull' => ['nullable'],
            'can_clear_cache' => ['nullable'],
            'can_composer' => ['nullable'],
            'can_npm' => ['nullable'],
            'can_run_build' => ['nullable'],
            'can_run_python' => ['nullable'],
            'can_restart_app' => ['nullable'],
            'can_view_files' => ['nullable'],
            'can_edit_files' => ['nullable'],
            'can_delete_files' => ['nullable'],
            'can_mysql' => ['nullable'],
            'can_postgresql' => ['nullable'],
        ]);

        $server = Server::findOrFail($data['server_id']);

        $cpanelUsername = trim($data['cpanel_username']);
        $cpanelPassword = $data['cpanel_password'];

        $cpanelUrl = $this->cpanelUrlFromServer($server);

        try {
            $accountInfo = $this->fetchSingleCpanelAccountInfo(
                $cpanelUrl,
                $cpanelUsername,
                $cpanelPassword
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'cPanel login failed: ' . $e->getMessage());
        }

        $domain = $data['cpanel_domain']
            ?: ($accountInfo['domain'] ?? null);

        $contactEmail = $data['contact_email']
            ?: ($accountInfo['email'] ?? null)
            ?: $cpanelUsername . '@developer.local';

        $framework = trim($data['framework'] ?? 'custom') ?: 'custom';
        $frameworkConfig = $this->frameworkDefaults($framework, $cpanelUsername, $domain);

        $projectRoot = $data['project_root']
            ?: $frameworkConfig['project_root']
            ?: '/home/' . $cpanelUsername . '/public_html';

        $dbType = strtolower(trim($data['db_type'] ?? 'mysql'));

        if (!in_array($dbType, ['mysql', 'postgresql', 'pgsql', 'postgres'], true)) {
            $dbType = 'mysql';
        }

        if (in_array($dbType, ['pgsql', 'postgres'], true)) {
            $dbType = 'postgresql';
        }

        $portalAccess = $request->boolean('developer_portal_access', true);

        $payload = [
            'server_id' => $server->id,
            'name' => $cpanelUsername,
            'email' => $contactEmail,
            'contact_email' => $contactEmail,
            'cpanel_username' => $cpanelUsername,
            'cpanel_domain' => $domain,

            'password' => bcrypt($cpanelPassword),
            'temporary_password' => Crypt::encryptString($cpanelPassword),
            'password_must_change' => false,

            'role' => 'developer',
            'ssh_username' => $cpanelUsername,
            'allowed_project_path' => $projectRoot,

            'project_type' => $frameworkConfig['project_type'],
            'framework' => $framework,
            'project_root' => $projectRoot,
            'build_command' => $frameworkConfig['build_command'],
            'deploy_command' => $frameworkConfig['deploy_command'],
            'start_command' => $frameworkConfig['start_command'],

            'can_git_pull' => $request->boolean('can_git_pull'),
            'can_clear_cache' => $request->boolean('can_clear_cache', true),
            'can_composer' => $request->boolean('can_composer'),
            'can_npm' => $request->boolean('can_npm'),
            'can_run_build' => $request->boolean('can_run_build'),
            'can_run_python' => $request->boolean('can_run_python'),
            'can_restart_app' => $request->boolean('can_restart_app'),
            'can_view_files' => $request->boolean('can_view_files', true),
            'can_edit_files' => $request->boolean('can_edit_files'),
            'can_delete_files' => $request->boolean('can_delete_files'),

            'can_mysql' => $request->boolean('can_mysql') || $dbType === 'mysql',
            'can_postgresql' => $request->boolean('can_postgresql') || $dbType === 'postgresql',
            'db_type' => $dbType,
            'db_host' => $data['db_host'] ?? 'localhost',
            'db_port' => $data['db_port'] ?? $this->defaultDbPort($dbType),
            'db_username' => $data['db_username'] ?? $cpanelUsername,
            'db_password' => !empty($data['db_password']) ? Crypt::encryptString($data['db_password']) : null,
            'db_name' => $data['db_name'] ?? '',
        ];

        $payload = array_merge($payload, $this->portalAccessPayload($portalAccess));

        DeveloperUser::updateOrCreate(
            [
                'cpanel_username' => $cpanelUsername,
            ],
            $this->filterDeveloperColumns($payload)
        );

        return back()
            ->with('success', 'Developer Codes login created from cPanel login.')
            ->with('created_logins', [
                [
                    'name' => $cpanelUsername,
                    'login' => $cpanelUsername,
                    'email' => $contactEmail,
                    'domain' => $domain,
                    'framework' => $framework,
                    'project_root' => $projectRoot,
                    'portal_access' => $portalAccess ? 'Enabled' : 'Disabled',
                    'password' => $cpanelPassword,
                    'url' => 'https://developercodes.webscepts.com/login',
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Developer Password
    |--------------------------------------------------------------------------
    */
    public function resetPassword(DeveloperUser $developer)
    {
        $temporaryPassword = Str::password(16);

        $payload = [
            'password' => bcrypt($temporaryPassword),
            'temporary_password' => Crypt::encryptString($temporaryPassword),
            'password_must_change' => true,
        ];

        $developer->update($this->filterDeveloperColumns($payload));

        return back()
            ->with('success', 'Temporary developer password reset.')
            ->with('created_logins', [
                [
                    'name' => $developer->name,
                    'login' => $developer->cpanel_username ?: $developer->email,
                    'email' => $developer->email,
                    'domain' => $developer->cpanel_domain,
                    'framework' => $developer->framework,
                    'project_root' => $developer->project_root,
                    'portal_access' => $this->developerPortalIsActive($developer) ? 'Enabled' : 'Disabled',
                    'password' => $temporaryPassword,
                    'url' => 'https://developercodes.webscepts.com/login',
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Turn Developer Portal Access ON/OFF
    |--------------------------------------------------------------------------
    */
    public function toggle(DeveloperUser $developer)
    {
        $enabled = !$this->developerPortalIsActive($developer);

        $developer->update(
            $this->filterDeveloperColumns($this->portalAccessPayload($enabled))
        );

        return back()->with(
            'success',
            $enabled ? 'Developer portal access enabled.' : 'Developer portal access disabled.'
        );
    }

    public function portalAccess(Request $request, DeveloperUser $developer)
    {
        $data = $request->validate([
            'enabled' => ['required'],
        ]);

        $enabled = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);

        $developer->update(
            $this->filterDeveloperColumns($this->portalAccessPayload($enabled))
        );

        return back()->with(
            'success',
            $enabled ? 'Developer portal access enabled.' : 'Developer portal access disabled.'
        );
    }

    public function enablePortal(DeveloperUser $developer)
    {
        $developer->update(
            $this->filterDeveloperColumns($this->portalAccessPayload(true))
        );

        return back()->with('success', 'Developer portal access enabled.');
    }

    public function disablePortal(DeveloperUser $developer)
    {
        $developer->update(
            $this->filterDeveloperColumns($this->portalAccessPayload(false))
        );

        return back()->with('success', 'Developer portal access disabled.');
    }

    public function destroy(DeveloperUser $developer)
    {
        $developer->delete();

        return back()->with('success', 'Developer login deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch WHM Accounts
    |--------------------------------------------------------------------------
    | This is the important updated part.
    | It uses the Server model fields:
    | host, username, password, ssh_port
    |--------------------------------------------------------------------------
    */
    private function fetchCpanelAccounts(Server $server): array
    {
        $host = trim((string) ($server->host ?? ''));
        $username = trim((string) ($server->username ?? ''));
        $password = $this->getServerPassword($server);
        $port = (int) ($server->ssh_port ?: 22);

        if (!$host) {
            throw new \Exception('Server host is missing.');
        }

        if (!$username) {
            throw new \Exception('Server username is missing.');
        }

        if (!$password) {
            throw new \Exception('Server password is missing.');
        }

        /*
        |--------------------------------------------------------------------------
        | Method 1: SSH WHM command
        |--------------------------------------------------------------------------
        | This works when your saved server login is SSH/root or sudo-capable.
        |--------------------------------------------------------------------------
        */
        try {
            $accounts = $this->fetchCpanelAccountsViaSsh(
                $host,
                $port,
                $username,
                $password,
                $server
            );

            if (!empty($accounts)) {
                return $accounts;
            }
        } catch (\Throwable $sshError) {
            /*
            |--------------------------------------------------------------------------
            | Method 2: HTTP WHM fallback
            |--------------------------------------------------------------------------
            | This works only if username/password can access WHM API.
            |--------------------------------------------------------------------------
            */
            try {
                $accounts = $this->fetchCpanelAccountsViaHttp(
                    $host,
                    $username,
                    $password,
                    $server
                );

                if (!empty($accounts)) {
                    return $accounts;
                }

                throw new \Exception('HTTP WHM returned empty account list.');
            } catch (\Throwable $httpError) {
                throw new \Exception(
                    'SSH WHM fetch failed: ' . $sshError->getMessage() .
                    ' | HTTP WHM fetch failed: ' . $httpError->getMessage()
                );
            }
        }

        throw new \Exception('Unable to fetch cPanel accounts from server.');
    }

    private function fetchCpanelAccountsViaSsh(
        string $host,
        int $port,
        string $username,
        string $password,
        Server $server
    ): array {
        $cleanHost = $this->cleanHost($host);

        $ssh = new SSH2($cleanHost, $port);
        $ssh->setTimeout(60);

        if (!$ssh->login($username, $password)) {
            throw new \Exception('SSH login failed for ' . $username . '@' . $cleanHost . ':' . $port);
        }

        $sudoPassword = str_replace("'", "'\"'\"'", $password);

        $command = <<<BASH
if [ -x /usr/local/cpanel/bin/whmapi1 ]; then
    /usr/local/cpanel/bin/whmapi1 listaccts --output=json
elif command -v whmapi1 >/dev/null 2>&1; then
    whmapi1 listaccts --output=json
elif command -v sudo >/dev/null 2>&1; then
    echo '{$sudoPassword}' | sudo -S /usr/local/cpanel/bin/whmapi1 listaccts --output=json 2>/dev/null
else
    echo '{"metadata":{"result":0,"reason":"whmapi1 command not found on server"}}'
fi
BASH;

        $output = trim((string) $ssh->exec($command));

        if (!$output) {
            throw new \Exception('Empty response from SSH WHM command.');
        }

        $jsonStart = strpos($output, '{');

        if ($jsonStart === false) {
            throw new \Exception('No JSON found in SSH output: ' . Str::limit($output, 500));
        }

        $output = substr($output, $jsonStart);

        $json = json_decode($output, true);

        if (!is_array($json)) {
            throw new \Exception('Invalid JSON from SSH WHM command: ' . Str::limit($output, 500));
        }

        $metadataResult = data_get($json, 'metadata.result');

        if ((string) $metadataResult === '0') {
            throw new \Exception(
                'WHM SSH command denied: ' .
                (
                    data_get($json, 'metadata.reason')
                    ?: data_get($json, 'cpanelresult.error')
                    ?: data_get($json, 'error')
                    ?: 'Access denied'
                )
            );
        }

        $rawAccounts = data_get($json, 'data.acct');

        if (!is_array($rawAccounts)) {
            $rawAccounts = data_get($json, 'acct', []);
        }

        if (!is_array($rawAccounts)) {
            throw new \Exception('WHM SSH command returned invalid account format.');
        }

        return $this->mapWhmAccounts($rawAccounts, $server);
    }

    private function fetchCpanelAccountsViaHttp(
        string $host,
        string $username,
        string $password,
        Server $server
    ): array {
        $cleanHost = $this->cleanHost($host);

        $attempts = [
            'https://' . $cleanHost . ':2087/json-api/listaccts',
            'http://' . $cleanHost . ':2086/json-api/listaccts',
        ];

        $lastStatus = null;
        $lastBody = null;

        foreach ($attempts as $url) {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->acceptJson()
                ->withOptions([
                    'verify' => false,
                    'connect_timeout' => 20,
                ])
                ->withBasicAuth($username, $password)
                ->get($url, [
                    'api.version' => 1,
                ]);

            $lastStatus = $response->status();
            $lastBody = $response->body();

            if (!$response->successful()) {
                continue;
            }

            $json = $response->json();

            $metadataResult = data_get($json, 'metadata.result');
            $cpanelResult = data_get($json, 'cpanelresult.data.result');

            if ((string) $metadataResult === '0' || (string) $cpanelResult === '0') {
                $lastBody = $response->body();
                continue;
            }

            $rawAccounts = data_get($json, 'data.acct');

            if (!is_array($rawAccounts)) {
                $rawAccounts = data_get($json, 'acct', []);
            }

            if (!is_array($rawAccounts)) {
                throw new \Exception('WHM HTTP connected but account list format is invalid.');
            }

            return $this->mapWhmAccounts($rawAccounts, $server);
        }

        throw new \Exception(
            'WHM username/password failed. HTTP ' .
            $lastStatus .
            ' - ' .
            Str::limit((string) $lastBody, 700)
        );
    }

    private function mapWhmAccounts(array $rawAccounts, Server $server): array
    {
        return collect($rawAccounts)
            ->map(function ($account) use ($server) {
                $user = $account['user'] ?? null;

                if (!$user) {
                    return null;
                }

                $email = $account['email']
                    ?? $account['contactemail']
                    ?? $account['contact_email']
                    ?? null;

                $domain = $account['domain'] ?? null;
                $home = '/home/' . $user;
                $documentRoot = $home . '/public_html';

                $framework = $this->guessFrameworkFromAccount($domain);
                $defaults = $this->frameworkDefaults($framework, $user, $domain);
                $dbType = $this->guessDbTypeFromFramework($framework);

                return [
                    'server_id' => $server->id,
                    'server_name' => $server->name ?? null,
                    'server_host' => $server->host ?? null,

                    'user' => $user,
                    'name' => $account['owner'] ?? $user,
                    'email' => $email,
                    'domain' => $domain,
                    'ip' => $account['ip'] ?? null,
                    'plan' => $account['plan'] ?? null,
                    'theme' => $account['theme'] ?? null,
                    'suspended' => !empty($account['suspended']),
                    'suspendreason' => $account['suspendreason'] ?? null,
                    'diskused' => $account['diskused'] ?? null,
                    'disklimit' => $account['disklimit'] ?? null,
                    'home' => $home,

                    'project_type' => $defaults['project_type'],
                    'framework' => $framework,
                    'project_root' => $documentRoot,
                    'build_command' => $defaults['build_command'],
                    'deploy_command' => $defaults['deploy_command'],
                    'start_command' => $defaults['start_command'],

                    'developer_portal_access' => true,
                    'portal_access_enabled' => true,

                    'can_view_files' => true,
                    'can_clear_cache' => in_array($framework, ['laravel', 'php', 'wordpress'], true),
                    'can_git_pull' => false,
                    'can_composer' => in_array($framework, ['laravel', 'php'], true),
                    'can_npm' => in_array($framework, ['react', 'vue', 'angular', 'node', 'nextjs', 'nuxt', 'svelte'], true),
                    'can_run_build' => in_array($framework, ['react', 'vue', 'angular', 'node', 'nextjs', 'nuxt', 'svelte'], true),
                    'can_run_python' => in_array($framework, ['python', 'flask', 'django', 'fastapi'], true),
                    'can_restart_app' => in_array($framework, ['node', 'nextjs', 'nuxt', 'python', 'flask', 'django', 'fastapi', 'springboot', 'java'], true),
                    'can_edit_files' => false,
                    'can_delete_files' => false,

                    'can_mysql' => true,
                    'can_postgresql' => true,
                    'db_type' => $dbType,
                    'db_host' => 'localhost',
                    'db_port' => $this->defaultDbPort($dbType),
                    'db_username' => $user,
                    'db_name' => '',
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | Validate One cPanel Login
    |--------------------------------------------------------------------------
    */
    private function fetchSingleCpanelAccountInfo(string $cpanelUrl, string $username, string $password): array
    {
        $cpanelUrl = rtrim($cpanelUrl, '/');

        $response = Http::withoutVerifying()
            ->timeout(30)
            ->acceptJson()
            ->withBasicAuth($username, $password)
            ->get($cpanelUrl . '/execute/ContactInfo/load_contact_info');

        if (!$response->successful()) {
            throw new \Exception(
                'Invalid cPanel username/password or cPanel API blocked. HTTP ' . $response->status()
            );
        }

        $json = $response->json();

        $status = data_get($json, 'status');

        if ((string) $status === '0') {
            throw new \Exception(data_get($json, 'errors.0') ?: 'cPanel API access denied.');
        }

        $email = data_get($json, 'data.email')
            ?: data_get($json, 'data.contact_email')
            ?: data_get($json, 'data.second_email');

        return [
            'email' => $email,
            'domain' => null,
        ];
    }

    private function getServerPassword(Server $server): ?string
    {
        $password = $server->password ?? null;

        if (!$password) {
            return null;
        }

        try {
            return decrypt($password);
        } catch (\Throwable $e) {
            try {
                return Crypt::decryptString($password);
            } catch (\Throwable $e2) {
                return $password;
            }
        }
    }

    private function cpanelUrlFromServer(Server $server): string
    {
        $host = $server->host ?? null;

        if (!$host) {
            throw new \Exception('Server host is missing.');
        }

        $host = $this->cleanHost($host);

        return 'https://' . $host . ':2083';
    }

    private function cleanHost(string $host): string
    {
        $host = trim($host);
        $host = preg_replace('#^https?://#', '', $host);
        $host = preg_replace('#/.*$#', '', $host);
        $host = preg_replace('#:\d+$#', '', $host);

        return $host;
    }

    /*
    |--------------------------------------------------------------------------
    | Developer Portal Access Helpers
    |--------------------------------------------------------------------------
    */
    private function portalAccessPayload(bool $enabled): array
    {
        return [
            'is_active' => $enabled,
            'developer_portal_access' => $enabled,
            'portal_access_enabled' => $enabled,
            'developer_portal_enabled' => $enabled,
            'portal_disabled_at' => $enabled ? null : now(),
            'portal_enabled_at' => $enabled ? now() : null,
        ];
    }

    private function developerPortalIsActive(DeveloperUser $developer): bool
    {
        $table = $developer->getTable();

        if (Schema::hasColumn($table, 'developer_portal_access')) {
            return (bool) $developer->developer_portal_access;
        }

        if (Schema::hasColumn($table, 'portal_access_enabled')) {
            return (bool) $developer->portal_access_enabled;
        }

        if (Schema::hasColumn($table, 'developer_portal_enabled')) {
            return (bool) $developer->developer_portal_enabled;
        }

        return (bool) $developer->is_active;
    }

    private function boolFromArray(array $array, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $array)) {
            return $default;
        }

        return filter_var($array[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /*
    |--------------------------------------------------------------------------
    | Frameworks
    |--------------------------------------------------------------------------
    */
    private function frameworkOptions(): array
    {
        return [
            'custom' => 'Custom / Other',
            'html' => 'Static HTML / CSS / JS',
            'php' => 'PHP',
            'wordpress' => 'WordPress',
            'laravel' => 'Laravel',
            'react' => 'React.js',
            'vue' => 'Vue.js',
            'angular' => 'Angular',
            'node' => 'Node.js / Express',
            'nextjs' => 'Next.js',
            'nuxt' => 'Nuxt.js',
            'svelte' => 'Svelte',
            'python' => 'Python',
            'flask' => 'Flask',
            'django' => 'Django',
            'fastapi' => 'FastAPI',
            'java' => 'Java',
            'springboot' => 'Spring Boot',
            'dotnet' => '.NET',
            'ruby' => 'Ruby / Rails',
            'go' => 'Go',
        ];
    }

    private function frameworkDefaults(string $framework, ?string $user = null, ?string $domain = null): array
    {
        $framework = strtolower(trim($framework ?: 'custom'));

        $home = $user ? '/home/' . $user : base_path();
        $publicHtml = $home . '/public_html';

        return match ($framework) {
            'laravel' => [
                'project_type' => 'php',
                'project_root' => $publicHtml,
                'build_command' => 'composer install --no-dev --optimize-autoloader && php artisan optimize:clear',
                'deploy_command' => 'php artisan migrate --force && php artisan optimize',
                'start_command' => '',
            ],

            'wordpress' => [
                'project_type' => 'cms',
                'project_root' => $publicHtml,
                'build_command' => '',
                'deploy_command' => '',
                'start_command' => '',
            ],

            'php' => [
                'project_type' => 'php',
                'project_root' => $publicHtml,
                'build_command' => 'composer install --no-dev',
                'deploy_command' => '',
                'start_command' => '',
            ],

            'react' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => 'npm run dev',
            ],

            'vue' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => 'npm run dev',
            ],

            'angular' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => 'ng serve',
            ],

            'nextjs' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => 'npm start',
            ],

            'nuxt' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => 'npm run preview',
            ],

            'svelte' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => 'npm run dev',
            ],

            'node' => [
                'project_type' => 'node',
                'project_root' => $publicHtml,
                'build_command' => 'npm install',
                'deploy_command' => 'npm install --production',
                'start_command' => 'npm start',
            ],

            'python' => [
                'project_type' => 'python',
                'project_root' => $publicHtml,
                'build_command' => 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
                'deploy_command' => './venv/bin/pip install -r requirements.txt',
                'start_command' => 'python3 app.py',
            ],

            'flask' => [
                'project_type' => 'python',
                'project_root' => $publicHtml,
                'build_command' => 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
                'deploy_command' => './venv/bin/pip install -r requirements.txt',
                'start_command' => './venv/bin/flask run --host=0.0.0.0',
            ],

            'django' => [
                'project_type' => 'python',
                'project_root' => $publicHtml,
                'build_command' => 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
                'deploy_command' => './venv/bin/python manage.py migrate && ./venv/bin/python manage.py collectstatic --noinput',
                'start_command' => './venv/bin/python manage.py runserver 0.0.0.0:8000',
            ],

            'fastapi' => [
                'project_type' => 'python',
                'project_root' => $publicHtml,
                'build_command' => 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
                'deploy_command' => './venv/bin/pip install -r requirements.txt',
                'start_command' => './venv/bin/uvicorn main:app --host 0.0.0.0 --port 8000',
            ],

            'java' => [
                'project_type' => 'java',
                'project_root' => $publicHtml,
                'build_command' => './mvnw clean package -DskipTests',
                'deploy_command' => './mvnw clean package -DskipTests',
                'start_command' => 'java -jar target/app.jar',
            ],

            'springboot' => [
                'project_type' => 'java',
                'project_root' => $publicHtml,
                'build_command' => './mvnw clean package -DskipTests',
                'deploy_command' => './mvnw clean package -DskipTests',
                'start_command' => 'java -jar target/*.jar',
            ],

            'dotnet' => [
                'project_type' => 'dotnet',
                'project_root' => $publicHtml,
                'build_command' => 'dotnet restore && dotnet build',
                'deploy_command' => 'dotnet publish -c Release',
                'start_command' => 'dotnet run',
            ],

            'ruby' => [
                'project_type' => 'ruby',
                'project_root' => $publicHtml,
                'build_command' => 'bundle install',
                'deploy_command' => 'bundle install --deployment',
                'start_command' => 'bundle exec rails server',
            ],

            'go' => [
                'project_type' => 'go',
                'project_root' => $publicHtml,
                'build_command' => 'go mod download && go build',
                'deploy_command' => 'go build',
                'start_command' => './app',
            ],

            'html' => [
                'project_type' => 'static',
                'project_root' => $publicHtml,
                'build_command' => '',
                'deploy_command' => '',
                'start_command' => '',
            ],

            default => [
                'project_type' => 'custom',
                'project_root' => $publicHtml,
                'build_command' => '',
                'deploy_command' => '',
                'start_command' => '',
            ],
        };
    }

    private function guessFrameworkFromAccount(?string $domain): string
    {
        if (!$domain) {
            return 'custom';
        }

        $domain = strtolower($domain);

        if (str_contains($domain, 'wordpress') || str_contains($domain, 'wp')) {
            return 'wordpress';
        }

        if (str_contains($domain, 'laravel')) {
            return 'laravel';
        }

        if (str_contains($domain, 'react')) {
            return 'react';
        }

        if (str_contains($domain, 'vue')) {
            return 'vue';
        }

        if (str_contains($domain, 'angular')) {
            return 'angular';
        }

        if (str_contains($domain, 'next')) {
            return 'nextjs';
        }

        if (str_contains($domain, 'nuxt')) {
            return 'nuxt';
        }

        if (str_contains($domain, 'node') || str_contains($domain, 'express')) {
            return 'node';
        }

        if (str_contains($domain, 'spring') || str_contains($domain, 'springboot')) {
            return 'springboot';
        }

        if (str_contains($domain, 'java')) {
            return 'java';
        }

        if (str_contains($domain, 'python')) {
            return 'python';
        }

        if (str_contains($domain, 'flask')) {
            return 'flask';
        }

        if (str_contains($domain, 'django')) {
            return 'django';
        }

        if (str_contains($domain, 'fastapi')) {
            return 'fastapi';
        }

        if (str_contains($domain, 'php')) {
            return 'php';
        }

        return 'custom';
    }

    private function guessDbTypeFromFramework(string $framework): string
    {
        $framework = strtolower($framework);

        return match ($framework) {
            'django', 'fastapi', 'flask', 'python', 'springboot', 'java', 'node', 'nextjs' => 'postgresql',
            default => 'mysql',
        };
    }

    private function defaultDbPort(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return in_array($dbType, ['postgresql', 'pgsql', 'postgres'], true) ? '5432' : '3306';
    }

    public function updateSettings(Request $request, DeveloperUser $developer)
    {
        $data = $request->validate([
            'framework' => ['nullable', 'string', 'max:100'],
            'project_root' => ['nullable', 'string', 'max:255'],
            'code_editor_url' => ['nullable', 'string', 'max:255'],

            'build_command' => ['nullable', 'string', 'max:500'],
            'deploy_command' => ['nullable', 'string', 'max:500'],
            'start_command' => ['nullable', 'string', 'max:500'],

            'developer_portal_access' => ['nullable'],

            'db_type' => ['nullable', 'string', 'max:50'],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_name' => ['nullable', 'string', 'max:255'],

            'can_view_files' => ['nullable'],
            'can_edit_files' => ['nullable'],
            'can_delete_files' => ['nullable'],
            'can_git_pull' => ['nullable'],
            'can_clear_cache' => ['nullable'],
            'can_composer' => ['nullable'],
            'can_npm' => ['nullable'],
            'can_run_build' => ['nullable'],
            'can_run_python' => ['nullable'],
            'can_restart_app' => ['nullable'],
            'can_mysql' => ['nullable'],
            'can_postgresql' => ['nullable'],
        ]);

        $enabled = $request->boolean('developer_portal_access');

        $payload = [
            'framework' => $data['framework'] ?? $developer->framework,
            'project_root' => $data['project_root'] ?? $developer->project_root,
            'allowed_project_path' => $data['project_root'] ?? $developer->allowed_project_path,
            'code_editor_url' => $data['code_editor_url'] ?? $developer->code_editor_url,

            'build_command' => $data['build_command'] ?? null,
            'deploy_command' => $data['deploy_command'] ?? null,
            'start_command' => $data['start_command'] ?? null,

            'is_active' => $enabled,
            'developer_portal_access' => $enabled,
            'portal_access_enabled' => $enabled,
            'developer_portal_enabled' => $enabled,

            'db_type' => $data['db_type'] ?? null,
            'db_host' => $data['db_host'] ?? null,
            'db_username' => $data['db_username'] ?? null,
            'db_name' => $data['db_name'] ?? null,

            'can_view_files' => $request->boolean('can_view_files'),
            'can_edit_files' => $request->boolean('can_edit_files'),
            'can_delete_files' => $request->boolean('can_delete_files'),
            'can_git_pull' => $request->boolean('can_git_pull'),
            'can_clear_cache' => $request->boolean('can_clear_cache'),
            'can_composer' => $request->boolean('can_composer'),
            'can_npm' => $request->boolean('can_npm'),
            'can_run_build' => $request->boolean('can_run_build'),
            'can_run_python' => $request->boolean('can_run_python'),
            'can_restart_app' => $request->boolean('can_restart_app'),
            'can_mysql' => $request->boolean('can_mysql'),
            'can_postgresql' => $request->boolean('can_postgresql'),
        ];

        $developer->update($this->filterDeveloperColumns($payload));

        return back()->with('success', 'Developer settings updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent SQL error if some columns are missing from developer_users table
    |--------------------------------------------------------------------------
    */
    private function filterDeveloperColumns(array $payload): array
    {
        $table = (new DeveloperUser())->getTable();

        return collect($payload)
            ->filter(function ($value, $column) use ($table) {
                return Schema::hasColumn($table, $column);
            })
            ->toArray();
    }
}