<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeveloperFileEditorController extends Controller
{
    public function index()
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        return view('developers.codeditor', [
            'developer' => $developer,
            'projectRoot' => $this->projectRoot($developer),
            'startDir' => $this->projectRoot($developer),
        ]);
    }

    public function tree(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->can($developer, 'can_view_files')) {
            return response()->json(['ok' => false, 'message' => 'No permission to view files.'], 403);
        }

        $dir = $this->safePath($developer, $request->input('dir') ?: $this->projectRoot($developer));

        try {
            $result = $this->cpanelUapi($developer, 'Fileman', 'list_files', [
                'dir' => $dir,
                'show_hidden' => 1,
                'include_mime' => 1,
                'include_permissions' => 1,
                'check_for_leaf_directories' => 1,
            ]);

            $data = $result['data'] ?? [];

            $dirs = collect($data['dirs'] ?? [])
                ->filter(fn ($item) => !$this->isBlockedName($item['file'] ?? ''))
                ->map(function ($item) {
                    return [
                        'type' => 'dir',
                        'name' => $item['file'] ?? basename($item['fullpath'] ?? ''),
                        'path' => $item['fullpath'] ?? $item['path'] ?? '',
                        'size' => $item['humansize'] ?? '',
                        'mime' => $item['mimetype'] ?? 'directory',
                        'modified' => $item['mtime'] ?? null,
                        'writable' => (bool)($item['write'] ?? true),
                    ];
                })
                ->values();

            $files = collect($data['files'] ?? [])
                ->filter(fn ($item) => !$this->isBlockedName($item['file'] ?? ''))
                ->map(function ($item) {
                    return [
                        'type' => 'file',
                        'name' => $item['file'] ?? basename($item['fullpath'] ?? ''),
                        'path' => $item['fullpath'] ?? $item['path'] ?? '',
                        'size' => $item['humansize'] ?? '',
                        'bytes' => $item['size'] ?? 0,
                        'mime' => $item['mimetype'] ?? '',
                        'modified' => $item['mtime'] ?? null,
                        'writable' => (bool)($item['write'] ?? true),
                    ];
                })
                ->values();

            return response()->json([
                'ok' => true,
                'dir' => $dir,
                'parent' => $this->parentDir($developer, $dir),
                'items' => $dirs->merge($files)->values(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function read(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->can($developer, 'can_view_files')) {
            return response()->json(['ok' => false, 'message' => 'No permission to read files.'], 403);
        }

        $path = $this->safePath($developer, $request->input('path'));

        if ($this->isBlockedFile($path)) {
            return response()->json(['ok' => false, 'message' => 'This file is protected.'], 403);
        }

        [$dir, $file] = $this->splitPath($path);

        try {
            $result = $this->cpanelUapi($developer, 'Fileman', 'get_file_content', [
                'dir' => $dir,
                'file' => $file,
                'from_charset' => '_DETECT_',
                'to_charset' => 'UTF-8',
            ]);

            $data = $result['data'] ?? [];

            return response()->json([
                'ok' => true,
                'path' => $path,
                'filename' => $data['filename'] ?? $file,
                'content' => $data['content'] ?? '',
                'language' => $this->languageForFile($file),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function save(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->can($developer, 'can_edit_files')) {
            return response()->json(['ok' => false, 'message' => 'No permission to save files.'], 403);
        }

        $path = $this->safePath($developer, $request->input('path'));
        $content = (string) $request->input('content', '');

        if ($this->isBlockedFile($path)) {
            return response()->json(['ok' => false, 'message' => 'This file is protected.'], 403);
        }

        [$dir, $file] = $this->splitPath($path);

        try {
            $result = $this->cpanelUapi($developer, 'Fileman', 'save_file_content', [
                'dir' => $dir,
                'file' => $file,
                'content' => $content,
                'from_charset' => 'UTF-8',
                'to_charset' => 'UTF-8',
                'fallback' => 1,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Saved successfully.',
                'data' => $result['data'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createFile(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->can($developer, 'can_edit_files')) {
            return response()->json(['ok' => false, 'message' => 'No permission to create files.'], 403);
        }

        $dir = $this->safePath($developer, $request->input('dir') ?: $this->projectRoot($developer));
        $name = trim((string) $request->input('name'));

        if (!$name || str_contains($name, '/') || str_contains($name, '\\')) {
            return response()->json(['ok' => false, 'message' => 'Invalid file name.'], 422);
        }

        $path = rtrim($dir, '/') . '/' . $name;

        if ($this->isBlockedFile($path)) {
            return response()->json(['ok' => false, 'message' => 'This file is protected.'], 403);
        }

        try {
            $this->cpanelUapi($developer, 'Fileman', 'save_file_content', [
                'dir' => $dir,
                'file' => $name,
                'content' => '',
                'from_charset' => 'UTF-8',
                'to_charset' => 'UTF-8',
                'fallback' => 1,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'File created.',
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function developer(): ?DeveloperUser
    {
        if (Auth::guard('developer')->check()) {
            return Auth::guard('developer')->user();
        }

        return null;
    }

    private function cpanelUapi(DeveloperUser $developer, string $module, string $function, array $params = []): array
    {
        $server = $this->serverForDeveloper($developer);

        $credentials = $this->serverCredentials($server);

        $host = $this->cleanHost((string) ($credentials['host'] ?? ''));
        $whmUsername = trim((string) ($credentials['username'] ?? 'root'));
        $whmPassword = trim((string) ($credentials['password'] ?? ''));

        $cpanelUser = trim((string) (
            $developer->cpanel_username
            ?: $developer->ssh_username
            ?: ''
        ));

        if (!$host) {
            throw new \Exception('WHM/cPanel host is missing on server record.');
        }

        if (!$whmUsername || !$whmPassword) {
            throw new \Exception('WHM username/password is missing on server record.');
        }

        if (!$cpanelUser) {
            throw new \Exception('Developer cPanel username is missing.');
        }

        $query = array_merge([
            'cpanel_jsonapi_user' => $cpanelUser,
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => $module,
            'cpanel_jsonapi_func' => $function,
        ], $params);

        $response = Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->withOptions([
                'verify' => false,
                'connect_timeout' => 20,
            ])
            ->withBasicAuth($whmUsername, $whmPassword)
            ->get('https://' . $host . ':2087/json-api/cpanel', $query);

        if (!$response->successful()) {
            throw new \Exception(
                'WHM cPanel API failed. HTTP ' .
                $response->status() .
                ' - ' .
                Str::limit($response->body(), 800)
            );
        }

        $json = $response->json();

        $result = data_get($json, 'cpanelresult.result');

        if (!$result) {
            $result = data_get($json, 'result');
        }

        $status = data_get($result, 'status');

        if ((string) $status === '0') {
            $errors = data_get($result, 'errors')
                ?: data_get($json, 'cpanelresult.error')
                ?: data_get($json, 'metadata.reason')
                ?: 'Unknown cPanel API error';

            if (is_array($errors)) {
                $errors = implode(', ', array_filter($errors));
            }

            throw new \Exception($errors);
        }

        return is_array($result) ? $result : [];
    }

    private function serverForDeveloper(DeveloperUser $developer): Server
    {
        if (Schema::hasColumn($developer->getTable(), 'server_id') && !empty($developer->server_id)) {
            $server = Server::find($developer->server_id);

            if ($server) {
                return $server;
            }
        }

        $server = Server::latest()->first();

        if (!$server) {
            throw new \Exception('Server record not found.');
        }

        return $server;
    }

    private function serverCredentials(Server $server): array
    {
        $host = $this->serverValue($server, [
            'host',
            'hostname',
            'ip',
            'ip_address',
            'server_ip',
            'public_ip',
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

    private function projectRoot(DeveloperUser $developer): string
    {
        $root = $developer->project_root
            ?: $developer->allowed_project_path
            ?: null;

        if (!$root) {
            $username = $developer->cpanel_username
                ?: $developer->ssh_username
                ?: 'developer';

            $root = '/home/' . $username . '/public_html';
        }

        return rtrim($root, '/');
    }

    private function safePath(DeveloperUser $developer, ?string $path): string
    {
        $root = $this->projectRoot($developer);
        $path = trim((string) $path);

        if (!$path) {
            return $root;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);

        if (str_contains($path, '..')) {
            throw new \Exception('Invalid path.');
        }

        if (!Str::startsWith($path, $root)) {
            $path = rtrim($root, '/') . '/' . ltrim($path, '/');
        }

        return rtrim($path, '/');
    }

    private function splitPath(string $path): array
    {
        $path = rtrim($path, '/');

        return [
            dirname($path),
            basename($path),
        ];
    }

    private function parentDir(DeveloperUser $developer, string $dir): ?string
    {
        $root = $this->projectRoot($developer);
        $dir = rtrim($dir, '/');

        if ($dir === $root) {
            return null;
        }

        $parent = dirname($dir);

        if (!Str::startsWith($parent, $root)) {
            return null;
        }

        return $parent;
    }

    private function cleanHost(string $host): string
    {
        $host = trim($host);
        $host = preg_replace('#^https?://#', '', $host);
        $host = preg_replace('#/.*$#', '', $host);
        $host = preg_replace('#:\d+$#', '', $host);

        return $host;
    }

    private function can(DeveloperUser $developer, string $permission): bool
    {
        if (!Schema::hasColumn($developer->getTable(), $permission)) {
            return true;
        }

        return (bool) ($developer->{$permission} ?? false);
    }

    private function isBlockedName(string $name): bool
    {
        return in_array($name, [
            '.trash',
            '.cagefs',
            '.cpanel',
            '.softaculous',
            '.spamassassin',
            'mail',
            'ssl',
            'tmp',
            'logs',
        ], true);
    }

    private function isBlockedFile(string $path): bool
    {
        $name = basename($path);

        return in_array($name, [
            '.env',
            'id_rsa',
            'id_dsa',
            'id_ecdsa',
            'id_ed25519',
        ], true) || str_ends_with($name, '.sql') || str_ends_with($name, '.pem') || str_ends_with($name, '.key');
    }

    private function languageForFile(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => 'php',
            'js', 'mjs', 'cjs' => 'javascript',
            'ts' => 'typescript',
            'css' => 'css',
            'scss' => 'scss',
            'html', 'htm' => 'html',
            'json' => 'json',
            'xml' => 'xml',
            'md' => 'markdown',
            'yml', 'yaml' => 'yaml',
            'py' => 'python',
            'java' => 'java',
            'go' => 'go',
            'rs' => 'rust',
            'sql' => 'sql',
            'sh', 'bash' => 'shell',
            'vue' => 'html',
            'blade.php' => 'php',
            default => 'plaintext',
        };
    }
}