<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SentinelWebScannerService
{
    public function scan(string $url): array
    {
        $url = $this->normalizeUrl($url);
        $domain = parse_url($url, PHP_URL_HOST);
        $ip = $domain ? gethostbyname($domain) : null;

        $started = microtime(true);

        $response = null;
        $body = '';
        $headers = [];
        $status = null;

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Webscepts-SentinelCore/1.0 Security Scanner',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->withoutVerifying()
                ->get($url);

            $status = $response->status();
            $body = (string) $response->body();
            $headers = $response->headers();
        } catch (\Throwable $e) {
            $body = '';
            $headers = [];
            $status = null;
        }

        $responseTime = round((microtime(true) - $started) * 1000, 2);

        $ssl = $this->checkSsl($domain);
        $technologies = $this->detectTechnologies($url, $body, $headers);
        $headerCheck = $this->checkSecurityHeaders($headers);
        $exposedFiles = $this->checkExposedFiles($url);
        $databaseRisks = $this->checkDatabaseRisks($url, $body);
        $frameworkRisks = $this->checkFrameworkRisks($url, $body, $technologies);

        $riskScore = 0;

        $riskScore += count($headerCheck['missing']) * 5;
        $riskScore += count($exposedFiles) * 15;
        $riskScore += count($databaseRisks) * 12;
        $riskScore += count($frameworkRisks) * 10;

        if (!$ssl['valid']) {
            $riskScore += 20;
        }

        if (!$status || $status >= 500) {
            $riskScore += 15;
        }

        $riskScore = min($riskScore, 100);

        $riskLevel = match (true) {
            $riskScore >= 75 => 'critical',
            $riskScore >= 50 => 'high',
            $riskScore >= 25 => 'medium',
            default => 'low',
        };

        return [
            'url' => $url,
            'domain' => $domain,
            'ip' => $ip,
            'http_status' => $status,
            'response_time_ms' => $responseTime,
            'ssl_valid' => $ssl['valid'],
            'ssl_expires_at' => $ssl['expires_at'],
            'detected_technologies' => $technologies,
            'security_headers' => $headerCheck['present'],
            'missing_headers' => $headerCheck['missing'],
            'exposed_files' => $exposedFiles,
            'database_risks' => $databaseRisks,
            'framework_risks' => $frameworkRisks,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'summary' => $this->summary($riskLevel, $riskScore, $technologies, $exposedFiles, $headerCheck['missing']),
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }

        return rtrim($url, '/');
    }

    private function detectTechnologies(string $url, string $body, array $headers): array
    {
        $detected = [];

        $serverHeader = $this->headerValue($headers, 'Server');
        $poweredBy = $this->headerValue($headers, 'X-Powered-By');

        if ($serverHeader) {
            $detected[] = [
                'name' => 'Server',
                'value' => $serverHeader,
                'type' => 'webserver',
            ];
        }

        if ($poweredBy) {
            $detected[] = [
                'name' => 'X-Powered-By',
                'value' => $poweredBy,
                'type' => 'header',
            ];
        }

        $lower = strtolower($body);

        $patterns = [
            'WordPress' => ['wp-content', 'wp-includes', 'wp-json', 'wordpress'],
            'WooCommerce' => ['woocommerce', 'wc-cart-fragments'],
            'Laravel' => ['laravel', 'csrf-token', 'x-csrf-token', '/vendor/laravel'],
            'Angular' => ['ng-version', 'ng-app', '_ngcontent', 'angular'],
            'React' => ['reactroot', 'react-dom', '__react'],
            'Vue.js' => ['vue.js', '__vue__', 'data-v-'],
            'Node.js / Express' => ['express', 'x-powered-by: express'],
            'Next.js' => ['__next', 'next/static'],
            'Nuxt.js' => ['__nuxt', 'nuxt'],
            'PHP' => ['.php', 'php', 'x-powered-by: php'],
            'jQuery' => ['jquery'],
            'Bootstrap' => ['bootstrap'],
            'Cloudflare' => ['cf-ray', 'cloudflare'],
            'LiteSpeed' => ['litespeed', 'x-litespeed-cache'],
            'Apache' => ['apache'],
            'Nginx' => ['nginx'],
        ];

        $headerText = strtolower(json_encode($headers));

        foreach ($patterns as $name => $checks) {
            foreach ($checks as $check) {
                if (str_contains($lower, strtolower($check)) || str_contains($headerText, strtolower($check))) {
                    $detected[] = [
                        'name' => $name,
                        'value' => 'Detected',
                        'type' => 'framework',
                    ];
                    break;
                }
            }
        }

        return collect($detected)->unique(fn ($item) => $item['name'] . $item['type'])->values()->toArray();
    }

    private function checkSecurityHeaders(array $headers): array
    {
        $required = [
            'Strict-Transport-Security',
            'Content-Security-Policy',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ];

        $present = [];
        $missing = [];

        foreach ($required as $header) {
            $value = $this->headerValue($headers, $header);

            if ($value) {
                $present[$header] = $value;
            } else {
                $missing[] = $header;
            }
        }

        return [
            'present' => $present,
            'missing' => $missing,
        ];
    }

    private function checkExposedFiles(string $url): array
    {
        $paths = [
            '/.env',
            '/.git/config',
            '/composer.json',
            '/composer.lock',
            '/package.json',
            '/package-lock.json',
            '/yarn.lock',
            '/wp-config.php',
            '/config.php',
            '/phpinfo.php',
            '/info.php',
            '/backup.zip',
            '/backup.tar.gz',
            '/database.sql',
            '/db.sql',
            '/dump.sql',
            '/storage/logs/laravel.log',
            '/vendor/composer/installed.json',
        ];

        $found = [];

        foreach ($paths as $path) {
            try {
                $testUrl = rtrim($url, '/') . $path;

                $response = Http::timeout(8)
                    ->withoutVerifying()
                    ->withHeaders([
                        'User-Agent' => 'Webscepts-SentinelCore/1.0',
                    ])
                    ->get($testUrl);

                $body = strtolower(substr((string) $response->body(), 0, 1000));

                if (
                    $response->successful()
                    && !str_contains($body, '<!doctype html')
                    && !str_contains($body, '<html')
                ) {
                    $found[] = [
                        'path' => $path,
                        'status' => $response->status(),
                        'risk' => 'Public sensitive file may be exposed',
                    ];
                }

                usleep(120000);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $found;
    }

    private function checkDatabaseRisks(string $url, string $body): array
    {
        $risks = [];
        $lower = strtolower($body);

        $dbPatterns = [
            'MySQL error exposure' => ['mysql_fetch', 'mysqli_', 'sqlstate', 'mysql server', 'you have an error in your sql syntax'],
            'PostgreSQL error exposure' => ['postgresql', 'pg_query', 'psql:', 'syntax error at or near'],
            'Database dump reference' => ['database.sql', 'dump.sql', '.sql.gz', 'phpmyadmin'],
            'Laravel database exception' => ['illuminate\\database\\queryexception', 'sqlstate['],
        ];

        foreach ($dbPatterns as $title => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($lower, $pattern)) {
                    $risks[] = [
                        'title' => $title,
                        'evidence' => $pattern,
                        'risk' => 'Database error or file exposure may reveal sensitive information.',
                    ];
                    break;
                }
            }
        }

        return $risks;
    }

    private function checkFrameworkRisks(string $url, string $body, array $technologies): array
    {
        $risks = [];
        $lower = strtolower($body);
        $techNames = collect($technologies)->pluck('name')->implode(' ');

        if (str_contains($techNames, 'WordPress')) {
            if (str_contains($lower, '?author=1') || str_contains($lower, '/wp-json/wp/v2/users')) {
                $risks[] = [
                    'framework' => 'WordPress',
                    'title' => 'Possible user enumeration',
                    'risk' => 'Disable public author/user enumeration where possible.',
                ];
            }

            $risks[] = [
                'framework' => 'WordPress',
                'title' => 'WordPress detected',
                'risk' => 'Keep core, themes and plugins updated. Hide wp-config.php and disable file editing.',
            ];
        }

        if (str_contains($techNames, 'Laravel')) {
            if (str_contains($lower, 'laravel') && str_contains($lower, 'debug')) {
                $risks[] = [
                    'framework' => 'Laravel',
                    'title' => 'Possible debug exposure',
                    'risk' => 'Make sure APP_DEBUG=false in production.',
                ];
            }

            $risks[] = [
                'framework' => 'Laravel',
                'title' => 'Laravel detected',
                'risk' => 'Protect .env, storage logs, debug pages and composer files.',
            ];
        }

        if (str_contains($techNames, 'Angular')) {
            $risks[] = [
                'framework' => 'Angular',
                'title' => 'Angular detected',
                'risk' => 'Check exposed source maps and environment files.',
            ];
        }

        if (str_contains($techNames, 'Node.js') || str_contains($techNames, 'Next.js')) {
            $risks[] = [
                'framework' => 'Node.js',
                'title' => 'Node/Next.js detected',
                'risk' => 'Check exposed package.json, source maps, API errors and server-side secrets.',
            ];
        }

        return $risks;
    }

    private function checkSsl(?string $domain): array
    {
        if (!$domain) {
            return [
                'valid' => false,
                'expires_at' => null,
            ];
        }

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $client = @stream_socket_client(
                'ssl://' . $domain . ':443',
                $errno,
                $errstr,
                8,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$client) {
                return [
                    'valid' => false,
                    'expires_at' => null,
                ];
            }

            $params = stream_context_get_params($client);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if (!$cert) {
                return [
                    'valid' => false,
                    'expires_at' => null,
                ];
            }

            $parsed = openssl_x509_parse($cert);
            $validTo = $parsed['validTo_time_t'] ?? null;

            return [
                'valid' => $validTo ? $validTo > time() : false,
                'expires_at' => $validTo ? date('Y-m-d H:i:s', $validTo) : null,
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'expires_at' => null,
            ];
        }
    }

    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }

        return null;
    }

    private function summary(string $level, int $score, array $tech, array $files, array $missingHeaders): string
    {
        $techNames = collect($tech)->pluck('name')->unique()->implode(', ');

        return "Risk level: {$level}. Score: {$score}/100. Technologies: " .
            ($techNames ?: 'Unknown') .
            '. Exposed files found: ' . count($files) .
            '. Missing security headers: ' . count($missingHeaders) . '.';
    }
}