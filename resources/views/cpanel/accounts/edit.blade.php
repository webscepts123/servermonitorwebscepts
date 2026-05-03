@extends('layouts.app')

@section('page-title', 'Manage cPanel Account')

@section('content')

@php
    $domain = $account['domain'] ?? 'Unknown domain';
    $email = $account['email'] ?? '-';
    $ip = $account['ip'] ?? '-';
    $package = $account['plan'] ?? '-';
    $diskUsed = $realDiskUsage ?? ($account['diskused'] ?? '-');
    $diskLimit = $realDiskLimit ?? ($account['disklimit'] ?? 'N/A');
    $owner = $account['owner'] ?? '-';
    $theme = $account['theme'] ?? '-';
    $suspended = !empty($account['suspended']);

    $adminEmail = $server->admin_email ?? null;
    $adminPhone = $server->admin_phone ?? null;

    $customerName = $server->customer_name ?? $user;
    $customerEmail = $server->customer_email ?? ($email !== '-' ? $email : null);
    $customerPhone = $server->customer_phone ?? null;

    $emailAlertsEnabled = !empty($server->email_alerts_enabled);
    $smsAlertsEnabled = !empty($server->sms_alerts_enabled);

    $accountStatus = $suspended ? 'Suspended' : 'Active';

    $wp = $wordpressData ?? [
        'detected' => false,
        'wp_cli_available' => false,
        'version' => null,
        'plugins_total' => 0,
        'plugins_active' => 0,
        'plugins_update' => 0,
        'themes_total' => 0,
        'themes_active' => 0,
        'themes_update' => 0,
        'status_message' => 'Not checked',
        'plugins' => [],
        'themes' => [],
    ];

    $mailSecurity = $emailSecurityData ?? [
        'spf' => 'Unknown',
        'dkim' => 'Unknown',
        'dmarc' => 'Unknown',
    ];

    $services = $remoteServices ?? [];

    $serverStatus = strtolower($server->status ?? 'unknown');

    $downMessage = "Webscept Alert: {$domain} account/server may be down or unavailable. Please check immediately.";
    $recoveryMessage = "Webscept Update: {$domain} account/server is back online and working.";

    $isWpDetected = !empty($wp['detected']);
    $wpStatusMessage = $wp['status_message'] ?? 'Not checked';
@endphp

