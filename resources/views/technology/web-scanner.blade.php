@extends('layouts.app')

@section('page-title', 'Web Scan Report')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | Safe Helpers
    |--------------------------------------------------------------------------
    */
    if (!function_exists('ws_scan_array')) {
        function ws_scan_array($value): array
        {
            if (is_array($value)) {
                return $value;
            }

            if (is_object($value)) {
                return json_decode(json_encode($value), true) ?: [];
            }

            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }

            return [];
        }
    }

    if (!function_exists('ws_scan_text')) {
        function ws_scan_text($value, $default = '-'): string
        {
            if (is_array($value)) {
                return json_encode($value);
            }

            if (is_object($value)) {
                return json_encode($value);
            }

            return filled($value) ? (string) $value : $default;
        }
    }

    $scanUrl = $scan->url ?? $scan->target_url ?? $scan->domain ?? '-';
    $domain = $scan->domain ?? parse_url($scanUrl, PHP_URL_HOST) ?? $scanUrl;
    $ip = $scan->ip ?? $scan->server_ip ?? $scan->host_ip ?? '-';

    $riskLevel = strtolower($scan->risk_level ?? $scan->risk ?? 'low');
    $riskScore = (int) ($scan->risk_score ?? $scan->score ?? 0);
    $httpStatus = $scan->http_status ?? $scan->status_code ?? $scan->status ?? null;
    $responseTime = $scan->response_time ?? $scan->load_time ?? $scan->time_ms ?? null;

    $technologies = collect(ws_scan_array($scan->detected_technologies ?? $scan->technologies ?? []));
    $securityHeaders = collect(ws_scan_array($scan->security_headers ?? $scan->headers ?? []));
    $missingHeaders = collect(ws_scan_array($scan->missing_security_headers ?? $scan->missing_headers ?? []));
    $sslInfo = collect(ws_scan_array($scan->ssl_info ?? $scan->ssl ?? []));
    $exposedFiles = collect(ws_scan_array($scan->exposed_files ?? []));
    $databaseRisks = collect(ws_scan_array($scan->database_risks ?? []));
    $frameworkRisks = collect(ws_scan_array($scan->framework_security_risks ?? $scan->framework_risks ?? []));
    $issues = collect(ws_scan_array($scan->issues ?? $scan->findings ?? $scan->security_findings ?? []));
    $pages = collect(ws_scan_array($scan->pages_scanned ?? $scan->pages ?? []));
    $forms = collect(ws_scan_array($scan->forms ?? []));
    $links = collect(ws_scan_array($scan->links ?? []));
    $recommendations = collect(ws_scan_array($scan->recommendations ?? []));
    $rawReport = ws_scan_array($scan->raw_report ?? $scan->report ?? []);

    $techNames = $technologies->map(function ($item) {
        if (is_array($item)) {
            return strtolower($item['name'] ?? $item['technology'] ?? $item['title'] ?? '');
        }

        return strtolower((string) $item);
    })->filter()->values();

    $hasWordPress = $techNames->contains(fn ($t) => str_contains($t, 'wordpress'));
    $hasLaravel = $techNames->contains(fn ($t) => str_contains($t, 'laravel'));
    $hasPhp = $techNames->contains(fn ($t) => str_contains($t, 'php'));
    $hasAngular = $techNames->contains(fn ($t) => str_contains($t, 'angular'));
    $hasNode = $techNames->contains(fn ($t) => str_contains($t, 'node') || str_contains($t, 'express') || str_contains($t, 'next'));
    $hasJquery = $techNames->contains(fn ($t) => str_contains($t, 'jquery'));
    $hasBootstrap = $techNames->contains(fn ($t) => str_contains($t, 'bootstrap'));
    $hasCloudflare = $techNames->contains(fn ($t) => str_contains($t, 'cloudflare'));
    $hasApache = $techNames->contains(fn ($t) => str_contains($t, 'apache'));
    $hasNginx = $techNames->contains(fn ($t) => str_contains($t, 'nginx'));

    $sslValid = $sslInfo->get('valid') ?? $sslInfo->get('status') === 'valid' ?? false;
    $sslStatusText = ($sslValid || str_starts_with($scanUrl, 'https://')) ? 'Valid' : 'Not Verified';

    if ($missingHeaders->isEmpty()) {
        $importantHeaders = [
            'Strict-Transport-Security',
            'Content-Security-Policy',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ];

        foreach ($importantHeaders as $header) {
            $value = $securityHeaders->get($header) ?? $securityHeaders->get(strtolower($header));
            if (empty($value)) {
                $missingHeaders->push($header);
            }
        }
    }

    if ($frameworkRisks->isEmpty()) {
        if ($hasWordPress) {
            $frameworkRisks->push([
                'title' => 'WordPress detected',
                'message' => 'Keep WordPress core, themes and plugins updated. Hide wp-config.php and disable file editing.',
                'level' => 'medium',
            ]);
        }

        if ($hasLaravel) {
            $frameworkRisks->push([
                'title' => 'Laravel detected',
                'message' => 'Protect .env, storage logs, debug pages, composer files and make sure APP_DEBUG=false in production.',
                'level' => 'high',
            ]);
        }

        if ($hasAngular) {
            $frameworkRisks->push([
                'title' => 'Angular detected',
                'message' => 'Check exposed source maps, environment files and public API endpoints.',
                'level' => 'medium',
            ]);
        }

        if ($hasNode) {
            $frameworkRisks->push([
                'title' => 'Node.js / Express detected',
                'message' => 'Check exposed package.json, source maps, stack traces, API errors and server-side secrets.',
                'level' => 'medium',
            ]);
        }

        if ($hasPhp) {
            $frameworkRisks->push([
                'title' => 'PHP detected',
                'message' => 'Disable display_errors, hide phpinfo pages and protect composer/vendor files.',
                'level' => 'medium',
            ]);
        }
    }

    if ($recommendations->isEmpty()) {
        $recommendations = collect([
            [
                'title' => 'Add missing security headers',
                'message' => 'Enable CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy and Permissions-Policy.',
                'level' => 'high',
            ],
            [
                'title' => 'Run CMS/plugin audit',
                'message' => 'Check WordPress, Laravel, Magento, Drupal, Joomla, PHP packages, Node packages and plugin versions.',
                'level' => 'medium',
            ],
            [
                'title' => 'Check exposed files',
                'message' => 'Block .env, .git, backup zip/sql files, logs, composer files, package files and debug endpoints.',
                'level' => 'high',
            ],
            [
                'title' => 'Schedule recurring monitoring',
                'message' => 'Run this scanner automatically with uptime, SSL, framework and security alerts.',
                'level' => 'info',
            ],
        ]);
    }

    $riskBadge = match ($riskLevel) {
        'critical' => 'bg-red-100 text-red-700 border-red-200',
        'high' => 'bg-orange-100 text-orange-700 border-orange-200',
        'medium' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        default => 'bg-green-100 text-green-700 border-green-200',
    };

    $riskBar = match ($riskLevel) {
        'critical' => 'bg-red-600',
        'high' => 'bg-orange-500',
        'medium' => 'bg-yellow-500',
        default => 'bg-green-600',
    };

    $statusBadge = match (true) {
        is_numeric($httpStatus) && $httpStatus >= 500 => 'bg-red-100 text-red-700 border-red-200',
        is_numeric($httpStatus) && $httpStatus >= 400 => 'bg-orange-100 text-orange-700 border-orange-200',
        is_numeric($httpStatus) && $httpStatus >= 300 => 'bg-blue-100 text-blue-700 border-blue-200',
        is_numeric($httpStatus) && $httpStatus >= 200 => 'bg-green-100 text-green-700 border-green-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200',
    };

    $lastChecked = $scan->scanned_at ?? $scan->checked_at ?? $scan->created_at ?? null;

    $scanModules = [
        'technology_detection' => ['Technology Detection', 'Detect CMS, PHP, Laravel, WordPress, JS libraries, CDN and server stack.', 'fa-layer-group'],
        'wordpress_plugins' => ['WordPress / Plugins', 'Check WordPress core, plugins, themes, wp-config and common WordPress exposures.', 'fa-brands fa-wordpress'],
        'laravel_php' => ['Laravel / PHP Security', 'Check APP_DEBUG, .env, storage logs, composer files, phpinfo and error pages.', 'fa-code'],
        'cms_detection' => ['CMS Detection', 'Detect Magento, Drupal, Joomla, Shopify, custom CMS and ecommerce platforms.', 'fa-cubes'],
        'security_headers' => ['Security Headers', 'Check CSP, HSTS, X-Frame-Options, Referrer-Policy and Permissions-Policy.', 'fa-shield-halved'],
        'ssl_tls' => ['SSL / TLS', 'Check HTTPS, certificate status, issuer and expiry.', 'fa-lock'],
        'exposed_files' => ['Exposed Files', 'Check .env, .git, backup, logs, composer, package and database dump files.', 'fa-file-shield'],
        'forms_csrf' => ['Forms / CSRF', 'Check login/register/contact/checkout forms and missing CSRF signs.', 'fa-wpforms'],
        'admin_paths' => ['Admin Paths', 'Check admin, login, wp-admin, dashboard, cpanel, backend paths safely.', 'fa-user-shield'],
        'database_risks' => ['Database Risks', 'Check public phpMyAdmin, exposed SQL dumps and DB error messages.', 'fa-database'],
        'malware_patterns' => ['Malware Patterns', 'Check suspicious injected scripts, hidden iframes and obfuscated JavaScript.', 'fa-bug'],
        'seo_performance' => ['SEO / Performance', 'Check meta tags, robots, sitemap, asset weight and response time.', 'fa-gauge-high'],
    ];
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-blue-950 to-slate-900 shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-red-500/20 rounded-full blur-3xl"></div>

        <div class="relative p-6 lg:p-8 text-white">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl lg:text-4xl font-black tracking-tight">
                            SentinelCore Web Scan Report
                        </h1>

                        <span class="px-3 py-1 rounded-full bg-green-500/20 text-green-100 border border-green-400/30 text-xs font-black">
                            <i class="fa-solid fa-circle text-[8px] mr-1"></i>
                            Live
                        </span>
                    </div>

                    <p class="text-slate-300 mt-3 max-w-4xl break-all text-base font-semibold">
                        {{ $scanUrl }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black">
                            <i class="fa-solid fa-globe mr-1"></i>
                            Domain: {{ $domain }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black">
                            <i class="fa-solid fa-network-wired mr-1"></i>
                            IP: {{ $ip }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black">
                            <i class="fa-solid fa-signal mr-1"></i>
                            HTTP: {{ $httpStatus ?? 'N/A' }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-300/30 text-xs font-black">
                            <i class="fa-solid fa-shield-halved mr-1"></i>
                            Risk: {{ strtoupper($riskLevel) }} / {{ $riskScore }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if(Route::has('technology.webscanner.scan'))
                        <form method="POST" action="{{ route('technology.webscanner.scan') }}">
                            @csrf
                            <input type="hidden" name="url" value="{{ $scanUrl }}">
                            <input type="hidden" name="rescan_id" value="{{ $scan->id ?? '' }}">
                            <button class="px-5 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                                <i class="fa-solid fa-rotate mr-2"></i>
                                Re-scan
                            </button>
                        </form>
                    @endif

                    <a href="{{ Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : url('/technology/web-scanner') }}"
                       class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/20 text-white font-black">
                        <i class="fa-solid fa-arrow-left mr-2"></i>
                        Back
                    </a>

                    <button type="button"
                            onclick="window.print()"
                            class="px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-white font-black">
                        <i class="fa-solid fa-print mr-2"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Summary Widgets --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Risk Level</p>
                    <div class="mt-3">
                        <span class="inline-flex px-4 py-2 rounded-2xl border {{ $riskBadge }} text-sm font-black uppercase">
                            {{ $riskLevel }}
                        </span>
                    </div>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>

            <div class="mt-5 h-3 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full {{ $riskBar }}" style="width: {{ min(max($riskScore, 0), 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Risk Score</p>
            <h3 class="text-4xl font-black text-slate-900 mt-2">{{ $riskScore }}/100</h3>
            <p class="text-xs text-slate-500 mt-4 font-bold">Higher score means higher risk.</p>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-xs uppercase tracking-widest text-slate-500 font-black">SSL Status</p>
            <h3 class="text-3xl font-black mt-3 {{ $sslStatusText === 'Valid' ? 'text-green-700' : 'text-red-700' }}">
                {{ $sslStatusText }}
            </h3>
            <p class="text-xs text-slate-500 mt-4 font-bold">
                {{ $sslInfo->get('issuer') ? 'Issuer: ' . $sslInfo->get('issuer') : 'HTTPS certificate check.' }}
            </p>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Response Time</p>
            <h3 class="text-3xl font-black text-slate-900 mt-3">
                {{ $responseTime ? $responseTime . ' ms' : '-' }}
            </h3>
            <p class="text-xs text-slate-500 mt-4 font-bold">
                HTTP status:
                <span class="px-2 py-1 rounded-lg border {{ $statusBadge }}">
                    {{ $httpStatus ?? 'N/A' }}
                </span>
            </p>
        </div>
    </div>

    {{-- Additional Module Options --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b bg-slate-50 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">
                    <i class="fa-solid fa-sliders text-blue-600 mr-2"></i>
                    Advanced Scan Options
                </h2>
                <p class="text-slate-500 mt-1">
                    Select extra modules for the next scan: WordPress, Laravel, CMS, plugins, exposed files, database risks and more.
                </p>
            </div>

            @if(Route::has('technology.webscanner.scan'))
                <form method="POST" action="{{ route('technology.webscanner.scan') }}">
                    @csrf
                    <input type="hidden" name="url" value="{{ $scanUrl }}">
                    <input type="hidden" name="rescan_id" value="{{ $scan->id ?? '' }}">
                    <button class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                        <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>
                        Run Selected Scan
                    </button>
                </form>
            @endif
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-4">
                @foreach($scanModules as $key => [$title, $description, $icon])
                    <label class="group rounded-2xl border border-slate-200 p-4 cursor-pointer hover:border-blue-300 hover:bg-blue-50/50 transition">
                        <div class="flex gap-3">
                            <input type="checkbox"
                                   name="scan_options[]"
                                   value="{{ $key }}"
                                   checked
                                   class="mt-1 w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 group-hover:bg-blue-100 group-hover:text-blue-700 flex items-center justify-center">
                                        <i class="fa-solid {{ $icon }}"></i>
                                    </span>

                                    <h3 class="font-black text-slate-900">
                                        {{ $title }}
                                    </h3>
                                </div>

                                <p class="text-xs text-slate-500 mt-2 leading-5">
                                    {{ $description }}
                                </p>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Platform Intelligence Widgets --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-4 gap-5">
        <div class="rounded-3xl border shadow bg-white overflow-hidden">
            <div class="p-5 bg-slate-50 border-b flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-brands fa-wordpress text-blue-600 mr-2"></i>
                    WordPress
                </h3>

                <span class="px-3 py-1 rounded-full text-xs font-black {{ $hasWordPress ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $hasWordPress ? 'Detected' : 'Not Detected' }}
                </span>
            </div>

            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">Core / Plugins</span>
                    <span class="text-xs font-black text-orange-700">Needs audit</span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">wp-config.php</span>
                    <span class="text-xs font-black text-green-700">Protected check</span>
                </div>

                <p class="text-sm text-slate-500 font-semibold leading-6">
                    Check wp-admin, plugins, themes, XML-RPC, REST API and update status.
                </p>
            </div>
        </div>

        <div class="rounded-3xl border shadow bg-white overflow-hidden">
            <div class="p-5 bg-slate-50 border-b flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-solid fa-code text-red-600 mr-2"></i>
                    Laravel / PHP
                </h3>

                <span class="px-3 py-1 rounded-full text-xs font-black {{ ($hasLaravel || $hasPhp) ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ ($hasLaravel || $hasPhp) ? 'Detected' : 'Not Detected' }}
                </span>
            </div>

            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">APP_DEBUG</span>
                    <span class="text-xs font-black text-red-700">Verify false</span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">.env / storage logs</span>
                    <span class="text-xs font-black text-orange-700">Needs check</span>
                </div>

                <p class="text-sm text-slate-500 font-semibold leading-6">
                    Protect .env, vendor, composer files, debug pages and stack traces.
                </p>
            </div>
        </div>

        <div class="rounded-3xl border shadow bg-white overflow-hidden">
            <div class="p-5 bg-slate-50 border-b flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-solid fa-window-restore text-purple-600 mr-2"></i>
                    Frontend
                </h3>

                <span class="px-3 py-1 rounded-full text-xs font-black {{ ($hasAngular || $hasJquery || $hasBootstrap) ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ ($hasAngular || $hasJquery || $hasBootstrap) ? 'Detected' : 'Check' }}
                </span>
            </div>

            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">Angular / JS</span>
                    <span class="text-xs font-black text-slate-700">{{ $hasAngular ? 'Detected' : 'Unknown' }}</span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">jQuery / Bootstrap</span>
                    <span class="text-xs font-black text-slate-700">{{ ($hasJquery || $hasBootstrap) ? 'Detected' : 'Unknown' }}</span>
                </div>

                <p class="text-sm text-slate-500 font-semibold leading-6">
                    Check outdated libraries, source maps, mixed content and injected scripts.
                </p>
            </div>
        </div>

        <div class="rounded-3xl border shadow bg-white overflow-hidden">
            <div class="p-5 bg-slate-50 border-b flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-solid fa-server text-green-600 mr-2"></i>
                    Server / CDN
                </h3>

                <span class="px-3 py-1 rounded-full text-xs font-black {{ ($hasApache || $hasNginx || $hasCloudflare) ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ ($hasApache || $hasNginx || $hasCloudflare) ? 'Detected' : 'Unknown' }}
                </span>
            </div>

            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">Apache / Nginx</span>
                    <span class="text-xs font-black text-slate-700">{{ $hasApache ? 'Apache' : ($hasNginx ? 'Nginx' : 'Unknown') }}</span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                    <span class="text-sm font-bold text-slate-600">Cloudflare</span>
                    <span class="text-xs font-black text-slate-700">{{ $hasCloudflare ? 'Detected' : 'Unknown' }}</span>
                </div>

                <p class="text-sm text-slate-500 font-semibold leading-6">
                    Check server tokens, WAF, firewall rules, SSL and cache headers.
                </p>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b bg-slate-50">
            <h2 class="text-2xl font-black text-slate-900">Summary</h2>
        </div>

        <div class="p-6">
            <p class="text-slate-700 font-bold leading-7">
                Risk level: <span class="uppercase">{{ $riskLevel }}</span>.
                Score: {{ $riskScore }}/100.
                Technologies:
                {{ $technologies->map(function ($item) {
                    return is_array($item) ? ($item['name'] ?? $item['technology'] ?? $item['title'] ?? null) : $item;
                })->filter()->unique()->implode(', ') ?: 'Not detected' }}.
                Exposed files found: {{ $exposedFiles->count() }}.
                Missing security headers: {{ $missingHeaders->count() }}.
            </p>
        </div>
    </div>

    {{-- Main Report Grid --}}
    <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6">

        {{-- Detected Technologies --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-900">Detected Technologies</h2>
                    <p class="text-slate-500 mt-1">Compact technology widgets instead of large rows.</p>
                </div>

                <span class="px-4 py-2 rounded-full bg-blue-100 text-blue-700 text-xs font-black">
                    {{ $technologies->count() }} found
                </span>
            </div>

            <div class="p-6">
                @if($technologies->count())
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($technologies as $tech)
                            @php
                                $tech = is_array($tech) ? $tech : ['name' => (string) $tech, 'type' => 'Detected'];
                                $name = $tech['name'] ?? $tech['technology'] ?? $tech['title'] ?? 'Unknown';
                                $type = $tech['type'] ?? $tech['category'] ?? $tech['status'] ?? 'Detected';

                                $icon = 'fa-layer-group';
                                $lower = strtolower($name);

                                if (str_contains($lower, 'wordpress')) $icon = 'fa-brands fa-wordpress';
                                elseif (str_contains($lower, 'laravel')) $icon = 'fa-code';
                                elseif (str_contains($lower, 'php')) $icon = 'fa-file-code';
                                elseif (str_contains($lower, 'apache') || str_contains($lower, 'nginx')) $icon = 'fa-server';
                                elseif (str_contains($lower, 'cloudflare')) $icon = 'fa-cloud';
                                elseif (str_contains($lower, 'jquery') || str_contains($lower, 'bootstrap') || str_contains($lower, 'angular')) $icon = 'fa-window-restore';
                            @endphp

                            <div class="rounded-2xl border border-slate-200 p-4 hover:shadow-md hover:border-blue-200 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <i class="fa-solid {{ $icon }}"></i>
                                    </div>

                                    <div class="min-w-0">
                                        <h3 class="font-black text-slate-900 truncate">
                                            {{ $name }}
                                        </h3>

                                        <p class="text-sm text-slate-500 font-semibold">
                                            {{ $type }}
                                        </p>
                                    </div>
                                </div>

                                @if(!empty($tech['version']))
                                    <div class="mt-3 px-3 py-2 rounded-xl bg-slate-50 text-xs text-slate-600 font-black">
                                        Version: {{ $tech['version'] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-3xl bg-slate-50 border border-slate-200 p-8 text-center">
                        <i class="fa-solid fa-layer-group text-4xl text-slate-300"></i>
                        <h3 class="text-xl font-black text-slate-900 mt-3">No technology detected</h3>
                        <p class="text-slate-500 mt-1">Run advanced scan with Technology Detection enabled.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Missing Security Headers --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Missing Security Headers</h2>
                <p class="text-slate-500 mt-1">Headers that should be added to improve browser security.</p>
            </div>

            <div class="p-6">
                @if($missingHeaders->count())
                    <div class="flex flex-wrap gap-3">
                        @foreach($missingHeaders as $header)
                            <span class="px-4 py-2 rounded-2xl bg-red-100 text-red-700 border border-red-200 text-sm font-black">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                {{ is_array($header) ? ($header['name'] ?? $header['header'] ?? 'Header') : $header }}
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-6 rounded-2xl bg-red-50 border border-red-200 p-5">
                        <h3 class="font-black text-red-900">Recommended Header Fix</h3>
                        <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 text-green-300 p-4 text-xs leading-6">add_header X-Frame-Options "SAMEORIGIN";
add_header X-Content-Type-Options "nosniff";
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()";
add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';";</pre>
                    </div>
                @else
                    <div class="rounded-3xl bg-green-50 border border-green-200 p-8 text-center">
                        <i class="fa-solid fa-circle-check text-4xl text-green-600"></i>
                        <h3 class="text-xl font-black text-green-900 mt-3">No missing security headers found</h3>
                    </div>
                @endif
            </div>
        </div>

        {{-- Exposed Files --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Exposed Files</h2>
                <p class="text-slate-500 mt-1">Sensitive public file checks.</p>
            </div>

            <div class="p-6">
                @if($exposedFiles->count())
                    <div class="space-y-3">
                        @foreach($exposedFiles as $file)
                            @php
                                $file = is_array($file) ? $file : ['path' => (string) $file];
                            @endphp

                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                                <h3 class="font-black text-red-800">
                                    {{ $file['path'] ?? $file['url'] ?? 'Exposed file' }}
                                </h3>

                                <p class="text-sm text-red-700 mt-1 font-bold">
                                    {{ $file['message'] ?? $file['description'] ?? 'This file should not be publicly accessible.' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-3xl bg-green-50 border border-green-200 p-8">
                        <p class="text-green-800 text-lg font-black">
                            <i class="fa-solid fa-circle-check mr-2"></i>
                            No exposed sensitive files found.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Database Risks --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Database Risks</h2>
                <p class="text-slate-500 mt-1">SQL dumps, phpMyAdmin and database error exposure.</p>
            </div>

            <div class="p-6">
                @if($databaseRisks->count())
                    <div class="space-y-3">
                        @foreach($databaseRisks as $risk)
                            @php
                                $risk = is_array($risk) ? $risk : ['title' => (string) $risk];
                            @endphp

                            <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                                <h3 class="font-black text-orange-900">
                                    {{ $risk['title'] ?? $risk['name'] ?? 'Database risk' }}
                                </h3>

                                <p class="text-sm text-orange-800 mt-1 font-bold">
                                    {{ $risk['message'] ?? $risk['description'] ?? 'Review this database security risk.' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-3xl bg-green-50 border border-green-200 p-8">
                        <p class="text-green-800 text-lg font-black">
                            <i class="fa-solid fa-circle-check mr-2"></i>
                            No database risks detected.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Framework Security Risks --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b bg-slate-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Framework Security Risks</h2>
                <p class="text-slate-500 mt-1">
                    WordPress, Laravel, PHP, Angular, Node.js and frontend framework checks.
                </p>
            </div>

            <input type="text"
                   id="frameworkSearch"
                   oninput="filterFrameworkRisks()"
                   placeholder="Search framework risks..."
                   class="w-full md:w-80 px-4 py-3 rounded-2xl border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6">
            @if($frameworkRisks->count())
                <div id="frameworkRiskList" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($frameworkRisks as $risk)
                        @php
                            $risk = is_array($risk) ? $risk : ['title' => (string) $risk, 'message' => 'Review this risk.'];
                            $level = strtolower($risk['level'] ?? $risk['severity'] ?? 'medium');

                            $levelClass = match($level) {
                                'critical' => 'bg-red-100 text-red-700 border-red-200',
                                'high' => 'bg-orange-100 text-orange-700 border-orange-200',
                                'low', 'info' => 'bg-blue-100 text-blue-700 border-blue-200',
                                default => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                            };
                        @endphp

                        <div class="framework-risk-item rounded-2xl border border-slate-200 p-5 hover:bg-slate-50 transition">
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="px-3 py-1 rounded-full border {{ $levelClass }} text-[10px] uppercase font-black">
                                    {{ $level }}
                                </span>

                                @if(!empty($risk['framework']))
                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] uppercase font-black">
                                        {{ $risk['framework'] }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-lg font-black text-slate-900">
                                {{ $risk['title'] ?? $risk['name'] ?? 'Framework risk' }}
                            </h3>

                            <p class="text-slate-600 mt-2 font-semibold leading-6">
                                {{ $risk['message'] ?? $risk['description'] ?? 'Review this framework risk.' }}
                            </p>

                            @if(!empty($risk['fix']))
                                <div class="mt-4 rounded-2xl bg-green-50 border border-green-200 p-4">
                                    <p class="text-xs uppercase text-green-700 font-black">Fix</p>
                                    <p class="text-green-800 font-bold mt-1">{{ $risk['fix'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-3xl bg-slate-50 border border-slate-200 p-8 text-center">
                    <i class="fa-solid fa-shield text-4xl text-slate-300"></i>
                    <h3 class="text-xl font-black text-slate-900 mt-3">No framework risks detected</h3>
                    <p class="text-slate-500 mt-1">Run advanced scan with Laravel / WordPress / CMS checks enabled.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Recommendations --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b bg-slate-50">
            <h2 class="text-2xl font-black text-slate-900">Developer Recommendations</h2>
            <p class="text-slate-500 mt-1">Actionable fixes for the developer or server admin.</p>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($recommendations as $recommendation)
                @php
                    $recommendation = is_array($recommendation)
                        ? $recommendation
                        : ['title' => (string) $recommendation, 'message' => 'Review this recommendation.'];

                    $level = strtolower($recommendation['level'] ?? 'info');

                    $recClass = match($level) {
                        'critical', 'high' => 'bg-red-50 border-red-200 text-red-900',
                        'medium' => 'bg-yellow-50 border-yellow-200 text-yellow-900',
                        default => 'bg-blue-50 border-blue-200 text-blue-900',
                    };
                @endphp

                <div class="rounded-2xl border p-5 {{ $recClass }}">
                    <h3 class="font-black">
                        {{ $recommendation['title'] ?? 'Recommendation' }}
                    </h3>

                    <p class="text-sm mt-2 font-semibold leading-6">
                        {{ $recommendation['message'] ?? $recommendation['description'] ?? 'Review this item.' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Pages and Forms --}}
    <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Pages Scanned</h2>
            </div>

            <div class="p-6">
                @if($pages->count())
                    <div class="space-y-3 max-h-[420px] overflow-y-auto pr-2">
                        @foreach($pages as $page)
                            @php
                                $page = is_array($page) ? $page : ['url' => (string) $page];
                            @endphp

                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-bold text-slate-900 break-all">
                                        {{ $page['url'] ?? '-' }}
                                    </p>

                                    <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-black">
                                        {{ $page['status'] ?? $page['http_status'] ?? 'N/A' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-slate-500 font-bold">No page crawl data available.</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Forms Found</h2>
            </div>

            <div class="p-6">
                @if($forms->count())
                    <div class="space-y-3 max-h-[420px] overflow-y-auto pr-2">
                        @foreach($forms as $form)
                            @php
                                $form = is_array($form) ? $form : ['action' => (string) $form];
                            @endphp

                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <span class="px-3 py-1 rounded-lg bg-blue-100 text-blue-700 text-xs font-black">
                                        {{ strtoupper($form['method'] ?? 'GET') }}
                                    </span>

                                    <span class="px-3 py-1 rounded-lg {{ !empty($form['csrf']) ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }} text-xs font-black">
                                        {{ !empty($form['csrf']) ? 'CSRF Found' : 'CSRF Unknown' }}
                                    </span>
                                </div>

                                <p class="font-bold text-slate-900 break-all">
                                    {{ $form['action'] ?? 'No action' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-slate-500 font-bold">No forms detected.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Raw Data --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <button type="button"
                onclick="toggleRawReport()"
                class="w-full p-6 bg-slate-50 border-b text-left flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Raw Scan Data</h2>
                <p class="text-slate-500 mt-1">Developer/debug JSON data.</p>
            </div>

            <i class="fa-solid fa-chevron-down text-slate-500"></i>
        </button>

        <div id="rawReportBox" class="hidden p-6">
            <pre class="max-h-[520px] overflow-auto rounded-2xl bg-slate-950 text-green-300 p-5 text-xs leading-6">{{ json_encode($rawReport ?: $scan->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
</div>

<style>
    @media print {
        aside,
        .ws-sidebar,
        .no-print,
        button,
        form {
            display: none !important;
        }

        body {
            background: #ffffff !important;
        }

        .shadow,
        .shadow-xl {
            box-shadow: none !important;
        }
    }
</style>

<script>
function toggleRawReport() {
    const box = document.getElementById('rawReportBox');

    if (!box) {
        return;
    }

    box.classList.toggle('hidden');
}

function filterFrameworkRisks() {
    const query = (document.getElementById('frameworkSearch')?.value || '').toLowerCase();

    document.querySelectorAll('.framework-risk-item').forEach(function (item) {
        item.style.display = item.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
}
</script>

@endsection