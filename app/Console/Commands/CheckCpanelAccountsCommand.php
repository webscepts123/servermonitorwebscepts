<?php

namespace App\Console\Commands;

use App\Http\Controllers\SmsController;
use App\Models\AccountMonitorStatus;
use App\Models\CpanelAccount;
use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CheckCpanelAccountsCommand extends Command
{
    protected $signature = 'cpanel-accounts:check
                            {--sleep=1 : Seconds between account checks}
                            {--account_id= : Check only one cPanel account ID}';

    protected $description = 'Check every cPanel account website, cPanel, WHM, CMS/framework issues, and send SMS/email alerts.';

    public function handle(): int
    {
        $sleep = (int) $this->option('sleep');
        $accountId = $this->option('account_id');

        if (!class_exists(CpanelAccount::class)) {
            $this->error('CpanelAccount model not found.');
            return self::FAILURE;
        }

        $query = CpanelAccount::query();

        if ($accountId) {
            $query->where('id', $accountId);
        }

        $accounts = $query->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->warn('No cPanel accounts found.');
            return self::SUCCESS;
        }

        $this->info('Checking cPanel accounts/websites: ' . $accounts->count());

        foreach ($accounts as $account) {
            try {
                $this->checkAccount($account);
            } catch (\Throwable $e) {
                Log::error('cPanel account monitor failed.', [
                    'account_id' => $account->id ?? null,
                    'error' => $e->getMessage(),
                ]);

                $this->error('Failed account #' . ($account->id ?? '-') . ': ' . $e->getMessage());
            }

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        $this->info('cPanel account check completed.');

        return self::SUCCESS;
    }

    private function checkAccount($account): void
    {
        $server = $this->serverForAccount($account);

        $domain = $this->accountValue($account, [
            'domain',
            'main_domain',
            'primary_domain',
            'website',
            'url',
        ]);

        $username = $this->accountValue($account, [
            'username',
            'cpanel_username',
            'user',
            'account',
            'name',
        ]);

        $host = $this->serverValue($server, [
            'host',
            'hostname',
            'ip',
            'ip_address',
            'server_ip',
            'public_ip',
        ]);

        if (!$domain && $host) {
            $domain = $host;
        }

        $domain = $this->cleanDomain($domain);
        $host = $this->cleanHost($host);

        $websiteUrl = $domain ? 'https://' . $domain : null;

        $websiteCheck = $this->checkWebsite($websiteUrl);
        $cpanelCheck = $this->checkPort($host, 2083);
        $whmCheck = $this->checkPort($host, 2087);
        $platformCheck = $this->detectPlatformAndIssues($domain, $websiteCheck['body'] ?? '', $websiteCheck['headers'] ?? []);

        $websiteUp = $websiteCheck['up'];
        $cpanelUp = $cpanelCheck['up'];
        $whmUp = $whmCheck['up'];

        $criticalIssues = $platformCheck['critical_issues'] ?? [];
        $warningIssues = $platformCheck['warning_issues'] ?? [];
        $detectedPlatforms = $platformCheck['platforms'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Down / Critical Logic
        |--------------------------------------------------------------------------
        | Send alert for:
        | - Website down
        | - cPanel down
        | - WHM down
        | - HTTP 500
        | - Exposed .env
        | - Laravel debug/error exposed
        | - PHP fatal error
        | - CMS fatal error
        |--------------------------------------------------------------------------
        */

        $isDown = !$websiteUp || !$cpanelUp || !$whmUp || count($criticalIssues) > 0;

        $reasonParts = [];

        if (!$websiteUp) {
            $reasonParts[] = 'Website issue: ' . $websiteCheck['message'];
        }

        if (!$cpanelUp) {
            $reasonParts[] = 'cPanel 2083 issue: ' . $cpanelCheck['message'];
        }

        if (!$whmUp) {
            $reasonParts[] = 'WHM 2087 issue: ' . $whmCheck['message'];
        }

        foreach ($criticalIssues as $issue) {
            $reasonParts[] = $issue;
        }

        $reason = implode(' | ', $reasonParts);

        $platformText = count($detectedPlatforms)
            ? implode(', ', $detectedPlatforms)
            : 'Unknown';

        $warningText = count($warningIssues)
            ? implode(' | ', $warningIssues)
            : null;

        $status = AccountMonitorStatus::firstOrCreate(
            [
                'cpanel_account_id' => $account->id,
            ],
            [
                'server_id' => $server->id ?? null,
                'account_name' => $username,
                'domain' => $domain,
                'host' => $host,
                'last_status' => 'up',
            ]
        );

        $previousStatus = $status->last_status;

        $status->update([
            'server_id' => $server->id ?? null,
            'account_name' => $username,
            'domain' => $domain,
            'host' => $host,
            'website_up' => $websiteUp,
            'cpanel_up' => $cpanelUp,
            'whm_up' => $whmUp,
            'wordpress_up' => !in_array('WordPress critical error detected', $criticalIssues, true),
            'last_status' => $isDown ? 'down' : 'up',
            'last_error' => $isDown ? $reason : $warningText,
            'last_checked_at' => now(),
        ]);

        $this->line(($isDown ? '[ISSUE] ' : '[OK] ') . ($domain ?: $username ?: 'Account #' . $account->id) . ' | Platform: ' . $platformText);

        Log::info('Website platform monitoring result.', [
            'account_id' => $account->id ?? null,
            'domain' => $domain,
            'host' => $host,
            'website_up' => $websiteUp,
            'cpanel_up' => $cpanelUp,
            'whm_up' => $whmUp,
            'platforms' => $detectedPlatforms,
            'critical_issues' => $criticalIssues,
            'warning_issues' => $warningIssues,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Send alert only when status changes
        |--------------------------------------------------------------------------
        | Prevents repeated SMS every 30 minutes.
        |--------------------------------------------------------------------------
        */

        if ($isDown && $previousStatus !== 'down') {
            $this->sendDownAlert($account, $server, $status, $reason, $platformText);
        }

        if (!$isDown && $previousStatus === 'down') {
            $this->sendRecoveryAlert($account, $server, $status, $platformText);
        }
    }

    private function checkWebsite(?string $url): array
    {
        if (!$url) {
            return [
                'up' => false,
                'message' => 'Website URL missing',
                'body' => '',
                'headers' => [],
                'status' => 0,
            ];
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(25)
                ->connectTimeout(10)
                ->withHeaders([
                    'User-Agent' => 'WebsceptsServerMonitor/1.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);

            $status = $response->status();
            $body = (string) $response->body();

            /*
            |--------------------------------------------------------------------------
            | 200-399 = OK
            | 400-499 = site reachable, but warning/error page
            | 500+ = critical
            |--------------------------------------------------------------------------
            */

            if ($status >= 200 && $status < 400) {
                return [
                    'up' => true,
                    'message' => 'HTTP ' . $status,
                    'body' => $body,
                    'headers' => $response->headers(),
                    'status' => $status,
                ];
            }

            if ($status >= 400 && $status < 500) {
                return [
                    'up' => true,
                    'message' => 'HTTP ' . $status . ' reachable but client error',
                    'body' => $body,
                    'headers' => $response->headers(),
                    'status' => $status,
                ];
            }

            return [
                'up' => false,
                'message' => 'HTTP ' . $status,
                'body' => $body,
                'headers' => $response->headers(),
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            return [
                'up' => false,
                'message' => Str::limit($e->getMessage(), 180),
                'body' => '',
                'headers' => [],
                'status' => 0,
            ];
        }
    }

    private function detectPlatformAndIssues(?string $domain, string $html, array $headers = []): array
    {
        $platforms = [];
        $criticalIssues = [];
        $warningIssues = [];

        $lower = strtolower($html);

        /*
        |--------------------------------------------------------------------------
        | CMS / Framework Detection
        |--------------------------------------------------------------------------
        */

        if (str_contains($lower, 'wp-content') || str_contains($lower, 'wp-includes') || str_contains($lower, 'wp-json')) {
            $platforms[] = 'WordPress';
        }

        if (str_contains($lower, 'mage/cookies') || str_contains($lower, 'magento') || str_contains($lower, 'x-magento')) {
            $platforms[] = 'Magento';
        }

        if (str_contains($lower, 'drupal-settings-json') || str_contains($lower, 'sites/default/files') || str_contains($lower, 'drupal')) {
            $platforms[] = 'Drupal';
        }

        if (str_contains($lower, 'joomla') || str_contains($lower, 'com_content') || str_contains($lower, '/media/system/js/')) {
            $platforms[] = 'Joomla';
        }

        if (str_contains($lower, 'laravel') || str_contains($lower, 'csrf-token') || str_contains($lower, 'mix-manifest') || str_contains($lower, '/vendor/livewire/')) {
            $platforms[] = 'Laravel';
        }

        if (str_contains($lower, 'codeigniter') || str_contains($lower, 'ci_session')) {
            $platforms[] = 'CodeIgniter';
        }

        if (str_contains($lower, 'react') || str_contains($lower, 'data-reactroot') || str_contains($lower, '_next/static')) {
            $platforms[] = 'React/Next.js';
        }

        if (str_contains($lower, 'vue') || str_contains($lower, 'data-v-') || str_contains($lower, '__nuxt')) {
            $platforms[] = 'Vue/Nuxt';
        }

        if (str_contains($lower, 'bootstrap')) {
            $platforms[] = 'Bootstrap';
        }

        if (str_contains($lower, 'tailwind')) {
            $platforms[] = 'Tailwind CSS';
        }

        if (str_contains($lower, 'jquery')) {
            $platforms[] = 'jQuery';
        }

        /*
        |--------------------------------------------------------------------------
        | Critical Issue Detection
        |--------------------------------------------------------------------------
        */

        $criticalPatterns = [
            'laravel_exception' => [
                'label' => 'Laravel exception/debug page exposed',
                'patterns' => [
                    'whoops',
                    'illuminate\\',
                    'symfony\\component\\debug',
                    'app\\exceptions\\handler',
                    'stack trace',
                    'debugbar',
                    'laravel.log',
                ],
            ],

            'php_error' => [
                'label' => 'PHP fatal/error text detected',
                'patterns' => [
                    'fatal error',
                    'parse error',
                    'warning: require',
                    'warning: include',
                    'uncaught exception',
                    'call to undefined function',
                    'call to a member function',
                    'failed opening required',
                    'allowed memory size',
                ],
            ],

            'wordpress_error' => [
                'label' => 'WordPress critical error detected',
                'patterns' => [
                    'there has been a critical error on this website',
                    'error establishing a database connection',
                    'wordpress database error',
                    'wp-content/debug.log',
                ],
            ],

            'magento_error' => [
                'label' => 'Magento error detected',
                'patterns' => [
                    'there has been an error processing your request',
                    'magento framework',
                    'report id',
                    'var/report',
                    'mage exception',
                ],
            ],

            'drupal_error' => [
                'label' => 'Drupal error detected',
                'patterns' => [
                    'the website encountered an unexpected error',
                    'drupal\\core',
                    'symfony\\component\\httpkernel',
                    'recoverable fatal error',
                ],
            ],

            'database_error' => [
                'label' => 'Database connection/query error detected',
                'patterns' => [
                    'sqlstate',
                    'mysql server has gone away',
                    'access denied for user',
                    'database connection failed',
                    'could not connect to database',
                    'too many connections',
                ],
            ],

            'exposed_env' => [
                'label' => 'Possible exposed environment/debug data',
                'patterns' => [
                    'app_key=',
                    'db_password=',
                    'aws_secret_access_key',
                    'mail_password=',
                    'stripe_secret',
                    'paypal_secret',
                ],
            ],
        ];

        foreach ($criticalPatterns as $group) {
            foreach ($group['patterns'] as $pattern) {
                if (str_contains($lower, strtolower($pattern))) {
                    $criticalIssues[] = $group['label'];
                    break;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Warning Issue Detection
        |--------------------------------------------------------------------------
        */

        if (str_contains($lower, 'mixed content')) {
            $warningIssues[] = 'Mixed content warning detected';
        }

        if (str_contains($lower, 'deprecated') || str_contains($lower, 'notice:')) {
            $warningIssues[] = 'PHP deprecated/notice text visible';
        }

        if (str_contains($lower, 'maintenance mode') || str_contains($lower, 'briefly unavailable for scheduled maintenance')) {
            $warningIssues[] = 'Maintenance mode detected';
        }

        if (str_contains($lower, 'index of /')) {
            $warningIssues[] = 'Directory listing may be exposed';
        }

        /*
        |--------------------------------------------------------------------------
        | Header Detection
        |--------------------------------------------------------------------------
        */

        foreach ($headers as $key => $value) {
            $headerLine = strtolower($key . ': ' . implode(',', (array) $value));

            if (str_contains($headerLine, 'x-powered-by: php')) {
                $platforms[] = 'PHP';
            }

            if (str_contains($headerLine, 'x-generator: drupal')) {
                $platforms[] = 'Drupal';
            }

            if (str_contains($headerLine, 'x-magento')) {
                $platforms[] = 'Magento';
            }

            if (str_contains($headerLine, 'x-litespeed')) {
                $platforms[] = 'LiteSpeed';
            }

            if (str_contains($headerLine, 'cloudflare')) {
                $platforms[] = 'Cloudflare';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Extra Safe Public Endpoint Checks
        |--------------------------------------------------------------------------
        | Only public non-auth requests. No login brute force, no exploit.
        |--------------------------------------------------------------------------
        */

        if ($domain) {
            $extra = $this->checkPublicEndpoints($domain);

            $platforms = array_merge($platforms, $extra['platforms']);
            $criticalIssues = array_merge($criticalIssues, $extra['critical_issues']);
            $warningIssues = array_merge($warningIssues, $extra['warning_issues']);
        }

        return [
            'platforms' => array_values(array_unique(array_filter($platforms))),
            'critical_issues' => array_values(array_unique(array_filter($criticalIssues))),
            'warning_issues' => array_values(array_unique(array_filter($warningIssues))),
        ];
    }

    private function checkPublicEndpoints(string $domain): array
    {
        $platforms = [];
        $criticalIssues = [];
        $warningIssues = [];

        $checks = [
            '/wp-json/' => 'WordPress',
            '/administrator/' => 'Joomla',
            '/user/login' => 'Drupal',
            '/admin' => 'Admin page',
            '/.env' => 'ENV',
        ];

        foreach ($checks as $path => $label) {
            $url = 'https://' . $domain . $path;

            try {
                $response = Http::withoutVerifying()
                    ->timeout(8)
                    ->connectTimeout(5)
                    ->withHeaders([
                        'User-Agent' => 'WebsceptsServerMonitor/1.0',
                    ])
                    ->get($url);

                $status = $response->status();
                $body = strtolower((string) $response->body());

                if ($label === 'WordPress' && $status >= 200 && $status < 500) {
                    $platforms[] = 'WordPress';
                }

                if ($label === 'Joomla' && $status >= 200 && $status < 500) {
                    $platforms[] = 'Joomla';
                }

                if ($label === 'Drupal' && $status >= 200 && $status < 500) {
                    $platforms[] = 'Drupal';
                }

                if ($label === 'Admin page' && $status >= 200 && $status < 400) {
                    $warningIssues[] = 'Admin page reachable at /admin';
                }

                if ($label === 'ENV') {
                    if ($status >= 200 && $status < 400 && (
                        str_contains($body, 'app_key=') ||
                        str_contains($body, 'db_password=') ||
                        str_contains($body, 'app_env=')
                    )) {
                        $criticalIssues[] = 'CRITICAL: .env file appears publicly exposed';
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [
            'platforms' => $platforms,
            'critical_issues' => $criticalIssues,
            'warning_issues' => $warningIssues,
        ];
    }

    private function checkPort(?string $host, int $port): array
    {
        if (!$host) {
            return [
                'up' => false,
                'message' => 'Host missing',
            ];
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, 8);

        if (is_resource($connection)) {
            fclose($connection);

            return [
                'up' => true,
                'message' => 'Port open',
            ];
        }

        return [
            'up' => false,
            'message' => trim($errstr ?: ('Error ' . $errno)),
        ];
    }

    private function sendDownAlert($account, $server, AccountMonitorStatus $status, string $reason, string $platformText): void
    {
        app(SmsController::class)->sendAccountDownAlert([
            'name' => $status->account_name ?: $status->domain,
            'domain' => $status->domain,
            'host' => $status->host,
            'reason' => Str::limit('Platform: ' . $platformText . ' | ' . $reason, 280),
            'phones' => $this->alertPhones($account, $server),
            'emails' => $this->alertEmails($account, $server),
        ]);

        $status->update([
            'last_down_alert_sent_at' => now(),
        ]);
    }

    private function sendRecoveryAlert($account, $server, AccountMonitorStatus $status, string $platformText): void
    {
        app(SmsController::class)->sendAccountRecoveryAlert([
            'name' => $status->account_name ?: $status->domain,
            'domain' => $status->domain,
            'host' => $status->host,
            'reason' => 'Platform: ' . $platformText,
            'phones' => $this->alertPhones($account, $server),
            'emails' => $this->alertEmails($account, $server),
        ]);

        $status->update([
            'last_recovery_alert_sent_at' => now(),
        ]);
    }

    private function serverForAccount($account)
    {
        $serverId = $this->accountValue($account, [
            'server_id',
            'hosting_server_id',
        ]);

        if ($serverId) {
            $server = Server::find($serverId);

            if ($server) {
                return $server;
            }
        }

        return Server::query()->orderBy('id')->first();
    }

    private function alertPhones($account, $server): array
    {
        $phones = [];

        foreach ([
            'admin_phone',
            'customer_phone',
            'phone',
            'mobile',
            'alert_phone',
            'sms_phone',
        ] as $column) {
            $value = $this->accountValue($account, [$column]);

            if ($value) {
                $phones[] = $value;
            }
        }

        foreach ([
            'admin_phone',
            'customer_phone',
            'phone',
            'mobile',
            'alert_phone',
            'sms_phone',
        ] as $column) {
            $value = $this->serverValue($server, [$column]);

            if ($value) {
                $phones[] = $value;
            }
        }

        $envPhones = explode(',', (string) env('MONITOR_ALERT_PHONES', ''));

        foreach ($envPhones as $phone) {
            if (trim($phone)) {
                $phones[] = trim($phone);
            }
        }

        return array_values(array_filter(array_unique($phones)));
    }

    private function alertEmails($account, $server): array
    {
        $emails = [];

        foreach ([
            'email',
            'admin_email',
            'customer_email',
            'alert_email',
        ] as $column) {
            $value = $this->accountValue($account, [$column]);

            if ($value) {
                $emails[] = $value;
            }
        }

        foreach ([
            'email',
            'admin_email',
            'customer_email',
            'alert_email',
        ] as $column) {
            $value = $this->serverValue($server, [$column]);

            if ($value) {
                $emails[] = $value;
            }
        }

        $envEmails = explode(',', (string) env('MONITOR_ALERT_EMAILS', ''));

        foreach ($envEmails as $email) {
            if (trim($email)) {
                $emails[] = trim($email);
            }
        }

        return array_values(array_filter(array_unique($emails)));
    }

    private function accountValue($account, array $keys): ?string
    {
        foreach ($keys as $key) {
            try {
                if (isset($account->{$key}) && $account->{$key} !== '') {
                    return trim((string) $account->{$key});
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private function serverValue($server, array $keys): ?string
    {
        if (!$server) {
            return null;
        }

        foreach ($keys as $key) {
            try {
                if (isset($server->{$key}) && $server->{$key} !== '') {
                    return trim((string) $server->{$key});
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private function cleanDomain(?string $domain): ?string
    {
        $domain = trim((string) $domain);

        if (!$domain) {
            return null;
        }

        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);

        return strtolower($domain);
    }

    private function cleanHost(?string $host): ?string
    {
        $host = trim((string) $host);

        if (!$host) {
            return null;
        }

        $host = preg_replace('#^https?://#i', '', $host);
        $host = preg_replace('#:\d+$#', '', $host);
        $host = preg_replace('#/.*$#', '', $host);

        return strtolower($host);
    }
}