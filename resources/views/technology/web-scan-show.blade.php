@extends('layouts.app')

@section('page-title', 'Web Scan Report')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | Safe Helpers
    |--------------------------------------------------------------------------
    */
    $toArray = function ($value) {
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
    };

    $scanUrl = $scan->url ?? $scan->target_url ?? '-';
    $domain = $scan->domain ?? parse_url($scanUrl, PHP_URL_HOST) ?? '-';
    $ip = $scan->ip ?? '-';

    $riskLevel = strtolower($scan->risk_level ?? 'low');
    $riskScore = (int) ($scan->risk_score ?? 0);
    $httpStatus = $scan->http_status ?? 'N/A';
    $responseTime = $scan->response_time_ms ?? $scan->response_time ?? null;

    $technologies = collect($toArray($scan->detected_technologies ?? []));
    $missingHeaders = collect($toArray($scan->missing_headers ?? []));
    $exposedFiles = collect($toArray($scan->exposed_files ?? []));
    $databaseRisks = collect($toArray($scan->database_risks ?? []));
    $frameworkRisks = collect($toArray($scan->framework_risks ?? []));
    $recommendations = collect($toArray($scan->recommendations ?? []));

    $techNames = $technologies->map(function ($tech) {
        if (is_array($tech)) {
            return strtolower($tech['name'] ?? $tech['technology'] ?? $tech['title'] ?? '');
        }

        return strtolower((string) $tech);
    })->filter()->values();

    $hasWordPress = $techNames->contains(fn ($name) => str_contains($name, 'wordpress'));
    $hasLaravel = $techNames->contains(fn ($name) => str_contains($name, 'laravel'));
    $hasPhp = $techNames->contains(fn ($name) => str_contains($name, 'php'));
    $hasAngular = $techNames->contains(fn ($name) => str_contains($name, 'angular'));
    $hasNode = $techNames->contains(fn ($name) => str_contains($name, 'node') || str_contains($name, 'express') || str_contains($name, 'next'));
    $hasJquery = $techNames->contains(fn ($name) => str_contains($name, 'jquery'));
    $hasBootstrap = $techNames->contains(fn ($name) => str_contains($name, 'bootstrap'));
    $hasCloudflare = $techNames->contains(fn ($name) => str_contains($name, 'cloudflare'));
    $hasApache = $techNames->contains(fn ($name) => str_contains($name, 'apache'));
    $hasNginx = $techNames->contains(fn ($name) => str_contains($name, 'nginx'));

    if ($frameworkRisks->isEmpty()) {
        if ($hasWordPress) {
            $frameworkRisks->push([
                'framework' => 'WordPress',
                'title' => 'WordPress detected',
                'risk' => 'Keep WordPress core, themes and plugins updated. Check wp-admin, XML-RPC, REST API, wp-config.php and plugin vulnerabilities.',
                'level' => 'medium',
                'fix' => 'Update WordPress, remove unused plugins, disable file editing and protect wp-config.php.',
            ]);
        }

        if ($hasLaravel) {
            $frameworkRisks->push([
                'framework' => 'Laravel',
                'title' => 'Laravel detected',
                'risk' => 'Check APP_DEBUG, exposed .env, storage logs, vendor files, composer files and Laravel error pages.',
                'level' => 'high',
                'fix' => 'Set APP_DEBUG=false, block .env, protect storage/logs and avoid exposing stack traces.',
            ]);
        }

        if ($hasPhp) {
            $frameworkRisks->push([
                'framework' => 'PHP',
                'title' => 'PHP detected',
                'risk' => 'Check display_errors, phpinfo pages, old PHP versions and public composer/vendor files.',
                'level' => 'medium',
                'fix' => 'Disable display_errors, remove phpinfo files and keep PHP updated.',
            ]);
        }

        if ($hasAngular) {
            $frameworkRisks->push([
                'framework' => 'Angular',
                'title' => 'Angular detected',
                'risk' => 'Check exposed source maps, environment files and public API endpoint leaks.',
                'level' => 'medium',
                'fix' => 'Disable production source maps and verify environment variables are not exposed.',
            ]);
        }

        if ($hasNode) {
            $frameworkRisks->push([
                'framework' => 'Node.js / Express',
                'title' => 'Node.js / Express detected',
                'risk' => 'Check exposed package.json, source maps, API errors, server stack traces and server-side secrets.',
                'level' => 'medium',
                'fix' => 'Hide stack traces, secure API errors and keep npm packages updated.',
            ]);
        }
    }

    if ($recommendations->isEmpty()) {
        $recommendations = collect([
            [
                'title' => 'Add missing security headers',
                'message' => 'Add HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy and Permissions-Policy.',
                'level' => 'high',
            ],
            [
                'title' => 'Run CMS and framework audit',
                'message' => 'Check WordPress plugins, Laravel debug exposure, PHP version, Angular source maps and Node packages.',
                'level' => 'medium',
            ],
            [
                'title' => 'Check public sensitive files',
                'message' => 'Block .env, .git, backup files, SQL dumps, composer files, package files and logs from public access.',
                'level' => 'high',
            ],
            [
                'title' => 'Schedule automatic scans',
                'message' => 'Run scans regularly and send email/SMS alerts when risk score, SSL, HTTP status or exposed files change.',
                'level' => 'info',
            ],
        ]);
    }

    $riskClass = match($riskLevel) {
        'critical' => 'bg-red-100 text-red-700 border-red-200',
        'high' => 'bg-orange-100 text-orange-700 border-orange-200',
        'medium' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        default => 'bg-green-100 text-green-700 border-green-200',
    };

    $riskBarClass = match($riskLevel) {
        'critical' => 'bg-red-600',
        'high' => 'bg-orange-500',
        'medium' => 'bg-yellow-500',
        default => 'bg-green-600',
    };

    $sslText = !empty($scan->ssl_valid) ? 'Valid' : 'Invalid';
    $sslClass = !empty($scan->ssl_valid) ? 'text-green-600' : 'text-red-600';

    $scanOptions = [
        'technology_detection' => ['Technology Detection', 'Detect CMS, frameworks, PHP, JavaScript libraries, CDN and server stack.', 'fa-layer-group'],
        'wordpress_plugins' => ['WordPress / Plugins', 'Check WordPress core, plugins, themes, wp-config, wp-admin and XML-RPC.', 'fa-brands fa-wordpress'],
        'laravel_php' => ['Laravel / PHP Security', 'Check APP_DEBUG, .env, storage logs, composer, vendor and phpinfo exposure.', 'fa-code'],
        'cms_detection' => ['CMS Platforms', 'Detect Magento, Drupal, Joomla, Shopify, WordPress and custom CMS platforms.', 'fa-cubes'],
        'security_headers' => ['Security Headers', 'Check CSP, HSTS, X-Frame-Options, Referrer-Policy and Permissions-Policy.', 'fa-shield-halved'],
        'ssl_tls' => ['SSL / TLS', 'Check HTTPS, certificate validity, expiry, issuer and redirect behaviour.', 'fa-lock'],
        'exposed_files' => ['Exposed Files', 'Check .env, .git, backups, logs, composer, package and database dump files.', 'fa-file-shield'],
        'forms_csrf' => ['Forms / CSRF', 'Check login, register, contact, checkout forms and CSRF token patterns.', 'fa-wpforms'],
        'admin_paths' => ['Admin Paths', 'Safely check admin, login, wp-admin, dashboard, cPanel and backend paths.', 'fa-user-shield'],
        'database_risks' => ['Database Risks', 'Check SQL dumps, phpMyAdmin exposure and database error messages.', 'fa-database'],
        'malware_patterns' => ['Malware Patterns', 'Check suspicious scripts, hidden iframes, obfuscated JavaScript and injections.', 'fa-bug'],
        'seo_performance' => ['SEO / Performance', 'Check meta tags, robots, sitemap, response time and heavy assets.', 'fa-gauge-high'],
    ];
