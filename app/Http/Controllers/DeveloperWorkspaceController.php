<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class DeveloperWorkspaceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Main Developer Workspace
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        return $this->safeView('developers.workspace', [
            'developer' => $developer,
            'projectRoot' => $this->projectRoot($developer),
            'editorBackendUrl' => $this->developerCodeEditorUrl($developer),
        ]);
    }

    public function projectFiles()
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        if (!$this->can($developer, 'can_view_files')) {
            return $this->permissionDenied();
        }

        $projectRoot = $this->projectRoot($developer);

        $files = $this->listProjectFiles($projectRoot);

        return $this->safeView('developers.project-files', [
            'developer' => $developer,
            'projectRoot' => $projectRoot,
            'files' => $files,
        ]);
    }

    public function commands()
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        return $this->safeView('developers.commands', [
            'developer' => $developer,
            'projectRoot' => $this->projectRoot($developer),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Visual Web VS Code
    |--------------------------------------------------------------------------
    | Public developer route:
    | https://developercodes.webscepts.com/codeditor
    |
    | Backend must be:
    | https://code-username.webscepts.com
    |--------------------------------------------------------------------------
    */

    public function codeEditor()
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
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
                ->with('error', 'VS Code backend URL is missing. Please press Setup VS Code + SSL for this developer account.');
        }

        return view('developers.codeditor', [
            'developer' => $developer,
            'editorBackendUrl' => $editorBackendUrl,
            'projectRoot' => $this->projectRoot($developer),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Developer Tool Pages
    |--------------------------------------------------------------------------
    */

    public function gitTools()
    {
        return $this->toolPage('developers.git-tools');
    }

    public function database()
    {
        return $this->toolPage('developers.database');
    }

    public function envManager()
    {
        return $this->toolPage('developers.env-manager');
    }

    public function errorLogs()
    {
        return $this->toolPage('developers.error-logs');
    }

    public function safeTerminal()
    {
        return $this->toolPage('developers.safe-terminal');
    }

    public function laravelTools()
    {
        return $this->toolPage('developers.laravel-tools');
    }

    public function frontendTools()
    {
        return $this->toolPage('developers.frontend-tools');
    }

    public function pythonTools()
    {
        return $this->toolPage('developers.python-tools');
    }

    public function deployment()
    {
        return $this->toolPage('developers.deployment');
    }

    public function healthCheck()
    {
        return $this->toolPage('developers.health-check');
    }

    public function securityNotes()
    {
        return $this->toolPage('developers.security-notes');
    }

    public function backupStatus()
    {
        return $this->toolPage('developers.backup-status');
    }

    public function permissions()
    {
        return $this->toolPage('developers.permissions');
    }

    public function accountSettings()
    {
        return $this->toolPage('developers.account-settings');
    }

    /*
    |--------------------------------------------------------------------------
    | Safe Developer Actions
    |--------------------------------------------------------------------------
    */

    public function gitPull(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        if (!$this->can($developer, 'can_git_pull')) {
            return back()->with('error', 'You do not have permission to run Git Pull.');
        }

        return $this->runSafeCommand($developer, 'git pull', 'Git pull completed.');
    }

    public function clearCache(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        if (!$this->can($developer, 'can_clear_cache')) {
            return back()->with('error', 'You do not have permission to clear cache.');
        }

        $projectRoot = $this->projectRoot($developer);

        if (File::exists($projectRoot . '/artisan')) {
            return $this->runSafeCommand(
                $developer,
                'php artisan optimize:clear',
                'Laravel cache cleared.'
            );
        }

        return back()->with('error', 'Laravel artisan file not found in project root.');
    }

    public function composerDump(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        if (!$this->can($developer, 'can_composer')) {
            return back()->with('error', 'You do not have permission to run Composer.');
        }

        return $this->runSafeCommand(
            $developer,
            'composer dump-autoload',
            'Composer dump-autoload completed.'
        );
    }

    public function npmBuild(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        if (!$this->can($developer, 'can_run_build') && !$this->can($developer, 'can_npm')) {
            return back()->with('error', 'You do not have permission to run NPM build.');
        }

        return $this->runSafeCommand(
            $developer,
            'npm run build',
            'NPM build completed.'
        );
    }

    public function openFolder(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        if (!$this->can($developer, 'can_view_files')) {
            return back()->with('error', 'You do not have permission to view files.');
        }

        return redirect()->route('developer.domain.project.files');
    }

    public function downloadEnvExample()
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        if (!$this->can($developer, 'can_view_files')) {
            return back()->with('error', 'You do not have permission to download files.');
        }

        $projectRoot = $this->projectRoot($developer);
        $path = $projectRoot . '/.env.example';

        if (!File::exists($path)) {
            return back()->with('error', '.env.example file not found.');
        }

        return Response::download($path, '.env.example');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    private function developer()
    {
        if (Auth::guard('developer')->check()) {
            return Auth::guard('developer')->user();
        }

        if (Auth::check()) {
            $developerId = request()->route('developer');

            if ($developerId instanceof DeveloperUser) {
                return $developerId;
            }

            if ($developerId) {
                return DeveloperUser::find($developerId);
            }

            return DeveloperUser::query()
                ->whereNotNull('cpanel_username')
                ->latest()
                ->first();
        }

        return null;
    }

    private function developerLoginRedirect()
    {
        if (request()->getHost() === 'developercodes.webscepts.com') {
            return redirect()->route('developer.login');
        }

        return redirect()->route('login');
    }

    private function permissionDenied()
    {
        if (request()->getHost() === 'developercodes.webscepts.com') {
            return redirect()
                ->route('developer.domain.workspace')
                ->with('error', 'You do not have permission to access this page.');
        }

        return back()->with('error', 'You do not have permission to access this page.');
    }

    private function toolPage(string $viewName)
    {
        $developer = $this->developer();

        if (!$developer) {
            return $this->developerLoginRedirect();
        }

        return $this->safeView($viewName, [
            'developer' => $developer,
            'projectRoot' => $this->projectRoot($developer),
            'editorBackendUrl' => $this->developerCodeEditorUrl($developer),
        ]);
    }

    private function safeView(string $viewName, array $data = [])
    {
        if (View::exists($viewName)) {
            return view($viewName, $data);
        }

        if (View::exists('developers.workspace')) {
            return view('developers.workspace', $data);
        }

        return response()->view('developers.fallback', $data, 200);
    }

    private function can($developer, string $permission): bool
    {
        if (!$developer) {
            return false;
        }

        $table = $developer->getTable();

        if (!Schema::hasColumn($table, $permission)) {
            return true;
        }

        return (bool) ($developer->{$permission} ?? false);
    }

    private function projectRoot($developer): string
    {
        $projectRoot = $developer->project_root
            ?? $developer->allowed_project_path
            ?? null;

        if (!$projectRoot) {
            $username = $developer->cpanel_username
                ?? $developer->ssh_username
                ?? 'developer';

            $projectRoot = '/home/' . $username . '/public_html';
        }

        return rtrim($projectRoot, '/');
    }

    private function developerCodeEditorUrl($developer): ?string
    {
        if (!$developer) {
            return null;
        }

        $table = $developer->getTable();

        if (Schema::hasColumn($table, 'code_editor_url')) {
            $url = trim((string) ($developer->code_editor_url ?? ''));

            if ($this->validCodeEditorBackendUrl($url)) {
                return $this->normalizeUrl($url);
            }
        }

        if (Schema::hasColumn($table, 'vscode_url')) {
            $url = trim((string) ($developer->vscode_url ?? ''));

            if ($this->validCodeEditorBackendUrl($url)) {
                return $this->normalizeUrl($url);
            }
        }

        $username = trim((string) (
            $developer->cpanel_username
            ?? $developer->ssh_username
            ?? ''
        ));

        if ($username) {
            $safeUsername = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $username));
            $safeUsername = trim($safeUsername, '-');

            return $this->normalizeUrl('https://code-' . $safeUsername . '.webscepts.com');
        }

        $fallback = config('services.vscode.url') ?: env('VSCODE_WEB_URL');

        if ($this->validCodeEditorBackendUrl($fallback)) {
            return $this->normalizeUrl($fallback);
        }

        return null;
    }

    private function validCodeEditorBackendUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if (!$url) {
            return false;
        }

        if (str_contains($url, 'developercodes.webscepts.com/codeditor')) {
            return false;
        }

        if (str_contains($url, 'developercodes.webscepts.com/codeeditor')) {
            return false;
        }

        if (str_ends_with(rtrim($url, '/'), '/codeditor')) {
            return false;
        }

        if (str_ends_with(rtrim($url, '/'), '/codeeditor')) {
            return false;
        }

        return true;
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

    private function listProjectFiles(string $projectRoot): array
    {
        if (!File::exists($projectRoot) || !File::isDirectory($projectRoot)) {
            return [];
        }

        $items = [];

        try {
            foreach (File::directories($projectRoot) as $directory) {
                $name = basename($directory);

                if ($this->shouldHidePath($name)) {
                    continue;
                }

                $items[] = [
                    'type' => 'folder',
                    'name' => $name,
                    'path' => $directory,
                    'size' => null,
                    'modified' => File::lastModified($directory),
                ];
            }

            foreach (File::files($projectRoot) as $file) {
                $name = $file->getFilename();

                if ($this->shouldHidePath($name)) {
                    continue;
                }

                $items[] = [
                    'type' => 'file',
                    'name' => $name,
                    'path' => $file->getRealPath(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        usort($items, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcmp($a['name'], $b['name']);
            }

            return $a['type'] === 'folder' ? -1 : 1;
        });

        return $items;
    }

    private function shouldHidePath(string $name): bool
    {
        $hidden = [
            '.git',
            '.svn',
            '.hg',
            'node_modules',
            'vendor',
            '.env',
        ];

        return in_array($name, $hidden, true);
    }

    private function runSafeCommand($developer, string $command, string $successMessage)
    {
        $projectRoot = $this->projectRoot($developer);

        if (!File::exists($projectRoot) || !File::isDirectory($projectRoot)) {
            return back()->with('error', 'Project root does not exist: ' . $projectRoot);
        }

        $allowedCommands = [
            'git pull',
            'php artisan optimize:clear',
            'composer dump-autoload',
            'npm run build',
        ];

        if (!in_array($command, $allowedCommands, true)) {
            return back()->with('error', 'This command is not allowed.');
        }

        $fullCommand = 'cd ' . escapeshellarg($projectRoot) . ' && ' . $command . ' 2>&1';

        try {
            $output = shell_exec($fullCommand);

            return back()
                ->with('success', $successMessage)
                ->with('command_output', trim((string) $output));
        } catch (\Throwable $e) {
            return back()->with('error', 'Command failed: ' . $e->getMessage());
        }
    }
}