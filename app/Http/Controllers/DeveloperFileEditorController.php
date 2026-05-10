<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use App\Models\Server;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeveloperFileEditorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Visual Code Editor Page
    |--------------------------------------------------------------------------
    | This editor does not use SSH, root, code-server, or proxy.
    | It uses Monaco Editor + cPanel File Manager API.
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $developer = $this->developer();

        if (!$developer) {
            return redirect()->route('developer.login');
        }

        $projectRoot = $this->projectRoot($developer);

        return view('developers.codeditor', [
            'developer' => $developer,
            'projectRoot' => $projectRoot,
            'startDir' => $projectRoot,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Load Folder Tree
    |--------------------------------------------------------------------------
    */

    public function tree(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->can($developer, 'can_view_files')) {
            return response()->json([
                'ok' => false,
                'message' => 'You do not have permission to view files.',
            ], 403);
        }

        try {
            $dir = $this->safePath(
                $developer,
                $request->input('dir') ?: $this->projectRoot($developer)
            );

            $result = $this->cpanelUapi($developer, 'Fileman', 'list_files', [
                'dir' => $dir,
                'show_hidden' => 1,
                'include_mime' => 1,
                'include_permissions' => 1,
                'check_for_leaf_directories' => 1,
            ]);

            $items = $this->normalizeListFilesResponse($result, $dir);

            return response()->json([
                'ok' => true,
                'dir' => $dir,
                'parent' => $this->parentDir($developer, $dir),
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Read File
    |--------------------------------------------------------------------------
    */

    public function read(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->can($developer, 'can_view_files')) {
            return response()->json([
                'ok' => false,
                'message' => 'You do not have permission to read files.',
            ], 403);
        }

        try {
            $path = $this->safePath($developer, $request->input('path'));

            if ($this->isBlockedFile($path)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This file is protected and cannot be opened.',
                ], 403);
            }

            [$dir, $file] = $this->splitPath($path);

            $result = $this->cpanelUapi($developer, 'Fileman', 'get_file_content', [
                'dir' => $dir,
                'file' => $file,
                'from_charset' => '_DETECT_',
                'to_charset' => 'UTF-8',
            ]);

            $data = $result['data'] ?? [];

            $content = '';

            if (is_array($data)) {
                $content = $data['content']
                    ?? $data['file_content']
                    ?? $data['contents']
                    ?? '';
            }

            if (is_string($data)) {
                $content = $data;
            }

            return response()->json([
                'ok' => true,
                'path' => $path,
                'filename' => $file,
                'content' => $content,
                'language' => $this->languageForFile($file),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save File
    |--------------------------------------------------------------------------
    */

    public function save(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->can($developer, 'can_edit_files')) {
            return response()->json([
                'ok' => false,
                'message' => 'You do not have permission to save files.',
            ], 403);
        }

        try {
            $path = $this->safePath($developer, $request->input('path'));
            $content = (string) $request->input('content', '');

            if ($this->isBlockedFile($path)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This file is protected and cannot be saved.',
                ], 403);
            }

            [$dir, $file] = $this->splitPath($path);

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
                'message' => 'File saved successfully.',
                'data' => $result['data'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create File
    |--------------------------------------------------------------------------
    */

    public function createFile(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->can($developer, 'can_edit_files')) {
            return response()->json([
                'ok' => false,
                'message' => 'You do not have permission to create files.',
            ], 403);
        }

        try {
            $dir = $this->safePath(
                $developer,
                $request->input('dir') ?: $this->projectRoot($developer)
            );

            $name = trim((string) $request->input('name'));

            if (!$name || str_contains($name, '/') || str_contains($name, '\\')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid file name.',
                ], 422);
            }

            $path = rtrim($dir, '/') . '/' . $name;

            if ($this->isBlockedFile($path)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This file is protected and cannot be created.',
                ], 403);
            }

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
                'message' => 'File created successfully.',
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Folder
    |--------------------------------------------------------------------------
    */

    public function createFolder(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->can($developer, 'can_edit_files')) {
            return response()->json([
                'ok' => false,
                'message' => 'You do not have permission to create folders.',
            ], 403);
        }

        try {
            $dir = $this->safePath(
                $developer,
                $request->input('dir') ?: $this->projectRoot($developer)
            );

            $name = trim((string) $request->input('name'));

            if (!$name || str_contains($name, '/') || str_contains($name, '\\')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid folder name.',
                ], 422);
            }

            $path = rtrim($dir, '/') . '/' . $name;

            $this->cpanelUapi($developer, 'Fileman', 'mkdir', [
                'path' => $dir,
                'name' => $name,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Folder created successfully.',
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete File
    |--------------------------------------------------------------------------
    */

    public function deleteFile(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->can($developer, 'can_delete_files')) {
            return response()->json([
                'ok' => false,
                'message' => 'You do not have permission to delete files.',
            ], 403);
        }

        try {
            $path = $this->safePath($developer, $request->input('path'));

            if ($this->isBlockedFile($path)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This file is protected and cannot be deleted.',
                ], 403);
            }

            $this->cpanelUapi($developer, 'Fileman', 'trash_files', [
                'sourcefiles' => $path,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'File moved to trash.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Current Developer
    |--------------------------------------------------------------------------
    */

    private function developer(): ?DeveloperUser
    {
        if (Auth::guard('developer')->check()) {
            return Auth::guard('developer')->user();
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | cPanel UAPI Caller
    |--------------------------------------------------------------------------
    | Method 1: WHM /json-api/cpanel
    | Method 2: WHM create_user_session
    | Method 3: Direct cPanel UAPI with cPanel username/password
    |--------------------------------------------------------------------------
    */

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

        if (!$cpanelUser) {
            throw new \Exception('Developer cPanel username is missing.');
        }

        $errors = [];

        /*
        |--------------------------------------------------------------------------
        | Method 1: Direct WHM json-api/cpanel
        |--------------------------------------------------------------------------
        */

        if ($whmUsername && $whmPassword) {
            try {
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

                if ($response->successful()) {
                    $json = $response->json();

                    $result = data_get($json, 'cpanelresult.result')
                        ?: data_get($json, 'result')
                        ?: [];

                    $status = data_get($result, 'status');

                    if ((string) $status !== '0' && !empty($result)) {
                        return is_array($result) ? $result : [];
                    }

                    $errors[] = 'WHM cPanel API denied: ' . Str::limit($response->body(), 500);
                } else {
                    $errors[] = 'WHM cPanel API HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 500);
                }
            } catch (\Throwable $e) {
                $errors[] = 'WHM cPanel API exception: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'WHM username/password missing.';
        }

        /*
        |--------------------------------------------------------------------------
        | Method 2: WHM create_user_session then cPanel cpsess UAPI
        |--------------------------------------------------------------------------
        */

        if ($whmUsername && $whmPassword) {
            try {
                $sessionResponse = Http::withoutVerifying()
                    ->timeout(60)
                    ->acceptJson()
                    ->withOptions([
                        'verify' => false,
                        'connect_timeout' => 20,
                    ])
                    ->withBasicAuth($whmUsername, $whmPassword)
                    ->get('https://' . $host . ':2087/json-api/create_user_session', [
                        'api.version' => 1,
                        'user' => $cpanelUser,
                        'service' => 'cpaneld',
                    ]);

                if ($sessionResponse->successful()) {
                    $sessionJson = $sessionResponse->json();
                    $sessionResult = data_get($sessionJson, 'metadata.result');

                    if ((string) $sessionResult !== '0') {
                        $sessionUrl = data_get($sessionJson, 'data.url');

                        if ($sessionUrl && preg_match('#/(cpsess[0-9]+)/#', $sessionUrl, $matches)) {
                            $cpsess = $matches[1];

                            $cookieJar = new CookieJar();

                            Http::withoutVerifying()
                                ->timeout(60)
                                ->withOptions([
                                    'verify' => false,
                                    'cookies' => $cookieJar,
                                    'allow_redirects' => true,
                                    'connect_timeout' => 20,
                                ])
                                ->get($sessionUrl);

                            $apiUrl = 'https://' . $host . ':2083/' . $cpsess . '/execute/' . $module . '/' . $function;

                            $apiResponse = Http::withoutVerifying()
                                ->timeout(60)
                                ->acceptJson()
                                ->withOptions([
                                    'verify' => false,
                                    'cookies' => $cookieJar,
                                    'connect_timeout' => 20,
                                ])
                                ->get($apiUrl, $params);

                            if ($apiResponse->successful()) {
                                $apiJson = $apiResponse->json();

                                if ((string) data_get($apiJson, 'status') !== '0') {
                                    return [
                                        'status' => data_get($apiJson, 'status', 1),
                                        'data' => data_get($apiJson, 'data', []),
                                        'errors' => data_get($apiJson, 'errors', []),
                                        'messages' => data_get($apiJson, 'messages', []),
                                    ];
                                }

                                $errors[] = 'cPanel cpsess UAPI denied: ' . Str::limit($apiResponse->body(), 500);
                            } else {
                                $errors[] = 'cPanel cpsess UAPI HTTP ' . $apiResponse->status() . ': ' . Str::limit($apiResponse->body(), 500);
                            }
                        } else {
                            $errors[] = 'Unable to extract cpsess token from WHM session URL.';
                        }
                    } else {
                        $errors[] = 'WHM create_user_session denied: ' . Str::limit($sessionResponse->body(), 500);
                    }
                } else {
                    $errors[] = 'WHM create_user_session HTTP ' . $sessionResponse->status() . ': ' . Str::limit($sessionResponse->body(), 500);
                }
            } catch (\Throwable $e) {
                $errors[] = 'WHM create_user_session exception: ' . $e->getMessage();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Method 3: Direct cPanel UAPI using saved cPanel password
        |--------------------------------------------------------------------------
        */

        $cpanelPassword = $this->developerCpanelPassword($developer);

        if (!$cpanelPassword) {
            throw new \Exception(
                'Cannot access cPanel File Manager API. WHM API/session failed and no cPanel password is saved for user ' .
                $cpanelUser .
                '. Errors: ' . implode(' | ', $errors)
            );
        }

        try {
            $directUrl = 'https://' . $host . ':2083/execute/' . $module . '/' . $function;

            $directResponse = Http::withoutVerifying()
                ->timeout(60)
                ->acceptJson()
                ->withOptions([
                    'verify' => false,
                    'connect_timeout' => 20,
                ])
                ->withBasicAuth($cpanelUser, $cpanelPassword)
                ->get($directUrl, $params);

            if (!$directResponse->successful()) {
                throw new \Exception(
                    'Direct cPanel UAPI failed. HTTP ' .
                    $directResponse->status() .
                    ' - ' .
                    Str::limit($directResponse->body(), 800)
                );
            }

            $directJson = $directResponse->json();

            if ((string) data_get($directJson, 'status') === '0') {
                $apiErrors = data_get($directJson, 'errors')
                    ?: data_get($directJson, 'messages')
                    ?: 'Unknown cPanel UAPI error';

                if (is_array($apiErrors)) {
                    $apiErrors = implode(', ', array_filter($apiErrors));
                }

                throw new \Exception($apiErrors);
            }

            return [
                'status' => data_get($directJson, 'status', 1),
                'data' => data_get($directJson, 'data', []),
                'errors' => data_get($directJson, 'errors', []),
                'messages' => data_get($directJson, 'messages', []),
            ];
        } catch (\Throwable $e) {
            throw new \Exception(
                'Direct cPanel UAPI also failed for user ' .
                $cpanelUser .
                '. ' .
                $e->getMessage() .
                ' Previous errors: ' .
                implode(' | ', $errors)
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Saved cPanel Password
    |--------------------------------------------------------------------------
    */

    private function developerCpanelPassword(DeveloperUser $developer): ?string
    {
        $possibleColumns = [
            'cpanel_password',
            'temporary_password',
            'ssh_password',
            'password_plain',
        ];

        foreach ($possibleColumns as $column) {
            if (!Schema::hasColumn($developer->getTable(), $column)) {
                continue;
            }

            if (empty($developer->{$column})) {
                continue;
            }

            $value = (string) $developer->{$column};

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

    /*
    |--------------------------------------------------------------------------
    | Normalize list_files Response
    |--------------------------------------------------------------------------
    */

    private function normalizeListFilesResponse(array $result, string $dir): array
    {
        $data = $result['data'] ?? [];
        $items = [];

        if (isset($data['dirs']) || isset($data['files'])) {
            foreach (($data['dirs'] ?? []) as $item) {
                $name = $item['file']
                    ?? $item['name']
                    ?? basename($item['fullpath'] ?? '');

                if (!$name || $this->isBlockedName($name)) {
                    continue;
                }

                $items[] = [
                    'type' => 'dir',
                    'name' => $name,
                    'path' => $item['fullpath']
                        ?? $item['path']
                        ?? rtrim($dir, '/') . '/' . $name,
                    'size' => $item['humansize'] ?? '',
                    'bytes' => $item['size'] ?? 0,
                    'mime' => $item['mimetype'] ?? 'directory',
                    'modified' => $item['mtime'] ?? null,
                    'writable' => (bool) ($item['write'] ?? true),
                ];
            }

            foreach (($data['files'] ?? []) as $item) {
                $name = $item['file']
                    ?? $item['name']
                    ?? basename($item['fullpath'] ?? '');

                if (!$name || $this->isBlockedName($name)) {
                    continue;
                }

                $items[] = [
                    'type' => 'file',
                    'name' => $name,
                    'path' => $item['fullpath']
                        ?? $item['path']
                        ?? rtrim($dir, '/') . '/' . $name,
                    'size' => $item['humansize'] ?? '',
                    'bytes' => $item['size'] ?? 0,
                    'mime' => $item['mimetype'] ?? '',
                    'modified' => $item['mtime'] ?? null,
                    'writable' => (bool) ($item['write'] ?? true),
                ];
            }

            return $this->sortItems($items);
        }

        if (is_array($data)) {
            foreach ($data as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $name = $item['file']
                    ?? $item['name']
                    ?? basename($item['fullpath'] ?? $item['path'] ?? '');

                if (!$name || $this->isBlockedName($name)) {
                    continue;
                }

                $isDir = false;

                if (isset($item['type'])) {
                    $isDir = in_array(strtolower((string) $item['type']), ['dir', 'directory'], true);
                }

                if (isset($item['isdir'])) {
                    $isDir = (bool) $item['isdir'];
                }

                if (isset($item['mimetype']) && $item['mimetype'] === 'directory') {
                    $isDir = true;
                }

                $items[] = [
                    'type' => $isDir ? 'dir' : 'file',
                    'name' => $name,
                    'path' => $item['fullpath']
                        ?? $item['path']
                        ?? rtrim($dir, '/') . '/' . $name,
                    'size' => $item['humansize'] ?? '',
                    'bytes' => $item['size'] ?? 0,
                    'mime' => $item['mimetype'] ?? '',
                    'modified' => $item['mtime'] ?? null,
                    'writable' => (bool) ($item['write'] ?? true),
                ];
            }
        }

        return $this->sortItems($items);
    }

    private function sortItems(array $items): array
    {
        usort($items, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcasecmp($a['name'], $b['name']);
            }

            return $a['type'] === 'dir' ? -1 : 1;
        });

        return array_values($items);
    }

    /*
    |--------------------------------------------------------------------------
    | Server / Credentials
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Paths / Security
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    private function can(DeveloperUser $developer, string $permission): bool
    {
        if (!Schema::hasColumn($developer->getTable(), $permission)) {
            return true;
        }

        return (bool) ($developer->{$permission} ?? false);
    }

    /*
    |--------------------------------------------------------------------------
    | Blocked Files / Folders
    |--------------------------------------------------------------------------
    */

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
        ], true)
        || str_ends_with($name, '.sql')
        || str_ends_with($name, '.pem')
        || str_ends_with($name, '.key');
    }

    /*
    |--------------------------------------------------------------------------
    | Monaco Language Detection
    |--------------------------------------------------------------------------
    */

    private function languageForFile(string $file): string
    {
        $file = strtolower($file);

        if (str_ends_with($file, '.blade.php')) {
            return 'php';
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => 'php',
            'js', 'mjs', 'cjs' => 'javascript',
            'ts' => 'typescript',
            'css' => 'css',
            'scss' => 'scss',
            'sass' => 'scss',
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
            'env' => 'plaintext',
            default => 'plaintext',
        };
    }
}