<div class="space-y-6">

    {{-- HERO HEADER --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 shadow-2xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-28 -left-24 w-80 h-80 rounded-full bg-purple-500/20 blur-3xl"></div>

        <div class="relative p-6 lg:p-8 text-white">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">

                <div>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="w-16 h-16 rounded-3xl bg-white/10 border border-white/20 flex items-center justify-center">
                            <i class="fa-solid fa-user-gear text-3xl"></i>
                        </div>

                        <div>
                            <h2 class="text-3xl lg:text-4xl font-black tracking-tight">
                                Manage Account: {{ $user }}
                            </h2>

                            <p class="text-slate-300 mt-2">
                                {{ $domain }} — {{ $server->host }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                            <i class="fa-solid fa-location-dot mr-1"></i>IP: {{ $ip }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                            <i class="fa-solid fa-box mr-1"></i>Package: {{ $package }}
                        </span>

                        <span class="px-4 py-2 rounded-full text-xs font-bold
                            {{ $suspended ? 'bg-red-500/20 border border-red-400/40 text-red-100' : 'bg-green-500/20 border border-green-400/40 text-green-100' }}">
                            <i class="fa-solid {{ $suspended ? 'fa-ban' : 'fa-circle-check' }} mr-1"></i>
                            {{ $accountStatus }}
                        </span>

                        <span class="px-4 py-2 rounded-full text-xs font-bold
                            {{ $smsAlertsEnabled ? 'bg-green-500/20 border border-green-400/40 text-green-100' : 'bg-slate-500/20 border border-slate-400/40 text-slate-100' }}">
                            <i class="fa-solid fa-message mr-1"></i>
                            SMS {{ $smsAlertsEnabled ? 'Enabled' : 'Disabled' }}
                        </span>

                        <span class="px-4 py-2 rounded-full text-xs font-bold
                            {{ $emailAlertsEnabled ? 'bg-blue-500/20 border border-blue-400/40 text-blue-100' : 'bg-slate-500/20 border border-slate-400/40 text-slate-100' }}">
                            <i class="fa-solid fa-envelope mr-1"></i>
                            Email {{ $emailAlertsEnabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 w-full xl:w-auto">
                    <a href="{{ route('servers.cpanel.index', $server) }}"
                       class="text-center px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white hover:bg-white/20 font-bold">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Accounts
                    </a>

                    @if($domain !== 'Unknown domain')
                        <a href="https://{{ $domain }}" target="_blank"
                           class="text-center px-5 py-3 rounded-2xl bg-blue-600 text-white hover:bg-blue-700 font-bold shadow-lg">
                            <i class="fa-solid fa-globe mr-2"></i>Website
                        </a>
                    @endif

                    @if(Route::has('servers.cpanel.login'))
                        <a href="{{ route('servers.cpanel.login', [$server, $user]) }}" target="_blank"
                           class="text-center px-5 py-3 rounded-2xl bg-green-600 text-white hover:bg-green-700 font-bold shadow-lg">
                            <i class="fa-solid fa-right-to-bracket mr-2"></i>Auto Login
                        </a>
                    @endif

                    @if(Route::has('servers.cpanel.login.email'))
                        <a href="{{ route('servers.cpanel.login.email', [$server, $user]) }}" target="_blank"
                           class="text-center px-5 py-3 rounded-2xl bg-purple-600 text-white hover:bg-purple-700 font-bold shadow-lg">
                            <i class="fa-solid fa-envelope mr-2"></i>Email
                        </a>
                    @endif

                    @if(Route::has('servers.cpanel.login.files'))
                        <a href="{{ route('servers.cpanel.login.files', [$server, $user]) }}" target="_blank"
                           class="text-center px-5 py-3 rounded-2xl bg-orange-600 text-white hover:bg-orange-700 font-bold shadow-lg">
                            <i class="fa-solid fa-folder-open mr-2"></i>Files
                        </a>
                    @endif

                    <button onclick="location.reload()"
                            class="text-center px-5 py-3 rounded-2xl bg-slate-700 text-white hover:bg-slate-600 font-bold shadow-lg">
                        <i class="fa-solid fa-rotate mr-2"></i>Refresh
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- MESSAGES --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif

    @if(!empty($error))
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>{{ $error }}
        </div>
    @endif

    {{-- QUICK STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">

        <button type="button" onclick="copyText('{{ $domain }}')"
                class="text-left bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Domain</p>
                    <h3 class="font-black text-slate-800 mt-1 break-all">{{ $domain }}</h3>
                    <p class="text-xs text-blue-600 mt-2">Click to copy</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-globe text-xl"></i>
                </div>
            </div>
        </button>

        <button type="button" onclick="copyText('{{ $email }}')"
                class="text-left bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Contact Email</p>
                    <h3 class="font-black text-slate-800 mt-1 break-all">{{ $email }}</h3>
                    <p class="text-xs text-blue-600 mt-2">Click to copy</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-envelope text-xl"></i>
                </div>
            </div>
        </button>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Disk Used</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-1">{{ $diskUsed ?: '-' }}</h3>
                    <p class="text-xs text-slate-500 mt-2">Limit: {{ $diskLimit }}</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-orange-100 text-orange-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-hard-drive text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Owner / Theme</p>
                    <h3 class="font-black text-slate-800 mt-1">{{ $owner }} / {{ $theme }}</h3>
                    <p class="text-xs text-slate-500 mt-2">Server: {{ ucfirst($serverStatus) }}</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-box text-xl"></i>
                </div>
            </div>
        </div>

    </div>

    {{-- TABS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-3 sticky top-4 z-20">
        <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-2">
            <button type="button" class="tab-btn active-tab" data-tab="overview">
                <i class="fa-solid fa-chart-line mr-2"></i>Overview
            </button>
            <button type="button" class="tab-btn" data-tab="alerts">
                <i class="fa-solid fa-bell mr-2"></i>Alerts
            </button>
            <button type="button" class="tab-btn" data-tab="manage">
                <i class="fa-solid fa-sliders mr-2"></i>Manage
            </button>
            <button type="button" class="tab-btn" data-tab="wordpress">
                <i class="fa-brands fa-wordpress mr-2"></i>WordPress
            </button>
            <button type="button" class="tab-btn" data-tab="email">
                <i class="fa-solid fa-envelope-circle-check mr-2"></i>Email DNS
            </button>
            <button type="button" class="tab-btn" data-tab="security">
                <i class="fa-solid fa-shield-halved mr-2"></i>Security
            </button>
            <button type="button" class="tab-btn" data-tab="services">
                <i class="fa-solid fa-server mr-2"></i>Services
            </button>
        </div>
    </div>

    {{-- OVERVIEW TAB --}}
    <div class="tab-panel" id="tab-overview">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-blue-600"></i>
                    Admin Alert Details
                </h3>

                <div class="space-y-4 text-sm">
                    <div>
                        <p class="text-slate-500">Admin Email</p>
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-bold text-slate-800 break-all">{{ $adminEmail ?? 'Not set' }}</p>
                            @if($adminEmail)
                                <button onclick="copyText('{{ $adminEmail }}')" class="text-blue-600 text-xs font-bold">Copy</button>
                            @endif
                        </div>
                    </div>

                    <div>
                        <p class="text-slate-500">Admin Phone</p>
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-bold text-slate-800">{{ $adminPhone ?? 'Not set' }}</p>
                            @if($adminPhone)
                                <button onclick="copyText('{{ $adminPhone }}')" class="text-blue-600 text-xs font-bold">Copy</button>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $emailAlertsEnabled ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                            Email {{ $emailAlertsEnabled ? 'Enabled' : 'Disabled' }}
                        </span>

                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $smsAlertsEnabled ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                            SMS {{ $smsAlertsEnabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                    <i class="fa-solid fa-user text-purple-600"></i>
                    Customer Alert Details
                </h3>

                <div class="space-y-4 text-sm">
                    <div>
                        <p class="text-slate-500">Customer Name</p>
                        <p class="font-bold text-slate-800">{{ $customerName ?? 'Not set' }}</p>
                    </div>

                    <div>
                        <p class="text-slate-500">Customer Email</p>
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-bold text-slate-800 break-all">{{ $customerEmail ?? 'Not set' }}</p>
                            @if($customerEmail)
                                <button onclick="copyText('{{ $customerEmail }}')" class="text-blue-600 text-xs font-bold">Copy</button>
                            @endif
                        </div>
                    </div>

                    <div>
                        <p class="text-slate-500">Customer Phone</p>
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-bold text-slate-800">{{ $customerPhone ?? 'Not set' }}</p>
                            @if($customerPhone)
                                <button onclick="copyText('{{ $customerPhone }}')" class="text-blue-600 text-xs font-bold">Copy</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-green-600"></i>
                    Account Status
                </h3>

                <div class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Account</span>
                        <span class="font-bold {{ $suspended ? 'text-red-600' : 'text-green-600' }}">
                            {{ $accountStatus }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Domain</span>
                        <span class="font-bold break-all text-slate-800">{{ $domain }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Server</span>
                        <span class="font-bold text-slate-800">{{ $server->name ?? $server->host }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Server Status</span>
                        <span class="font-bold {{ $serverStatus === 'online' ? 'text-green-600' : 'text-red-600' }}">
                            {{ ucfirst($server->status ?? 'unknown') }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ALERTS TAB --}}
    <div class="tab-panel hidden" id="tab-alerts">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                    <i class="fa-solid fa-message text-green-600"></i>
                    Send Manual SMS
                </h3>

                <form method="POST"
                      action="{{ Route::has('servers.cpanel.sms') ? route('servers.cpanel.sms', [$server, $user]) : (Route::has('sms.send') ? route('sms.send') : '#') }}"
                      class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold mb-1">Phone Number</label>
                        <input type="text"
                               name="phone"
                               value="{{ old('phone', $customerPhone ?? $adminPhone ?? '') }}"
                               placeholder="947XXXXXXXX"
                               required
                               class="form-input-modern">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Message</label>
                        <textarea name="message"
                                  rows="4"
                                  maxlength="500"
                                  required
                                  class="form-input-modern">{{ old('message', "Webscept Alert: {$domain} account status is {$accountStatus}.") }}</textarea>
                    </div>

                    <button class="w-full px-5 py-3 rounded-2xl bg-green-600 text-white hover:bg-green-700 font-bold">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Send SMS
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                    <i class="fa-solid fa-envelope text-blue-600"></i>
                    Send Manual Email
                </h3>

                <form method="POST"
                      action="{{ Route::has('servers.cpanel.email') ? route('servers.cpanel.email', [$server, $user]) : (Route::has('email.send') ? route('email.send') : '#') }}"
                      class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold mb-1">Email Address</label>
                        <input type="email"
                               name="email"
                               value="{{ old('email', $customerEmail ?? $adminEmail ?? '') }}"
                               required
                               class="form-input-modern">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Subject</label>
                        <input type="text"
                               name="subject"
                               value="{{ old('subject', 'Webscept Account Alert - '.$domain) }}"
                               required
                               class="form-input-modern">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Message</label>
                        <textarea name="message"
                                  rows="4"
                                  required
                                  class="form-input-modern">{{ old('message', "Hello,\n\nThis is an update regarding your account {$domain}.\n\nStatus: {$accountStatus}\nServer: {$server->host}\n\nRegards,\nWebscept Monitoring") }}</textarea>
                    </div>

                    <button class="w-full px-5 py-3 rounded-2xl bg-blue-600 text-white hover:bg-blue-700 font-bold">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Send Email
                    </button>
                </form>
            </div>

        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6 mt-6">
            <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                <i class="fa-solid fa-bell text-red-600"></i>
                Quick Alert Actions
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                @if(Route::has('sms.down'))
                    <form method="POST" action="{{ route('sms.down', $server) }}">
                        @csrf
                        <button onclick="return confirm('Send DOWN SMS alert to admin and customer?')"
                                class="quick-action bg-red-600 hover:bg-red-700">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            Send Down SMS
                        </button>
                    </form>
                @endif

                @if(Route::has('sms.recovery'))
                    <form method="POST" action="{{ route('sms.recovery', $server) }}">
                        @csrf
                        <button onclick="return confirm('Send RECOVERY SMS alert to admin and customer?')"
                                class="quick-action bg-emerald-600 hover:bg-emerald-700">
                            <i class="fa-solid fa-circle-check"></i>
                            Send Recovery SMS
                        </button>
                    </form>
                @endif

                @if(Route::has('email.down'))
                    <form method="POST" action="{{ route('email.down', $server) }}">
                        @csrf
                        <button onclick="return confirm('Send DOWN email alert to admin and customer?')"
                                class="quick-action bg-orange-600 hover:bg-orange-700">
                            <i class="fa-solid fa-envelope"></i>
                            Send Down Email
                        </button>
                    </form>
                @endif

                @if(Route::has('email.recovery'))
                    <form method="POST" action="{{ route('email.recovery', $server) }}">
                        @csrf
                        <button onclick="return confirm('Send RECOVERY email alert to admin and customer?')"
                                class="quick-action bg-blue-600 hover:bg-blue-700">
                            <i class="fa-solid fa-envelope-circle-check"></i>
                            Send Recovery Email
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- MANAGE TAB --}}
    <div class="tab-panel hidden" id="tab-manage">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-lg font-black mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-key text-blue-600"></i>
                    Update Password
                </h3>

                <form method="POST" action="{{ route('servers.cpanel.password', [$server, $user]) }}">
                    @csrf

                    <label class="block text-sm font-semibold mb-1">New Password</label>
                    <input type="password" name="password" class="form-input-modern" placeholder="Enter strong password">

                    @error('password')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror

                    <button class="mt-4 w-full px-5 py-3 rounded-2xl bg-blue-600 text-white hover:bg-blue-700 font-bold">
                        Update Password
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-lg font-black mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-purple-600"></i>
                    Change Package
                </h3>

                <form method="POST" action="{{ route('servers.cpanel.package', [$server, $user]) }}">
                    @csrf

                    <label class="block text-sm font-semibold mb-1">Package</label>
                    <select name="package" class="form-input-modern">
                        @foreach($packages ?? [] as $packageItem)
                            @php
                                $pkgName = $packageItem['name'] ?? $packageItem['pkg'] ?? null;
                                $currentPlan = $account['plan'] ?? null;
                            @endphp

                            @if($pkgName)
                                <option value="{{ $pkgName }}" {{ $currentPlan == $pkgName ? 'selected' : '' }}>
                                    {{ $pkgName }}
                                </option>
                            @endif
                        @endforeach
                    </select>

                    <button class="mt-4 w-full px-5 py-3 rounded-2xl bg-purple-600 text-white hover:bg-purple-700 font-bold">
                        Change Package
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-lg font-black mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-network-wired text-green-600"></i>
                    Change IP Address
                </h3>

                <form method="POST" action="{{ route('servers.cpanel.ip', [$server, $user]) }}">
                    @csrf

                    <label class="block text-sm font-semibold mb-1">IP Address</label>
                    <select name="ip" class="form-input-modern">
                        @foreach($ips ?? [] as $ipItem)
                            @php
                                $ipValue = $ipItem['ip'] ?? null;
                                $currentIp = $account['ip'] ?? null;
                            @endphp

                            @if($ipValue)
                                <option value="{{ $ipValue }}" {{ $currentIp == $ipValue ? 'selected' : '' }}>
                                    {{ $ipValue }}
                                </option>
                            @endif
                        @endforeach
                    </select>

                    <button class="mt-4 w-full px-5 py-3 rounded-2xl bg-green-600 text-white hover:bg-green-700 font-bold">
                        Change IP
                    </button>
                </form>
            </div>

        </div>
    </div>

    {{-- WORDPRESS TAB --}}
    <div class="tab-panel hidden" id="tab-wordpress">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-6">
                <div>
                    <h3 class="text-2xl font-black flex items-center gap-2">
                        <i class="fa-brands fa-wordpress text-blue-700"></i>
                        WordPress Manager
                    </h3>
                    <p class="text-sm text-slate-500">
                        Real remote WordPress data from {{ $realPublicHtml ?? "/home/{$user}/public_html" }}.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if(Route::has('servers.wordpress.show'))
                        <a href="{{ route('servers.wordpress.show', [$server, $user]) }}"
                           class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm font-bold">
                            Open Manager
                        </a>
                    @endif

                    @if(Route::has('servers.cpanel.login.wordpress'))
                        <a href="{{ route('servers.cpanel.login.wordpress', [$server, $user]) }}"
                           target="_blank"
                           class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-700 text-sm font-bold">
                            Auto Login WP
                        </a>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="wp-card">
                    <p class="text-sm text-slate-500">WP Core</p>
                    <h4 class="text-2xl font-black mt-1">{{ $wp['version'] ?? 'Not Detected' }}</h4>
                    <p class="text-xs text-slate-500 mt-2">{{ $wpStatusMessage }}</p>
                </div>

                <div class="wp-card">
                    <p class="text-sm text-slate-500">Plugins</p>
                    <h4 class="text-2xl font-black mt-1">{{ $wp['plugins_total'] ?? 0 }}</h4>
                    <p class="text-xs text-green-600 mt-2">{{ $wp['plugins_active'] ?? 0 }} active</p>
                    <p class="text-xs text-red-600">{{ $wp['plugins_update'] ?? 0 }} updates</p>
                </div>

                <div class="wp-card">
                    <p class="text-sm text-slate-500">Themes</p>
                    <h4 class="text-2xl font-black mt-1">{{ $wp['themes_total'] ?? 0 }}</h4>
                    <p class="text-xs text-green-600 mt-2">{{ $wp['themes_active'] ?? 0 }} active</p>
                    <p class="text-xs text-red-600">{{ $wp['themes_update'] ?? 0 }} updates</p>
                </div>

                <div class="wp-card">
                    <p class="text-sm text-slate-500">Status</p>
                    @if($isWpDetected)
                        <h4 class="text-lg font-black mt-1 text-green-600">WordPress Detected</h4>
                    @else
                        <h4 class="text-lg font-black mt-1 text-red-600">Not Detected</h4>
                    @endif
                    <p class="text-xs text-slate-500 mt-2">
                        WP-CLI: {{ !empty($wp['wp_cli_available']) ? 'Available' : 'Unavailable' }}
                    </p>
                </div>
            </div>

            @if(!empty($wp['plugins']))
                <div class="mt-6 overflow-x-auto rounded-2xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-3 text-left">Plugin</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-left">Version</th>
                                <th class="p-3 text-left">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wp['plugins'] as $plugin)
                                <tr class="border-t">
                                    <td class="p-3 font-semibold">{{ $plugin['name'] ?? '-' }}</td>
                                    <td class="p-3">{{ $plugin['status'] ?? '-' }}</td>
                                    <td class="p-3">{{ $plugin['version'] ?? '-' }}</td>
                                    <td class="p-3">
                                        <span class="{{ ($plugin['update'] ?? '') === 'available' ? 'text-red-600 font-bold' : 'text-green-600 font-bold' }}">
                                            {{ $plugin['update'] ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-3">
                @if(Route::has('servers.wordpress.coreUpdate'))
                    <form method="POST" action="{{ route('servers.wordpress.coreUpdate', [$server, $user]) }}">
                        @csrf
                        <button class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold">
                            Update Core
                        </button>
                    </form>
                @endif

                @if(Route::has('servers.wordpress.plugins.updateAll'))
                    <form method="POST" action="{{ route('servers.wordpress.plugins.updateAll', [$server, $user]) }}">
                        @csrf
                        <button class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-bold">
                            Update Plugins
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- EMAIL DNS TAB --}}
    <div class="tab-panel hidden" id="tab-email">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h3 class="text-2xl font-black mb-5 flex items-center gap-2">
                <i class="fa-solid fa-envelope-circle-check text-green-600"></i>
                Email Account & Domain Security
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="dns-card">
                    <h4 class="font-black">SPF Record</h4>
                    <p class="text-sm mt-2 {{ ($mailSecurity['spf'] ?? '') === 'Configured' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $mailSecurity['spf'] ?? 'Unknown' }}
                    </p>
                </div>

                <div class="dns-card">
                    <h4 class="font-black">DKIM Record</h4>
                    <p class="text-sm mt-2 {{ str_contains($mailSecurity['dkim'] ?? '', 'Configured') ? 'text-green-600' : 'text-red-600' }}">
                        {{ $mailSecurity['dkim'] ?? 'Unknown' }}
                    </p>
                </div>

                <div class="dns-card">
                    <h4 class="font-black">DMARC Policy</h4>
                    <p class="text-sm mt-2 {{ ($mailSecurity['dmarc'] ?? '') === 'Configured' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $mailSecurity['dmarc'] ?? 'Unknown' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- SECURITY TAB --}}
    <div class="tab-panel hidden" id="tab-security">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-red-600"></i>
                    Account Security
                </h3>

                <div class="space-y-3">
                    <div class="security-item">
                        <i class="fa-solid fa-lock text-green-600 mt-1"></i>
                        <div>
                            <h4 class="font-bold">Password Security</h4>
                            <p class="text-sm text-slate-500">Use a strong password and update it regularly.</p>
                        </div>
                    </div>

                    <div class="security-item">
                        <i class="fa-solid fa-user-shield text-blue-600 mt-1"></i>
                        <div>
                            <h4 class="font-bold">cPanel Access</h4>
                            <p class="text-sm text-slate-500">Check unknown logins from cPanel access logs.</p>
                        </div>
                    </div>

                    <div class="security-item">
                        <i class="fa-solid fa-bug text-red-600 mt-1"></i>
                        <div>
                            <h4 class="font-bold">Malware / Abuse</h4>
                            <p class="text-sm text-slate-500">Scan public_html for suspicious PHP files and mail scripts.</p>
                        </div>
                    </div>

                    <div class="security-item">
                        <i class="fa-solid fa-envelope-circle-check text-purple-600 mt-1"></i>
                        <div>
                            <h4 class="font-bold">Email Security</h4>
                            <p class="text-sm text-slate-500">Check forwarders, autoresponders, mail queue and spam scripts.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <h3 class="text-xl font-black mb-5 flex items-center gap-2">
                    <i class="fa-brands fa-wordpress text-blue-700"></i>
                    WordPress Hardening
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="security-mini-card">Keep WordPress core updated.</div>
                    <div class="security-mini-card">Remove unused plugins.</div>
                    <div class="security-mini-card">Delete old themes.</div>
                    <div class="security-mini-card">Protect wp-config.php.</div>
                </div>
            </div>

        </div>
    </div>

    {{-- SERVICES TAB --}}
    <div class="tab-panel hidden" id="tab-services">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h3 class="text-2xl font-black mb-5 flex items-center gap-2">
                <i class="fa-solid fa-server text-blue-600"></i>
                Remote Services
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse($services as $name => $status)
                    @php
                        $active = strtolower(trim($status)) === 'active';
                    @endphp

                    <div class="rounded-2xl border p-5">
                        <div class="flex justify-between items-center gap-4">
                            <span class="font-black uppercase">{{ $name }}</span>
                            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ ucfirst($status ?: 'unknown') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-8 rounded-2xl border text-center text-slate-500">
                        No remote service data available.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

</div>

{{-- TOAST --}}
<div id="copyToast"
     class="fixed bottom-6 right-6 hidden px-5 py-3 rounded-2xl bg-slate-900 text-white shadow-xl font-bold z-50">
    Copied
</div>

<style>
    .tab-btn {
        padding: 12px 14px;
        border-radius: 16px;
        font-weight: 800;
        color: #475569;
        background: #f1f5f9;
        transition: all .2s ease;
        font-size: 14px;
    }

    .tab-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .active-tab {
        background: #0f172a !important;
        color: white !important;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .18);
    }

    .form-input-modern {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 16px;
        padding: 12px 16px;
        outline: none;
        transition: all .2s ease;
    }

    .form-input-modern:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
    }

    .quick-action {
        width: 100%;
        color: white;
        padding: 14px 18px;
        border-radius: 16px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .wp-card,
    .dns-card {
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 20px;
        background: white;
        transition: all .2s ease;
    }

    .wp-card:hover,
    .dns-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
    }

    .security-item {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .security-mini-card {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 16px;
        font-weight: 700;
        color: #334155;
        background: #f8fafc;
    }
</style>

<script>
    function copyText(text) {
        if (!text || text === '-') return;

        navigator.clipboard.writeText(text).then(() => {
            const toast = document.getElementById('copyToast');
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 1600);
        });
    }

    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', function () {
            const tab = this.dataset.tab;

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active-tab');
            });

            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.add('hidden');
            });

            this.classList.add('active-tab');
            document.getElementById('tab-' + tab).classList.remove('hidden');

            localStorage.setItem('cpanelActiveTab', tab);
        });
    });

    const savedTab = localStorage.getItem('cpanelActiveTab');

    if (savedTab && document.querySelector(`[data-tab="${savedTab}"]`)) {
        document.querySelector(`[data-tab="${savedTab}"]`).click();
    }
</script>

@endsection