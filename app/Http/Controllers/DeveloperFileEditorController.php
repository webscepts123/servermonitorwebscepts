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
                'message' => $this->cleanErrorMessage($e->getMessage()),
            ], 500);
        }
    }

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
                'message' => $this->cleanErrorMessage($e->getMessage()),
            ], 500);
        }
    }

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
                'message' => $this->cleanErrorMessage($e->getMessage()),
            ], 500);
        }
    }

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
                'message' => $this->cleanErrorMessage($e->getMessage()),
            ], 500);
        }
    }

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
        $servers = $this->candidateServers($developer);
        $cpanelUser = trim((string) ($developer->cpanel_username ?: $developer->ssh_username ?: ''));

        if (!$cpanelUser) {
            throw new \Exception('Developer cPanel username is missing.');
        }

        $allErrors = [];

        foreach ($servers as $server) {
            $credentials = $this->serverCredentials($server);

            $host = $this->cleanHost((string) ($credentials['host'] ?? ''));
            $whmUsername = trim((string) ($credentials['username'] ?? 'root'));
            $whmPassword = trim((string) ($credentials['password'] ?? ''));
            $whmToken = trim((string) ($credentials['token'] ?? ''));

            if (!$host) {
                $allErrors[] = 'Server #' . $server->id . ': WHM/cPanel host is missing.';
                continue;
            }

            $serverLabel = ($server->name ?? 'Server') . ' #' . $server->id . ' (' . $host . ')';

            /*
            |--------------------------------------------------------------------------
            | Method 1: WHM cPanel API with WHM API token/password.
            |--------------------------------------------------------------------------
            */
            try {
                $query = array_merge([
                    'cpanel_jsonapi_user' => $cpanelUser,
                    'cpanel_jsonapi_apiversion' => 3,
                    'cpanel_jsonapi_module' => $module,
                    'cpanel_jsonapi_func' => $function,
                ], $params);

                $response = $this->whmApiRequest(
                    $host,
                    $whmUsername,
                    $whmPassword,
                    $whmToken,
                    '/json-api/cpanel',
                    $query
                );

                if ($response && $response->successful()) {
                    $json = $response->json();

                    $result = data_get($json, 'cpanelresult.result')
                        ?: data_get($json, 'result')
                        ?: [];

                    $status = data_get($result, 'status');

                    if ((string) $status !== '0' && !empty($result)) {
                        return is_array($result) ? $result : [];
                    }

                    $reason = data_get($json, 'cpanelresult.error')
                        ?: data_get($json, 'cpanelresult.data.reason')
                        ?: data_get($json, 'metadata.reason')
                        ?: 'Access denied';

                    $allErrors[] = $serverLabel . ': WHM cPanel API denied - ' . $reason;
                } elseif ($response) {
                    $allErrors[] = $serverLabel . ': WHM cPanel API HTTP ' . $response->status() . ' - ' . Str::limit($response->body(), 300);
                } else {
                    $allErrors[] = $serverLabel . ': WHM token/password missing.';
                }
            } catch (\Throwable $e) {
                $allErrors[] = $serverLabel . ': WHM cPanel API exception - ' . $e->getMessage();
            }

            /*
            |--------------------------------------------------------------------------
            | Method 2: WHM create_user_session with WHM API token/password.
            |--------------------------------------------------------------------------
            */
            try {
                $sessionResponse = $this->whmApiRequest(
                    $host,
                    $whmUsername,
                    $whmPassword,
                    $whmToken,
                    '/json-api/create_user_session',
                    [
                        'api.version' => 1,
                        'user' => $cpanelUser,
                        'service' => 'cpaneld',
                        'app' => 'FileManager',
                    ]
                );

                if ($sessionResponse && $sessionResponse->successful()) {
                    $sessionJson = $sessionResponse->json();
                    $sessionResult = data_get($sessionJson, 'metadata.result');

                    if ((string) $sessionResult !== '0') {
                        $sessionUrl = data_get($sessionJson, 'data.url');

                        if ($sessionUrl && preg_match('#/(cpsess[0-9]+)/#', $sessionUrl, $matches)) {
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
                                $matches[1],
                                $cookieJar,
                                $module,
                                $function,
                                $params
                            );
                        }

                        $allErrors[] = $serverLabel . ': WHM session URL missing cpsess token.';
                    } else {
                        $reason = data_get($sessionJson, 'metadata.reason')
                            ?: data_get($sessionJson, 'cpanelresult.error')
                            ?: data_get($sessionJson, 'cpanelresult.data.reason')
                            ?: 'Access denied';

                        $allErrors[] = $serverLabel . ': WHM create_user_session denied - ' . $reason;
                    }
                } elseif ($sessionResponse) {
                    $allErrors[] = $serverLabel . ': WHM create_user_session HTTP ' . $sessionResponse->status() . ' - ' . Str::limit($sessionResponse->body(), 300);
                } else {
                    $allErrors[] = $serverLabel . ': WHM token/password missing.';
                }
            } catch (\Throwable $e) {
                $allErrors[] = $serverLabel . ': WHM create_user_session exception - ' . $e->getMessage();
            }

            /*
            |--------------------------------------------------------------------------
            | Method 3: Direct cPanel API token.
            |--------------------------------------------------------------------------
            */
            $cpanelToken = $this->developerCpanelToken($developer);

            if ($cpanelToken) {
                try {
                    $directTokenUrl = 'https://' . $host . ':2083/execute/' . $module . '/' . $function;

                    $tokenResponse = $this->cpanelRequest(
                        Http::withoutVerifying()
                            ->timeout(60)
                            ->acceptJson()
                            ->withOptions([
                                'verify' => false,
                                'connect_timeout' => 20,
                            ])
                            ->withHeaders([
                                'Authorization' => 'cpanel ' . $cpanelUser . ':' . $cpanelToken,
                            ]),
                        $directTokenUrl,
                        $params
                    );

                    if ($tokenResponse->successful()) {
                        $tokenJson = $tokenResponse->json();

                        if ((string) data_get($tokenJson, 'status') !== '0') {
                            return [
                                'status' => data_get($tokenJson, 'status', 1),
                                'data' => data_get($tokenJson, 'data', []),
                                'errors' => data_get($tokenJson, 'errors', []),
                                'messages' => data_get($tokenJson, 'messages', []),
                            ];
                        }
                    }

                    $allErrors[] = $serverLabel . ': Direct cPanel API token failed HTTP ' . $tokenResponse->status();
                } catch (\Throwable $e) {
                    $allErrors[] = $serverLabel . ': Direct cPanel API token exception - ' . $e->getMessage();
                }
            } else {
                $allErrors[] = $serverLabel . ': No cPanel API token saved for developer.';
            }

            /*
            |--------------------------------------------------------------------------
            | Method 4: Direct cPanel Basic Auth.
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

                    $allErrors[] = $serverLabel . ': Direct cPanel Basic Auth failed HTTP ' . $directResponse->status();
                } catch (\Throwable $e) {
                    $allErrors[] = $serverLabel . ': Direct cPanel Basic Auth exception - ' . $e->getMessage();
                }

                /*
                |--------------------------------------------------------------------------
                | Method 5: Direct cPanel login session.
                |--------------------------------------------------------------------------
                */
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

                    if ($loginResponse->successful()) {
                        $loginJson = $loginResponse->json();

                        if ((int) data_get($loginJson, 'status') === 1) {
                            $securityToken = data_get($loginJson, 'security_token');

                            if ($securityToken) {
                                return $this->callCpanelSessionUapi(
                                    $host,
                                    trim($securityToken, '/'),
                                    $cookieJar,
                                    $module,
                                    $function,
                                    $params
                                );
                            }

                            $allErrors[] = $serverLabel . ': Direct cPanel login missing security_token.';
                        } else {
                            $reason = data_get($loginJson, 'message')
                                ?: data_get($loginJson, 'reason')
                                ?: 'Invalid cPanel login.';

                            $allErrors[] = $serverLabel . ': Direct cPanel login denied - ' . $reason;
                        }
                    } else {
                        $allErrors[] = $serverLabel . ': Direct cPanel login failed HTTP ' . $loginResponse->status();
                    }
                } catch (\Throwable $e) {
                    $allErrors[] = $serverLabel . ': Direct cPanel login session exception - ' . $e->getMessage();
                }
            } else {
                $allErrors[] = $serverLabel . ': No real cPanel password saved.';
            }
        }

        throw new \Exception(
            'Cannot access cPanel File Manager API for user ' .
            $cpanelUser .
            '. The WHM API token/password cannot create a cPanel session, or no valid cPanel token/password is saved. Errors: ' .
            implode(' | ', array_slice($allErrors, -12))
        );
    }

    private function whmApiRequest(
        string $host,
        ?string $username,
        ?string $password,
        ?string $token,
        string $path,
        array $params = []
    ) {
        $username = trim((string) $username) ?: 'root';
        $password = trim((string) $password);
        $token = trim((string) $token);

        $request = Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->withOptions([
                'verify' => false,
                'connect_timeout' => 20,
            ]);

        if ($token) {
            $request = $request->withHeaders([
                'Authorization' => 'whm ' . $username . ':' . $token,
            ]);
        } elseif ($password) {
            $request = $request->withBasicAuth($username, $password);
        } else {
            return null;
        }

        return $request->get('https://' . $host . ':2087' . $path, $params);
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
            throw new \Exception('cPanel session UAPI failed HTTP ' . $apiResponse->status());
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
            return $pendingRequest->asForm()->post($url, $params);
        }

        return $pendingRequest->get($url, $params);
    }

    private function developerCpanelPassword(DeveloperUser $developer): ?string
    {
        $possibleColumns = [
            'cpanel_password',
            'cpanel_plain_password',
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

            if (Str::startsWith($value, ['$2y$', '$argon2i$', '$argon2id$'])) {
                continue;
            }

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

    private function developerCpanelToken(DeveloperUser $developer): ?string
    {
        $possibleColumns = [
            'cpanel_api_token',
            'cpanel_token',
            'api_token',
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

    private function candidateServers(DeveloperUser $developer)
    {
        $servers = collect();

        if (Schema::hasColumn($developer->getTable(), 'server_id') && !empty($developer->server_id)) {
            $server = Server::find($developer->server_id);

            if ($server) {
                $servers->push($server);
            }
        }

        if ($servers->isEmpty()) {
            throw new \Exception(
                'Developer server_id is missing or invalid. Assign the correct server to this developer account.'
            );
        }

        return $servers;
    }

    private function serverForDeveloper(DeveloperUser $developer): Server
    {
        return $this->candidateServers($developer)->first();
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

        $token = $this->serverSecret($server, [
            'whm_api_token',
            'whm_token',
            'api_token',
            'access_hash',
        ]);

        return [
            'host' => $host ? trim($host) : null,
            'username' => trim($username ?: 'root'),
            'password' => $password ? trim($password) : null,
            'token' => $token ? trim($token) : null,
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

        return Str::limit(trim($message), 1400);
    }
}