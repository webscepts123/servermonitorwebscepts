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

            return back()->with('success', count($accounts) . ' cPanel accounts loaded from ' . $server->name . '.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to fetch cPanel accounts: ' . $e->getMessage());
        }
    }

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

    private function fetchCpanelAccounts(Server $server): array
    {
        $host = $server->host;
        $username = $server->username ?: 'root';
        $password = $this->serverPassword($server);

        $token = $server->whm_token
            ?? $server->cpanel_token
            ?? $server->api_token
            ?? null;

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
            throw new \Exception('WHM API failed. HTTP ' . $response->status() . ' - ' . Str::limit($response->body(), 200));
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

                    'project_type' => 'web',
                    'framework' => $framework,
                    'project_root' => $documentRoot,
                    'build_command' => $this->frameworkDefaults($framework, $user, $domain)['build_command'],
                    'deploy_command' => $this->frameworkDefaults($framework, $user, $domain)['deploy_command'],
                    'start_command' => $this->frameworkDefaults($framework, $user, $domain)['start_command'],

                    'can_view_files' => true,
                    'can_clear_cache' => in_array($framework, ['laravel', 'php', 'wordpress']),
                    'can_git_pull' => false,
                    'can_composer' => in_array($framework, ['laravel', 'php']),
                    'can_npm' => in_array($framework, ['react', 'vue', 'angular', 'node']),
                    'can_run_build' => in_array($framework, ['react', 'vue', 'angular', 'node']),
                    'can_run_python' => in_array($framework, ['python', 'flask', 'django']),
                    'can_restart_app' => false,
                    'can_edit_files' => false,
                    'can_delete_files' => false,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
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
                'start_command' => 'npm run dev',
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

        if (str_contains($domain, 'api')) {
            return 'node';
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

        if (str_contains($domain, 'python') || str_contains($domain, 'flask')) {
            return 'flask';
        }

        return 'custom';
    }

    private function serverPassword(Server $server): string
    {
        $password = $server->password ?? '';

        if (!$password) {
            return '';
        }

        try {
            return Crypt::decryptString($password);
        } catch (\Throwable $e) {
            return $password;
        }
    }
}