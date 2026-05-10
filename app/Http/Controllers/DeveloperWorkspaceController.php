<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DeveloperWorkspaceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Developer Workspace Pages
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return $this->renderWorkspacePage(
            'Overview',
            'Workspace overview, project details, quick tools and permissions.',
            'fa-solid fa-layer-group',
            'overview'
        );
    }

    public function projectFiles()
    {
        return $this->renderWorkspacePage(
            'Project Files',
            'View project path, public path, file access status and editor shortcuts.',
            'fa-solid fa-folder-tree',
            'project-files'
        );
    }

    public function commands()
    {
        return $this->renderWorkspacePage(
            'Commands',
            'Run approved project commands only. Unsafe shell access is blocked.',
            'fa-solid fa-terminal',
            'commands'
        );
    }

    public function gitTools()
    {
        return $this->renderWorkspacePage(
            'Git Tools',
            'Check branch details and run approved Git actions.',
            'fa-solid fa-code-branch',
            'git-tools'
        );
    }

    public function database()
    {
        return $this->renderWorkspacePage(
            'Database Access',
            'View assigned MySQL and PostgreSQL access details.',
            'fa-solid fa-database',
            'database'
        );
    }

    public function envManager()
    {
        return $this->renderWorkspacePage(
            'ENV Manager',
            'Safe environment configuration notes and .env example tools.',
            'fa-solid fa-file-code',
            'env-manager'
        );
    }

    public function errorLogs()
    {
        return $this->renderWorkspacePage(
            'Error Logs',
            'Developer-friendly error log and debugging section.',
            'fa-solid fa-file-lines',
            'error-logs'
        );
    }

    public function safeTerminal()
    {
        return $this->renderWorkspacePage(
            'Safe Terminal',
            'Run only approved commands. Full unrestricted terminal is disabled.',
            'fa-solid fa-square-terminal',
            'safe-terminal'
        );
    }

    public function laravelTools()
    {
        return $this->renderWorkspacePage(
            'Laravel Tools',
            'Laravel cache, route, config, composer and migration tools.',
            'fa-brands fa-laravel',
            'laravel-tools'
        );
    }

    public function frontendTools()
    {
        return $this->renderWorkspacePage(
            'Frontend Build Tools',
            'React, Vue, Angular, Node and NPM build tools.',
            'fa-brands fa-react',
            'frontend-tools'
        );
    }

    public function pythonTools()
    {
        return $this->renderWorkspacePage(
            'Python Tools',
            'Python, Flask, Django and FastAPI developer tools.',
            'fa-brands fa-python',
            'python-tools'
        );
    }

    public function deployment()
    {
        return $this->renderWorkspacePage(
            'Deployment',
            'Deploy command notes, post-deploy checks and rollback information.',
            'fa-solid fa-rocket',
            'deployment'
        );
    }

    public function healthCheck()
    {
        return $this->renderWorkspacePage(
            'Health Check',
            'Website, SSL, disk and performance status checks.',
            'fa-solid fa-heart-pulse',
            'health-check'
        );
    }

    public function securityNotes()
    {
        return $this->renderWorkspacePage(
            'Security Notes',
            'Safe development security notes, restricted access and permission details.',
            'fa-solid fa-shield-halved',
            'security-notes'
        );
    }

    public function backupStatus()
    {
        return $this->renderWorkspacePage(
            'Backup Status',
            'Backup notes and project protection status.',
            'fa-solid fa-cloud-arrow-up',
            'backup-status'
        );
    }

    public function permissions()
    {
        return $this->renderWorkspacePage(
            'Permissions',
            'View allowed and disabled developer actions.',
            'fa-solid fa-user-shield',
            'permissions'
        );
    }

    public function accountSettings()
    {
        return $this->renderWorkspacePage(
            'Account Settings',
            'Developer profile, cPanel username, project path and login details.',
            'fa-solid fa-user-gear',
            'account-settings'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Web VS Code / Code Editor
    |--------------------------------------------------------------------------
    | Public developer URL:
    | https://developercodes.webscepts.com/codeditor
    |
    | Backend editor URL:
    | developer_users.code_editor_url first
    | developer_users.vscode_url fallback if column exists
    | .env VSCODE_WEB_URL only final fallback
    |--------------------------------------------------------------------------
    */

 

    private function developerCodeEditorUrl($developer): ?string
    {
        if (!$developer) {
            return null;
        }

        $table = $developer->getTable();

        /*
        |--------------------------------------------------------------------------
        | 1. Saved per-account code-server URL
        |--------------------------------------------------------------------------
        | Example:
        | https://code-devteengirls.webscepts.com
        |--------------------------------------------------------------------------
        */

        if (Schema::hasColumn($table, 'code_editor_url')) {
            $url = trim((string) ($developer->code_editor_url ?? ''));

            if ($url) {
                return $this->normalizeUrl($url);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Optional old fallback column
        |--------------------------------------------------------------------------
        */

        if (Schema::hasColumn($table, 'vscode_url')) {
            $url = trim((string) ($developer->vscode_url ?? ''));

            if ($url) {
                return $this->normalizeUrl($url);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Auto-generate code-server URL from cPanel username
        |--------------------------------------------------------------------------
        | This must point to the code-server backend, not website domain.
        |--------------------------------------------------------------------------
        */

        $username = trim((string) (
            $developer->cpanel_username
            ?? $developer->ssh_username
            ?? ''
        ));

        if ($username) {
            return $this->normalizeUrl('https://code-' . strtolower($username) . '.webscepts.com');
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Final global fallback only
        |--------------------------------------------------------------------------
        */

        $fallback = config('services.vscode.url') ?: env('VSCODE_WEB_URL');

        if ($fallback) {
            return $this->normalizeUrl($fallback);
        }

        return null;
    }

    public function clearCache(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        if (!$this->can($developer, 'can_clear_cache')) {
            return back()->with('error', 'Clear Cache permission is disabled.');
        }

        $projectRoot = $this->projectRoot($developer);

        if (!$this->validProjectPath($projectRoot)) {
            return back()->with('error', 'Project path is invalid or not accessible.');
        }

        if (file_exists($projectRoot . '/artisan')) {
            $command = 'php artisan optimize:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear';
        } else {
            $command = 'rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/framework/views/* 2>/dev/null || true';
        }

        $result = $this->runSafeCommand($command, $projectRoot, 120);

        return $this->commandResponse($result, 'Project cache cleared successfully.');
    }

    public function composerDump(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        if (!$this->can($developer, 'can_composer')) {
            return back()->with('error', 'Composer permission is disabled.');
        }

        $projectRoot = $this->projectRoot($developer);

        if (!$this->validProjectPath($projectRoot)) {
            return back()->with('error', 'Project path is invalid or not accessible.');
        }

        if (!file_exists($projectRoot . '/composer.json')) {
            return back()->with('error', 'composer.json was not found in the project path.');
        }

        $result = $this->runSafeCommand('composer dump-autoload -o', $projectRoot, 180);

        return $this->commandResponse($result, 'Composer dump-autoload completed successfully.');
    }

    public function npmBuild(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        if (!$this->can($developer, 'can_npm') && !$this->can($developer, 'can_run_build')) {
            return back()->with('error', 'NPM Build permission is disabled.');
        }

        $projectRoot = $this->projectRoot($developer);

        if (!$this->validProjectPath($projectRoot)) {
            return back()->with('error', 'Project path is invalid or not accessible.');
        }

        if (!file_exists($projectRoot . '/package.json')) {
            return back()->with('error', 'package.json was not found in the project path.');
        }

        $command = 'npm install && npm run build';

        $result = $this->runSafeCommand($command, $projectRoot, 600);

        return $this->commandResponse($result, 'NPM build completed successfully.');
    }

    public function openFolder(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        if (!$this->can($developer, 'can_view_files')) {
            return back()->with('error', 'File access permission is disabled.');
        }

        return redirect()->route('developer.domain.codeditor');
    }

    public function downloadEnvExample()
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        $projectName = $developer->cpanel_domain
            ?? $developer->cpanel_username
            ?? 'developer-project';

        $dbType = $developer->db_type ?? 'mysql';
        $dbHost = $developer->db_host ?? '127.0.0.1';
        $dbPort = $developer->db_port ?? ($dbType === 'postgresql' ? '5432' : '3306');
        $dbName = $developer->db_name ?? '';
        $dbUser = $developer->db_username ?? '';

        $env = <<<ENV
APP_NAME="{$projectName}"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://{$projectName}

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION={$dbType}
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD=

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="\${APP_NAME}"

# IMPORTANT:
# This is only an example file.
# Do not expose real passwords, API keys, tokens, or APP_KEY publicly.
ENV;

        $fileName = 'env-example-' . Str::slug($projectName) . '.txt';

        return Response::make($env, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Render Workspace View
    |--------------------------------------------------------------------------
    */

    private function renderWorkspacePage(string $title, string $description, string $icon, string $page)
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        $projectRoot = $this->projectRoot($developer);

        return view('developers.workspace', [
            'pageTitle' => $title,
            'pageDescription' => $description,
            'pageIcon' => $icon,
            'activeDeveloperPage' => $page,
            'gitBranch' => $this->gitBranch($projectRoot),
            'projectRoot' => $projectRoot,
            'editorBackendUrl' => $this->developerCodeEditorUrl($developer),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function developer()
    {
        return auth()->guard('developer')->user();
    }

    private function can($developer, string $permission): bool
    {
        if (!$developer) {
            return false;
        }

        $table = $developer->getTable();

        if (Schema::hasColumn($table, $permission)) {
            return (bool) $developer->{$permission};
        }

        /*
        |--------------------------------------------------------------------------
        | If permission column is missing, keep safe default false.
        |--------------------------------------------------------------------------
        */

        return false;
    }

    public function codeEditor()
{
    $developer = $this->developer();

    if (!$developer) {
        return redirect()->route('developer.login');
    }

    if (!$this->can($developer, 'can_view_files')) {
        return redirect()
            ->route('developer.domain.workspace')
            ->with('error', 'You do not have permission to access the code editor.');
    }

    $editorBackendUrl = $this->developerCodeEditorUrl($developer);

    if (!$editorBackendUrl) {
        return redirect()
            ->route('developer.domain.workspace')
            ->with('error', 'Code editor URL is not configured for this developer account.');
    }

    return view('developers.codeditor', [
        'developer' => $developer,
        'editorBackendUrl' => $editorBackendUrl,
        'projectRoot' => $this->projectRoot($developer),
    ]);
}

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (!$url) {
            return $url;
        }

        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }

        return rtrim($url, '/');
    }

    private function projectRoot($developer): string
    {
        $path = $developer->project_root
            ?? $developer->allowed_project_path
            ?? null;

        if (!$path) {
            $username = $developer->cpanel_username
                ?? $developer->ssh_username
                ?? 'developer';

            $path = '/home/' . $username . '/public_html';
        }

        return rtrim($path, '/');
    }

    private function validProjectPath(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        $path = rtrim($path, '/');

        if (!str_starts_with($path, '/home/') && !str_starts_with($path, '/var/www/')) {
            return false;
        }

        return is_dir($path);
    }

    private function gitBranch(string $projectRoot): string
    {
        if (!$this->validProjectPath($projectRoot)) {
            return 'unknown';
        }

        if (!is_dir($projectRoot . '/.git')) {
            return 'not-git';
        }

        $result = $this->runSafeCommand('git rev-parse --abbrev-ref HEAD', $projectRoot, 20);

        if (!$result['success']) {
            return 'unknown';
        }

        return trim($result['output']) ?: 'unknown';
    }

    private function runSafeCommand(string $command, string $workingDirectory, int $timeout = 120): array
    {
        try {
            $process = Process::fromShellCommandline($command, $workingDirectory);
            $process->setTimeout($timeout);
            $process->run();

            $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());

            return [
                'success' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'output' => $output ?: 'No output returned.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'exit_code' => 1,
                'output' => $e->getMessage(),
            ];
        }
    }

    private function commandResponse(array $result, string $successMessage)
    {
        if ($result['success']) {
            return back()
                ->with('success', $successMessage)
                ->with('command_output', $result['output']);
        }

        return back()
            ->with('error', 'Command failed: ' . Str::limit($result['output'], 1000))
            ->with('command_output', $result['output']);
    }
}