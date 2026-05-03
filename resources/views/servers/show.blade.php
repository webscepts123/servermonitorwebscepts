@extends('layouts.app')

@section('page-title', $server->name ?? 'Server Details')

@section('content')

@php
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

    $hiddenBaseFields = [
        'name' => $server->name,
        'host' => $server->host,
        'website_url' => $server->website_url,
        'panel_type' => $server->panel_type,
        'ssh_port' => $server->ssh_port ?? 22,
        'username' => $server->username,
        'admin_email' => $server->admin_email,
        'admin_phone' => $server->admin_phone,
        'customer_name' => $server->customer_name,
        'customer_email' => $server->customer_email,
        'customer_phone' => $server->customer_phone,
        'backup_server_id' => $server->backup_server_id,
        'backup_path' => $server->backup_path,
        'local_backup_path' => $server->local_backup_path,
        'google_drive_remote' => $server->google_drive_remote,
        'disk_warning_percent' => $server->disk_warning_percent ?? 80,
        'disk_transfer_percent' => $server->disk_transfer_percent ?? 90,
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

                    @if($emailEnabled)
                        <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                            Email Alerts Enabled
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-slate-500/20 border border-slate-400/40 text-slate-100 text-xs font-bold">
                            Email Alerts Disabled
                        </span>
                    @endif

                    @if($smsEnabled)
                        <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                            SMS Alerts Enabled
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-slate-500/20 border border-slate-400/40 text-slate-100 text-xs font-bold">
                            SMS Alerts Disabled
                        </span>
                    @endif

                    @if($litespeedActive)
                        <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                            LiteSpeed Active
                        </span>
                    @elseif($litespeedStatus)
                        <span class="px-4 py-2 rounded-full bg-orange-500/20 border border-orange-400/40 text-orange-100 text-xs font-bold">
                            LiteSpeed {{ ucfirst($litespeedStatus) }}
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-slate-500/20 border border-slate-400/40 text-slate-100 text-xs font-bold">
                            LiteSpeed Unknown
                        </span>
                    @endif
                </div>
            </div>

            {{-- ACTION BUTTONS --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 w-full xl:w-[560px]">

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

                @if(Route::has('servers.litespeed.index'))
                    <a href="{{ route('servers.litespeed.index', $server) }}"
                       class="w-full min-h-[58px] flex items-center justify-center text-center px-4 py-3 rounded-xl bg-red-600 text-white hover:bg-red-700 text-sm font-black">
                        <i class="fa-solid fa-bolt mr-1"></i> LiteSpeed
                    </a>
                @endif

                @if(Route::has('servers.litespeed.activate'))
                    <form method="POST" action="{{ route('servers.litespeed.activate', $server) }}" class="w-full">
                        @csrf
                        <button onclick="return confirm('Activate or restart LiteSpeed on this server?')"
                                class="w-full min-h-[58px] px-4 py-3 rounded-xl bg-orange-600 text-white hover:bg-orange-700 text-sm font-black">
                            <i class="fa-solid fa-bolt-lightning mr-1"></i> Activate LS
                        </button>
                    </form>
                @endif

                @if(Route::has('sms.down'))
                    <form method="POST" action="{{ route('sms.down', $server) }}" class="w-full">
                        @csrf
                        <button onclick="return confirm('Send DOWN SMS alert to admin and customer?')"
                                class="w-full min-h-[58px] px-4 py-3 rounded-xl bg-red-700 text-white hover:bg-red-800 text-sm font-black">
                            <i class="fa-solid fa-message mr-1"></i> Down SMS
                        </button>
                    </form>
                @endif

                @if(Route::has('sms.recovery'))
                    <form method="POST" action="{{ route('sms.recovery', $server) }}" class="w-full">
                        @csrf
                        <button onclick="return confirm('Send RECOVERY SMS alert to admin and customer?')"
                                class="w-full min-h-[58px] px-4 py-3 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-black">
                            <i class="fa-solid fa-message mr-1"></i> Recovery SMS
                        </button>
                    </form>
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
                <div class="h-2 bg-blue-600 rounded-full" style="width: {{ min($cpu, 100) }}%"></div>
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
                <div class="h-2 bg-purple-600 rounded-full" style="width: {{ min($ram, 100) }}%"></div>
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
                Last check: {{ $lastChecked ?? 'No checks yet' }}
            </p>
        </div>

    </div>

    {{-- SERVER + ALERT DETAILS WITH EDIT --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- SERVER INFO --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-800">Server Info</h3>

                <button type="button"
                        onclick="toggleBox('serverInfoEdit')"
                        class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                    <i class="fa-solid fa-pen mr-1"></i>Edit
                </button>
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
                        {{ $lastChecked ?? 'No checks yet' }}
                    </span>
                </div>
            </div>

            <div id="serverInfoEdit" class="hidden mt-6 border-t pt-5">
                <form method="POST" action="{{ route('servers.update', $server) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="admin_email" value="{{ $server->admin_email }}">
                    <input type="hidden" name="admin_phone" value="{{ $server->admin_phone }}">
                    <input type="hidden" name="customer_name" value="{{ $server->customer_name }}">
                    <input type="hidden" name="customer_email" value="{{ $server->customer_email }}">
                    <input type="hidden" name="customer_phone" value="{{ $server->customer_phone }}">
                    <input type="hidden" name="backup_server_id" value="{{ $server->backup_server_id }}">
                    <input type="hidden" name="backup_path" value="{{ $server->backup_path }}">
                    <input type="hidden" name="local_backup_path" value="{{ $server->local_backup_path }}">
                    <input type="hidden" name="google_drive_remote" value="{{ $server->google_drive_remote }}">
                    <input type="hidden" name="disk_warning_percent" value="{{ $server->disk_warning_percent ?? 80 }}">
                    <input type="hidden" name="disk_transfer_percent" value="{{ $server->disk_transfer_percent ?? 90 }}">

                    @if($server->is_active)
                        <input type="hidden" name="is_active" value="1">
                    @endif

                    @if($server->auto_transfer)
                        <input type="hidden" name="auto_transfer" value="1">
                    @endif

                    @if($server->google_drive_sync)
                        <input type="hidden" name="google_drive_sync" value="1">
                    @endif

                    @if($server->email_alerts_enabled)
                        <input type="hidden" name="email_alerts_enabled" value="1">
                    @endif

                    @if($server->sms_alerts_enabled)
                        <input type="hidden" name="sms_alerts_enabled" value="1">
                    @endif

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Server Name</label>
                        <input type="text"
                               name="name"
                               value="{{ old('name', $server->name) }}"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Host / IP</label>
                        <input type="text"
                               name="host"
                               value="{{ old('host', $server->host) }}"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Website URL</label>
                        <input type="url"
                               name="website_url"
                               value="{{ old('website_url', $server->website_url) }}"
                               placeholder="https://example.com"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">SSH Port</label>
                            <input type="number"
                                   name="ssh_port"
                                   value="{{ old('ssh_port', $server->ssh_port ?? 22) }}"
                                   class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Panel Type</label>
                            <select name="panel_type"
                                    class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">Auto Detect</option>
                                <option value="cpanel" {{ old('panel_type', $server->panel_type) === 'cpanel' ? 'selected' : '' }}>
                                    cPanel / WHM
                                </option>
                                <option value="plesk" {{ old('panel_type', $server->panel_type) === 'plesk' ? 'selected' : '' }}>
                                    Plesk
                                </option>
                                <option value="none" {{ old('panel_type', $server->panel_type) === 'none' ? 'selected' : '' }}>
                                    No Panel
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Username</label>
                        <input type="text"
                               name="username"
                               value="{{ old('username', $server->username) }}"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Password</label>
                        <input type="password"
                               name="password"
                               placeholder="Leave blank to keep current password"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <button class="w-full px-5 py-3 rounded-xl bg-blue-600 text-white font-black hover:bg-blue-700">
                        Save Server Info
                    </button>
                </form>
            </div>
        </div>

        {{-- ADMIN ALERTS --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-800">Admin Alerts</h3>

                <button type="button"
                        onclick="toggleBox('adminAlertsEdit')"
                        class="px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-bold hover:bg-purple-700">
                    <i class="fa-solid fa-pen mr-1"></i>Edit
                </button>
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

            <div id="adminAlertsEdit" class="hidden mt-6 border-t pt-5">
                <form method="POST" action="{{ route('servers.update', $server) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="name" value="{{ $server->name }}">
                    <input type="hidden" name="host" value="{{ $server->host }}">
                    <input type="hidden" name="website_url" value="{{ $server->website_url }}">
                    <input type="hidden" name="panel_type" value="{{ $server->panel_type }}">
                    <input type="hidden" name="ssh_port" value="{{ $server->ssh_port ?? 22 }}">
                    <input type="hidden" name="username" value="{{ $server->username }}">
                    <input type="hidden" name="customer_name" value="{{ $server->customer_name }}">
                    <input type="hidden" name="customer_email" value="{{ $server->customer_email }}">
                    <input type="hidden" name="customer_phone" value="{{ $server->customer_phone }}">
                    <input type="hidden" name="backup_server_id" value="{{ $server->backup_server_id }}">
                    <input type="hidden" name="backup_path" value="{{ $server->backup_path }}">
                    <input type="hidden" name="local_backup_path" value="{{ $server->local_backup_path }}">
                    <input type="hidden" name="google_drive_remote" value="{{ $server->google_drive_remote }}">
                    <input type="hidden" name="disk_warning_percent" value="{{ $server->disk_warning_percent ?? 80 }}">
                    <input type="hidden" name="disk_transfer_percent" value="{{ $server->disk_transfer_percent ?? 90 }}">

                    @if($server->is_active)
                        <input type="hidden" name="is_active" value="1">
                    @endif

                    @if($server->auto_transfer)
                        <input type="hidden" name="auto_transfer" value="1">
                    @endif

                    @if($server->google_drive_sync)
                        <input type="hidden" name="google_drive_sync" value="1">
                    @endif

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Admin Email</label>
                        <input type="email"
                               name="admin_email"
                               value="{{ old('admin_email', $server->admin_email) }}"
                               placeholder="admin@example.com"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-purple-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Admin Phone</label>
                        <input type="text"
                               name="admin_phone"
                               value="{{ old('admin_phone', $server->admin_phone) }}"
                               placeholder="947XXXXXXXX"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-purple-500 outline-none">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 rounded-xl border p-4 cursor-pointer hover:bg-slate-50">
                            <input type="checkbox"
                                   name="email_alerts_enabled"
                                   value="1"
                                   class="w-5 h-5"
                                   {{ old('email_alerts_enabled', $server->email_alerts_enabled) ? 'checked' : '' }}>
                            <span class="font-bold text-slate-700">Email Alerts</span>
                        </label>

                        <label class="flex items-center gap-3 rounded-xl border p-4 cursor-pointer hover:bg-slate-50">
                            <input type="checkbox"
                                   name="sms_alerts_enabled"
                                   value="1"
                                   class="w-5 h-5"
                                   {{ old('sms_alerts_enabled', $server->sms_alerts_enabled) ? 'checked' : '' }}>
                            <span class="font-bold text-slate-700">SMS Alerts</span>
                        </label>
                    </div>

                    <button class="w-full px-5 py-3 rounded-xl bg-purple-600 text-white font-black hover:bg-purple-700">
                        Save Admin Alerts
                    </button>
                </form>
            </div>
        </div>

        {{-- CUSTOMER ALERTS --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-800">Customer Alerts</h3>

                <button type="button"
                        onclick="toggleBox('customerAlertsEdit')"
                        class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-bold hover:bg-green-700">
                    <i class="fa-solid fa-pen mr-1"></i>Edit
                </button>
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

            <div id="customerAlertsEdit" class="hidden mt-6 border-t pt-5">
                <form method="POST" action="{{ route('servers.update', $server) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="name" value="{{ $server->name }}">
                    <input type="hidden" name="host" value="{{ $server->host }}">
                    <input type="hidden" name="website_url" value="{{ $server->website_url }}">
                    <input type="hidden" name="panel_type" value="{{ $server->panel_type }}">
                    <input type="hidden" name="ssh_port" value="{{ $server->ssh_port ?? 22 }}">
                    <input type="hidden" name="username" value="{{ $server->username }}">
                    <input type="hidden" name="admin_email" value="{{ $server->admin_email }}">
                    <input type="hidden" name="admin_phone" value="{{ $server->admin_phone }}">
                    <input type="hidden" name="backup_server_id" value="{{ $server->backup_server_id }}">
                    <input type="hidden" name="backup_path" value="{{ $server->backup_path }}">
                    <input type="hidden" name="local_backup_path" value="{{ $server->local_backup_path }}">
                    <input type="hidden" name="google_drive_remote" value="{{ $server->google_drive_remote }}">
                    <input type="hidden" name="disk_warning_percent" value="{{ $server->disk_warning_percent ?? 80 }}">
                    <input type="hidden" name="disk_transfer_percent" value="{{ $server->disk_transfer_percent ?? 90 }}">

                    @if($server->is_active)
                        <input type="hidden" name="is_active" value="1">
                    @endif

                    @if($server->auto_transfer)
                        <input type="hidden" name="auto_transfer" value="1">
                    @endif

                    @if($server->google_drive_sync)
                        <input type="hidden" name="google_drive_sync" value="1">
                    @endif

                    @if($server->email_alerts_enabled)
                        <input type="hidden" name="email_alerts_enabled" value="1">
                    @endif

                    @if($server->sms_alerts_enabled)
                        <input type="hidden" name="sms_alerts_enabled" value="1">
                    @endif

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Customer Name</label>
                        <input type="text"
                               name="customer_name"
                               value="{{ old('customer_name', $server->customer_name) }}"
                               placeholder="Customer name"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-green-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Customer Email</label>
                        <input type="email"
                               name="customer_email"
                               value="{{ old('customer_email', $server->customer_email) }}"
                               placeholder="customer@example.com"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-green-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Customer Phone</label>
                        <input type="text"
                               name="customer_phone"
                               value="{{ old('customer_phone', $server->customer_phone) }}"
                               placeholder="947XXXXXXXX"
                               class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-green-500 outline-none">
                    </div>

                    <button class="w-full px-5 py-3 rounded-xl bg-green-600 text-white font-black hover:bg-green-700">
                        Save Customer Alerts
                    </button>
                </form>
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

            <div class="rounded-2xl border p-4">
                <p class="text-sm text-slate-500 font-semibold">LSWS</p>
                <h4 class="text-xl font-black mt-1 {{ ($services['lsws'] ?? '') === 'active' ? 'text-green-600' : 'text-slate-700' }}">
                    {{ ucfirst($services['lsws'] ?? 'unknown') }}
                </h4>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-sm text-slate-500 font-semibold">LSHTTPD</p>
                <h4 class="text-xl font-black mt-1 {{ ($services['lshttpd'] ?? '') === 'active' ? 'text-green-600' : 'text-slate-700' }}">
                    {{ ucfirst($services['lshttpd'] ?? 'unknown') }}
                </h4>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-sm text-slate-500 font-semibold">OpenLiteSpeed</p>
                <h4 class="text-xl font-black mt-1 {{ ($services['openlitespeed'] ?? '') === 'active' ? 'text-green-600' : 'text-slate-700' }}">
                    {{ ucfirst($services['openlitespeed'] ?? 'unknown') }}
                </h4>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-sm text-slate-500 font-semibold">LiteSpeed</p>
                <h4 class="text-xl font-black mt-1 {{ ($services['litespeed'] ?? '') === 'active' ? 'text-green-600' : 'text-slate-700' }}">
                    {{ ucfirst($services['litespeed'] ?? 'unknown') }}
                </h4>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-sm text-slate-500 font-semibold">LSWSCTRL</p>
                <h4 class="text-xl font-black mt-1 {{ ($services['lswsctrl'] ?? '') === 'active' ? 'text-green-600' : 'text-slate-700' }}">
                    {{ ucfirst($services['lswsctrl'] ?? 'unknown') }}
                </h4>
            </div>

        </div>
    </div>

    {{-- MANUAL SMS --}}
    @if(Route::has('sms.send'))
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h3 class="text-xl font-black text-slate-800 mb-4">Send Manual SMS</h3>

            <form method="POST" action="{{ route('sms.send') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @csrf

                <div>
                    <label class="font-bold text-slate-700">Phone Number</label>
                    <input type="text"
                           name="phone"
                           value="{{ old('phone', $customerPhone ?? $adminPhone ?? '') }}"
                           placeholder="947XXXXXXXX"
                           required
                           class="w-full mt-1 px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div class="lg:col-span-2">
                    <label class="font-bold text-slate-700">Message</label>
                    <input type="text"
                           name="message"
                           value="{{ old('message', $isOnline ? 'Webscept: '.$server->name.' is online.' : 'Webscept Alert: '.$server->name.' is offline.') }}"
                           maxlength="500"
                           required
                           class="w-full mt-1 px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div class="lg:col-span-3">
                    <button class="px-6 py-3 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-700">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Send SMS
                    </button>
                </div>
            </form>
        </div>
    @endif

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

                <div class="rounded-xl border p-4 md:col-span-2">
                    <p class="font-bold">LiteSpeed</p>

                    @if($litespeedActive)
                        <span class="text-green-600 font-black">Active</span>
                    @elseif($litespeedStatus)
                        <span class="text-red-600 font-black">{{ ucfirst($litespeedStatus) }}</span>
                    @else
                        <span class="text-slate-500 font-black">Unknown</span>
                    @endif

                    <p class="text-xs text-slate-500 mt-1">LSWS / OpenLiteSpeed / LSHTTPD</p>
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
                    Abuse, firewall, email, SSH, LiteSpeed, service and disk issues.
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
                <h4 class="font-black text-slate-800">Email Abuse Protection</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Monitor Exim queue, suspicious forwarders, spam scripts and outgoing mail limits.
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-black text-slate-800">Customer File Protection</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Keep daily backups, scan web directories, and restrict destructive terminal commands.
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

<script>
function toggleBox(id) {
    const box = document.getElementById(id);

    if (!box) {
        return;
    }

    box.classList.toggle('hidden');
}
</script>

@endsection