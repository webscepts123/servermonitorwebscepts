@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('content')

@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Carbon;

    $servers = $servers ?? collect();
    $latestChecks = $latestChecks ?? collect();
    $enterpriseModules = $enterpriseModules ?? [];
    $sentinelModules = $sentinelModules ?? [];
    $sentinelStats = $sentinelStats ?? [];
    $latestWebScans = $latestWebScans ?? collect();
    $latestSecurityAlerts = $latestSecurityAlerts ?? collect();
    $frameworkSecurity = $frameworkSecurity ?? [];

    // Build latest check lookup so this blade still works even if controller did not attach latest_check.
    $latestChecksByServer = collect($latestChecks)->filter(function ($check) {
        return !empty($check->server_id);
    })->sortByDesc(function ($check) {
        return $check->created_at ?? $check->id ?? 0;
    })->keyBy('server_id');

    $totalServers = $totalServers ?? $servers->count();
    $onlineServers = $onlineServers ?? $servers->where('status', 'online')->count();
    $offlineServers = $offlineServers ?? $servers->where('status', 'offline')->count();
    $activeServers = $activeServers ?? $servers->where('is_active', true)->count();
    $inactiveServers = $inactiveServers ?? max($totalServers - $activeServers, 0);

    $avgResponseTime = $avgResponseTime ?? null;
    $avgCpu = $avgCpu ?? 0;
    $avgRam = $avgRam ?? 0;
    $avgDisk = $avgDisk ?? 0;

    $criticalDiskServers = $criticalDiskServers ?? 0;
    $highCpuServers = $highCpuServers ?? 0;
    $highRamServers = $highRamServers ?? 0;

    $securityScore = $securityScore ?? 0;
    $securityLevel = $securityLevel ?? 'Needs Setup';

    $lastCheckedAt = $lastCheckedAt ?? null;

    $riskBadge = function ($level) {
        $level = strtolower((string) $level);

        return match ($level) {
            'critical' => 'bg-red-100 text-red-700 border-red-200',
            'high' => 'bg-orange-100 text-orange-700 border-orange-200',
            'medium' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'low' => 'bg-green-100 text-green-700 border-green-200',
            'danger' => 'bg-red-100 text-red-700 border-red-200',
            'warning' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };

    $statusBadge = function ($status) {
        $status = strtolower((string) $status);

        if ($status === 'online') {
            return 'bg-green-100 text-green-700 border-green-200';
        }

        if ($status === 'offline') {
            return 'bg-red-100 text-red-700 border-red-200';
        }

        return 'bg-yellow-100 text-yellow-700 border-yellow-200';
    };

    $moduleColor = function ($color) {
        return match ($color) {
            'blue' => 'bg-blue-100 text-blue-700',
            'green' => 'bg-green-100 text-green-700',
            'purple' => 'bg-purple-100 text-purple-700',
            'orange' => 'bg-orange-100 text-orange-700',
            'red' => 'bg-red-100 text-red-700',
            'cyan' => 'bg-cyan-100 text-cyan-700',
            default => 'bg-slate-100 text-slate-700',
        };
    };

    $securityScoreClass = match (true) {
        $securityScore >= 85 => 'text-green-600',
        $securityScore >= 65 => 'text-blue-600',
        $securityScore >= 40 => 'text-yellow-600',
        default => 'text-red-600',
    };

    $securityScoreBar = match (true) {
        $securityScore >= 85 => 'from-green-500 to-emerald-600',
        $securityScore >= 65 => 'from-blue-500 to-cyan-600',
        $securityScore >= 40 => 'from-yellow-500 to-orange-500',
        default => 'from-red-500 to-orange-600',
    };

    $safeRoute = function ($name, $params = []) {
        return Route::has($name) ? route($name, $params) : '#';
    };

    $metricNumber = function ($value) {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $clean === '' ? 0 : (float) $clean;
    };

    $objectValue = function ($object, array $keys) {
        if (!$object) {
            return null;
        }

        foreach ($keys as $key) {
            if (is_array($object) && array_key_exists($key, $object) && $object[$key] !== null && $object[$key] !== '') {
                return $object[$key];
            }

            if (is_object($object) && isset($object->{$key}) && $object->{$key} !== null && $object->{$key} !== '') {
                return $object->{$key};
            }
        }

        return null;
    };

    $serverMetric = function ($server, array $serverKeys, array $checkKeys = []) use ($metricNumber, $objectValue, $latestChecksByServer) {
        $checkKeys = $checkKeys ?: $serverKeys;

        $serverValue = $objectValue($server, $serverKeys);
        if ($serverValue !== null) {
            return $metricNumber($serverValue);
        }

        $latestCheck = $server->latest_check ?? $server->latestCheck ?? null;

        if (!$latestCheck && !empty($server->id)) {
            $latestCheck = $latestChecksByServer->get($server->id);
        }

        $checkValue = $objectValue($latestCheck, $checkKeys);
        if ($checkValue !== null) {
            return $metricNumber($checkValue);
        }

        return 0;
    };

    $serverLatestCheck = function ($server) use ($latestChecksByServer) {
        if (!empty($server->latest_check)) {
            return $server->latest_check;
        }

        if (!empty($server->latestCheck)) {
            return $server->latestCheck;
        }

        if (!empty($server->id)) {
            return $latestChecksByServer->get($server->id);
        }

        return null;
    };

    // Recalculate averages from live rows when controller sends empty/zero values.
    $calculatedCpuValues = collect($servers)->map(fn ($server) => $serverMetric($server, ['cpu_usage', 'cpu', 'cpu_percent', 'cpu_average'], ['cpu_usage', 'cpu', 'cpu_percent', 'cpu_average']))->filter(fn ($value) => $value > 0);
    $calculatedRamValues = collect($servers)->map(fn ($server) => $serverMetric($server, ['ram_usage', 'ram', 'memory_usage', 'memory_percent'], ['ram_usage', 'ram', 'memory_usage', 'memory_percent']))->filter(fn ($value) => $value > 0);
    $calculatedDiskValues = collect($servers)->map(fn ($server) => $serverMetric($server, ['disk_usage', 'disk', 'disk_percent', 'storage_usage'], ['disk_usage', 'disk', 'disk_percent', 'storage_usage']))->filter(fn ($value) => $value > 0);

    $avgCpu = ($avgCpu ?? 0) > 0 ? $avgCpu : round($calculatedCpuValues->avg() ?? 0, 2);
    $avgRam = ($avgRam ?? 0) > 0 ? $avgRam : round($calculatedRamValues->avg() ?? 0, 2);
    $avgDisk = ($avgDisk ?? 0) > 0 ? $avgDisk : round($calculatedDiskValues->avg() ?? 0, 2);

    $criticalDiskServers = ($criticalDiskServers ?? 0) > 0 ? $criticalDiskServers : collect($servers)->filter(fn ($server) => $serverMetric($server, ['disk_usage', 'disk', 'disk_percent', 'storage_usage']) >= 90)->count();
    $highCpuServers = ($highCpuServers ?? 0) > 0 ? $highCpuServers : collect($servers)->filter(fn ($server) => $serverMetric($server, ['cpu_usage', 'cpu', 'cpu_percent', 'cpu_average']) >= 85)->count();
    $highRamServers = ($highRamServers ?? 0) > 0 ? $highRamServers : collect($servers)->filter(fn ($server) => $serverMetric($server, ['ram_usage', 'ram', 'memory_usage', 'memory_percent']) >= 85)->count();
@endphp

<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-red-500/10 rounded-full blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-3xl lg:text-5xl font-black tracking-tight">
                        Enterprise Monitoring Dashboard
                    </h1>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                        Live System
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold">
                        <i class="fa-solid fa-shield-virus"></i>
                        Webscepts SentinelCore
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-5xl">
                    Real-time server health, cPanel/Plesk monitoring, WordPress/Laravel/Angular/Node.js/PHP framework protection,
                    database risk detection, backup failover, ClouDNS failover and encrypted customer file security.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Servers: {{ $totalServers }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Online: {{ $onlineServers }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                        Offline: {{ $offlineServers }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                        Sentinel Score: {{ $securityScore }}%
                    </span>

                    <span class="px-4 py-2 rounded-full bg-orange-500/20 border border-orange-400/40 text-orange-100 text-xs font-bold">
                        Web Scans: {{ $sentinelStats['web_scans'] ?? 0 }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row xl:flex-col gap-3">
                @if(Route::has('servers.create'))
                    <a href="{{ route('servers.create') }}"
                       class="px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                        <i class="fa-solid fa-plus mr-2"></i>
                        Add Server
                    </a>
                @endif

                @if(Route::has('technology.webscanner.index'))
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="px-6 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black text-center">
                        <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>
                        Smart Web Scan
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- MAIN STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Total Servers</p>
                    <h2 class="text-4xl font-black mt-2 text-slate-900">{{ $totalServers }}</h2>
                    <p class="text-xs text-slate-400 mt-2">Active: {{ $activeServers }} / Inactive: {{ $inactiveServers }}</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-server text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Online Servers</p>
                    <h2 class="text-4xl font-black mt-2 text-green-600">{{ $onlineServers }}</h2>
                    <p class="text-xs text-slate-400 mt-2">Live healthy servers</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Offline Servers</p>
                    <h2 class="text-4xl font-black mt-2 text-red-600">{{ $offlineServers }}</h2>
                    <p class="text-xs text-slate-400 mt-2">Needs immediate check</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Last Check</p>
                    <h2 class="text-xl font-black mt-2 text-slate-900">
                        {{ $lastCheckedAt ? Carbon::parse($lastCheckedAt)->diffForHumans() : 'N/A' }}
                    </h2>
                    <p class="text-xs text-slate-400 mt-2">
                        {{ $lastCheckedAt ? Carbon::parse($lastCheckedAt)->format('Y-m-d H:i:s') : 'No checks yet' }}
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- SENTINELCORE SECURITY TECHNOLOGY --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-red-950 to-blue-950 p-7 text-white shadow-xl">
        <div class="absolute -top-28 right-0 w-96 h-96 bg-red-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-28 left-0 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>

        <div class="relative grid grid-cols-1 xl:grid-cols-3 gap-6 items-center">
            <div class="xl:col-span-2">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-3xl lg:text-4xl font-black">
                        Webscepts SentinelCore
                    </h2>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Smart Security Technology
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-4xl">
                    AI-style smart Python security engine for WordPress, Laravel, Angular, Node.js, PHP frameworks,
                    MySQL/PostgreSQL risk detection, exposed file scanning, security headers, SSL checks, encrypted vault,
                    cPanel/Plesk protection and automated alerting.
                </p>

                <div class="mt-5 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Credentials</p>
                        <p class="text-xl font-black">{{ $sentinelStats['encrypted_credentials'] ?? 0 }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">SMS Alerts</p>
                        <p class="text-xl font-black">{{ $sentinelStats['sms_alerts'] ?? 0 }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Email Alerts</p>
                        <p class="text-xl font-black">{{ $sentinelStats['email_alerts'] ?? 0 }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">DNS Failover</p>
                        <p class="text-xl font-black">{{ $sentinelStats['dns_failover'] ?? 0 }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Web Scans</p>
                        <p class="text-xl font-black">{{ $sentinelStats['web_scans'] ?? 0 }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Domains</p>
                        <p class="text-xl font-black">{{ $sentinelStats['linked_domains'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white/10 border border-white/10 p-6">
                <p class="text-slate-300 font-bold">SentinelCore Score</p>
                <div class="flex items-end gap-2 mt-2">
                    <h3 class="text-6xl font-black">{{ $securityScore }}</h3>
                    <span class="text-2xl font-black mb-2">%</span>
                </div>

                <p class="font-black text-lg mt-2">{{ $securityLevel }}</p>

                <div class="mt-5 h-4 bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r {{ $securityScoreBar }}"
                         style="width: {{ min($securityScore, 100) }}%"></div>
                </div>

                <div class="mt-5 flex flex-col gap-3">
                    @if(Route::has('technology.index'))
                        <a href="{{ route('technology.index') }}"
                           class="px-5 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black text-center">
                            Open SentinelCore
                        </a>
                    @endif

                    @if(Route::has('technology.webscanner.index'))
                        <a href="{{ route('technology.webscanner.index') }}"
                           class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                            Run Smart Scan
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- SENTINEL MODULES --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        @foreach($sentinelModules as $module)
            <a href="{{ $module['route'] ?? '#' }}"
               class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl hover:-translate-y-1 transition">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-slate-500 font-bold">{{ $module['title'] }}</p>
                        <h2 class="text-2xl font-black mt-2 text-slate-900">{{ $module['value'] }}</h2>
                    </div>

                    <div class="w-14 h-14 rounded-2xl {{ $module['color'] ?? 'bg-slate-100 text-slate-700' }} flex items-center justify-center">
                        <i class="fa-solid {{ $module['icon'] }} text-xl"></i>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ENTERPRISE MODULES --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Enterprise Modules</h2>
                <p class="text-slate-500 mt-1">Core monitoring, cache, backup, domain and security modules.</p>
            </div>

            <input type="text"
                   id="moduleSearch"
                   oninput="filterCards('moduleSearch', '.enterprise-module-card')"
                   placeholder="Search modules..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($enterpriseModules as $module)
                <a href="{{ $module['route'] ?? '#' }}"
                   class="enterprise-module-card rounded-3xl border border-slate-100 p-5 hover:shadow-lg transition bg-white">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl {{ $moduleColor($module['color'] ?? 'blue') }} flex items-center justify-center shrink-0">
                            <i class="fa-solid {{ $module['icon'] ?? 'fa-circle' }} text-xl"></i>
                        </div>

                        <div>
                            <h3 class="font-black text-slate-900 text-lg">{{ $module['title'] ?? 'Module' }}</h3>
                            <p class="text-2xl font-black mt-1">{{ $module['value'] ?? '-' }}</p>
                            <p class="text-sm text-slate-500 mt-2">{{ $module['description'] ?? 'Enterprise module ready.' }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- RESOURCE INSIGHTS --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Resource Health</h2>
                    <p class="text-sm text-slate-500">Average CPU, RAM and disk usage.</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <div class="flex justify-between text-sm font-bold text-slate-600 mb-2">
                        <span>CPU Average</span>
                        <span>{{ $avgCpu }}%</span>
                    </div>
                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 rounded-full" style="width: {{ min($avgCpu, 100) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-sm font-bold text-slate-600 mb-2">
                        <span>RAM Average</span>
                        <span>{{ $avgRam }}%</span>
                    </div>
                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-purple-600 rounded-full" style="width: {{ min($avgRam, 100) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-sm font-bold text-slate-600 mb-2">
                        <span>Disk Average</span>
                        <span>{{ $avgDisk }}%</span>
                    </div>
                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-500 rounded-full" style="width: {{ min($avgDisk, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Critical Counters</h2>
                    <p class="text-sm text-slate-500">Servers needing attention.</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between rounded-2xl bg-red-50 border border-red-100 p-4">
                    <span class="font-bold text-red-700">Critical Disk</span>
                    <span class="font-black text-red-700">{{ $criticalDiskServers }}</span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-orange-50 border border-orange-100 p-4">
                    <span class="font-bold text-orange-700">High CPU</span>
                    <span class="font-black text-orange-700">{{ $highCpuServers }}</span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-yellow-50 border border-yellow-100 p-4">
                    <span class="font-bold text-yellow-700">High RAM</span>
                    <span class="font-black text-yellow-700">{{ $highRamServers }}</span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 border border-slate-100 p-4">
                    <span class="font-bold text-slate-700">Security Alerts</span>
                    <span class="font-black text-slate-900">{{ $sentinelStats['security_alerts'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Quick Actions</h2>
                    <p class="text-sm text-slate-500">Fast access to key tools.</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-bolt"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @if(Route::has('servers.index'))
                    <a href="{{ route('servers.index') }}"
                       class="rounded-2xl border px-4 py-3 font-black text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-server mr-2 text-blue-600"></i>
                        Servers
                    </a>
                @endif

                @if(Route::has('backups.index'))
                    <a href="{{ route('backups.index') }}"
                       class="rounded-2xl border px-4 py-3 font-black text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-cloud-arrow-up mr-2 text-orange-600"></i>
                        Backups
                    </a>
                @endif

                @if(Route::has('domains.index'))
                    <a href="{{ route('domains.index') }}"
                       class="rounded-2xl border px-4 py-3 font-black text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-globe mr-2 text-green-600"></i>
                        Domains
                    </a>
                @endif

                @if(Route::has('technology.index'))
                    <a href="{{ route('technology.index') }}"
                       class="rounded-2xl border px-4 py-3 font-black text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-shield-virus mr-2 text-red-600"></i>
                        SentinelCore
                    </a>
                @endif

                @if(Route::has('technology.webscanner.index'))
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="rounded-2xl border px-4 py-3 font-black text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-magnifying-glass-chart mr-2 text-purple-600"></i>
                        Web Scanner
                    </a>
                @endif

                @if(Route::has('tools.logs'))
                    <a href="{{ route('tools.logs') }}"
                       class="rounded-2xl border px-4 py-3 font-black text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-file-lines mr-2 text-slate-600"></i>
                        Logs
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- FRAMEWORK SECURITY --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Framework Security Protection</h2>
                <p class="text-slate-500 mt-1">
                    SentinelCore scans WordPress, Laravel, Angular, Node.js, PHP, MySQL and PostgreSQL risks.
                </p>
            </div>

            <input type="text"
                   id="frameworkSearch"
                   oninput="filterCards('frameworkSearch', '.framework-card')"
                   placeholder="Search frameworks..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($frameworkSecurity as $framework)
                <div class="framework-card rounded-3xl border border-slate-100 p-5 hover:shadow-lg transition">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                            <i class="{{ $framework['icon'] ?? 'fa-solid fa-code' }} text-xl"></i>
                        </div>

                        <div>
                            <h3 class="font-black text-slate-900 text-lg">{{ $framework['name'] ?? 'Framework' }}</h3>
                            <p class="text-sm text-green-600 font-black mt-1">{{ $framework['status'] ?? 'Ready' }}</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($framework['checks'] ?? [] as $check)
                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">
                                        {{ $check }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- LATEST WEB SCANS + ALERTS --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Latest Smart Web Scans</h2>
                    <p class="text-sm text-slate-500">Recent SentinelCore Python security scans.</p>
                </div>

                @if(Route::has('technology.webscanner.index'))
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="px-4 py-2 rounded-xl bg-blue-600 text-white font-black text-sm">
                        View
                    </a>
                @endif
            </div>

            <div class="divide-y">
                @forelse($latestWebScans as $scan)
                    <div class="p-5 hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="font-black text-slate-900 break-all">
                                    {{ $scan->domain ?? $scan->url ?? 'Unknown domain' }}
                                </h3>

                                <p class="text-xs text-slate-500 mt-1">
                                    IP: {{ $scan->ip ?? '-' }} |
                                    HTTP: {{ $scan->http_status ?? 'N/A' }} |
                                    Score: {{ $scan->risk_score ?? 0 }}/100
                                </p>

                                <p class="text-sm text-slate-500 mt-2">
                                    {{ Str::limit($scan->summary ?? 'No summary available.', 120) }}
                                </p>
                            </div>

                            <span class="px-3 py-1 rounded-full border {{ $riskBadge($scan->risk_level ?? 'low') }} text-xs font-black uppercase">
                                {{ $scan->risk_level ?? 'low' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center text-slate-500">
                        No SentinelCore web scans found.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Latest Security Alerts</h2>
                    <p class="text-sm text-slate-500">Recent server, backup, DNS and scan alerts.</p>
                </div>

                @if(Route::has('security.alerts'))
                    <a href="{{ route('security.alerts') }}"
                       class="px-4 py-2 rounded-xl bg-red-600 text-white font-black text-sm">
                        View
                    </a>
                @endif
            </div>

            <div class="divide-y">
                @forelse($latestSecurityAlerts as $alert)
                    <div class="p-5 hover:bg-slate-50">
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 rounded-2xl {{ $riskBadge($alert->level ?? 'info') }} flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-bell"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-black text-slate-900">
                                        {{ $alert->title ?? 'Security Alert' }}
                                    </h3>

                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black uppercase">
                                        {{ $alert->type ?? 'security' }}
                                    </span>
                                </div>

                                <p class="text-sm text-slate-500 mt-1">
                                    {{ Str::limit($alert->message ?? 'No message.', 140) }}
                                </p>

                                <p class="text-xs text-slate-400 mt-2">
                                    {{ !empty($alert->created_at) ? Carbon::parse($alert->created_at)->diffForHumans() : '' }}
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

    {{-- SERVERS TABLE --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Server Security Overview</h2>
                <p class="text-slate-500 mt-1">
                    Live server status, resources, panel access, backup, failover and SentinelCore protection.
                </p>
            </div>

            <div class="flex flex-col md:flex-row gap-3">
                <input type="text"
                       id="serverSearch"
                       oninput="filterServerRows()"
                       placeholder="Search servers..."
                       class="w-full md:w-80 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">

                <select id="serverStatusFilter"
                        onchange="filterServerRows()"
                        class="px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] text-sm">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Host</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-left">Resources</th>
                        <th class="p-4 text-left">SentinelCore</th>
                        <th class="p-4 text-left">Alerts</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($servers as $server)
                        @php
                            $latestCheck = $serverLatestCheck($server);

                            $cpu = $serverMetric($server, ['cpu_usage', 'cpu', 'cpu_percent', 'cpu_average'], ['cpu_usage', 'cpu', 'cpu_percent', 'cpu_average']);
                            $ram = $serverMetric($server, ['ram_usage', 'ram', 'memory_usage', 'memory_percent'], ['ram_usage', 'ram', 'memory_usage', 'memory_percent']);
                            $disk = $serverMetric($server, ['disk_usage', 'disk', 'disk_percent', 'storage_usage'], ['disk_usage', 'disk', 'disk_percent', 'storage_usage']);
                            $responseTime = $serverMetric($server, ['response_time', 'latency', 'speed'], ['response_time', 'latency', 'speed']);

                            $serverScore = 0;
                            $serverScore += !empty($server->password) ? 20 : 0;
                            $serverScore += !empty($server->email_alerts_enabled) ? 15 : 0;
                            $serverScore += !empty($server->sms_alerts_enabled) ? 15 : 0;
                            $serverScore += !empty($server->google_drive_sync) ? 15 : 0;
                            $serverScore += !empty($server->failover_enabled) ? 15 : 0;
                            $serverScore += !empty($server->dns_failover_enabled) ? 20 : 0;

                            $serverLevel = match (true) {
                                $serverScore >= 85 => ['Enterprise', 'bg-green-100 text-green-700 border-green-200'],
                                $serverScore >= 60 => ['Protected', 'bg-blue-100 text-blue-700 border-blue-200'],
                                $serverScore >= 35 => ['Basic', 'bg-yellow-100 text-yellow-700 border-yellow-200'],
                                default => ['Risk', 'bg-red-100 text-red-700 border-red-200'],
                            };

                            $serverStatus = strtolower((string) (($latestCheck->status ?? null) ?: ($server->status ?? 'offline')));
                        @endphp

                        <tr class="server-row border-t hover:bg-slate-50 transition"
                            data-search="{{ strtolower(($server->name ?? '').' '.($server->host ?? '').' '.($server->website_url ?? '').' '.($server->linked_domain ?? '')) }}"
                            data-status="{{ $serverStatus }}">
                            <td class="p-4">
                                <div class="font-black text-slate-900">{{ $server->name }}</div>
                                <div class="text-xs text-slate-500 mt-1">
                                    {{ ($server->username ?: 'root') . '@' . ($server->host ?: '-') . ':' . ($server->ssh_port ?: 22) }}
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="font-bold text-slate-700">{{ $server->host }}</div>
                                <div class="text-xs text-slate-500 mt-1 break-all">
                                    {{ $server->website_url ?? $server->linked_domain ?? $server->domain ?? $server->url ?? 'No website linked' }}
                                </div>

                                <div class="text-xs text-slate-400 mt-1">
                                    Response: {{ $responseTime > 0 ? round($responseTime, 2) . ' ms' : 'N/A' }}
                                </div>
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full border {{ $statusBadge($serverStatus ?: 'offline') }} text-xs font-black uppercase">
                                    {{ ucfirst($serverStatus ?: 'offline') }}
                                </span>

                                <div class="mt-2 text-xs">
                                    @if(!empty($server->is_active))
                                        <span class="text-green-600 font-bold">Monitoring Enabled</span>
                                    @else
                                        <span class="text-red-600 font-bold">Monitoring Disabled</span>
                                    @endif
                                </div>
                            </td>

                            <td class="p-4 min-w-[220px]">
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                            <span>CPU</span>
                                            <span>{{ $cpu }}%</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full {{ $cpu >= 85 ? 'bg-red-600' : 'bg-blue-600' }} rounded-full"
                                                 style="width: {{ min($cpu, 100) }}%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                            <span>RAM</span>
                                            <span>{{ $ram }}%</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full {{ $ram >= 85 ? 'bg-red-600' : 'bg-purple-600' }} rounded-full"
                                                 style="width: {{ min($ram, 100) }}%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                            <span>Disk</span>
                                            <span>{{ $disk }}%</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full {{ $disk >= 90 ? 'bg-red-600' : 'bg-orange-500' }} rounded-full"
                                                 style="width: {{ min($disk, 100) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="space-y-2">
                                    <span class="inline-flex px-3 py-1 rounded-full border {{ $serverLevel[1] }} text-xs font-black">
                                        {{ $serverLevel[0] }} {{ $serverScore }}%
                                    </span>

                                    <div class="flex flex-wrap gap-1">
                                        @if(!empty($server->google_drive_sync))
                                            <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-[10px] font-black">Drive</span>
                                        @endif

                                        @if(!empty($server->failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-orange-100 text-orange-700 text-[10px] font-black">Backup FO</span>
                                        @endif

                                        @if(!empty($server->dns_failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-[10px] font-black">DNS FO</span>
                                        @endif

                                        @if(!empty($server->sms_alerts_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-purple-100 text-purple-700 text-[10px] font-black">SMS</span>
                                        @endif

                                        @if(!empty($server->email_alerts_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-cyan-100 text-cyan-700 text-[10px] font-black">Email</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="p-4">
                                @if($cpu >= 85 || $ram >= 85 || $disk >= 90 || $serverStatus === 'offline')
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-black">
                                        Needs Attention
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-black">
                                        Healthy
                                    </span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if(Route::has('servers.show'))
                                        <a href="{{ route('servers.show', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold">
                                            View
                                        </a>
                                    @endif

                                    @if(Route::has('servers.check'))
                                        <form method="POST" action="{{ route('servers.check', $server) }}">
                                            @csrf
                                            <button class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold">
                                                Check
                                            </button>
                                        </form>
                                    @endif

                                    @if(Route::has('technology.webscanner.index'))
                                        <a href="{{ route('technology.webscanner.index') }}"
                                           class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold">
                                            Scan
                                        </a>
                                    @endif

                                    @if(Route::has('servers.edit'))
                                        <a href="{{ route('servers.edit', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-white font-bold">
                                            Edit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-slate-500">
                                No servers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- LATEST CHECKS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-xl font-black text-slate-900">Latest Server Checks</h2>
            <p class="text-sm text-slate-500 mt-1">Recent health check history from monitoring jobs.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-left">Response</th>
                        <th class="p-4 text-left">Message</th>
                        <th class="p-4 text-left">Checked At</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($latestChecks as $check)
                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4 font-black">
                                {{ $check->server->name ?? 'Unknown Server' }}
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full border {{ $statusBadge($check->status ?? 'unknown') }} text-xs font-black uppercase">
                                    {{ $check->status ?? 'unknown' }}
                                </span>
                            </td>

                            <td class="p-4">
                                {{ ($check->response_time ?? $check->latency ?? $check->speed ?? null) ? round(($check->response_time ?? $check->latency ?? $check->speed), 2) . ' ms' : 'N/A' }}
                            </td>

                            <td class="p-4 text-slate-600">
                                {{ Str::limit($check->message ?? $check->error_message ?? 'No message.', 120) }}
                            </td>

                            <td class="p-4 text-slate-500">
                                {{ $check->created_at ? $check->created_at->diffForHumans() : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center text-slate-500">
                                No server checks found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function filterCards(inputId, cardSelector) {
    const input = document.getElementById(inputId);
    const value = input ? input.value.toLowerCase() : '';

    document.querySelectorAll(cardSelector).forEach(function(card) {
        card.style.display = card.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
}

function filterServerRows() {
    const search = document.getElementById('serverSearch')?.value.toLowerCase() || '';
    const status = document.getElementById('serverStatusFilter')?.value.toLowerCase() || '';

    document.querySelectorAll('.server-row').forEach(function(row) {
        const rowSearch = row.getAttribute('data-search') || row.innerText.toLowerCase();
        const rowStatus = row.getAttribute('data-status') || '';

        let show = rowSearch.includes(search);

        if (status) {
            show = show && rowStatus === status;
        }

        row.style.display = show ? '' : 'none';
    });
}
</script>

@endsection