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

    public function tree(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->can($developer, 'can_view_files')) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission to view files.'], 403);
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

            return response()->json([
                'ok' => true,
                'dir' => $dir,
                'parent' => $this->parentDir($developer, $dir),
                'items' => $this->normalizeListFilesResponse($result, $dir),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $this->cleanErrorMessage($e->getMessage()),
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
            return response()->json(['ok' => false, 'message' => 'You do not have permission to read files.'], 403);
        }

        try {
            $path = $this->safePath($developer, $request->input('path'));

            if ($this->isBlockedFile($path)) {
                return response()->json(['ok' => false, 'message' => 'This file is protected and cannot be opened.'], 403);
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
                'message' => $this->cleanErrorMessage($e->getMessage()),
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
            return response()->json(['ok' => false, 'message' => 'You do not have permission to save files.'], 403);
        }

        try {
            $path = $this->safePath($developer, $request->input('path'));
            $content = (string) $request->input('content', '');

            if ($this->isBlockedFile($path)) {
                return response()->json(['ok' => false, 'message' => 'This file is protected and cannot be saved.'], 403);
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
                'message' => $this->cleanErrorMessage($e->getMessage()),
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
            return response()->json(['ok' => false, 'message' => 'You do not have permission to create files.'], 403);
        }

        try {
            $dir = $this->safePath(
                $developer,
                $request->input('dir') ?: $this->projectRoot($developer)
            );

            $name = trim((string) $request->input('name'));

            if (!$name || str_contains($name, '/') || str_contains($name, '\\')) {
                return response()->json(['ok' => false, 'message' => 'Invalid file name.'], 422);
            }

            $path = rtrim($dir, '/') . '/' . $name;

            if ($this->isBlockedFile($path)) {
                return response()->json(['ok' => false, 'message' => 'This file is protected and cannot be created.'], 403);
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
                'message' => $this->cleanErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    public function createFolder(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->can($developer, 'can_edit_files')) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission to create folders.'], 403);
        }

        try {
            $dir = $this->safePath(
                $developer,
                $request->input('dir') ?: $this->projectRoot($developer)
            );

            $name = trim((string) $request->input('name'));

            if (!$name || str_contains($name, '/') || str_contains($name, '\\')) {
                return response()->json(['ok' => false, 'message' => 'Invalid folder name.'], 422);
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
                'message' => $this->cleanErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    public function deleteFile(Request $request)
    {
        $developer = $this->developer();

        if (!$developer) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$this->can($developer, 'can_delete_files')) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission to delete files.'], 403);
        }

        try {
            $path = $this->safePath($developer, $request->input('path'));

            if ($this->isBlockedFile($path)) {
                return response()->json(['ok' => false, 'message' => 'This file is protected and cannot be deleted.'], 403);
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
                'message' => $this->cleanErrorMessage($e->getMessage()),
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

        if (!$cpanelUser) {
            throw new \Exception('Developer cPanel username is missing.');
        }

        $errors = [];

        /*
        |--------------------------------------------------------------------------
        | Method 1: WHM /json-api/cpanel
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

                    $errors[] = 'WHM cPanel API denied';
                } else {
                    $errors[] = 'WHM cPanel API HTTP ' . $response->status();
                }
            } catch (\Throwable $e) {
                $errors[] = 'WHM cPanel API exception: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'WHM username/password missing.';
        }

        /*
        |--------------------------------------------------------------------------
        | Method 2: WHM create_user_session
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

                            return $this->callCpanelSessionUapi(
                                $host,
                                $cpsess,
                                $cookieJar,
                                $module,
                                $function,
                                $params
                            );
                        }

                        $errors[] = 'Unable to extract cpsess token from WHM session URL.';
                    } else {
                        $errors[] = 'WHM create_user_session denied';
                    }
                } else {
                    $errors[] = 'WHM create_user_session HTTP ' . $sessionResponse->status();
                }
            } catch (\Throwable $e) {
                $errors[] = 'WHM create_user_session exception: ' . $e->getMessage();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Method 3: Direct cPanel Basic Auth
        |--------------------------------------------------------------------------
        */

        $cpanelPassword = $this->developerCpanelPassword($developer);

        if ($cpanelPassword) {
            try {
                $directUrl = 'https://' . $host . ':2083/execute/' . $module . '/' . $function;

                $directResponse = $this->cpanelRequest(
                    Http::withoutVerifying()
                        ->timeout(60)
                        ->acceptJson()
                        ->withOptions([
                            'verify' => false,
                            'connect_timeout' => 20,
                        ])
                        ->withBasicAuth($cpanelUser, $cpanelPassword),
                    $directUrl,
                    $params
                );

                if ($directResponse->successful()) {
                    $directJson = $directResponse->json();

                    if ((string) data_get($directJson, 'status') !== '0') {
                        return [
                            'status' => data_get($directJson, 'status', 1),
                            'data' => data_get($directJson, 'data', []),
                            'errors' => data_get($directJson, 'errors', []),
                            'messages' => data_get($directJson, 'messages', []),
                        ];
                    }
                }

                $errors[] = 'Direct cPanel Basic Auth failed HTTP ' . $directResponse->status();
            } catch (\Throwable $e) {
                $errors[] = 'Direct cPanel Basic Auth exception: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'No real cPanel password saved.';
        }

        /*
        |--------------------------------------------------------------------------
        | Method 4: Direct cPanel login session.
        |--------------------------------------------------------------------------
        | This fixes HTTP 401 cPanel Login page when Basic Auth is blocked.
        |--------------------------------------------------------------------------
        */

        if ($cpanelPassword) {
            try {
                $cookieJar = new CookieJar();

                $loginResponse = Http::withoutVerifying()
                    ->timeout(60)
                    ->asForm()
                    ->acceptJson()
                    ->withOptions([
                        'verify' => false,
                        'cookies' => $cookieJar,
                        'allow_redirects' => false,
                        'connect_timeout' => 20,
                    ])
                    ->post('https://' . $host . ':2083/login/?login_only=1', [
                        'user' => $cpanelUser,
                        'pass' => $cpanelPassword,
                    ]);

                if (!$loginResponse->successful()) {
                    $errors[] = 'Direct cPanel login failed HTTP ' . $loginResponse->status();
                    throw new \Exception('Direct cPanel login failed.');
                }

                $loginJson = $loginResponse->json();

                if ((int) data_get($loginJson, 'status') !== 1) {
                    $errors[] = 'Direct cPanel login denied: ' . (
                        data_get($loginJson, 'message')
                        ?: data_get($loginJson, 'reason')
                        ?: 'Invalid cPanel username/password.'
                    );

                    throw new \Exception('Direct cPanel login denied.');
                }

                $securityToken = data_get($loginJson, 'security_token');

                if (!$securityToken) {
                    $errors[] = 'Direct cPanel login did not return security_token.';
                    throw new \Exception('Missing security_token.');
                }

                $securityToken = '/' . ltrim($securityToken, '/');

                return $this->callCpanelSessionUapi(
                    $host,
                    trim($securityToken, '/'),
                    $cookieJar,
                    $module,
                    $function,
                    $params
                );
            } catch (\Throwable $e) {
                $errors[] = 'Direct cPanel login session exception: ' . $e->getMessage();
            }
        }

        throw new \Exception(
            'Cannot access cPanel File Manager API for user ' .
            $cpanelUser .
            '. The saved password is not accepted by cPanel or WHM access is limited. Errors: ' .
            implode(' | ', $errors)
        );
    }

    private function callCpanelSessionUapi(
        string $host,
        string $token,
        CookieJar $cookieJar,
        string $module,
        string $function,
        array $params = []
    ): array {
        $token = trim($token, '/');
        $apiUrl = 'https://' . $host . ':2083/' . $token . '/execute/' . $module . '/' . $function;

        $apiResponse = $this->cpanelRequest(
            Http::withoutVerifying()
                ->timeout(60)
                ->acceptJson()
                ->withOptions([
                    'verify' => false,
                    'cookies' => $cookieJar,
                    'connect_timeout' => 20,
                ]),
            $apiUrl,
            $params
        );

        if (!$apiResponse->successful()) {
            throw new \Exception(
                'cPanel session UAPI failed HTTP ' .
                $apiResponse->status()
            );
        }

        $apiJson = $apiResponse->json();

        if ((string) data_get($apiJson, 'status') === '0') {
            $errors = data_get($apiJson, 'errors')
                ?: data_get($apiJson, 'messages')
                ?: 'Unknown cPanel UAPI error';

            if (is_array($errors)) {
                $errors = implode(', ', array_filter($errors));
            }

            throw new \Exception($errors);
        }

        return [
            'status' => data_get($apiJson, 'status', 1),
            'data' => data_get($apiJson, 'data', []),
            'errors' => data_get($apiJson, 'errors', []),
            'messages' => data_get($apiJson, 'messages', []),
        ];
    }

    private function cpanelRequest($pendingRequest, string $url, array $params)
    {
        if (array_key_exists('content', $params)) {
            return $pendingRequest
                ->asForm()
                ->post($url, $params);
        }

        return $pendingRequest->get($url, $params);
    }

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
                    'path' => $item['fullpath'] ?? $item['path'] ?? rtrim($dir, '/') . '/' . $name,
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
                    'path' => $item['fullpath'] ?? $item['path'] ?? rtrim($dir, '/') . '/' . $name,
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
                    'path' => $item['fullpath'] ?? $item['path'] ?? rtrim($dir, '/') . '/' . $name,
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
        ], true)
        || str_ends_with($name, '.sql')
        || str_ends_with($name, '.pem')
        || str_ends_with($name, '.key');
    }

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
            'scss', 'sass' => 'scss',
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

    private function cleanErrorMessage(string $message): string
    {
        $message = strip_tags($message);
        $message = html_entity_decode($message);
        $message = preg_replace('/\s+/', ' ', $message);

        return Str::limit(trim($message), 900);
    }
}