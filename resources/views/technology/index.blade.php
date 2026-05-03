@extends('layouts.app')

@section('page-title', 'Webscepts SentinelCore')

@section('content')

@php
    $securityStats = $securityStats ?? [];
    $securityChecks = $securityChecks ?? [];
    $recentAlerts = $recentAlerts ?? collect();

    $frameworkModules = [
        [
            'name' => 'WordPress Security',
            'icon' => 'fa-brands fa-wordpress',
            'color' => 'blue',
            'checks' => [
                'Detect WordPress core, wp-content, wp-json and login endpoints',
                'Scan wp-config.php exposure and directory listing',
                'Check plugin/theme update risks',
                'Detect public author/user enumeration',
                'Check XML-RPC and REST API exposure',
                'Detect WooCommerce and eCommerce risks',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'name' => 'Laravel Security',
            'icon' => 'fa-brands fa-laravel',
            'color' => 'red',
            'checks' => [
                'Detect exposed .env files',
                'Detect APP_DEBUG leaks and Laravel error pages',
                'Check storage/logs/laravel.log exposure',
                'Scan composer.json and vendor exposure',
                'Protect database credentials and APP_KEY',
                'Check public folder misconfiguration',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'name' => 'Angular Security',
            'icon' => 'fa-brands fa-angular',
            'color' => 'purple',
            'checks' => [
                'Detect Angular app bundles and ng-version',
                'Check source map exposure',
                'Detect environment.ts / API endpoint leaks',
                'Check CSP compatibility',
                'Detect exposed frontend build files',
                'Review client-side secret exposure',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'name' => 'Node.js / Express',
            'icon' => 'fa-brands fa-node-js',
            'color' => 'green',
            'checks' => [
                'Detect Node.js, Express, Next.js and Nuxt.js',
                'Check package.json and lock file exposure',
                'Detect stack traces and API errors',
                'Scan .env and server-side secret risks',
                'Check risky open debug endpoints',
                'Review API security headers',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'name' => 'PHP Frameworks',
            'icon' => 'fa-brands fa-php',
            'color' => 'indigo',
            'checks' => [
                'Detect PHP version/header exposure',
                'Check phpinfo.php and info.php exposure',
                'Scan CodeIgniter, Symfony, Yii, CakePHP indicators',
                'Detect SQL/PHP warning leaks',
                'Check public config.php exposure',
                'Review disabled security headers',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'name' => 'React / Vue Frontend',
            'icon' => 'fa-solid fa-code',
            'color' => 'cyan',
            'checks' => [
                'Detect React, Vue, Next.js and Nuxt.js',
                'Check source maps and build artifacts',
                'Detect public API keys in frontend bundle',
                'Check CSP and X-Frame protections',
                'Scan exposed runtime config files',
                'Review static asset fingerprinting',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'name' => 'MySQL / MariaDB',
            'icon' => 'fa-solid fa-database',
            'color' => 'orange',
            'checks' => [
                'Detect SQLSTATE and MySQL error leaks',
                'Check database.sql / dump.sql exposure',
                'Detect phpMyAdmin public risks',
                'Review Laravel DB exception exposure',
                'Check backup SQL files in public folders',
                'Recommend DB credential encryption',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'name' => 'PostgreSQL',
            'icon' => 'fa-solid fa-server',
            'color' => 'slate',
            'checks' => [
                'Detect PostgreSQL error leakage',
                'Check pgAdmin exposure indicators',
                'Detect psql syntax traces',
                'Review database dump exposure',
                'Check app-level DB exception visibility',
                'Recommend encrypted connection secrets',
            ],
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
    ];

    $securityLayers = [
        [
            'title' => 'Web Scanner',
            'description' => 'Scan websites for exposed files, missing headers, SSL issues, framework risks and database leaks.',
            'icon' => 'fa-magnifying-glass-chart',
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
            'button' => 'Open Scanner',
            'color' => 'blue',
        ],
        [
            'title' => 'Encryption Vault',
            'description' => 'Encrypt uploaded server files, database exports, API secrets, keys and private configuration files.',
            'icon' => 'fa-file-shield',
            'route' => null,
            'button' => 'Use Vault Below',
            'color' => 'purple',
        ],
        [
            'title' => 'Credential Rotation',
            'description' => 'Re-encrypt stored server credentials using Laravel application encryption.',
            'icon' => 'fa-arrows-rotate',
            'route' => null,
            'button' => 'Rotate Encryption',
            'color' => 'red',
        ],
        [
            'title' => 'DNS Failover Security',
            'description' => 'Protect linked domains by switching A records to backup server during disk or server failure.',
            'icon' => 'fa-globe',
            'route' => Route::has('domains.index') ? route('domains.index') : null,
            'button' => 'Domain Manager',
            'color' => 'green',
        ],
        [
            'title' => 'Backup Protection',
            'description' => 'Secure selected cPanel account backups, Google Drive sync, local vault and backup server transfer.',
            'icon' => 'fa-cloud-arrow-up',
            'route' => Route::has('backups.index') ? route('backups.index') : null,
            'button' => 'Backup Manager',
            'color' => 'orange',
        ],
        [
            'title' => 'Server Security Alerts',
            'description' => 'Monitor SSH, firewall, ports, services, malware signs, failed logins and resource usage.',
            'icon' => 'fa-shield-halved',
            'route' => Route::has('security.alerts') ? route('security.alerts') : null,
            'button' => 'View Alerts',
            'color' => 'slate',
        ],
    ];

    $hardeningChecklist = [
        'WordPress' => [
            'Disable file editing in wp-config.php',
            'Hide wp-config.php and protect wp-admin',
            'Remove unused plugins and themes',
            'Disable XML-RPC if not needed',
            'Enable WAF or LiteSpeed security rules',
            'Keep WordPress core, plugin and theme versions updated',
        ],
        'Laravel' => [
            'Set APP_DEBUG=false in production',
            'Never expose .env or storage/logs',
            'Use php artisan config:cache after deployment',
            'Store files outside public_html/public',
            'Encrypt credentials and API tokens',
            'Protect admin routes with authentication and throttling',
        ],
        'Angular / React / Vue' => [
            'Do not store secrets in frontend environment files',
            'Disable public source maps in production',
            'Use strict Content-Security-Policy',
            'Validate all API responses server-side',
            'Avoid exposing internal API URLs',
            'Use secure cookies and token rotation',
        ],
        'Node.js' => [
            'Hide stack traces in production',
            'Use helmet security headers',
            'Do not expose package.json or .env',
            'Rate-limit login and API endpoints',
            'Patch npm dependencies regularly',
            'Run Node apps behind Nginx/LiteSpeed proxy',
        ],
        'Database' => [
            'Never place SQL dumps in public folders',
            'Encrypt database credentials',
            'Restrict DB access to localhost/private network',
            'Rotate MySQL/PostgreSQL passwords',
            'Backup with encrypted storage',
            'Monitor DB error leakage on websites',
        ],
    ];

    $riskColors = [
        'green' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200', 'button' => 'bg-green-600 hover:bg-green-700'],
        'blue' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'button' => 'bg-blue-600 hover:bg-blue-700'],
        'red' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200', 'button' => 'bg-red-600 hover:bg-red-700'],
        'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'button' => 'bg-purple-600 hover:bg-purple-700'],
        'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'button' => 'bg-orange-600 hover:bg-orange-700'],
        'indigo' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'button' => 'bg-indigo-600 hover:bg-indigo-700'],
        'cyan' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-700', 'border' => 'border-cyan-200', 'button' => 'bg-cyan-600 hover:bg-cyan-700'],
        'slate' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'button' => 'bg-slate-900 hover:bg-slate-700'],
    ];
@endphp

<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-3xl lg:text-5xl font-black tracking-tight">
                        Webscepts SentinelCore
                    </h1>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        <span class="inline-block w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                        Enterprise Technology Core
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-4xl">
                    Enterprise security technology core for encrypted server credentials, protected backup files,
                    WordPress, Laravel, Angular, Node.js, PHP frameworks, database privacy, web scanning,
                    DNS failover and customer file protection.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        <i class="fa-solid fa-lock mr-1"></i> Encryption Enabled
                    </span>

                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Laravel Crypt
                    </span>

                    <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                        Secure Vault
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                        Customer File Shield
                    </span>

                    <span class="px-4 py-2 rounded-full bg-orange-500/20 border border-orange-400/40 text-orange-100 text-xs font-bold">
                        Framework Scanner
                    </span>

                    <span class="px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold">
                        DB Leak Detection
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                @if(Route::has('technology.webscanner.index'))
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                        <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>
                        Web Scanner
                    </a>
                @endif

                <form method="POST"
                      action="{{ route('technology.rotate.passwords') }}"
                      onsubmit="return confirm('Re-encrypt all server password records?')">
                    @csrf

                    <button class="w-full px-6 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                        <i class="fa-solid fa-arrows-rotate mr-2"></i>
                        Rotate Encryption
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ALERTS --}}
    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-bold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-bold">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4">
            <div class="font-black mb-2">Please fix these errors:</div>
            <ul class="list-disc ml-5 text-sm font-semibold">
                @foreach($errors->all() as $errorItem)
                    <li>{{ $errorItem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('encrypted_text'))
        <div class="rounded-3xl bg-white shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <h3 class="text-lg font-black text-slate-900">Encrypted Output</h3>
                <button type="button"
                        onclick="copyText('encryptedOutput')"
                        class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-black">
                    Copy
                </button>
            </div>
            <textarea id="encryptedOutput" readonly class="w-full min-h-32 rounded-xl border p-4 text-xs font-mono bg-slate-50">{{ session('encrypted_text') }}</textarea>
        </div>
    @endif

    @if(session('decrypted_text'))
        <div class="rounded-3xl bg-white shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <h3 class="text-lg font-black text-slate-900">Decrypted Output</h3>
                <button type="button"
                        onclick="copyText('decryptedOutput')"
                        class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-black">
                    Copy
                </button>
            </div>
            <textarea id="decryptedOutput" readonly class="w-full min-h-32 rounded-xl border p-4 text-sm bg-slate-50">{{ session('decrypted_text') }}</textarea>
        </div>
    @endif

    @if(session('encrypted_file_path'))
        <div class="rounded-2xl bg-blue-100 border border-blue-300 text-blue-800 p-4 font-bold">
            <i class="fa-solid fa-file-shield mr-2"></i>
            Encrypted file saved: {{ session('encrypted_file_path') }}
        </div>
    @endif

    {{-- STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">Protected Servers</p>
                    <h2 class="text-4xl font-black mt-2">{{ $securityStats['servers'] ?? 0 }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-server text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">Encrypted Credentials</p>
                    <h2 class="text-4xl font-black mt-2 text-green-600">{{ $securityStats['encrypted_passwords'] ?? 0 }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-key text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">DNS Failover</p>
                    <h2 class="text-4xl font-black mt-2 text-blue-600">{{ $securityStats['dns_failover'] ?? 0 }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-globe text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">Backup Failover</p>
                    <h2 class="text-4xl font-black mt-2 text-red-600">{{ $securityStats['backup_failover'] ?? 0 }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                    <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- SENTINELCORE SECURITY LAYERS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">SentinelCore Security Layers</h2>
                <p class="text-slate-500 mt-1">Enterprise modules for scanning, encryption, backup, DNS and security monitoring.</p>
            </div>

            <input type="text"
                   id="layerSearch"
                   oninput="filterCards('layerSearch', '.security-layer-card')"
                   placeholder="Search security modules..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($securityLayers as $layer)
                @php
                    $color = $riskColors[$layer['color']] ?? $riskColors['blue'];
                @endphp

                <div class="security-layer-card rounded-3xl border {{ $color['border'] }} p-5 hover:shadow-lg transition bg-white">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl {{ $color['bg'] }} {{ $color['text'] }} flex items-center justify-center shrink-0">
                            <i class="fa-solid {{ $layer['icon'] }} text-xl"></i>
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-lg font-black text-slate-900">{{ $layer['title'] }}</h3>
                            <p class="text-sm text-slate-500 mt-1">{{ $layer['description'] }}</p>

                            @if($layer['route'])
                                <a href="{{ $layer['route'] }}"
                                   class="inline-flex mt-4 px-4 py-2 rounded-xl {{ $color['button'] }} text-white text-sm font-black">
                                    {{ $layer['button'] }}
                                </a>
                            @else
                                <span class="inline-flex mt-4 px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-black">
                                    {{ $layer['button'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- FRAMEWORK SECURITY MODULES --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Framework & Database Security Modules</h2>
                <p class="text-slate-500 mt-1">
                    WordPress, Laravel, Angular, Node.js, PHP, React/Vue, MySQL and PostgreSQL risk modules.
                </p>
            </div>

            <input type="text"
                   id="frameworkSearch"
                   oninput="filterCards('frameworkSearch', '.framework-card')"
                   placeholder="Search frameworks..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6 grid grid-cols-1 xl:grid-cols-2 gap-5">
            @foreach($frameworkModules as $module)
                @php
                    $color = $riskColors[$module['color']] ?? $riskColors['blue'];
                @endphp

                <div class="framework-card rounded-3xl border {{ $color['border'] }} p-5 hover:shadow-lg transition bg-white">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-5">
                        <div class="w-16 h-16 rounded-3xl {{ $color['bg'] }} {{ $color['text'] }} flex items-center justify-center shrink-0">
                            <i class="{{ $module['icon'] }} text-2xl"></i>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <h3 class="text-xl font-black text-slate-900">{{ $module['name'] }}</h3>

                                @if($module['route'])
                                    <a href="{{ $module['route'] }}"
                                       class="px-4 py-2 rounded-xl {{ $color['button'] }} text-white text-xs font-black text-center">
                                        Scan
                                    </a>
                                @endif
                            </div>

                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($module['checks'] as $check)
                                    <div class="flex items-start gap-2 rounded-xl bg-slate-50 border border-slate-100 p-3">
                                        <i class="fa-solid fa-check-circle text-green-600 mt-0.5"></i>
                                        <p class="text-sm text-slate-600 font-semibold">{{ $check }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- TECHNOLOGY CHECKS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-2xl font-black text-slate-900">SentinelCore Technology Checks</h2>
            <p class="text-slate-500 mt-1">Encryption, database privacy and server file protection status.</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 p-6">
            @foreach($securityChecks as $check)
                <div class="rounded-2xl border p-5 hover:shadow transition">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl {{ $check['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} flex items-center justify-center">
                            <i class="fa-solid {{ $check['status'] ? 'fa-shield-check' : 'fa-triangle-exclamation' }}"></i>
                        </div>

                        <div class="min-w-0">
                            <h3 class="font-black text-slate-900">{{ $check['title'] }}</h3>
                            <p class="text-sm text-slate-500 mt-1">{{ $check['description'] }}</p>

                            <span class="inline-flex mt-3 px-3 py-1 rounded-full text-xs font-black {{ $check['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $check['status'] ? 'Protected' : 'Action Needed' }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- HARDENING CHECKLIST --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-2xl font-black text-slate-900">Enterprise Hardening Checklist</h2>
            <p class="text-slate-500 mt-1">Use these rules to secure different application stacks.</p>
        </div>

        <div class="p-6">
            <div class="flex flex-wrap gap-2 mb-5">
                @foreach(array_keys($hardeningChecklist) as $index => $group)
                    <button type="button"
                            onclick="showChecklist('{{ \Illuminate\Support\Str::slug($group) }}')"
                            class="checklist-tab px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-sm {{ $index === 0 ? 'active-tab' : '' }}">
                        {{ $group }}
                    </button>
                @endforeach
            </div>

            @foreach($hardeningChecklist as $group => $items)
                <div id="checklist-{{ \Illuminate\Support\Str::slug($group) }}"
                     class="checklist-panel {{ !$loop->first ? 'hidden' : '' }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($items as $item)
                            <div class="rounded-2xl border p-4 flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-green-100 text-green-700 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <p class="text-sm font-semibold text-slate-600">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ENCRYPTION TOOLS --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- TEXT ENCRYPT --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-xl font-black text-slate-900 mb-2">
                Encrypt Sensitive Text
            </h2>
            <p class="text-sm text-slate-500 mb-5">
                Encrypt API keys, credentials, database secrets, WordPress salts, Laravel APP_KEY notes or private server details.
            </p>

            <form method="POST" action="{{ route('technology.encrypt.text') }}" class="space-y-4">
                @csrf

                <textarea name="plain_text"
                          rows="6"
                          required
                          placeholder="Paste sensitive text here..."
                          class="w-full rounded-2xl border p-4 outline-none focus:ring-2 focus:ring-blue-500"></textarea>

                <button class="px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    <i class="fa-solid fa-lock mr-2"></i>
                    Encrypt Text
                </button>
            </form>
        </div>

        {{-- TEXT DECRYPT --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-xl font-black text-slate-900 mb-2">
                Decrypt Protected Text
            </h2>
            <p class="text-sm text-slate-500 mb-5">
                Decrypt only inside authenticated admin panel. Never expose decrypted secrets to public users.
            </p>

            <form method="POST" action="{{ route('technology.decrypt.text') }}" class="space-y-4">
                @csrf

                <textarea name="encrypted_text"
                          rows="6"
                          required
                          placeholder="Paste encrypted payload..."
                          class="w-full rounded-2xl border p-4 outline-none focus:ring-2 focus:ring-purple-500"></textarea>

                <button class="px-5 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-black">
                    <i class="fa-solid fa-unlock-keyhole mr-2"></i>
                    Decrypt Text
                </button>
            </form>
        </div>

    </div>

    {{-- FILE ENCRYPTION --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
            <div>
                <h2 class="text-xl font-black text-slate-900">
                    SentinelCore File Vault
                </h2>
                <p class="text-sm text-slate-500">
                    Encrypt uploaded files before storing them in Laravel storage. Use this for database exports,
                    backup config files, security notes, SSL keys, server credentials and customer files.
                </p>
            </div>

            <div class="px-4 py-2 rounded-full bg-slate-100 text-slate-700 text-xs font-black">
                storage/app/sentinel-vault
            </div>
        </div>

        <form method="POST"
              action="{{ route('technology.encrypt.file') }}"
              enctype="multipart/form-data"
              class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @csrf

            <div class="lg:col-span-2">
                <input type="file"
                       name="secure_file"
                       required
                       class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <button class="px-5 py-3 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-black">
                <i class="fa-solid fa-file-shield mr-2"></i>
                Encrypt File
            </button>
        </form>
    </div>

    {{-- SECURITY RULES --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-xl font-black text-slate-900 mb-4">
            Enterprise Protection Rules
        </h2>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Database Protection
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Never store raw passwords, API tokens, customer private data or server credentials directly.
                    Use encryption before saving and never expose SQL dumps publicly.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    File Protection
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Store sensitive files only in storage/app. Do not place database exports, .env files,
                    backups, cpmove archives or customer files inside public_html/public.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Framework Protection
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Scan WordPress, Laravel, Angular, Node.js and PHP applications for exposed configs,
                    public debug pages, dependency leaks and missing headers.
                </p>
            </div>
        </div>
    </div>

    {{-- RECENT ALERTS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-900">Recent SentinelCore Alerts</h2>
                <p class="text-sm text-slate-500">Latest encryption, security, backup, DNS and web-scan alerts.</p>
            </div>

            <input type="text"
                   id="alertSearch"
                   oninput="filterCards('alertSearch', '.sentinel-alert-row')"
                   placeholder="Search alerts..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="divide-y">
            @forelse($recentAlerts as $alert)
                <div class="sentinel-alert-row p-5 hover:bg-slate-50">
                    <div class="flex items-start gap-4">
                        <div class="w-11 h-11 rounded-2xl
                            @if($alert->level === 'danger') bg-red-100 text-red-700
                            @elseif($alert->level === 'warning') bg-yellow-100 text-yellow-700
                            @else bg-blue-100 text-blue-700
                            @endif
                            flex items-center justify-center">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-black text-slate-900">{{ $alert->title }}</h3>
                                <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black uppercase">
                                    {{ $alert->level }}
                                </span>
                                <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black uppercase">
                                    {{ $alert->type ?? 'security' }}
                                </span>
                            </div>

                            <p class="text-sm text-slate-500 mt-1">
                                {{ \Illuminate\Support\Str::limit($alert->message, 180) }}
                            </p>

                            <p class="text-xs text-slate-400 mt-2">
                                {{ $alert->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-slate-500">
                    No security alerts found.
                </div>
            @endforelse
        </div>
    </div>

</div>

<style>
    .active-tab {
        background: #0f172a !important;
        color: white !important;
    }
</style>

<script>
function filterCards(inputId, cardSelector) {
    const input = document.getElementById(inputId);
    const value = input ? input.value.toLowerCase() : '';
    const cards = document.querySelectorAll(cardSelector);

    cards.forEach(function (card) {
        card.style.display = card.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
}

function showChecklist(group) {
    document.querySelectorAll('.checklist-panel').forEach(function(panel) {
        panel.classList.add('hidden');
    });

    document.querySelectorAll('.checklist-tab').forEach(function(tab) {
        tab.classList.remove('active-tab');
    });

    const panel = document.getElementById('checklist-' + group);

    if (panel) {
        panel.classList.remove('hidden');
    }

    event.currentTarget.classList.add('active-tab');
}

function copyText(id) {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    navigator.clipboard.writeText(el.value || el.innerText || '');
    alert('Copied');
}
</script>

@endsection