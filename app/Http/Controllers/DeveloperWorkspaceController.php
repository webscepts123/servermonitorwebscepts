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
                count($accounts) . ' cPanel accounts loaded from ' . ($server->name ?? $server->host ?? $server->ip_address ?? 'server')
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

        $server = Server::findOrFail($data['server_id']);

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

            $codeEditorDomain = $this->codeEditorDomainForUsername($cpanelUsername);
            $codeEditorUrl = 'https://' . $codeEditorDomain;

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
                'server_id' => $server->id,

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

                'code_editor_url' => $codeEditorUrl,
                'vscode_url' => $codeEditorUrl,

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

            $developer = DeveloperUser::updateOrCreate(
                [
                    'cpanel_username' => $cpanelUsername,
                ],
                $this->filterDeveloperColumns($payload)
            );

            $setupMessage = null;

            try {
                $setupResult = $this->provisionCodeEditorForDeveloper($developer);
                $setupMessage = 'VS Code SSL ready: ' . $setupResult['url'];
            } catch (\Throwable $setupError) {
                $setupMessage = 'VS Code setup failed: ' . $setupError->getMessage();
            }

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
                'codeditor' => 'https://developercodes.webscepts.com/codeditor',
                'code_editor_url' => $codeEditorUrl,
                'code_editor_setup' => $setupMessage,
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

        $codeEditorUrl = 'https://' . $this->codeEditorDomainForUsername($cpanelUsername);
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

            'code_editor_url' => $codeEditorUrl,
            'vscode_url' => $codeEditorUrl,

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

        $developer = DeveloperUser::updateOrCreate(
            [
                'cpanel_username' => $cpanelUsername,
            ],
            $this->filterDeveloperColumns($payload)
        );

        $setupMessage = null;

        try {
            $setupResult = $this->provisionCodeEditorForDeveloper($developer);
            $setupMessage = 'VS Code SSL ready: ' . $setupResult['url'];
        } catch (\Throwable $setupError) {
            $setupMessage = 'VS Code setup failed: ' . $setupError->getMessage();
        }

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
                    'codeditor' => 'https://developercodes.webscepts.com/codeditor',
                    'code_editor_url' => $codeEditorUrl,
                    'code_editor_setup' => $setupMessage,
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Existing Developer Settings From Blade
    |--------------------------------------------------------------------------
    */
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

        $codeEditorUrl = trim($data['code_editor_url'] ?? '');

        if (!$codeEditorUrl && !empty($developer->cpanel_username)) {
            $codeEditorUrl = 'https://' . $this->codeEditorDomainForUsername($developer->cpanel_username);
        }

        $payload = [
            'framework' => $data['framework'] ?? $developer->framework,
            'project_root' => $data['project_root'] ?? $developer->project_root,
            'allowed_project_path' => $data['project_root'] ?? $developer->allowed_project_path,
            'code_editor_url' => $codeEditorUrl,
            'vscode_url' => $codeEditorUrl,

            'build_command' => $data['build_command'] ?? null,
            'deploy_command' => $data['deploy_command'] ?? null,
            'start_command' => $data['start_command'] ?? null,

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

        $payload = array_merge($payload, $this->portalAccessPayload($enabled));

        $developer->update($this->filterDeveloperColumns($payload));

        return back()->with('success', 'Developer settings updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Manual Button: Setup VS Code + CloudNS + Nginx + SSL
    |--------------------------------------------------------------------------
    */
    public function setupCodeEditor(DeveloperUser $developer)
    {
        try {
            $result = $this->provisionCodeEditorForDeveloper($developer);

            return back()->with(
                'success',
                'VS Code, CloudNS DNS, Nginx and Let’s Encrypt SSL setup completed: ' . $result['url']
            );
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'VS Code setup failed: ' . $e->getMessage()
            );
        }
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
                    'codeditor' => 'https://developercodes.webscepts.com/codeditor',
                    'code_editor_url' => $developer->code_editor_url ?? null,
                ],
            ]);
    }

    public function toggle(DeveloperUser $developer)
    {
        $active = !$this->developerPortalIsActive($developer);

        $developer->update($this->filterDeveloperColumns(
            $this->portalAccessPayload($active)
        ));

        return back()->with('success', 'Developer status updated.');
    }

    public function destroy(DeveloperUser $developer)
    {
        $developer->delete();

        return back()->with('success', 'Developer login deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | VS Code Provisioning
    |--------------------------------------------------------------------------
    */
    private function provisionCodeEditorForDeveloper(DeveloperUser $developer): array
    {
        $username = trim((string) ($developer->cpanel_username ?: $developer->ssh_username));

        if (!$username) {
            throw new \Exception('Developer cPanel username is missing.');
        }

        $server = null;

        if (Schema::hasColumn($developer->getTable(), 'server_id') && !empty($developer->server_id)) {
            $server = Server::find($developer->server_id);
        }

        if (!$server) {
            $server = Server::latest()->first();
        }

        if (!$server) {
            throw new \Exception('Server record not found for this developer.');
        }

        $serverIp = $this->serverPublicIp($server);

        if (!$serverIp) {
            throw new \Exception('Server public IP is missing.');
        }

        $domain = $this->codeEditorDomainForUsername($username);
        $subdomain = $this->codeEditorSubdomainForUsername($username);
        $port = $this->codeEditorPortForDeveloper($developer);

        $projectRoot = $developer->project_root
            ?: $developer->allowed_project_path
            ?: '/home/' . $username . '/public_html';

        $projectRoot = rtrim($projectRoot, '/');

        /*
        |--------------------------------------------------------------------------
        | 1. CloudNS DNS A Record
        |--------------------------------------------------------------------------
        */
        $this->createOrUpdateCloudnsARecord($subdomain, $serverIp);

        /*
        |--------------------------------------------------------------------------
        | 2. SSH to target server and setup code-server, Nginx, SSL
        |--------------------------------------------------------------------------
        */
        $ssh = $this->connectServerSsh($server);

        $this->installCodeServerOnRemote($ssh);
        $this->createRemoteCodeServerService($ssh, $developer, $domain, $port, $projectRoot);
        $this->createRemoteNginxConfig($ssh, $domain, $port);
        $this->installRemoteLetsEncryptSsl($ssh, $domain);

        /*
        |--------------------------------------------------------------------------
        | 3. Save URL
        |--------------------------------------------------------------------------
        */
        $developer->update(
            $this->filterDeveloperColumns([
                'code_editor_url' => 'https://' . $domain,
                'vscode_url' => 'https://' . $domain,
                'code_editor_port' => $port,
            ])
        );

        return [
            'domain' => $domain,
            'url' => 'https://' . $domain,
            'port' => $port,
            'project_root' => $projectRoot,
        ];
    }

    private function codeEditorSubdomainForUsername(string $username): string
    {
        $safeUsername = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $username));
        $safeUsername = trim($safeUsername, '-');

        return 'code-' . $safeUsername;
    }

    private function codeEditorDomainForUsername(string $username): string
    {
        $baseDomain = config('services.code_editor.base_domain', env('CODE_EDITOR_BASE_DOMAIN', 'webscepts.com'));

        return $this->codeEditorSubdomainForUsername($username) . '.' . $baseDomain;
    }

    private function codeEditorPortForDeveloper(DeveloperUser $developer): int
    {
        $basePort = (int) config('services.code_editor.port_start', env('CODE_EDITOR_PORT_START', 8081));

        return $basePort + max(((int) $developer->id - 1), 0);
    }

    private function createOrUpdateCloudnsARecord(string $subdomain, string $serverIp): void
    {
        $authId = config('services.cloudns.auth_id', env('CLOUDNS_AUTH_ID'));
        $authPassword = config('services.cloudns.auth_password', env('CLOUDNS_AUTH_PASSWORD'));
        $zone = config('services.cloudns.zone', env('CLOUDNS_ZONE', 'webscepts.com'));

        if (!$authId || !$authPassword || !$zone) {
            throw new \Exception('CloudNS API credentials are missing in .env / services.php.');
        }

        $listResponse = Http::asForm()
            ->timeout(60)
            ->post('https://api.cloudns.net/dns/records.json', [
                'auth-id' => $authId,
                'auth-password' => $authPassword,
                'domain-name' => $zone,
                'host' => $subdomain,
                'type' => 'A',
            ]);

        if (!$listResponse->successful()) {
            throw new \Exception('CloudNS list records failed: ' . $listResponse->body());
        }

        $records = $listResponse->json();
        $existingRecordId = null;

        if (is_array($records)) {
            foreach ($records as $recordId => $record) {
                $recordHost = $record['host'] ?? '';
                $recordValue = $record['record'] ?? '';

                if ($recordHost === $subdomain) {
                    $existingRecordId = $recordId;

                    if ($recordValue === $serverIp) {
                        return;
                    }

                    break;
                }
            }
        }

        if ($existingRecordId) {
            $modifyResponse = Http::asForm()
                ->timeout(60)
                ->post('https://api.cloudns.net/dns/mod-record.json', [
                    'auth-id' => $authId,
                    'auth-password' => $authPassword,
                    'domain-name' => $zone,
                    'record-id' => $existingRecordId,
                    'host' => $subdomain,
                    'record' => $serverIp,
                    'ttl' => 300,
                ]);

            if (!$modifyResponse->successful()) {
                throw new \Exception('CloudNS update record failed: ' . $modifyResponse->body());
            }

            $body = $modifyResponse->json();

            if (isset($body['status']) && strtolower((string) $body['status']) === 'failed') {
                throw new \Exception('CloudNS update record failed: ' . json_encode($body));
            }

            return;
        }

        $addResponse = Http::asForm()
            ->timeout(60)
            ->post('https://api.cloudns.net/dns/add-record.json', [
                'auth-id' => $authId,
                'auth-password' => $authPassword,
                'domain-name' => $zone,
                'record-type' => 'A',
                'host' => $subdomain,
                'record' => $serverIp,
                'ttl' => 300,
            ]);

        if (!$addResponse->successful()) {
            throw new \Exception('CloudNS add record failed: ' . $addResponse->body());
        }

        $body = $addResponse->json();

        if (isset($body['status']) && strtolower((string) $body['status']) === 'failed') {
            throw new \Exception('CloudNS add record failed: ' . json_encode($body));
        }
    }

    private function connectServerSsh(Server $server): SSH2
    {
        $credentials = $this->serverCredentials($server);

        $host = $this->cleanHost((string) $credentials['host']);
        $port = (int) ($server->ssh_port ?? 22);
        $username = trim((string) ($credentials['username'] ?: 'root'));
        $password = trim((string) ($credentials['password'] ?? ''));

        if (!$host) {
            throw new \Exception('SSH host is missing.');
        }

        if (!$username) {
            throw new \Exception('SSH username is missing.');
        }

        if (!$password) {
            throw new \Exception('SSH password is missing.');
        }

        $ssh = new SSH2($host, $port);
        $ssh->setTimeout(60);

        if (!$ssh->login($username, $password)) {
            throw new \Exception('SSH login failed for server ' . $host . ':' . $port . ' as ' . $username);
        }

        return $ssh;
    }

    private function installCodeServerOnRemote(SSH2 $ssh): void
    {
        $command = <<<'BASH'
set -e

if ! command -v code-server >/dev/null 2>&1; then
    curl -fsSL https://code-server.dev/install.sh | sh
fi

code-server --version || true
BASH;

        $this->runRemoteCommand($ssh, $command, 600);
    }

    private function createRemoteCodeServerService(
        SSH2 $ssh,
        DeveloperUser $developer,
        string $domain,
        int $port,
        string $projectRoot
    ): void {
        $username = trim((string) ($developer->cpanel_username ?: $developer->ssh_username));

        if (!$username) {
            throw new \Exception('Developer username is missing.');
        }

        $serviceName = 'code-' . strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $username));
        $serviceName = trim($serviceName, '-');

        $password = null;

        if (!empty($developer->temporary_password)) {
            try {
                $password = Crypt::decryptString($developer->temporary_password);
            } catch (\Throwable $e) {
                $password = null;
            }
        }

        if (!$password) {
            $password = Str::password(22);
        }

        $safeUser = $this->shellArg($username);
        $safeProjectRoot = $this->shellArg($projectRoot);
        $safeServiceName = $this->shellArg($serviceName);

        $serviceContent = <<<SERVICE
