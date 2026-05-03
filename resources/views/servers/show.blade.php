@extends('layouts.app')

@section('page-title', $server->name ?? 'Server Details')

@section('content')

@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $latest = $server->checks->first();

    if ($latest && is_array($latest->services)) {
        $services = $latest->services;
    } elseif ($latest && is_string($latest->services)) {
        $services = json_decode($latest->services, true) ?: [];
    } else {
        $services = [];
    }

    $securityAlerts = $server->securityAlerts()->latest()->limit(20)->get();

    $cpu = (float) ($latest->cpu_usage ?? 0);
    $ram = (float) ($latest->ram_usage ?? 0);
    $disk = (float) ($latest->disk_usage ?? 0);

    $serverStatus = strtolower(trim($server->status ?? $latest->status ?? 'offline'));

    if ($latest && isset($latest->online)) {
        $serverStatus = $latest->online ? 'online' : 'offline';
    }

    $isOnline = $serverStatus === 'online';

    $dangerCount = $securityAlerts->where('level', 'danger')->count();
    $warningCount = $securityAlerts->where('level', 'warning')->count();
    $openAlerts = $securityAlerts->where('is_resolved', false)->count();

    $emailEnabled = !empty($server->email_alerts_enabled);
    $smsEnabled = !empty($server->sms_alerts_enabled);

    $adminEmail = $server->admin_email ?? null;
    $adminPhone = $server->admin_phone ?? null;

    $customerName = $server->customer_name ?? null;
    $customerEmail = $server->customer_email ?? null;
    $customerPhone = $server->customer_phone ?? null;

    $litespeedStatus =
        $services['lsws'] ??
        $services['lshttpd'] ??
        $services['openlitespeed'] ??
        $services['litespeed'] ??
        $services['lswsctrl'] ??
        null;

    $litespeedActive = strtolower(trim($litespeedStatus ?? '')) === 'active';

    $panelType = strtolower($server->panel_type ?? 'auto');

    $lastChecked = $latest?->checked_at ?? $latest?->created_at ?? null;
    $lastCheckedText = $lastChecked ? Carbon::parse($lastChecked)->diffForHumans() : 'No checks yet';

    $sentinelScore = 0;
    $sentinelScore += !empty($server->password) ? 20 : 0;
    $sentinelScore += $emailEnabled ? 15 : 0;
    $sentinelScore += $smsEnabled ? 15 : 0;
    $sentinelScore += !empty($server->google_drive_sync) ? 15 : 0;
    $sentinelScore += !empty($server->failover_enabled) ? 15 : 0;
    $sentinelScore += !empty($server->dns_failover_enabled) ? 20 : 0;

    $sentinelLevel = match (true) {
        $sentinelScore >= 85 => 'Enterprise',
        $sentinelScore >= 60 => 'Protected',
        $sentinelScore >= 35 => 'Basic',
        default => 'Risk',
    };

    $sentinelColor = match (true) {
        $sentinelScore >= 85 => 'text-green-600',
        $sentinelScore >= 60 => 'text-blue-600',
        $sentinelScore >= 35 => 'text-yellow-600',
        default => 'text-red-600',
    };

    $sentinelBar = match (true) {
        $sentinelScore >= 85 => 'from-green-500 to-emerald-600',
        $sentinelScore >= 60 => 'from-blue-500 to-cyan-600',
        $sentinelScore >= 35 => 'from-yellow-500 to-orange-500',
        default => 'from-red-500 to-orange-600',
    };

    $frameworkSecurity = [
        ['name' => 'WordPress', 'icon' => 'fa-brands fa-wordpress', 'checks' => ['wp-config.php', 'XML-RPC', 'Plugins']],
        ['name' => 'Laravel', 'icon' => 'fa-brands fa-laravel', 'checks' => ['.env', 'APP_DEBUG', 'Storage logs']],
        ['name' => 'Angular', 'icon' => 'fa-brands fa-angular', 'checks' => ['Source maps', 'Env files']],
        ['name' => 'Node.js', 'icon' => 'fa-brands fa-node-js', 'checks' => ['package.json', '.env', 'Stack traces']],
        ['name' => 'PHP', 'icon' => 'fa-brands fa-php', 'checks' => ['phpinfo.php', 'config.php']],
        ['name' => 'Database', 'icon' => 'fa-solid fa-database', 'checks' => ['MySQL', 'PostgreSQL', 'SQL dumps']],
    ];
