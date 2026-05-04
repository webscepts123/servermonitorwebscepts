<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
    | Fetch all cPanel accounts from selected server
    |--------------------------------------------------------------------------
    | Uses Server model:
    | - host / ip
    | - root username / WHM username
    | - root password / WHM password
    | - WHM token if available
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
                count($accounts) . ' cPanel accounts loaded from ' . ($server->name ?? $server->host)
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to fetch cPanel accounts: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Import selected WHM/cPanel users to Developer Codes
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

            $framework = $account['framework'] ?? 'custom';
            $frameworkConfig = $this->frameworkDefaults($framework, $cpanelUsername, $domain);

            $projectRoot = trim($account['project_root'] ?? '') ?: $frameworkConfig['project_root'];
            $allowedPath = $projectRoot ?: ('/home/' . $cpanelUsername);

            $temporaryPassword = Str::password(16);

            DeveloperUser::updateOrCreate(
                [
                    'cpanel_username' => $cpanelUsername,
                ],
                [
                    'server_id' => $data['server_id'],

                    'name' => $account['name'] ?: $cpanelUsername,
                    'email' => $contactEmail,
                    'contact_email' => $contactEmail,
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

                    'is_active' => true,
                ]
            );

            $updated++;

            $createdLogins[] = [
                'name' => $account['name'] ?: $cpanelUsername,
                'login' => $cpanelUsername,
                'email' => $contactEmail,
                'domain' => $domain,
                'framework' => $framework,
                'project_root' => $projectRoot,
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
    | Import one cPanel login manually
    |--------------------------------------------------------------------------
    | Uses selected server host from Server model.
    | User enters only cPanel username/password.
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

        $framework = $data['framework'] ?? 'custom';
        $frameworkConfig = $this->frameworkDefaults($framework, $cpanelUsername, $domain);

        $projectRoot = $data['project_root']
            ?: $frameworkConfig['project_root']
            ?: '/home/' . $cpanelUsername . '/public_html';

        DeveloperUser::updateOrCreate(
            [
                'cpanel_username' => $cpanelUsername,
            ],
            [
                'server_id' => $server->id,
                'name' => $cpanelUsername,
                'email' => $contactEmail,
                'contact_email' => $contactEmail,
                'cpanel_domain' => $domain,

                /*
                |--------------------------------------------------------------------------
                | Developer Codes password
                |--------------------------------------------------------------------------
                | This uses same cPanel password for Developer Codes login.
                |--------------------------------------------------------------------------
                */
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

                'is_active' => true,
            ]
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
                    'password' => $cpanelPassword,
                    'url' => 'https://developercodes.webscepts.com/login',
                ],
            ]);
    }

    public function resetPassword(DeveloperUser $developer)
    {
        $temporaryPassword = Str::password(16);

        $developer->update([
            'password' => bcrypt($temporaryPassword),
            'temporary_password' => Crypt::encryptString($temporaryPassword),
            'password_must_change' => true,
        ]);

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
                    'password' => $temporaryPassword,
                    'url' => 'https://developercodes.webscepts.com/login',
                ],
            ]);
    }

    public function toggle(DeveloperUser $developer)
    {
        $developer->update([
            'is_active' => !$developer->is_active,
        ]);

        return back()->with('success', 'Developer status updated.');
    }

    public function destroy(DeveloperUser $developer)
    {
        $developer->delete();

        return back()->with('success', 'Developer login deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | WHM account fetch using Server model credentials
    |--------------------------------------------------------------------------
    */
    private function fetchCpanelAccounts(Server $server): array
    {
        $credentials = $this->serverCredentials($server);

        $host = $credentials['host'];
        $username = $credentials['username'];
        $password = $credentials['password'];
        $token = $credentials['token'];

        if (!$host) {
            throw new \Exception('Server host/IP is missing.');
        }

        if (!$username) {
            throw new \Exception('Server root/WHM username is missing.');
        }

        if (!$token && !$password) {
            throw new \Exception('Server root/WHM password or WHM API token is missing.');
        }

        $url = 'https://' . $host . ':2087/json-api/listaccts';

        $request = Http::withoutVerifying()
            ->timeout(45)
            ->acceptJson()
            ->withOptions([
                'verify' => false,
            ]);

        if ($token) {
            $request = $request->withHeaders([
                'Authorization' => 'whm ' . $username . ':' . $token,
            ]);
        } else {
            $request = $request->withBasicAuth($username, $password);
        }

        $response = $request->get($url, [
            'api.version' => 1,
        ]);

        if (!$response->successful()) {
            throw new \Exception(
                'WHM API failed. HTTP ' . $response->status() . ' - ' . Str::limit($response->body(), 300)
            );
        }

        $json = $response->json();

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

                return [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'server_host' => $server->host,

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

                    'can_view_files' => true,
                    'can_clear_cache' => in_array($framework, ['laravel', 'php', 'wordpress'], true),
                    'can_git_pull' => false,
                    'can_composer' => in_array($framework, ['laravel', 'php'], true),
                    'can_npm' => in_array($framework, ['react', 'vue', 'angular', 'node', 'nextjs', 'nuxt', 'svelte'], true),
                    'can_run_build' => in_array($framework, ['react', 'vue', 'angular', 'node', 'nextjs', 'nuxt', 'svelte'], true),
                    'can_run_python' => in_array($framework, ['python', 'flask', 'django', 'fastapi'], true),
                    'can_restart_app' => false,
                    'can_edit_files' => false,
                    'can_delete_files' => false,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | Single cPanel API check
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
    | Get server credentials from Server model
    |--------------------------------------------------------------------------
    | Supports different possible column names safely.
    |--------------------------------------------------------------------------
    */
    private function serverCredentials(Server $server): array
    {
        return [
            'host' => $this->serverValue($server, [
                'host',
                'ip',
                'ip_address',
                'server_ip',
                'hostname',
            ]),

            'username' => $this->serverValue($server, [
                'whm_username',
                'root_username',
                'ssh_username',
                'username',
                'user',
            ]) ?: 'root',

            'password' => $this->serverSecret($server, [
                'whm_password',
                'root_password',
                'ssh_password',
                'password',
            ]),

            'token' => $this->serverSecret($server, [
                'whm_token',
                'cpanel_token',
                'api_token',
                'access_hash',
            ]),
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
                return $value;
            }
        }

        return null;
    }

    private function cpanelUrlFromServer(Server $server): string
    {
        $host = $this->serverValue($server, [
            'host',
            'ip',
            'ip_address',
            'server_ip',
            'hostname',
        ]);

        if (!$host) {
            throw new \Exception('Server host/IP is missing.');
        }

        return 'https://' . $host . ':2083';
    }

    private function frameworkOptions(): array
    {
        return [
            'custom' => 'Custom / Other',
            'html' => 'Static HTML / CSS / JS',
            'php' => 'PHP',
            'wordpress' => 'WordPress',
            'laravel' => 'Laravel',
            'react' => 'React',
            'vue' => 'Vue.js',
            'angular' => 'Angular',
            'node' => 'Node.js / Express',
            'python' => 'Python',
            'flask' => 'Flask',
            'django' => 'Django',
            'fastapi' => 'FastAPI',
            'nextjs' => 'Next.js',
            'nuxt' => 'Nuxt',
            'svelte' => 'Svelte',
            'java' => 'Java / Spring Boot',
            'dotnet' => '.NET',
            'ruby' => 'Ruby / Rails',
            'go' => 'Go',
        ];
    }

    private function frameworkDefaults(string $framework, ?string $user = null, ?string $domain = null): array
    {
        $home = $user ? '/home/' . $user : base_path();
        $publicHtml = $home . '/public_html';

        return match ($framework) {
            'laravel' => [
                'project_type' => 'php',
                'project_root' => $publicHtml,
                'build_command' => 'composer install --no-dev && php artisan optimize:clear',
                'deploy_command' => 'php artisan migrate --force && php artisan optimize:clear',
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
            'react', 'vue', 'angular', 'nextjs', 'nuxt', 'svelte' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => $framework === 'angular' ? 'ng serve' : 'npm run dev',
            ],
            'node' => [
                'project_type' => 'node',
                'project_root' => $publicHtml,
                'build_command' => 'npm install',
                'deploy_command' => 'npm install --production',
                'start_command' => 'npm start',
            ],
            'python', 'flask', 'django', 'fastapi' => [
                'project_type' => 'python',
                'project_root' => $publicHtml,
                'build_command' => 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
                'deploy_command' => './venv/bin/pip install -r requirements.txt',
                'start_command' => match ($framework) {
                    'flask' => './venv/bin/flask run',
                    'django' => './venv/bin/python manage.py runserver',
                    'fastapi' => './venv/bin/uvicorn main:app --host 0.0.0.0 --port 8000',
                    default => 'python3 app.py',
                },
            ],
            'java' => [
                'project_type' => 'java',
                'project_root' => $publicHtml,
                'build_command' => './mvnw clean package',
                'deploy_command' => './mvnw clean package -DskipTests',
                'start_command' => 'java -jar target/app.jar',
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

        if (str_contains($domain, 'wp') || str_contains($domain, 'wordpress')) {
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

        if (str_contains($domain, 'node') || str_contains($domain, 'api')) {
            return 'node';
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

        return 'custom';
    }
}