@endphp

<div class="space-y-6">

    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-bold">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl lg:text-4xl font-black">SentinelCore Web Scan Report</h1>
                    <span class="px-3 py-1 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-black">
                        <i class="fa-solid fa-circle text-[8px] mr-1"></i> Live
                    </span>
                </div>

                <p class="text-slate-300 mt-2 break-all">{{ $scanUrl }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Domain: {{ $domain }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        IP: {{ $ip }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        HTTP: {{ $httpStatus }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-xs font-bold">
                        Risk: {{ strtoupper($riskLevel) }} / {{ $riskScore }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                @if(Route::has('technology.webscanner.rescan'))
                    <form method="POST" action="{{ route('technology.webscanner.rescan', $scan) }}">
                        @csrf
                        <button class="px-5 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                            <i class="fa-solid fa-rotate mr-2"></i>
                            Re-scan
                        </button>
                    </form>
                @endif

                <a href="{{ Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : url('/technology/web-scanner') }}"
                   class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white font-black">
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

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">Risk Level</p>
            <span class="inline-block mt-3 px-4 py-2 rounded-full border {{ $riskClass }} font-black uppercase">
                {{ $riskLevel }}
            </span>

            <div class="mt-5 h-3 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full {{ $riskBarClass }}" style="width: {{ min(max($riskScore, 0), 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">Risk Score</p>
            <h2 class="text-4xl font-black mt-2">{{ $riskScore }}/100</h2>
            <p class="text-xs text-slate-500 mt-3 font-bold">Higher score means higher risk.</p>
        </div>

        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">SSL Status</p>
            <h2 class="text-2xl font-black mt-2 {{ $sslClass }}">
                {{ $sslText }}
            </h2>
            <p class="text-xs text-slate-500 mt-3 font-bold">HTTPS certificate status.</p>
        </div>

        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">Response Time</p>
            <h2 class="text-2xl font-black mt-2">{{ $responseTime ?? 'N/A' }} ms</h2>
            <p class="text-xs text-slate-500 mt-3 font-bold">HTTP response speed.</p>
        </div>
    </div>

    {{-- Advanced Scan Options --}}
    <div class="bg-white rounded-3xl shadow border overflow-hidden">
        <div class="p-6 border-b bg-slate-50 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">
                    <i class="fa-solid fa-sliders text-blue-600 mr-2"></i>
                    Additional Scan Options
                </h2>
                <p class="text-slate-500 mt-1">
                    Add more checks for WordPress, Laravel, CMS platforms, plugins, exposed files, database risks and frontend frameworks.
                </p>
            </div>

            @if(Route::has('technology.webscanner.rescan'))
                <form method="POST" action="{{ route('technology.webscanner.rescan', $scan) }}">
                    @csrf
                    <input type="hidden" name="advanced_scan" value="1">
                    <button class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                        <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>
                        Run Advanced Scan
                    </button>
                </form>
            @endif
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-4">
                @foreach($scanOptions as $key => [$title, $description, $icon])
                    <label class="rounded-2xl border border-slate-200 p-4 hover:border-blue-300 hover:bg-blue-50/40 transition cursor-pointer">
                        <div class="flex gap-3">
                            <input type="checkbox"
                                   name="scan_options[]"
                                   value="{{ $key }}"
                                   checked
                                   class="mt-1 w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">

                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="w-10 h-10 rounded-xl bg-slate-100 text-blue-600 flex items-center justify-center">
                                        @if(Str::startsWith($icon, 'fa-brands'))
                                            <i class="{{ $icon }}"></i>
                                        @else
                                            <i class="fa-solid {{ $icon }}"></i>
                                        @endif
                                    </span>

                                    <h3 class="font-black text-slate-900">{{ $title }}</h3>
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

    {{-- Platform Widgets --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-brands fa-wordpress text-blue-600 mr-2"></i>
                    WordPress
                </h3>
                <span class="px-3 py-1 rounded-full text-xs font-black {{ $hasWordPress ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $hasWordPress ? 'Detected' : 'Not Detected' }}
                </span>
            </div>
            <div class="p-5 space-y-3">
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">Core / Plugins</span>
                    <span class="font-black text-orange-700 text-xs">Needs Audit</span>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">wp-config.php</span>
                    <span class="font-black text-green-700 text-xs">Protect</span>
                </div>
                <p class="text-sm text-slate-500 font-semibold">
                    Check wp-admin, XML-RPC, REST API, plugin versions, themes and public backup files.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-solid fa-code text-red-600 mr-2"></i>
                    Laravel / PHP
                </h3>
                <span class="px-3 py-1 rounded-full text-xs font-black {{ ($hasLaravel || $hasPhp) ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ ($hasLaravel || $hasPhp) ? 'Detected' : 'Not Detected' }}
                </span>
            </div>
            <div class="p-5 space-y-3">
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">APP_DEBUG</span>
                    <span class="font-black text-red-700 text-xs">Verify False</span>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">.env / Logs</span>
                    <span class="font-black text-orange-700 text-xs">Needs Check</span>
                </div>
                <p class="text-sm text-slate-500 font-semibold">
                    Protect .env, storage logs, composer files, vendor folder and Laravel stack traces.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-solid fa-window-restore text-purple-600 mr-2"></i>
                    Frontend
                </h3>
                <span class="px-3 py-1 rounded-full text-xs font-black {{ ($hasAngular || $hasJquery || $hasBootstrap) ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ ($hasAngular || $hasJquery || $hasBootstrap) ? 'Detected' : 'Check' }}
                </span>
            </div>
            <div class="p-5 space-y-3">
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">Angular / JS</span>
                    <span class="font-black text-slate-700 text-xs">{{ $hasAngular ? 'Detected' : 'Unknown' }}</span>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">jQuery / Bootstrap</span>
                    <span class="font-black text-slate-700 text-xs">{{ ($hasJquery || $hasBootstrap) ? 'Detected' : 'Unknown' }}</span>
                </div>
                <p class="text-sm text-slate-500 font-semibold">
                    Check outdated libraries, source maps, mixed content and injected scripts.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-900">
                    <i class="fa-solid fa-server text-green-600 mr-2"></i>
                    Server / CDN
                </h3>
                <span class="px-3 py-1 rounded-full text-xs font-black {{ ($hasApache || $hasNginx || $hasCloudflare) ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ ($hasApache || $hasNginx || $hasCloudflare) ? 'Detected' : 'Unknown' }}
                </span>
            </div>
            <div class="p-5 space-y-3">
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">Apache / Nginx</span>
                    <span class="font-black text-slate-700 text-xs">{{ $hasApache ? 'Apache' : ($hasNginx ? 'Nginx' : 'Unknown') }}</span>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3 flex justify-between">
                    <span class="font-bold text-slate-600">Cloudflare</span>
                    <span class="font-black text-slate-700 text-xs">{{ $hasCloudflare ? 'Detected' : 'Unknown' }}</span>
                </div>
                <p class="text-sm text-slate-500 font-semibold">
                    Check server headers, WAF, firewall, SSL, cache and CDN protection.
                </p>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="bg-white rounded-3xl shadow border p-6">
        <h2 class="text-2xl font-black mb-3">Summary</h2>
        <p class="text-slate-600 font-semibold leading-7">
            {{ $scan->summary }}
        </p>
    </div>

    {{-- Main Report --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- Detected Technologies --}}
        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-black">Detected Technologies</h2>
                    <p class="text-sm text-slate-500 mt-1">Compact widgets for server, CMS, frameworks and libraries.</p>
                </div>
                <span class="px-3 py-2 rounded-xl bg-blue-100 text-blue-700 text-xs font-black">
                    {{ $technologies->count() }} Found
                </span>
            </div>

            <div class="p-5">
                @if($technologies->count())
                    <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4">
                        @foreach($technologies as $tech)
                            @php
                                $tech = is_array($tech) ? $tech : ['name' => (string) $tech, 'value' => 'Detected'];
                                $name = $tech['name'] ?? $tech['technology'] ?? $tech['title'] ?? '-';
                                $value = $tech['value'] ?? $tech['type'] ?? $tech['status'] ?? 'Detected';
                                $lowerName = strtolower($name);

                                $icon = 'fa-layer-group';
                                if (str_contains($lowerName, 'wordpress')) {
                                    $icon = 'fa-brands fa-wordpress';
                                } elseif (str_contains($lowerName, 'laravel')) {
                                    $icon = 'fa-code';
                                } elseif (str_contains($lowerName, 'php')) {
                                    $icon = 'fa-file-code';
                                } elseif (str_contains($lowerName, 'apache') || str_contains($lowerName, 'nginx')) {
                                    $icon = 'fa-server';
                                } elseif (str_contains($lowerName, 'cloudflare')) {
                                    $icon = 'fa-cloud';
                                } elseif (str_contains($lowerName, 'jquery') || str_contains($lowerName, 'bootstrap') || str_contains($lowerName, 'angular')) {
                                    $icon = 'fa-window-restore';
                                }
                            @endphp

                            <div class="rounded-2xl border p-4 hover:border-blue-300 hover:shadow-md transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                        @if(Str::startsWith($icon, 'fa-brands'))
                                            <i class="{{ $icon }}"></i>
                                        @else
                                            <i class="fa-solid {{ $icon }}"></i>
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <p class="font-black text-slate-900 truncate">{{ $name }}</p>
                                        <p class="text-sm text-slate-500 font-semibold">{{ $value }}</p>
                                    </div>
                                </div>

                                @if(!empty($tech['version']))
                                    <div class="mt-3 rounded-xl bg-slate-50 p-2 text-xs font-black text-slate-600">
                                        Version: {{ $tech['version'] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-slate-500">No technologies detected.</p>
                @endif
            </div>
        </div>

        {{-- Missing Headers --}}
        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50">
                <h2 class="text-xl font-black">Missing Security Headers</h2>
                <p class="text-sm text-slate-500 mt-1">Recommended browser protection headers.</p>
            </div>

            <div class="p-5">
                @if($missingHeaders->count())
                    <div class="flex flex-wrap gap-2">
                        @foreach($missingHeaders as $header)
                            <span class="px-3 py-2 rounded-xl bg-red-100 text-red-700 text-xs font-black">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                {{ is_array($header) ? ($header['name'] ?? $header['header'] ?? 'Header') : $header }}
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-5 rounded-2xl bg-slate-950 text-green-300 p-4 text-xs overflow-auto">
<pre>add_header X-Frame-Options "SAMEORIGIN";
add_header X-Content-Type-Options "nosniff";
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()";
add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';";</pre>
                    </div>
                @else
                    <span class="px-3 py-2 rounded-xl bg-green-100 text-green-700 text-xs font-black">
                        All important headers detected
                    </span>
                @endif
            </div>
        </div>

        {{-- Exposed Files --}}
        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50">
                <h2 class="text-xl font-black">Exposed Files</h2>
                <p class="text-sm text-slate-500 mt-1">Sensitive public files and backup exposure.</p>
            </div>

            <div class="p-5 space-y-3">
                @forelse($exposedFiles as $file)
                    @php
                        $file = is_array($file) ? $file : ['path' => (string) $file, 'risk' => 'Sensitive file exposed'];
                    @endphp

                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                        <p class="font-black text-red-700">{{ $file['path'] ?? $file['url'] ?? '-' }}</p>
                        <p class="text-sm text-red-600">{{ $file['risk'] ?? $file['message'] ?? '-' }}</p>
                    </div>
                @empty
                    <div class="rounded-2xl bg-green-50 border border-green-200 p-5">
                        <p class="text-green-700 font-black">
                            <i class="fa-solid fa-circle-check mr-2"></i>
                            No exposed sensitive files found.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Database Risks --}}
        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b bg-slate-50">
                <h2 class="text-xl font-black">Database Risks</h2>
                <p class="text-sm text-slate-500 mt-1">SQL dump, phpMyAdmin and database error exposure.</p>
            </div>

            <div class="p-5 space-y-3">
                @forelse($databaseRisks as $risk)
                    @php
                        $risk = is_array($risk) ? $risk : ['title' => (string) $risk, 'risk' => 'Database risk detected'];
                    @endphp

                    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                        <p class="font-black text-orange-700">{{ $risk['title'] ?? '-' }}</p>
                        <p class="text-sm text-orange-600">{{ $risk['risk'] ?? $risk['message'] ?? '-' }}</p>
                    </div>
                @empty
                    <div class="rounded-2xl bg-green-50 border border-green-200 p-5">
                        <p class="text-green-700 font-black">
                            <i class="fa-solid fa-circle-check mr-2"></i>
                            No database risks detected.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Framework Security Risks --}}
    <div class="bg-white rounded-3xl shadow border overflow-hidden">
        <div class="p-5 border-b bg-slate-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-black">Framework Security Risks</h2>
                <p class="text-sm text-slate-500 mt-1">
                    WordPress, Laravel, PHP, Angular, Node.js and frontend framework checks.
                </p>
            </div>

            <input type="text"
                   id="frameworkSearch"
                   oninput="filterFrameworkRisks()"
                   placeholder="Search framework risks..."
                   class="w-full md:w-80 px-4 py-3 rounded-2xl border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-5">
            @if($frameworkRisks->count())
                <div id="frameworkRiskList" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    @foreach($frameworkRisks as $risk)
                        @php
                            $risk = is_array($risk) ? $risk : ['framework' => 'Framework', 'title' => (string) $risk, 'risk' => 'Review this risk.'];
                            $level = strtolower($risk['level'] ?? $risk['severity'] ?? 'medium');

                            $levelClass = match($level) {
                                'critical' => 'bg-red-100 text-red-700 border-red-200',
                                'high' => 'bg-orange-100 text-orange-700 border-orange-200',
                                'low', 'info' => 'bg-blue-100 text-blue-700 border-blue-200',
                                default => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                            };
                        @endphp

                        <div class="framework-risk-item rounded-2xl border p-5 hover:bg-slate-50 transition">
                            <div class="flex flex-wrap gap-2 mb-3">
                                <span class="px-3 py-1 rounded-full border {{ $levelClass }} text-[10px] uppercase font-black">
                                    {{ $level }}
                                </span>

                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] uppercase font-black">
                                    {{ $risk['framework'] ?? 'Framework' }}
                                </span>
                            </div>

                            <p class="font-black text-lg text-slate-900">
                                {{ $risk['framework'] ?? 'Framework' }} — {{ $risk['title'] ?? '-' }}
                            </p>

                            <p class="text-sm text-slate-500 mt-2 leading-6">
                                {{ $risk['risk'] ?? $risk['message'] ?? '-' }}
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
                <p class="text-slate-500">No framework-specific risks detected.</p>
            @endif
        </div>
    </div>

    {{-- Developer Recommendations --}}
    <div class="bg-white rounded-3xl shadow border overflow-hidden">
        <div class="p-5 border-b bg-slate-50">
            <h2 class="text-xl font-black">Developer Recommendations</h2>
            <p class="text-sm text-slate-500 mt-1">Actionable fixes for developers and server admins.</p>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
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
                    <p class="font-black">{{ $recommendation['title'] ?? 'Recommendation' }}</p>
                    <p class="text-sm mt-2 font-semibold leading-6">
                        {{ $recommendation['message'] ?? $recommendation['description'] ?? 'Review this item.' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
function filterFrameworkRisks() {
    const query = (document.getElementById('frameworkSearch')?.value || '').toLowerCase();

    document.querySelectorAll('.framework-risk-item').forEach(function (item) {
        item.style.display = item.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
}
</script>

@endsection