@endphp

<div class="space-y-6">

    {{-- SESSION ALERTS --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-300 text-green-800 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-300 text-red-800 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-100 border border-red-300 text-red-800 rounded-2xl p-4 font-semibold">
            <div class="font-black mb-2">Please fix these errors:</div>
            <ul class="list-disc ml-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- SERVER HERO --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 rounded-3xl shadow-xl p-7 text-white">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-start xl:justify-between gap-7">

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="w-16 h-16 rounded-3xl bg-white/10 border border-white/20 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-server text-3xl"></i>
                    </div>

                    <div>
                        <h2 class="text-3xl lg:text-4xl font-black tracking-tight">
                            {{ $server->name ?? 'Unknown Server' }}
                        </h2>

                        <p class="text-slate-300 mt-1">
                            {{ $server->host ?? 'No host' }} : {{ $server->ssh_port ?? 22 }}
                        </p>
                    </div>

                    @if($isOnline)
                        <span class="px-5 py-2 rounded-full bg-green-500/20 border border-green-400 text-green-100 font-black">
                            <i class="fa-solid fa-circle mr-1 text-xs"></i> Online
                        </span>
                    @else
                        <span class="px-5 py-2 rounded-full bg-red-500/20 border border-red-400 text-red-100 font-black">
                            <i class="fa-solid fa-circle mr-1 text-xs"></i> Offline
                        </span>
                    @endif
                </div>

                <p class="text-slate-400 text-sm mt-5">
                    Website:
                    @if(!empty($server->website_url))
                        <a href="{{ $server->website_url }}" target="_blank" class="text-blue-300 hover:underline font-semibold">
                            {{ $server->website_url }}
                        </a>
                    @else
                        N/A
                    @endif
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Panel: {{ $panelType === 'cpanel' ? 'cPanel / WHM' : ($panelType === 'plesk' ? 'Plesk' : 'Auto Detect') }}
                    </span>

                    @if($latest && $latest->ssh_online)
                        <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                            SSH Connected
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-slate-500/20 border border-slate-400/40 text-slate-100 text-xs font-bold">
                            SSH Unknown
                        </span>
                    @endif

                    <span class="px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold">
                        Webscepts SentinelCore
                    </span>

                    @if($litespeedActive)
                        <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                            LiteSpeed Active
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-slate-500/20 border border-slate-400/40 text-slate-100 text-xs font-bold">
                            LiteSpeed {{ $litespeedStatus ? ucfirst($litespeedStatus) : 'Unknown' }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- ACTION BUTTONS --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 w-full xl:w-[620px]">

                @if(Route::has('servers.checkNow'))
                    <form method="POST" action="{{ route('servers.checkNow', $server) }}" class="w-full">
                        @csrf
                        <button class="w-full min-h-[58px] px-4 py-3 rounded-xl bg-green-600 text-white hover:bg-green-700 text-sm font-black">
                            <i class="fa-solid fa-rotate mr-1"></i> Check Now
                        </button>
                    </form>
                @endif

                @if(Route::has('servers.securityScan'))
                    <form method="POST" action="{{ route('servers.securityScan', $server) }}" class="w-full">
                        @csrf
                        <button class="w-full min-h-[58px] px-4 py-3 rounded-xl bg-purple-600 text-white hover:bg-purple-700 text-sm font-black">
                            <i class="fa-solid fa-shield-halved mr-1"></i> Security Scan
                        </button>
                    </form>
                @endif

                @if(Route::has('technology.webscanner.index'))
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-red-600 text-white hover:bg-red-700 text-sm font-black">
                        <i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Smart Scan
                    </a>
                @endif

                @if(Route::has('technology.index'))
                    <a href="{{ route('technology.index') }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-black">
                        <i class="fa-solid fa-shield-virus mr-1"></i> SentinelCore
                    </a>
                @endif

                @if(Route::has('servers.litespeed.index'))
                    <a href="{{ route('servers.litespeed.index', $server) }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-red-600 text-white hover:bg-red-700 text-sm font-black">
                        <i class="fa-solid fa-bolt mr-1"></i> LiteSpeed
                    </a>
                @endif

                @if(Route::has('servers.cpanel.index'))
                    <a href="{{ route('servers.cpanel.index', $server) }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm font-black">
                        <i class="fa-solid fa-users mr-1"></i> Accounts
                    </a>
                @endif

                @if(Route::has('servers.terminal'))
                    <a href="{{ route('servers.terminal', $server) }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-slate-800 text-white hover:bg-slate-700 text-sm font-black">
                        <i class="fa-solid fa-terminal mr-1"></i> Terminal
                    </a>
                @endif

                @if(Route::has('servers.edit'))
                    <a href="{{ route('servers.edit', $server) }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-cyan-600 text-white hover:bg-cyan-700 text-sm font-black">
                        <i class="fa-solid fa-pen mr-1"></i> Edit
                    </a>
                @endif

                @if(Route::has('backups.index'))
                    <a href="{{ route('backups.index') }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-teal-600 text-white hover:bg-teal-700 text-sm font-black">
                        <i class="fa-solid fa-cloud-arrow-up mr-1"></i> Backup
                    </a>
                @endif

            </div>

        </div>
    </div>

    {{-- SENTINELCORE SERVER PROTECTION --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-red-950 to-blue-950 p-7 text-white shadow-xl">
        <div class="absolute -top-28 right-0 w-96 h-96 bg-red-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-28 left-0 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>

        <div class="relative grid grid-cols-1 xl:grid-cols-3 gap-6 items-center">
            <div class="xl:col-span-2">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-3xl font-black">
                        Webscepts SentinelCore Protection
                    </h2>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        {{ $sentinelLevel }}
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-4xl">
                    Smart protection for encrypted credentials, cPanel/Plesk accounts, WordPress, Laravel, Angular,
                    Node.js, PHP, MySQL/PostgreSQL, exposed files, SSL, headers, backups and DNS failover.
                </p>

                <div class="mt-5 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Password</p>
                        <p class="text-sm font-black">{{ !empty($server->password) ? 'Encrypted' : 'Missing' }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Email</p>
                        <p class="text-sm font-black">{{ $emailEnabled ? 'Enabled' : 'Disabled' }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">SMS</p>
                        <p class="text-sm font-black">{{ $smsEnabled ? 'Enabled' : 'Disabled' }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Drive</p>
                        <p class="text-sm font-black">{{ !empty($server->google_drive_sync) ? 'Enabled' : 'Disabled' }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Backup FO</p>
                        <p class="text-sm font-black">{{ !empty($server->failover_enabled) ? 'Enabled' : 'Disabled' }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">DNS FO</p>
                        <p class="text-sm font-black">{{ !empty($server->dns_failover_enabled) ? 'Enabled' : 'Disabled' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white/10 border border-white/10 p-6">
                <p class="text-slate-300 font-bold">Server Security Score</p>

                <div class="flex items-end gap-2 mt-2">
                    <h3 class="text-6xl font-black">{{ $sentinelScore }}</h3>
                    <span class="text-2xl font-black mb-2">%</span>
                </div>

                <p class="font-black text-lg mt-2 {{ $sentinelColor }}">
                    {{ $sentinelLevel }}
                </p>

                <div class="mt-5 h-4 bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r {{ $sentinelBar }}"
                         style="width: {{ min($sentinelScore, 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- QUICK STATS --}}
    <div class="grid grid-cols-1 xl:grid-cols-4 md:grid-cols-2 gap-5">

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold">CPU Usage</p>
                    <h3 class="text-3xl font-black mt-2">{{ $latest->cpu_usage ?? '-' }}%</h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-microchip"></i>
                </div>
            </div>

            <div class="h-2 bg-slate-200 rounded-full mt-4">
                <div class="h-2 {{ $cpu >= 85 ? 'bg-red-600' : 'bg-blue-600' }} rounded-full" style="width: {{ min($cpu, 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold">RAM Usage</p>
                    <h3 class="text-3xl font-black mt-2">{{ $latest->ram_usage ?? '-' }}%</h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-memory"></i>
                </div>
            </div>

            <div class="h-2 bg-slate-200 rounded-full mt-4">
                <div class="h-2 {{ $ram >= 85 ? 'bg-red-600' : 'bg-purple-600' }} rounded-full" style="width: {{ min($ram, 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold">Disk Usage</p>
                    <h3 class="text-3xl font-black mt-2 {{ $disk >= 90 ? 'text-red-600' : 'text-slate-800' }}">
                        {{ $latest->disk_usage ?? '-' }}%
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-700 flex items-center justify-center">
                    <i class="fa-solid fa-hard-drive"></i>
                </div>
            </div>

            <div class="h-2 bg-slate-200 rounded-full mt-4">
                <div class="h-2 {{ $disk >= 90 ? 'bg-red-600' : 'bg-orange-500' }} rounded-full"
                     style="width: {{ min($disk, 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold">Response Time</p>
                    <h3 class="text-3xl font-black mt-2">
                        {{ $latest->response_time ?? 'N/A' }}
                        @if(!empty($latest->response_time))
                            <span class="text-base">ms</span>
                        @endif
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-gauge-high"></i>
                </div>
            </div>

            <p class="text-xs text-slate-500 mt-3">
                Last check: {{ $lastCheckedText }}
            </p>
        </div>

    </div>

    {{-- FRAMEWORK SECURITY MODULES --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h3 class="text-2xl font-black text-slate-900">Technology & Framework Security</h3>
                <p class="text-sm text-slate-500">
                    Use Smart Scan to detect exposed files, headers, SSL, CMS and framework risks on this server website.
                </p>
            </div>

            @if(Route::has('technology.webscanner.index'))
                <a href="{{ route('technology.webscanner.index') }}"
                   class="px-5 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black text-center">
                    Run Website Scan
                </a>
            @endif
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($frameworkSecurity as $item)
                <div class="rounded-3xl border border-slate-100 p-5 hover:shadow-lg transition">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                            <i class="{{ $item['icon'] }} text-xl"></i>
                        </div>

                        <div>
                            <h4 class="font-black text-slate-900 text-lg">{{ $item['name'] }}</h4>
                            <p class="text-sm text-green-600 font-black mt-1">Scanner Ready</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($item['checks'] as $check)
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

    {{-- SERVER + ALERT DETAILS --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- SERVER INFO --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-800">Server Info</h3>

                @if(Route::has('servers.edit'))
                    <a href="{{ route('servers.edit', $server) }}"
                       class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                        <i class="fa-solid fa-pen mr-1"></i>Edit
                    </a>
                @endif
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Host</span>
                    <span class="font-bold text-slate-800 break-all">{{ $server->host ?? 'N/A' }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">SSH Port</span>
                    <span class="font-bold text-slate-800">{{ $server->ssh_port ?? 22 }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Username</span>
                    <span class="font-bold text-slate-800">{{ $server->username ?? 'N/A' }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Panel Type</span>
                    <span class="font-bold text-slate-800">
                        {{ $panelType === 'cpanel' ? 'cPanel / WHM' : ($panelType === 'plesk' ? 'Plesk' : 'Auto Detect') }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Status</span>
                    <span class="font-black {{ $isOnline ? 'text-green-600' : 'text-red-600' }}">
                        {{ $isOnline ? 'Online' : 'Offline' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Last Checked</span>
                    <span class="font-bold text-slate-800">
                        {{ $lastCheckedText }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ADMIN ALERTS --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-800">Admin Alerts</h3>

                @if(Route::has('servers.edit'))
                    <a href="{{ route('servers.edit', $server) }}"
                       class="px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-bold hover:bg-purple-700">
                        <i class="fa-solid fa-pen mr-1"></i>Edit
                    </a>
                @endif
            </div>

            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-slate-500">Admin Email</p>
                    <p class="font-bold text-slate-800 break-all">{{ $adminEmail ?? 'Not set' }}</p>
                </div>

                <div>
                    <p class="text-slate-500">Admin Phone</p>
                    <p class="font-bold text-slate-800">{{ $adminPhone ?? 'Not set' }}</p>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $emailEnabled ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                        Email {{ $emailEnabled ? 'Enabled' : 'Disabled' }}
                    </span>

                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $smsEnabled ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                        SMS {{ $smsEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- CUSTOMER ALERTS --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-800">Customer Alerts</h3>

                @if(Route::has('servers.edit'))
                    <a href="{{ route('servers.edit', $server) }}"
                       class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-bold hover:bg-green-700">
                        <i class="fa-solid fa-pen mr-1"></i>Edit
                    </a>
                @endif
            </div>

            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-slate-500">Customer Name</p>
                    <p class="font-bold text-slate-800">{{ $customerName ?? 'Not set' }}</p>
                </div>

                <div>
                    <p class="text-slate-500">Customer Email</p>
                    <p class="font-bold text-slate-800 break-all">{{ $customerEmail ?? 'Not set' }}</p>
                </div>

                <div>
                    <p class="text-slate-500">Customer Phone</p>
                    <p class="font-bold text-slate-800">{{ $customerPhone ?? 'Not set' }}</p>
                </div>
            </div>
        </div>

    </div>

    {{-- LITESPEED SUMMARY --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
            <div>
                <h3 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-red-600"></i>
                    LiteSpeed Status
                </h3>
                <p class="text-sm text-slate-500">
                    Manage LSWS / OpenLiteSpeed service, WebAdmin, ports, config test and logs.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if(Route::has('servers.litespeed.index'))
                    <a href="{{ route('servers.litespeed.index', $server) }}"
                       class="px-5 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                        Open LiteSpeed Manager
                    </a>
                @endif

                @if(Route::has('servers.litespeed.restart'))
                    <form method="POST" action="{{ route('servers.litespeed.restart', $server) }}">
                        @csrf
                        <button onclick="return confirm('Restart LiteSpeed on this server?')"
                                class="px-5 py-3 rounded-2xl bg-slate-900 hover:bg-slate-700 text-white font-black">
                            Restart LS
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @foreach(['lsws', 'lshttpd', 'openlitespeed', 'litespeed', 'lswsctrl'] as $serviceName)
                @php
                    $serviceValue = $services[$serviceName] ?? 'unknown';
                    $active = strtolower($serviceValue) === 'active';
                @endphp

                <div class="rounded-2xl border p-4">
                    <p class="text-sm text-slate-500 font-semibold uppercase">{{ $serviceName }}</p>
                    <h4 class="text-xl font-black mt-1 {{ $active ? 'text-green-600' : 'text-slate-700' }}">
                        {{ ucfirst($serviceValue) }}
                    </h4>
                </div>
            @endforeach
        </div>
    </div>

    {{-- PANEL STATUS + SERVICES --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h3 class="text-xl font-black mb-4">Panel & Website Status</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-xl border p-4">
                    <p class="font-bold">cPanel / WHM</p>
                    @if($latest && !empty($latest->cpanel_online))
                        <span class="text-green-600 font-black">Online</span>
                    @else
                        <span class="text-red-600 font-black">Offline / Unknown</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port 2087</p>
                </div>

                <div class="rounded-xl border p-4">
                    <p class="font-bold">Plesk</p>
                    @if($latest && !empty($latest->plesk_online))
                        <span class="text-green-600 font-black">Online</span>
                    @else
                        <span class="text-slate-500 font-black">Not Detected</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port 8443</p>
                </div>

                <div class="rounded-xl border p-4">
                    <p class="font-bold">Website</p>
                    @if($latest && !empty($latest->website_online))
                        <span class="text-green-600 font-black">Online</span>
                    @elseif($isOnline)
                        <span class="text-yellow-600 font-black">Server Online / Website Unknown</span>
                    @else
                        <span class="text-red-600 font-black">Offline</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port 80 / 443</p>
                </div>

                <div class="rounded-xl border p-4">
                    <p class="font-bold">SSH</p>
                    @if($latest && !empty($latest->ssh_online))
                        <span class="text-green-600 font-black">Connected</span>
                    @else
                        <span class="text-red-600 font-black">Failed / Unknown</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port {{ $server->ssh_port ?? 22 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h3 class="text-xl font-black mb-4">Services</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($services as $name => $status)
                    @php
                        $serviceStatus = strtolower(trim($status ?? 'unknown'));
                    @endphp

                    <div class="rounded-xl border p-4">
                        <p class="font-black uppercase">{{ $name }}</p>

                        @if($serviceStatus === 'active')
                            <span class="text-green-600 font-black">Active</span>
                        @elseif($serviceStatus === 'unknown' || $serviceStatus === '')
                            <span class="text-slate-500 font-black">Unknown</span>
                        @else
                            <span class="text-red-600 font-black">{{ ucfirst($serviceStatus) }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-slate-500">No service data available.</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- SECURITY SUMMARY --}}
    <div class="grid grid-cols-1 xl:grid-cols-4 md:grid-cols-2 gap-5">

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <p class="text-slate-500 text-sm font-semibold">Firewall Status</p>
            <h3 class="text-lg font-black mt-2 break-words">
                {{ $latest->firewall_status ?? 'Unknown' }}
            </h3>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <p class="text-slate-500 text-sm font-semibold">Danger Alerts</p>
            <h3 class="text-3xl font-black text-red-600 mt-2">{{ $dangerCount }}</h3>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <p class="text-slate-500 text-sm font-semibold">Warning Alerts</p>
            <h3 class="text-3xl font-black text-yellow-600 mt-2">{{ $warningCount }}</h3>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
            <p class="text-slate-500 text-sm font-semibold">Open Alerts</p>
            <h3 class="text-3xl font-black mt-2">{{ $openAlerts }}</h3>
        </div>

    </div>

    {{-- SECURITY ALERTS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
            <div>
                <h3 class="text-xl font-black">Security Alerts</h3>
                <p class="text-sm text-slate-500">
                    Abuse, firewall, email, SSH, LiteSpeed, service, disk and SentinelCore issues.
                </p>
            </div>

            @if(Route::has('servers.securityScan'))
                <form method="POST" action="{{ route('servers.securityScan', $server) }}">
                    @csrf
                    <button class="w-full lg:w-auto px-5 py-3 rounded-xl bg-purple-600 text-white hover:bg-purple-700 font-bold">
                        Run Security Scan
                    </button>
                </form>
            @endif
        </div>

        <div class="divide-y">
            @forelse($securityAlerts as $alert)
                <details class="p-5 group">
                    <summary class="cursor-pointer list-none">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap gap-2 items-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold
                                        @if($alert->level === 'danger') bg-red-100 text-red-700
                                        @elseif($alert->level === 'warning') bg-yellow-100 text-yellow-700
                                        @else bg-blue-100 text-blue-700
                                        @endif">
                                        {{ strtoupper($alert->level ?? 'INFO') }}
                                    </span>

                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold">
                                        {{ strtoupper($alert->type ?? 'SECURITY') }}
                                    </span>

                                    @if($alert->is_resolved)
                                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">
                                            RESOLVED
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold">
                                            OPEN
                                        </span>
                                    @endif
                                </div>

                                <h4 class="font-black text-slate-800 mt-3 break-words">
                                    {{ $alert->title ?? 'Security Alert' }}
                                </h4>

                                <p class="text-sm text-slate-500 mt-1">
                                    Detected: {{ $alert->detected_at ?? $alert->created_at }}
                                </p>
                            </div>

                            <span class="text-sm text-blue-600 font-bold">
                                View Details
                            </span>
                        </div>
                    </summary>

                    <div class="mt-4">
                        @if($alert->source_ip || $alert->location)
                            <div class="mb-3 text-sm text-slate-600">
                                @if($alert->source_ip)
                                    <div><strong>Source IP:</strong> {{ $alert->source_ip }}</div>
                                @endif

                                @if($alert->location)
                                    <div><strong>Location:</strong> {{ $alert->location }}</div>
                                @endif
                            </div>
                        @endif

                        @if($alert->message)
                            <pre class="bg-slate-950 text-green-400 rounded-xl p-4 overflow-x-auto text-xs max-h-80 whitespace-pre-wrap">{{ $alert->message }}</pre>
                        @else
                            <p class="text-sm text-slate-500">No additional details.</p>
                        @endif
                    </div>
                </details>
            @empty
                <div class="p-8 text-center text-slate-500">
                    No security alerts yet. Run Security Scan.
                </div>
            @endforelse
        </div>
    </div>

    {{-- RECOMMENDED ACTIONS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h3 class="text-xl font-black mb-4">Recommended Security Actions</h3>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <div class="rounded-xl border p-4">
                <h4 class="font-black text-slate-800">SentinelCore Smart Scan</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Run website scan for exposed .env, composer files, package files, SQL dumps, missing headers and SSL issues.
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-black text-slate-800">Firewall Protection</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Keep only required ports open: 22, 80, 443, 2083, 2087, 7080 only when needed.
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-black text-slate-800">LiteSpeed Protection</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Protect WebAdmin on port 7080 with strong password and IP restrictions.
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-black text-slate-800">Customer File Protection</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Store sensitive files outside public folders and encrypt important backups with SentinelCore vault.
                </p>
            </div>

        </div>
    </div>

    {{-- RECENT CHECKS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b">
            <h3 class="text-xl font-black">Recent Checks</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4">Time</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">SSH</th>
                        <th class="p-4">CPU</th>
                        <th class="p-4">RAM</th>
                        <th class="p-4">Disk</th>
                        <th class="p-4">Speed</th>
                        <th class="p-4">Message</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($server->checks as $check)
                        @php
                            $checkOnline = !empty($check->online);
                        @endphp

                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4 whitespace-nowrap">
                                {{ $check->checked_at ?? $check->created_at }}
                            </td>

                            <td class="p-4">
                                @if($checkOnline)
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm font-bold">
                                        Online
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-bold">
                                        Offline
                                    </span>
                                @endif
                            </td>

                            <td class="p-4">
                                @if(!empty($check->ssh_online))
                                    <span class="text-green-600 font-black">Connected</span>
                                @else
                                    <span class="text-red-600 font-black">Failed</span>
                                @endif
                            </td>

                            <td class="p-4">{{ $check->cpu_usage ?? '-' }}%</td>
                            <td class="p-4">{{ $check->ram_usage ?? '-' }}%</td>
                            <td class="p-4">{{ $check->disk_usage ?? '-' }}%</td>

                            <td class="p-4">
                                {{ $check->response_time ?? 'N/A' }}
                                @if(!empty($check->response_time))
                                    ms
                                @endif
                            </td>

                            <td class="p-4 text-slate-600">{{ $check->status ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center text-slate-500">
                                No checks found yet. Click <b>Check Now</b>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection