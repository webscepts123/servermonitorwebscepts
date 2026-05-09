<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeveloperWorkspaceController extends Controller
{
    public function index()
    {
        $servers = $this->servers();

        $workspace = [
            'project_path' => base_path(),
            'public_path' => public_path(),
            'storage_path' => storage_path(),
            'app_name' => config('app.name'),
            'app_env' => app()->environment(),
            'app_url' => config('app.url'),
            'developer_codes_url' => env('DEVELOPER_CODES_URL', 'https://developercodes.webscepts.com'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'git_branch' => $this->runCommand('git rev-parse --abbrev-ref HEAD'),
            'git_status' => $this->runCommand('git status --short'),
            'last_commit' => $this->runCommand('git log -1 --pretty=format:"%h - %s (%cr)"'),
            'composer_status' => File::exists(base_path('composer.json')) ? 'composer.json found' : 'composer.json missing',
            'env_status' => File::exists(base_path('.env')) ? '.env protected' : '.env missing',
            'node_status' => File::exists(base_path('package.json')) ? 'package.json found' : 'package.json missing',
            'storage_writable' => is_writable(storage_path()),
            'cache_writable' => is_writable(base_path('bootstrap/cache')),
            'php_fpm_socket' => File::exists('/run/php-fpm/www.sock') ? '/run/php-fpm/www.sock' : 'Not detected',
        ];

        $quickCommands = [
            [
                'title' => 'Clear Laravel Cache',
                'description' => 'Clear config, route, view and app cache.',
                'route' => $this->routeByDomain('clear.cache'),
                'color' => 'blue',
                'icon' => 'fa-broom',
            ],
            [
                'title' => 'Git Pull',
                'description' => 'Pull latest code from the current Git branch and clear Laravel cache.',
                'route' => $this->routeByDomain('git.pull'),
                'color' => 'green',
                'icon' => 'fa-code-pull-request',
            ],
            [
                'title' => 'Composer Dump',
                'description' => 'Refresh Composer autoload files.',
                'route' => $this->routeByDomain('composer.dump'),
                'color' => 'purple',
                'icon' => 'fa-box',
            ],
            [
                'title' => 'NPM Build',
                'description' => 'Run npm install and npm run build if package.json exists.',
                'route' => $this->routeByDomain('npm.build'),
                'color' => 'orange',
                'icon' => 'fa-brands fa-node-js',
            ],
        ];

        $safeFolders = $this->safeFolders();

        return view('developers.workspace', compact(
            'servers',
            'workspace',
            'quickCommands',
            'safeFolders'
        ));
    }

    public function gitPull()
    {
        try {
            $this->ensureGitRepository();

            $output = $this->runCommand(
                'git status --short 2>&1 && ' .
                'git pull 2>&1 && ' .
                'composer dump-autoload 2>&1 && ' .
                'php artisan optimize:clear 2>&1'
            );

            return back()
                ->with('success', 'Git pull completed successfully.')
                ->with('command_output', $output);
        } catch (\Throwable $e) {
            Log::error('Developer git pull failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Git pull failed: ' . $e->getMessage());
        }
    }

    public function clearCache()
    {
        try {
            $output = $this->runCommand(
                'php artisan optimize:clear 2>&1 && ' .
                'php artisan view:clear 2>&1 && ' .
                'php artisan cache:clear 2>&1 && ' .
                'php artisan route:clear 2>&1 && ' .
                'php artisan config:clear 2>&1'
            );

            return back()
                ->with('success', 'Laravel cache cleared successfully.')
                ->with('command_output', $output);
        } catch (\Throwable $e) {
            Log::error('Developer cache clear failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Cache clear failed: ' . $e->getMessage());
        }
    }

    public function composerDump()
    {
        try {
            if (!File::exists(base_path('composer.json'))) {
                return back()->with('error', 'composer.json not found in project root.');
            }

            $output = $this->runCommand(
                'composer dump-autoload 2>&1 && ' .
                'php artisan optimize:clear 2>&1'
            );

            return back()
                ->with('success', 'Composer autoload refreshed successfully.')
                ->with('command_output', $output);
        } catch (\Throwable $e) {
            Log::error('Developer composer dump failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Composer dump failed: ' . $e->getMessage());
        }
    }

    public function npmBuild()
    {
        try {
            if (!File::exists(base_path('package.json'))) {
                return back()->with('error', 'package.json not found in project root.');
            }

            $output = $this->runCommand(
                'npm install 2>&1 && ' .
                'npm run build 2>&1 && ' .
                'php artisan optimize:clear 2>&1'
            );

            return back()
                ->with('success', 'NPM build completed successfully.')
                ->with('command_output', $output);
        } catch (\Throwable $e) {
            Log::error('Developer npm build failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'NPM build failed: ' . $e->getMessage());
        }
    }

    public function openFolder(Request $request)
    {
        $data = $request->validate([
            'folder' => 'required|string|max:255',
        ]);

        $folder = $this->resolveSafeFolder($data['folder']);

        if (!$folder) {
            return back()->with('error', 'Folder is not allowed.');
        }

        $items = collect(File::files($folder))
            ->map(function ($file) use ($folder) {
                return [
                    'name' => $file->getFilename(),
                    'path' => str_replace(base_path() . '/', '', $file->getPathname()),
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    'type' => 'file',
                ];
            })
            ->merge(
                collect(File::directories($folder))->map(function ($dir) {
                    return [
                        'name' => basename($dir),
                        'path' => str_replace(base_path() . '/', '', $dir),
                        'size' => null,
                        'modified' => date('Y-m-d H:i:s', filemtime($dir)),
                        'type' => 'folder',
                    ];
                })
            )
            ->sortBy('name')
            ->values();

        return back()
            ->with('success', 'Folder loaded: ' . str_replace(base_path() . '/', '', $folder))
            ->with('folder_items', $items->toArray())
            ->with('folder_path', str_replace(base_path() . '/', '', $folder));
    }

    public function downloadEnvExample()
    {
        $example = [
            'APP_NAME="Webscepts SentinelCore"',
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_URL=https://systemmonitor.webscepts.com',
            '',
            'DEVELOPER_CODES_URL=https://developercodes.webscepts.com',
            '',
            'SESSION_DRIVER=file',
            'SESSION_LIFETIME=120',
            'SESSION_ENCRYPT=false',
            'SESSION_PATH=/',
            'SESSION_DOMAIN=systemmonitor.webscepts.com',
            'SESSION_SECURE_COOKIE=true',
            'SESSION_HTTP_ONLY=true',
            'SESSION_SAME_SITE=lax',
            '',
            'MAIL_MAILER=smtp',
            'MAIL_HOST=bizmail.webscepts.com',
            'MAIL_PORT=587',
            'MAIL_USERNAME=system.crm@webscepts.com',
            'MAIL_PASSWORD="CHANGE_ME"',
            'MAIL_ENCRYPTION=tls',
            'MAIL_FROM_ADDRESS=system.crm@webscepts.com',
            'MAIL_FROM_NAME="Webscepts SentinelCore"',
            '',
            'SENTINEL_PYTHON_BIN=/usr/bin/python3',
            '',
            'CLOUDNS_AUTH_ID=57908',
            'CLOUDNS_AUTH_PASSWORD="CHANGE_ME"',
            'CLOUDNS_API_URL=https://api.cloudns.net',
        ];

        return response(implode(PHP_EOL, $example), 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="sentinelcore.env.example"',
        ]);
    }

    private function servers()
    {
        if (!class_exists(Server::class) || !Schema::hasTable('servers')) {
            return collect();
        }

        return Server::latest()->get();
    }

    private function runCommand(string $command): string
    {
        $basePath = base_path();

        $safeCommand = 'cd ' . escapeshellarg($basePath) . ' && ' . $command;

        $output = shell_exec($safeCommand);

        return trim($output ?? '');
    }

    private function ensureGitRepository(): void
    {
        if (!File::exists(base_path('.git'))) {
            throw new \Exception('This project is not a Git repository.');
        }
    }

    private function routeByDomain(string $action): string
    {
        $host = request()->getHost();

        $domainMap = [
            'git.pull' => 'developer.domain.git.pull',
            'clear.cache' => 'developer.domain.clear.cache',
            'composer.dump' => 'developer.domain.composer.dump',
            'npm.build' => 'developer.domain.npm.build',
        ];

        $normalMap = [
            'git.pull' => 'developers.git.pull',
            'clear.cache' => 'developers.clear.cache',
            'composer.dump' => 'developers.composer.dump',
            'npm.build' => 'developers.npm.build',
        ];

        if ($host === 'developercodes.webscepts.com' && isset($domainMap[$action]) && app('router')->has($domainMap[$action])) {
            return route($domainMap[$action]);
        }

        if (isset($normalMap[$action]) && app('router')->has($normalMap[$action])) {
            return route($normalMap[$action]);
        }

        return '#';
    }

    private function safeFolders(): array
    {
        return [
            [
                'label' => 'Controllers',
                'path' => 'app/Http/Controllers',
                'icon' => 'fa-code',
            ],
            [
                'label' => 'Models',
                'path' => 'app/Models',
                'icon' => 'fa-database',
            ],
            [
                'label' => 'Services',
                'path' => 'app/Services',
                'icon' => 'fa-gears',
            ],
            [
                'label' => 'Console Commands',
                'path' => 'app/Console/Commands',
                'icon' => 'fa-terminal',
            ],
            [
                'label' => 'Blade Views',
                'path' => 'resources/views',
                'icon' => 'fa-file-code',
            ],
            [
                'label' => 'Routes',
                'path' => 'routes',
                'icon' => 'fa-route',
            ],
            [
                'label' => 'Migrations',
                'path' => 'database/migrations',
                'icon' => 'fa-table',
            ],
            [
                'label' => 'Sentinel Python',
                'path' => 'sentinelcore/python',
                'icon' => 'fa-brands fa-python',
            ],
        ];
    }

    public function codeEditor()
    {
        $developer = auth()->guard('developer')->user();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        if (isset($developer->can_view_files) && !$developer->can_view_files) {
            return redirect()
                ->route('developer.domain.workspace')
                ->with('error', 'You do not have permission to access the code editor.');
        }

        $vscodeUrl = config('services.vscode.url') ?: env('VSCODE_WEB_URL');

        if (!$vscodeUrl) {
            return redirect()
                ->route('developer.domain.workspace')
                ->with('error', 'Web VS Code URL is not configured. Please add VSCODE_WEB_URL in .env.');
        }

        return redirect()->away($vscodeUrl);
    }

    private function resolveSafeFolder(string $relativePath): ?string
    {
        $allowed = collect($this->safeFolders())->pluck('path')->toArray();

        $relativePath = trim($relativePath, '/');

        if (!in_array($relativePath, $allowed, true)) {
            return null;
        }

        $fullPath = base_path($relativePath);

        if (!File::isDirectory($fullPath)) {
            return null;
        }

        return $fullPath;
    }
}