[Unit]
Description=Code Server for {$username}
After=network.target

[Service]
Type=simple
User={$username}
WorkingDirectory={$projectRoot}
Environment=PASSWORD={$password}
ExecStart=/usr/bin/code-server --bind-addr 127.0.0.1:{$port} --auth password {$projectRoot}
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SERVICE;

        $safeServiceContent = $this->shellArg($serviceContent);

        $command = <<<BASH
set -e

CODE_SERVER_PATH=\$(command -v code-server || echo /usr/bin/code-server)

if ! id {$safeUser} >/dev/null 2>&1; then
    echo "System user {$username} does not exist on target server."
    exit 1
fi

if [ ! -d {$safeProjectRoot} ]; then
    mkdir -p {$safeProjectRoot}
    chown {$username}:{$username} {$safeProjectRoot} || true
fi

printf "%s" {$safeServiceContent} > /etc/systemd/system/{$serviceName}.service
sed -i "s#ExecStart=/usr/bin/code-server#ExecStart=\$CODE_SERVER_PATH#g" /etc/systemd/system/{$serviceName}.service

systemctl daemon-reload
systemctl enable {$safeServiceName}
systemctl restart {$safeServiceName}
systemctl status {$safeServiceName} --no-pager
BASH;

        $this->runRemoteCommand($ssh, $command, 300);
    }

    private function createRemoteNginxConfig(SSH2 $ssh, string $domain, int $port): void
    {
        $nginxConfig = <<<NGINX
server {
    listen 80;
    server_name {$domain};

    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;

        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;

        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
    }
}
NGINX;

        $safeNginxConfig = $this->shellArg($nginxConfig);

        $command = <<<BASH
