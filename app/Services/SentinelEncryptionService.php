<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SentinelEncryptionService
{
    protected string $vaultPath;

    protected array $dangerousExtensions = [
        'php',
        'phtml',
        'phar',
        'cgi',
        'pl',
        'py',
        'sh',
        'bash',
        'exe',
        'bat',
        'cmd',
        'js',
        'jar',
        'asp',
        'aspx',
        'jsp',
    ];

    protected array $sensitiveNames = [
        '.env',
        'wp-config.php',
        'config.php',
        'configuration.php',
        'database.php',
        'settings.php',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'yarn.lock',
        'backup.sql',
        'database.sql',
        'dump.sql',
        'db.sql',
        'id_rsa',
        'id_dsa',
        'private.key',
        'server.key',
        'ssl.key',
    ];

    protected array $sensitivePatterns = [
        'APP_KEY=',
        'APP_ENV=',
        'APP_DEBUG=',
        'DB_PASSWORD=',
        'DB_USERNAME=',
        'DB_DATABASE=',
        'MYSQL_PASSWORD',
        'POSTGRES_PASSWORD',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_ACCESS_KEY_ID',
        'MAIL_PASSWORD',
        'API_TOKEN',
        'SECRET_KEY',
        'PRIVATE KEY',
        'BEGIN RSA PRIVATE KEY',
        'BEGIN OPENSSH PRIVATE KEY',
        'password',
        'passwd',
        'auth_token',
        'bearer ',
    ];

    public function __construct()
    {
        $this->vaultPath = storage_path('app/sentinel-vault');

        if (!is_dir($this->vaultPath)) {
            mkdir($this->vaultPath, 0750, true);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TEXT ENCRYPTION
    |--------------------------------------------------------------------------
    */

    public function encryptText(string $plainText): string
    {
        return Crypt::encryptString($plainText);
    }

    public function decryptText(string $encryptedText): string
    {
        return Crypt::decryptString($encryptedText);
    }

    public function encryptArray(array $data): string
    {
        return Crypt::encryptString(json_encode($data, JSON_PRETTY_PRINT));
    }

    public function decryptArray(string $payload): array
    {
        $json = Crypt::decryptString($payload);

        return json_decode($json, true) ?: [];
    }

    /*
    |--------------------------------------------------------------------------
    | FILE VAULT ENCRYPTION
    |--------------------------------------------------------------------------
    */

    public function encryptUploadedFile(UploadedFile $file): string
    {
        $originalName = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension());

        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw new \Exception('Unable to read uploaded file.');
        }

        $analysis = $this->analyzeFileSecurity(
            filename: $originalName,
            content: $content,
            extension: $extension
        );

        if ($analysis['blocked']) {
            throw new \Exception('SentinelCore blocked this file: ' . implode(', ', $analysis['reasons']));
        }

        $hash = hash('sha256', $content);

        $payload = [
            'original_name' => $originalName,
            'safe_name' => $safeName,
            'extension' => $extension,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sha256' => $hash,
            'risk_score' => $analysis['risk_score'],
            'risk_level' => $analysis['risk_level'],
            'reasons' => $analysis['reasons'],
            'encrypted_at' => now()->toDateTimeString(),
            'content' => base64_encode($content),
        ];

        $encrypted = Crypt::encryptString(json_encode($payload));

        $fileName = now()->format('Ymd_His') . '_' . $safeName . '.' . $extension . '.sentinel.encrypted';

        $path = $this->vaultPath . '/' . $fileName;

        file_put_contents($path, $encrypted);

        chmod($path, 0640);

        return 'sentinel-vault/' . $fileName;
    }

    public function decryptVaultFile(string $relativePath): string
    {
        $path = storage_path('app/' . ltrim($relativePath, '/'));

        if (!file_exists($path)) {
            throw new \Exception('Encrypted file not found.');
        }

        $encrypted = file_get_contents($path);

        if ($encrypted === false) {
            throw new \Exception('Unable to read encrypted file.');
        }

        $json = Crypt::decryptString($encrypted);
        $payload = json_decode($json, true);

        if (!is_array($payload) || empty($payload['content'])) {
            throw new \Exception('Invalid SentinelCore encrypted file.');
        }

        $content = base64_decode($payload['content']);

        if (!empty($payload['sha256'])) {
            $currentHash = hash('sha256', $content);

            if (!hash_equals($payload['sha256'], $currentHash)) {
                throw new \Exception('File integrity check failed. File may be corrupted or tampered.');
            }
        }

        return $content;
    }

    public function inspectVaultFile(string $relativePath): array
    {
        $path = storage_path('app/' . ltrim($relativePath, '/'));

        if (!file_exists($path)) {
            throw new \Exception('Encrypted file not found.');
        }

        $encrypted = file_get_contents($path);

        if ($encrypted === false) {
            throw new \Exception('Unable to read encrypted file.');
        }

        $json = Crypt::decryptString($encrypted);
        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            throw new \Exception('Invalid SentinelCore encrypted file.');
        }

        unset($payload['content']);

        return $payload;
    }

    /*
    |--------------------------------------------------------------------------
    | FILE SECURITY AI-STYLE ANALYSIS
    |--------------------------------------------------------------------------
    */

    public function analyzeFileSecurity(string $filename, string $content, ?string $extension = null): array
    {
        $extension = strtolower($extension ?: pathinfo($filename, PATHINFO_EXTENSION));
        $lowerName = strtolower($filename);
        $lowerContent = strtolower(substr($content, 0, 250000));

        $score = 0;
        $reasons = [];
        $blocked = false;

        if (in_array($extension, $this->dangerousExtensions, true)) {
            $score += 35;
            $reasons[] = "Executable or server-side file extension detected: {$extension}";
        }

        foreach ($this->sensitiveNames as $sensitiveName) {
            if ($lowerName === strtolower($sensitiveName) || str_contains($lowerName, strtolower($sensitiveName))) {
                $score += 30;
                $reasons[] = "Sensitive filename detected: {$sensitiveName}";
                break;
            }
        }

        foreach ($this->sensitivePatterns as $pattern) {
            if (str_contains($lowerContent, strtolower($pattern))) {
                $score += 15;
                $reasons[] = "Sensitive content pattern detected: {$pattern}";
            }
        }

        if (str_contains($lowerContent, '<?php')) {
            $score += 25;
            $reasons[] = 'PHP code detected inside file.';
        }

        if (preg_match('/eval\s*\(|base64_decode\s*\(|shell_exec\s*\(|passthru\s*\(|system\s*\(|exec\s*\(/i', $content)) {
            $score += 35;
            $reasons[] = 'Dangerous PHP execution function detected.';
        }

        if (preg_match('/<script\b[^>]*>.*?<\/script>/is', $content)) {
            $score += 15;
            $reasons[] = 'JavaScript block detected.';
        }

        if (preg_match('/(DROP TABLE|UNION SELECT|INSERT INTO|INFORMATION_SCHEMA|LOAD_FILE)/i', $content)) {
            $score += 25;
            $reasons[] = 'SQL dump or SQL injection-style pattern detected.';
        }

        if ($score >= 80) {
            $blocked = true;
            $riskLevel = 'critical';
        } elseif ($score >= 55) {
            $riskLevel = 'high';
        } elseif ($score >= 30) {
            $riskLevel = 'medium';
        } else {
            $riskLevel = 'low';
        }

        return [
            'blocked' => $blocked,
            'risk_score' => min($score, 100),
            'risk_level' => $riskLevel,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CPANEL / WHM USER PROTECTION ANALYSIS
    |--------------------------------------------------------------------------
    */

    public function analyzeCpanelUsers(array $accounts): array
    {
        $results = [];

        foreach ($accounts as $account) {
            $username = $account['user'] ?? $account['username'] ?? null;
            $domain = $account['domain'] ?? null;
            $email = $account['email'] ?? null;
            $ip = $account['ip'] ?? null;
            $plan = $account['plan'] ?? null;
            $diskUsed = $account['diskused'] ?? null;
            $suspended = $account['suspended'] ?? false;
            $owner = $account['owner'] ?? null;

            $score = 0;
            $risks = [];
            $recommendations = [];

            if (!$username) {
                $score += 20;
                $risks[] = 'Account username missing.';
            }

            if ($username && preg_match('/^(test|demo|admin|user|backup|root)$/i', $username)) {
                $score += 20;
                $risks[] = 'Weak or generic cPanel username.';
                $recommendations[] = 'Use unique usernames for cPanel accounts.';
            }

            if (!$domain) {
                $score += 15;
                $risks[] = 'No primary domain detected.';
            }

            if (!$email) {
                $score += 10;
                $risks[] = 'No contact email set.';
                $recommendations[] = 'Add customer email for alerts.';
            }

            if (!empty($suspended) && $suspended !== '0') {
                $score += 10;
                $risks[] = 'Account is suspended.';
            }

            if ($diskUsed && $this->extractDiskMb($diskUsed) > 10000) {
                $score += 10;
                $risks[] = 'High disk usage account.';
                $recommendations[] = 'Enable account-level backup and malware scan.';
            }

            if ($owner && strtolower($owner) === 'root') {
                $score += 5;
                $risks[] = 'Account owned by root reseller.';
            }

            $results[] = [
                'panel' => 'cpanel',
                'username' => $username,
                'domain' => $domain,
                'email' => $email,
                'ip' => $ip,
                'plan' => $plan,
                'disk_used' => $diskUsed,
                'risk_score' => min($score, 100),
                'risk_level' => $this->riskLevel($score),
                'risks' => $risks,
                'recommendations' => $recommendations ?: [
                    'Keep CMS, plugins and passwords updated.',
                    'Enable account backups and malware scanning.',
                ],
            ];
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | PLESK USER PROTECTION ANALYSIS
    |--------------------------------------------------------------------------
    */

    public function analyzePleskUsers(array $users): array
    {
        $results = [];

        foreach ($users as $user) {
            $username = $user['login'] ?? $user['username'] ?? $user['name'] ?? null;
            $email = $user['email'] ?? null;
            $role = $user['role'] ?? $user['type'] ?? null;
            $status = $user['status'] ?? null;
            $domains = $user['domains'] ?? [];

            $score = 0;
            $risks = [];
            $recommendations = [];

            if (!$username) {
                $score += 20;
                $risks[] = 'Plesk username missing.';
            }

            if ($username && preg_match('/^(admin|test|demo|user|root)$/i', $username)) {
                $score += 25;
                $risks[] = 'Generic Plesk username detected.';
            }

            if (!$email) {
                $score += 10;
                $risks[] = 'No email assigned to Plesk user.';
            }

            if ($role && str_contains(strtolower($role), 'admin')) {
                $score += 20;
                $risks[] = 'User has administrative privileges.';
                $recommendations[] = 'Review admin privileges and apply least privilege.';
            }

            if ($status && !in_array(strtolower((string) $status), ['active', 'enabled', '0'], true)) {
                $score += 10;
                $risks[] = 'User status is not active/enabled.';
            }

            if (is_array($domains) && count($domains) > 10) {
                $score += 10;
                $risks[] = 'User controls many domains.';
            }

            $results[] = [
                'panel' => 'plesk',
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'domains_count' => is_array($domains) ? count($domains) : 0,
                'risk_score' => min($score, 100),
                'risk_level' => $this->riskLevel($score),
                'risks' => $risks,
                'recommendations' => $recommendations ?: [
                    'Enable strong password policy.',
                    'Use least privilege for Plesk users.',
                    'Enable backups and security scanning.',
                ],
            ];
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD / SECRET STRENGTH ANALYSIS
    |--------------------------------------------------------------------------
    */

    public function analyzePasswordStrength(?string $password): array
    {
        $password = (string) $password;
        $score = 0;
        $risks = [];

        if (strlen($password) >= 12) {
            $score += 25;
        } else {
            $risks[] = 'Password is shorter than 12 characters.';
        }

        if (preg_match('/[A-Z]/', $password)) {
            $score += 15;
        } else {
            $risks[] = 'Missing uppercase character.';
        }

        if (preg_match('/[a-z]/', $password)) {
            $score += 15;
        } else {
            $risks[] = 'Missing lowercase character.';
        }

        if (preg_match('/[0-9]/', $password)) {
            $score += 15;
        } else {
            $risks[] = 'Missing number.';
        }

        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 20;
        } else {
            $risks[] = 'Missing special character.';
        }

        if (preg_match('/(password|admin|root|123456|qwerty|welcome|webscepts)/i', $password)) {
            $score -= 30;
            $risks[] = 'Common or predictable word detected.';
        }

        $score = max(0, min($score, 100));

        return [
            'score' => $score,
            'risk_level' => $this->riskLevel(100 - $score),
            'risks' => $risks,
            'recommendations' => [
                'Use at least 14 characters.',
                'Use uppercase, lowercase, numbers and symbols.',
                'Do not reuse server, cPanel, WHM, Plesk or database passwords.',
                'Rotate passwords regularly.',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SERVER / WEBSITE HARDENING RECOMMENDATIONS
    |--------------------------------------------------------------------------
    */

    public function generateHardeningPlan(array $context = []): array
    {
        $panel = strtolower($context['panel'] ?? 'general');
        $framework = strtolower($context['framework'] ?? 'general');

        $plan = [
            'global' => [
                'Force HTTPS and redirect HTTP to HTTPS.',
                'Disable directory listing.',
                'Protect .env, config, backup and SQL files from public access.',
                'Enable firewall and only allow required ports.',
                'Use strong admin passwords and rotate credentials.',
                'Enable daily backups and remote backup sync.',
                'Use security headers: HSTS, CSP, X-Frame-Options, X-Content-Type-Options.',
                'Disable public debug pages and stack traces.',
            ],
            'panel' => [],
            'framework' => [],
            'database' => [
                'Never expose database ports publicly.',
                'Restrict MySQL/PostgreSQL to localhost or private network.',
                'Encrypt database credentials in application config.',
                'Do not keep .sql backups in public folders.',
                'Use least-privilege database users.',
            ],
        ];

        if ($panel === 'cpanel' || $panel === 'whm') {
            $plan['panel'] = [
                'Enable cPHulk brute force protection.',
                'Enable ModSecurity and OWASP rules.',
                'Disable compilers for untrusted users.',
                'Enable jailed shell or disable shell access.',
                'Check suspicious cron jobs for every cPanel user.',
                'Scan public_html for malware PHP files.',
                'Enable account-level backup selection.',
            ];
        }

        if ($panel === 'plesk') {
            $plan['panel'] = [
                'Enable Fail2Ban.',
                'Enable Plesk Firewall.',
                'Use WordPress Toolkit security hardening.',
                'Disable unused subscriptions and users.',
                'Review admin users and apply least privilege.',
                'Check scheduled tasks for suspicious scripts.',
            ];
        }

        if ($framework === 'wordpress') {
            $plan['framework'] = [
                'Keep WordPress core, plugins and themes updated.',
                'Disable XML-RPC if not required.',
                'Block wp-config.php access.',
                'Disable file editor in WordPress admin.',
                'Remove unused plugins and themes.',
                'Use WAF and login rate limiting.',
            ];
        }

        if ($framework === 'laravel') {
            $plan['framework'] = [
                'Set APP_DEBUG=false.',
                'Protect .env and storage/logs.',
                'Run config:cache and route:cache in production.',
                'Keep composer dependencies updated.',
                'Ensure public points only to Laravel public directory.',
                'Encrypt API tokens and credentials.',
            ];
        }

        if ($framework === 'node' || $framework === 'nodejs') {
            $plan['framework'] = [
                'Disable stack traces in production.',
                'Use Helmet security headers.',
                'Protect .env and package files.',
                'Rate-limit APIs and login endpoints.',
                'Run npm audit and update dependencies.',
                'Use PM2/systemd with least privilege user.',
            ];
        }

        if ($framework === 'angular' || $framework === 'react' || $framework === 'vue') {
            $plan['framework'] = [
                'Do not place secrets in frontend environment files.',
                'Disable production source maps.',
                'Use strong Content-Security-Policy.',
                'Validate all API access server-side.',
                'Protect API tokens and session cookies.',
            ];
        }

        return $plan;
    }

    /*
    |--------------------------------------------------------------------------
    | SMALL HELPERS
    |--------------------------------------------------------------------------
    */

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }

    private function extractDiskMb(?string $disk): float
    {
        $disk = trim((string) $disk);

        if ($disk === '') {
            return 0;
        }

        if (preg_match('/([\d.]+)\s*G/i', $disk, $m)) {
            return (float) $m[1] * 1024;
        }

        if (preg_match('/([\d.]+)\s*M/i', $disk, $m)) {
            return (float) $m[1];
        }

        if (preg_match('/([\d.]+)\s*K/i', $disk, $m)) {
            return (float) $m[1] / 1024;
        }

        if (is_numeric($disk)) {
            return (float) $disk;
        }

        return 0;
    }
}