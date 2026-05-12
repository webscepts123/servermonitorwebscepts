@extends('layouts.app')

@section('page-title', 'Web Scanner Report')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | Safe Data Helpers
    |--------------------------------------------------------------------------
    */

    function ws_array_value($value) {
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

    $scanUrl = $scan->url ?? $scan->domain ?? '-';
    $domain = $scan->domain ?? parse_url($scanUrl, PHP_URL_HOST) ?? $scanUrl;
    $ip = $scan->ip ?? $scan->server_ip ?? '-';

    $riskLevel = strtolower($scan->risk_level ?? 'low');
    $riskScore = (int) ($scan->risk_score ?? 0);
    $httpStatus = $scan->http_status ?? $scan->status_code ?? null;

    $detectedTechnologies = collect(ws_array_value($scan->detected_technologies ?? []));
    $securityHeaders = collect(ws_array_value($scan->security_headers ?? $scan->headers ?? []));
    $sslInfo = collect(ws_array_value($scan->ssl_info ?? $scan->ssl ?? []));
    $issues = collect(ws_array_value($scan->issues ?? $scan->findings ?? $scan->security_findings ?? []));
    $criticalIssues = collect(ws_array_value($scan->critical_issues ?? []));
    $warningIssues = collect(ws_array_value($scan->warning_issues ?? []));
    $recommendations = collect(ws_array_value($scan->recommendations ?? []));
    $pages = collect(ws_array_value($scan->pages_scanned ?? $scan->pages ?? []));
    $forms = collect(ws_array_value($scan->forms ?? []));
    $links = collect(ws_array_value($scan->links ?? []));
    $rawReport = ws_array_value($scan->raw_report ?? $scan->report ?? []);

    if ($issues->isEmpty()) {
        $issues = $criticalIssues->merge($warningIssues);
    }

    if ($recommendations->isEmpty()) {
        $recommendations = collect([
            ['title' => 'Enable strict security headers', 'message' => 'Add Content-Security-Policy, X-Frame-Options, X-Content-Type-Options and Referrer-Policy headers.'],
            ['title' => 'Keep CMS and frameworks updated', 'message' => 'Check WordPress, Laravel, Magento, Drupal and other CMS/framework versions regularly.'],
            ['title' => 'Review public files', 'message' => 'Make sure .env, backup files, debug pages, logs and admin paths are not publicly exposed.'],
            ['title' => 'Monitor uptime and SSL', 'message' => 'Schedule recurring scans to detect downtime, SSL expiry and HTTP status changes.'],
        ]);
    }

    $riskClass = match ($riskLevel) {
        'critical' => 'bg-red-100 text-red-700 border-red-200',
        'high' => 'bg-orange-100 text-orange-700 border-orange-200',
        'medium' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        default => 'bg-green-100 text-green-700 border-green-200',
    };

    $riskBarClass = match ($riskLevel) {
        'critical' => 'bg-red-600',
        'high' => 'bg-orange-500',
        'medium' => 'bg-yellow-500',
        default => 'bg-green-600',
    };

    $statusClass = match (true) {
        $httpStatus >= 500 => 'bg-red-100 text-red-700 border-red-200',
        $httpStatus >= 400 => 'bg-orange-100 text-orange-700 border-orange-200',
        $httpStatus >= 300 => 'bg-blue-100 text-blue-700 border-blue-200',
        $httpStatus >= 200 => 'bg-green-100 text-green-700 border-green-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200',
    };

    $lastChecked = $scan->scanned_at ?? $scan->checked_at ?? $scan->created_at ?? null;

    $scanOptions = ws_array_value($scan->scan_options ?? []);
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-blue-950 to-slate-900 shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl"></div>

        <div class="relative p-6 lg:p-8 text-white">
            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl lg:text-4xl font-black tracking-tight">
                            Web Scanner Report
                        </h1>

                        <span class="px-3 py-1 rounded-full bg-green-500/20 text-green-100 border border-green-400/30 text-xs font-black">
                            <i class="fa-solid fa-circle text-[8px] mr-1"></i>
                            Live
                        </span>
                    </div>

                    <p class="text-slate-300 mt-2 max-w-4xl break-all">
                        {{ $scanUrl }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black">
                            <i class="fa-solid fa-globe mr-1"></i>
                            {{ $domain }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black">
                            <i class="fa-solid fa-network-wired mr-1"></i>
                            {{ $ip }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black">
                            <i class="fa-solid fa-clock mr-1"></i>
                            {{ $lastChecked ? \Carbon\Carbon::parse($lastChecked)->format('Y-m-d H:i') : 'Not checked' }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black">
                            <i class="fa-solid fa-server mr-1"></i>
                            {{ $scan->server?->name ?? 'Not linked' }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/20 text-white font-black">
                        <i class="fa-solid fa-arrow-left mr-2"></i>
                        Back
                    </a>

                    @if($scanUrl && $scanUrl !== '-')
                        <a href="{{ Str::startsWith($scanUrl, ['http://', 'https://']) ? $scanUrl : 'https://' . $scanUrl }}"
                           target="_blank"
                           class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                            <i class="fa-solid fa-up-right-from-square mr-2"></i>
                            Open Site
                        </a>
                    @endif

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

    {{-- Alerts --}}
    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-black">
            <i class="fa-solid fa-circle-check mr-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-black">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Risk Level</p>
                    <div class="mt-3">
                        <span class="inline-flex px-4 py-2 rounded-2xl border {{ $riskClass }} text-sm font-black uppercase">
                            {{ $riskLevel }} / {{ $riskScore }}
                        </span>
                    </div>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>

            <div class="mt-5 h-3 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full {{ $riskBarClass }}" style="width: {{ min(max($riskScore, 0), 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-black">HTTP Status</p>
                    <div class="mt-3">
                        <span class="inline-flex px-4 py-2 rounded-2xl border {{ $statusClass }} text-sm font-black">
                            HTTP {{ $httpStatus ?? 'N/A' }}
                        </span>
                    </div>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-signal"></i>
                </div>
            </div>

            <p class="text-xs text-slate-500 mt-5 font-bold">
                {{ $scan->response_time ?? $scan->load_time ?? '-' }} ms response time
            </p>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Technologies</p>
                    <h3 class="text-4xl font-black text-slate-900 mt-2">
                        {{ $detectedTechnologies->count() }}
                    </h3>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>

            <p class="text-xs text-slate-500 mt-5 font-bold">
                CMS, frameworks, libraries and server stack.
            </p>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Issues</p>
                    <h3 class="text-4xl font-black text-slate-900 mt-2">
                        {{ $issues->count() }}
                    </h3>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>

            <p class="text-xs text-slate-500 mt-5 font-bold">
                Critical, warning and info findings.
            </p>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="grid grid-cols-1 2xl:grid-cols-3 gap-6">

        {{-- Left Main --}}
        <div class="2xl:col-span-2 space-y-6">

            {{-- Advanced Rescan Options --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">
                            <i class="fa-solid fa-sliders text-blue-600 mr-2"></i>
                            Advanced Scan Options
                        </h2>
                        <p class="text-slate-500 mt-1">
                            Choose deeper checks and run the scan again.
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('technology.webscanner.rescan', $scan) }}"
                          onsubmit="return confirm('Run advanced rescan now?');">
                        @csrf

                        <input type="hidden" name="quick_rescan" value="1">

                        <button class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                            <i class="fa-solid fa-rotate mr-2"></i>
                            Quick Rescan
                        </button>
                    </form>
                </div>

                <form method="POST"
                      action="{{ route('technology.webscanner.rescan', $scan) }}"
                      class="p-6 space-y-6"
                      onsubmit="return confirm('Start advanced scan with selected options?');">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @php
                            $options = [
                                'technology_detection' => ['Technology Detection', 'Detect CMS, frameworks, JS libraries, servers.'],
                                'security_headers' => ['Security Headers', 'Check CSP, HSTS, X-Frame-Options and more.'],
                                'ssl_tls' => ['SSL / TLS Check', 'Check certificate, HTTPS, expiry and weak config.'],
                                'cms_plugins' => ['CMS / Plugin Check', 'WordPress, Magento, Drupal, Joomla and plugins.'],
                                'laravel_debug' => ['Laravel / PHP Issues', 'Check debug mode, exposed env files and errors.'],
                                'admin_paths' => ['Admin Paths', 'Find admin, login, dashboard and backend paths.'],
                                'exposed_files' => ['Exposed Files', 'Check .env, backups, logs, composer and git files.'],
                                'mixed_content' => ['Mixed Content', 'Find HTTP assets on HTTPS websites.'],
                                'forms_csrf' => ['Forms / CSRF', 'Check forms, empty actions and token patterns.'],
                                'seo_basic' => ['SEO Basic', 'Meta title, description, robots and headings.'],
                                'performance' => ['Performance', 'Asset size, response time and blocking assets.'],
                                'malware_patterns' => ['Malware Patterns', 'Detect suspicious scripts and injected code.'],
                            ];
                        @endphp

                        @foreach($options as $key => [$title, $description])
                            <label class="group rounded-2xl border border-slate-200 p-4 cursor-pointer hover:border-blue-300 hover:bg-blue-50/40 transition">
                                <div class="flex items-start gap-3">
                                    <input type="checkbox"
                                           name="options[]"
                                           value="{{ $key }}"
                                           class="mt-1 w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                           {{ in_array($key, $scanOptions) || empty($scanOptions) ? 'checked' : '' }}>

                                    <div>
                                        <div class="font-black text-slate-900">
                                            {{ $title }}
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1 leading-5">
                                            {{ $description }}
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-black text-slate-700 mb-2">Max Pages</label>
                            <input type="number"
                                   name="max_pages"
                                   value="{{ old('max_pages', $scan->max_pages ?? 10) }}"
                                   min="1"
                                   max="100"
                                   class="w-full px-4 py-3 rounded-2xl border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-black text-slate-700 mb-2">Timeout Seconds</label>
                            <input type="number"
                                   name="timeout"
                                   value="{{ old('timeout', $scan->timeout ?? 20) }}"
                                   min="5"
                                   max="120"
                                   class="w-full px-4 py-3 rounded-2xl border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-black text-slate-700 mb-2">User Agent</label>
                            <select name="user_agent" class="w-full px-4 py-3 rounded-2xl border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="desktop">Desktop Browser</option>
                                <option value="mobile">Mobile Browser</option>
                                <option value="bot">Search Bot</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-black text-slate-700 mb-2">Scan Mode</label>
                            <select name="scan_mode" class="w-full px-4 py-3 rounded-2xl border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="safe">Safe Scan</option>
                                <option value="deep">Deep Scan</option>
                                <option value="headers_only">Headers Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pt-2">
                        <div class="text-sm text-slate-500 font-bold">
                            <i class="fa-solid fa-circle-info text-blue-600 mr-1"></i>
                            Safe scan only reads public pages and headers. It does not attack or exploit.
                        </div>

                        <button class="px-7 py-4 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-black">
                            <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>
                            Run Advanced Scan
                        </button>
                    </div>
                </form>
            </div>

            {{-- Technologies --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">
                            Detected Technologies
                        </h2>
                        <p class="text-slate-500 mt-1">
                            CMS, server, framework, language, CDN and JavaScript libraries.
                        </p>
                    </div>

                    <span class="px-4 py-2 rounded-full bg-blue-100 text-blue-700 text-xs font-black">
                        {{ $detectedTechnologies->count() }} found
                    </span>
                </div>

                <div class="p-6">
                    @if($detectedTechnologies->count())
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach($detectedTechnologies as $tech)
                                @php
                                    $tech = is_array($tech) ? $tech : ['name' => (string) $tech];
                                @endphp

                                <div class="rounded-2xl border border-slate-200 p-4 hover:shadow-md transition">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-black text-slate-900">
                                                {{ $tech['name'] ?? 'Unknown' }}
                                            </h3>

                                            <p class="text-xs text-slate-500 mt-1">
                                                {{ $tech['type'] ?? $tech['category'] ?? 'Technology' }}
                                            </p>
                                        </div>

                                        @if(!empty($tech['version']))
                                            <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700 text-[10px] font-black">
                                                v{{ $tech['version'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if(!empty($tech['confidence']))
                                        <div class="mt-4">
                                            <div class="flex justify-between text-xs font-bold text-slate-500 mb-1">
                                                <span>Confidence</span>
                                                <span>{{ $tech['confidence'] }}%</span>
                                            </div>
                                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-blue-600" style="width: {{ (int) $tech['confidence'] }}%"></div>
                                            </div>
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

            {{-- Issues --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">
                            Security Findings
                        </h2>
                        <p class="text-slate-500 mt-1">
                            Issues detected during this scan.
                        </p>
                    </div>

                    <input id="issueSearch"
                           oninput="filterIssues()"
                           placeholder="Search findings..."
                           class="w-full md:w-72 px-4 py-3 rounded-2xl border border-slate-300 outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="p-6">
                    @if($issues->count())
                        <div class="space-y-3" id="issueList">
                            @foreach($issues as $issue)
                                @php
                                    $issue = is_array($issue) ? $issue : ['title' => (string) $issue];
                                    $level = strtolower($issue['level'] ?? $issue['severity'] ?? 'info');

                                    $levelClass = match($level) {
                                        'critical' => 'bg-red-100 text-red-700 border-red-200',
                                        'high' => 'bg-orange-100 text-orange-700 border-orange-200',
                                        'medium', 'warning' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                        default => 'bg-blue-100 text-blue-700 border-blue-200',
                                    };
                                @endphp

                                <div class="issue-item rounded-2xl border border-slate-200 p-5 hover:bg-slate-50 transition">
                                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="px-3 py-1 rounded-full border {{ $levelClass }} text-[10px] font-black uppercase">
                                                    {{ $level }}
                                                </span>

                                                @if(!empty($issue['type']))
                                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black uppercase">
                                                        {{ $issue['type'] }}
                                                    </span>
                                                @endif
                                            </div>

                                            <h3 class="font-black text-slate-900 mt-3">
                                                {{ $issue['title'] ?? $issue['name'] ?? 'Security issue' }}
                                            </h3>

                                            <p class="text-slate-600 text-sm mt-2 leading-6">
                                                {{ $issue['message'] ?? $issue['description'] ?? $issue['details'] ?? 'No details available.' }}
                                            </p>

                                            @if(!empty($issue['url']))
                                                <p class="text-xs text-slate-500 mt-2 break-all">
                                                    <i class="fa-solid fa-link mr-1"></i>
                                                    {{ $issue['url'] }}
                                                </p>
                                            @endif
                                        </div>

                                        @if(!empty($issue['recommendation']))
                                            <div class="md:w-72 rounded-2xl bg-green-50 border border-green-200 p-4">
                                                <div class="text-xs font-black text-green-700 uppercase">Fix</div>
                                                <p class="text-sm text-green-800 font-bold mt-1">
                                                    {{ $issue['recommendation'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-3xl bg-green-50 border border-green-200 p-8 text-center">
                            <i class="fa-solid fa-circle-check text-5xl text-green-600"></i>
                            <h3 class="text-xl font-black text-green-900 mt-3">No security findings detected</h3>
                            <p class="text-green-700 mt-1 font-bold">Run advanced scan for deeper checks.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Pages / Forms --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b bg-slate-50">
                        <h2 class="text-2xl font-black text-slate-900">Pages Scanned</h2>
                        <p class="text-slate-500 mt-1">Crawled URLs and status codes.</p>
                    </div>

                    <div class="p-6">
                        @if($pages->count())
                            <div class="space-y-3 max-h-[420px] overflow-y-auto pr-2">
                                @foreach($pages as $page)
                                    @php
                                        $page = is_array($page) ? $page : ['url' => (string) $page];
                                        $pageStatus = $page['status'] ?? $page['http_status'] ?? null;
                                    @endphp

                                    <div class="rounded-2xl border border-slate-200 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="font-bold text-slate-900 text-sm break-all">
                                                {{ $page['url'] ?? '-' }}
                                            </div>

                                            <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700 text-[10px] font-black">
                                                {{ $pageStatus ?? 'N/A' }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center text-slate-500 py-8 font-bold">
                                No page crawl data.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b bg-slate-50">
                        <h2 class="text-2xl font-black text-slate-900">Forms Found</h2>
                        <p class="text-slate-500 mt-1">Login, register, contact and checkout forms.</p>
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
                                            <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-[10px] font-black">
                                                {{ strtoupper($form['method'] ?? 'GET') }}
                                            </span>

                                            @if(!empty($form['csrf']))
                                                <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-[10px] font-black">
                                                    CSRF
                                                </span>
                                            @else
                                                <span class="px-2 py-1 rounded-lg bg-yellow-100 text-yellow-700 text-[10px] font-black">
                                                    CSRF Unknown
                                                </span>
                                            @endif
                                        </div>

                                        <div class="font-bold text-slate-900 text-sm break-all">
                                            {{ $form['action'] ?? 'No action' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center text-slate-500 py-8 font-bold">
                                No forms found.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Sidebar --}}
        <div class="space-y-6">

            {{-- Scan Meta --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50">
                    <h2 class="text-2xl font-black text-slate-900">Scan Details</h2>
                </div>

                <div class="p-6 space-y-4">
                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-black">URL</p>
                        <p class="font-black text-slate-900 mt-1 break-all">{{ $scanUrl }}</p>
                    </div>

                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Domain</p>
                        <p class="font-black text-slate-900 mt-1 break-all">{{ $domain }}</p>
                    </div>

                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-black">IP Address</p>
                        <p class="font-black text-slate-900 mt-1 break-all">{{ $ip }}</p>
                    </div>

                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Server</p>
                        <p class="font-black text-slate-900 mt-1 break-all">{{ $scan->server?->name ?? 'Not linked' }}</p>
                    </div>
                </div>
            </div>

            {{-- Security Headers --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50">
                    <h2 class="text-2xl font-black text-slate-900">Security Headers</h2>
                </div>

                <div class="p-6 space-y-3">
                    @php
                        $importantHeaders = [
                            'Content-Security-Policy',
                            'Strict-Transport-Security',
                            'X-Frame-Options',
                            'X-Content-Type-Options',
                            'Referrer-Policy',
                            'Permissions-Policy',
                        ];
                    @endphp

                    @foreach($importantHeaders as $header)
                        @php
                            $value = $securityHeaders->get($header) ?? $securityHeaders->get(strtolower($header));
                            $hasHeader = !empty($value);
                        @endphp

                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 p-4">
                            <div class="font-black text-slate-800 text-sm">
                                {{ $header }}
                            </div>

                            @if($hasHeader)
                                <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-[10px] font-black">
                                    Found
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-[10px] font-black">
                                    Missing
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- SSL --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50">
                    <h2 class="text-2xl font-black text-slate-900">SSL / HTTPS</h2>
                </div>

                <div class="p-6 space-y-4">
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-black">HTTPS</p>
                        <p class="font-black mt-1 {{ ($sslInfo->get('valid') || Str::startsWith($scanUrl, 'https://')) ? 'text-green-700' : 'text-red-700' }}">
                            {{ ($sslInfo->get('valid') || Str::startsWith($scanUrl, 'https://')) ? 'Enabled' : 'Not detected' }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Issuer</p>
                        <p class="font-black text-slate-900 mt-1 break-all">
                            {{ $sslInfo->get('issuer') ?? '-' }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-slate-500 font-black">Expires</p>
                        <p class="font-black text-slate-900 mt-1">
                            {{ $sslInfo->get('expires_at') ?? $sslInfo->get('valid_to') ?? '-' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Recommendations --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50">
                    <h2 class="text-2xl font-black text-slate-900">Recommendations</h2>
                </div>

                <div class="p-6 space-y-3">
                    @foreach($recommendations as $recommendation)
                        @php
                            $recommendation = is_array($recommendation)
                                ? $recommendation
                                : ['title' => (string) $recommendation, 'message' => 'Review this recommendation.'];
                        @endphp

                        <div class="rounded-2xl bg-blue-50 border border-blue-200 p-4">
                            <h3 class="font-black text-blue-950">
                                {{ $recommendation['title'] ?? 'Recommendation' }}
                            </h3>

                            <p class="text-sm text-blue-800 mt-1 font-bold leading-6">
                                {{ $recommendation['message'] ?? $recommendation['description'] ?? 'Review this item.' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b bg-slate-50">
                    <h2 class="text-2xl font-black text-slate-900">Actions</h2>
                </div>

                <div class="p-6 space-y-3">
                    <button type="button"
                            onclick="copyReportLink()"
                            class="w-full px-5 py-3 rounded-2xl bg-slate-900 text-white font-black hover:bg-slate-800">
                        <i class="fa-solid fa-copy mr-2"></i>
                        Copy Report Link
                    </button>

                    <button type="button"
                            onclick="window.print()"
                            class="w-full px-5 py-3 rounded-2xl bg-blue-600 text-white font-black hover:bg-blue-700">
                        <i class="fa-solid fa-file-pdf mr-2"></i>
                        Print / Save PDF
                    </button>

                    <form method="POST"
                          action="{{ route('technology.webscanner.destroy', $scan) }}"
                          onsubmit="return confirm('Delete this scan report?');">
                        @csrf
                        @method('DELETE')

                        <button class="w-full px-5 py-3 rounded-2xl bg-red-600 text-white font-black hover:bg-red-700">
                            <i class="fa-solid fa-trash mr-2"></i>
                            Delete Scan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Raw Report --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <button type="button"
                onclick="toggleRawReport()"
                class="w-full p-6 bg-slate-50 border-b text-left flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Raw Scan Data</h2>
                <p class="text-slate-500 mt-1">Developer/debug JSON report data.</p>
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
        .ws-top-button,
        button,
        form,
        .no-print {
            display: none !important;
        }

        body {
            background: white !important;
        }

        .shadow,
        .shadow-xl {
            box-shadow: none !important;
        }
    }
</style>

<script>
function copyReportLink() {
    const url = window.location.href;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
            alert('Report link copied.');
        });

        return;
    }

    prompt('Copy this report link:', url);
}

function toggleRawReport() {
    const box = document.getElementById('rawReportBox');

    if (!box) {
        return;
    }

    box.classList.toggle('hidden');
}

function filterIssues() {
    const query = (document.getElementById('issueSearch')?.value || '').toLowerCase();

    document.querySelectorAll('.issue-item').forEach(function (item) {
        item.style.display = item.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
}
</script>

@endsection