set -e

if ! command -v nginx >/dev/null 2>&1; then
    echo "Nginx is not installed on target server."
    exit 1
fi

mkdir -p /etc/nginx/conf.d

printf "%s" {$safeNginxConfig} > /etc/nginx/conf.d/{$domain}.conf

nginx -t
systemctl reload nginx
BASH;

        $this->runRemoteCommand($ssh, $command, 120);
    }

    private function installRemoteLetsEncryptSsl(SSH2 $ssh, string $domain): void
    {
        $email = config('services.code_editor.certbot_email', env('CERTBOT_EMAIL', 'info@webscepts.com'));

        $safeDomain = $this->shellArg($domain);
        $safeEmail = $this->shellArg($email);

        $command = <<<BASH
set -e

if ! command -v certbot >/dev/null 2>&1; then
    if command -v dnf >/dev/null 2>&1; then
        dnf install -y epel-release || true
        dnf install -y certbot python3-certbot-nginx
    elif command -v yum >/dev/null 2>&1; then
        yum install -y epel-release || true
        yum install -y certbot python3-certbot-nginx
    elif command -v apt-get >/dev/null 2>&1; then
        apt-get update
        apt-get install -y certbot python3-certbot-nginx
    else
        echo "Cannot install certbot. Unknown package manager."
        exit 1
    fi
fi

sleep 8

certbot --nginx -d {$safeDomain} --non-interactive --agree-tos -m {$safeEmail} --redirect

nginx -t
systemctl reload nginx
BASH;

        $this->runRemoteCommand($ssh, $command, 600);
    }

    private function runRemoteCommand(SSH2 $ssh, string $command, int $timeout = 120): string
    {
        $ssh->setTimeout($timeout);

        $wrapped = 'bash -lc ' . $this->shellArg($command);

        $output = $ssh->exec($wrapped);
        $exitStatus = $ssh->getExitStatus();

        if ($exitStatus !== 0 && $exitStatus !== null) {
            throw new \Exception(trim((string) $output) ?: 'Remote command failed.');
        }

        return trim((string) $output);
    }

    private function shellArg(string $value): string
    {
        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch WHM Accounts
    |--------------------------------------------------------------------------
    */
    private function fetchCpanelAccounts(Server $server): array
    {
        $credentials = $this->serverCredentials($server);

        $host = $credentials['host'];
        $username = $credentials['username'];
        $password = $credentials['password'];

        if (!$host) {
            throw new \Exception('Server host/IP is missing.');
        }

        if (!$username) {
            throw new \Exception('WHM username is missing. Add whm_username, root_username, username, or user on server record.');
        }

        if (!$password) {
            throw new \Exception('WHM/root/reseller password is missing. Add whm_password, root_password, ssh_password, or password on server record.');
        }

        $host = $this->cleanHost($host);

        $url = 'https://' . $host . ':2087/json-api/listaccts';

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

        if (!$response->successful()) {
            throw new \Exception(
                'WHM username/password failed. HTTP ' .
                $response->status() .
                ' - ' .
                Str::limit($response->body(), 700)
            );
        }

        $json = $response->json();

        $metadataResult = data_get($json, 'metadata.result');

        if ((string) $metadataResult === '0') {
            throw new \Exception(
                'WHM API denied username/password request: ' .
                (
                    data_get($json, 'metadata.reason')
                    ?: data_get($json, 'cpanelresult.error')
                    ?: data_get($json, 'cpanelresult.data.reason')
                    ?: 'Access denied'
                )
            );
        }

        $rawAccounts = data_get($json, 'data.acct', []);

        if (!is_array($rawAccounts)) {
            throw new \Exception('No cPanel accounts found from WHM response.');
        }

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
                $codeEditorUrl = 'https://' . $this->codeEditorDomainForUsername($user);

                return [
                    'server_id' => $server->id,
                    'server_name' => $server->name ?? null,
                    'server_host' => $server->host ?? $server->hostname ?? $server->ip_address ?? null,

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
                    'code_editor_url' => $codeEditorUrl,

                    'developer_portal_access' => true,

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

    /*
    |--------------------------------------------------------------------------
    | Read Server Credentials Safely
    |--------------------------------------------------------------------------
    */
    private function serverCredentials(Server $server): array
    {
        $host = $this->serverValue($server, [
            'host',
            'hostname',
            'ip',
            'ip_address',
            'server_ip',
        ]);

        $username = $this->serverValue($server, [
            'whm_username',
            'root_username',
            'username',
            'user',
            'ssh_username',
        ]);

        $password = $this->serverSecret($server, [
            'whm_password',
            'root_password',
            'password',
            'ssh_password',
        ]);

        return [
            'host' => $host ? trim($host) : null,
            'username' => trim($username ?: 'root'),
            'password' => $password ? trim($password) : null,
        ];
    }

    private function serverValue(Server $server, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($server->getTable(), $column) && !empty($server->{$column})) {
                return trim((string) $server->{$column});
            }
        }

        return null;
    }

    private function serverSecret(Server $server, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($server->getTable(), $column) || empty($server->{$column})) {
                continue;
            }

            $value = (string) $server->{$column};

            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                try {
                    return decrypt($value);
                } catch (\Throwable $e2) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function serverPublicIp(Server $server): ?string
    {
        $value = $this->serverValue($server, [
            'public_ip',
            'ip_address',
            'server_ip',
            'ip',
            'host',
            'hostname',
        ]);

        if (!$value) {
            return null;
        }

        $host = $this->cleanHost($value);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $resolved = gethostbyname($host);

        if ($resolved && $resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
            return $resolved;
        }

        return null;
    }

    private function cpanelUrlFromServer(Server $server): string
    {
        $host = $this->serverValue($server, [
            'host',
            'hostname',
            'ip',
            'ip_address',
            'server_ip',
        ]);

        if (!$host) {
            throw new \Exception('Server host/IP is missing.');
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
    | Portal Access Helpers
    |--------------------------------------------------------------------------
    */
    private function boolFromArray(array $array, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $array)) {
            return $default;
        }

        $value = $array[$key];

        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private function portalAccessPayload(bool $enabled): array
    {
        return [
            'is_active' => $enabled,
            'developer_portal_access' => $enabled,
            'portal_access_enabled' => $enabled,
            'developer_portal_enabled' => $enabled,
        ];
    }

    private function developerPortalIsActive(DeveloperUser $developer): bool
    {
        return (bool) (
            $developer->developer_portal_access
            ?? $developer->portal_access_enabled
            ?? $developer->developer_portal_enabled
            ?? $developer->is_active
            ?? false
        );
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