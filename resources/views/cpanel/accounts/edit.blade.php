@extends('layouts.app')

@section('page-title', 'Manage cPanel Account')

@section('content')

@php
    $account = $account ?? [];
    $packages = $packages ?? [];
    $ips = $ips ?? [];
    $error = $error ?? null;

    $domain = $account['domain'] ?? $account['main_domain'] ?? 'Unknown domain';
    $email = $account['email'] ?? $account['contactemail'] ?? $account['contact_email'] ?? '-';
    $plan = $account['plan'] ?? $account['package'] ?? '-';
    $ip = $account['ip'] ?? $account['ipv4'] ?? '-';
    $owner = $account['owner'] ?? '-';
    $theme = $account['theme'] ?? '-';
    $partition = $account['partition'] ?? '-';
    $suspended = !empty($account['suspended']);
    $suspendReason = $account['suspendreason'] ?? $account['suspend_reason'] ?? null;

    $diskUsed = $account['diskused'] ?? $account['diskused_human'] ?? $realDiskUsage ?? '-';
    $diskLimit = $account['disklimit'] ?? $account['disklimit_human'] ?? $realDiskLimit ?? '-';

    $realHomePath = $realHomePath ?? "/home/{$user}";
    $realPublicHtml = $realPublicHtml ?? "/home/{$user}/public_html";
    $remoteServices = $remoteServices ?? [];

    $wordpressData = $wordpressData ?? [
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

    $emailSecurityData = $emailSecurityData ?? [
        'spf' => 'Unknown',
        'dkim' => 'Unknown',
        'dmarc' => 'Unknown',
    ];

    $developerLoginUrl = 'https://developercodes.webscepts.com/login';
    $visualCodeEditorUrl = 'https://developercodes.webscepts.com/codeditor';
@endphp

<div class="max-w-7xl mx-auto space-y-6">

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

    @if($error)
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-black">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>
            {{ $error }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4">
            <div class="font-black mb-2">Please fix these errors:</div>
            <ul class="list-disc ml-5 text-sm font-bold">
                @foreach($errors->all() as $errorItem)
                    <li>{{ $errorItem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-purple-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl lg:text-5xl font-black tracking-tight">
                        {{ $user }}
                    </h1>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-black">
                        <i class="fa-solid fa-globe"></i>
                        {{ $domain }}
                    </span>

                    @if($suspended)
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-black">
                            <i class="fa-solid fa-ban"></i>
                            Suspended
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-black">
                            <i class="fa-solid fa-circle-check"></i>
                            Active
                        </span>
                    @endif
                </div>

                <p class="text-slate-300 mt-3 max-w-4xl">
                    Manage cPanel account settings, login shortcuts, package, IP, password, WordPress status, email security, and Developer Codes access.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Server: {{ $server->name ?? 'Server' }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Host: {{ $server->host ?? $server->hostname ?? $server->ip_address ?? '-' }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Package: {{ $plan }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        IP: {{ $ip }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row xl:flex-col gap-3 shrink-0">
                <a href="{{ route('servers.cpanel.index', $server) }}"
                   class="px-6 py-4 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-black text-center">
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    Back to Accounts
                </a>

                <a href="{{ route('servers.cpanel.login', [$server, $user]) }}"
                   target="_blank"
                   class="px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                    <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>
                    Auto Login
                </a>

                <a href="{{ route('servers.cpanel.login.files', [$server, $user]) }}"
                   target="_blank"
                   class="px-6 py-4 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black text-center">
                    <i class="fa-solid fa-folder-open mr-2"></i>
                    File Manager
                </a>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <a href="{{ route('servers.cpanel.login', [$server, $user]) }}"
           target="_blank"
           class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-lg transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">cPanel</p>
                    <h2 class="text-xl font-black text-slate-900 mt-2">Open Panel</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-gauge text-xl"></i>
                </div>
            </div>
        </a>

        <a href="{{ route('servers.cpanel.login.files', [$server, $user]) }}"
           target="_blank"
           class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-lg transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Files</p>
                    <h2 class="text-xl font-black text-slate-900 mt-2">File Manager</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-folder-tree text-xl"></i>
                </div>
            </div>
        </a>

        <a href="{{ route('servers.cpanel.login.email', [$server, $user]) }}"
           target="_blank"
           class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-lg transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Email</p>
                    <h2 class="text-xl font-black text-slate-900 mt-2">Email Accounts</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-envelope text-xl"></i>
                </div>
            </div>
        </a>

        <a href="{{ route('servers.cpanel.login.wordpress', [$server, $user]) }}"
           target="_blank"
           class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-lg transition">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">WordPress</p>
                    <h2 class="text-xl font-black text-slate-900 mt-2">WP Manager</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-cyan-100 text-cyan-700 flex items-center justify-center">
                    <i class="fa-brands fa-wordpress text-xl"></i>
                </div>
            </div>
        </a>
    </div>

    {{-- Account Overview --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="xl:col-span-2 bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Account Overview</h2>
                <p class="text-slate-500 mt-1">Main WHM/cPanel account information.</p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach([
                    'Username' => $user,
                    'Domain' => $domain,
                    'Contact Email' => $email,
                    'Owner' => $owner,
                    'Package' => $plan,
                    'IP Address' => $ip,
                    'Theme' => $theme,
                    'Partition' => $partition,
                    'Disk Used' => $diskUsed,
                    'Disk Limit' => $diskLimit,
                    'Home Path' => $realHomePath,
                    'Public HTML' => $realPublicHtml,
                ] as $label => $value)
                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <div class="text-xs uppercase tracking-wider text-slate-500 font-black">{{ $label }}</div>
                        <div class="text-slate-900 font-black mt-1 break-all">{{ $value ?: '-' }}</div>
                    </div>
                @endforeach

                @if($suspended)
                    <div class="md:col-span-2 rounded-2xl bg-red-50 border border-red-200 p-4">
                        <div class="text-xs uppercase tracking-wider text-red-500 font-black">Suspension Reason</div>
                        <div class="text-red-800 font-black mt-1">{{ $suspendReason ?: 'No reason given' }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Developer Codes --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-blue-50">
                <h2 class="text-2xl font-black text-slate-900">Developer Codes</h2>
                <p class="text-blue-700 text-sm font-bold mt-1">
                    Visual Code Editor uses saved real cPanel password.
                </p>
            </div>

            <div class="p-6 space-y-4">
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">Developer Login</div>
                    <a href="{{ $developerLoginUrl }}"
                       target="_blank"
                       class="text-blue-700 font-black mt-1 block break-all">
                        {{ $developerLoginUrl }}
                    </a>
                </div>

                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">Visual Code Editor</div>
                    <a href="{{ $visualCodeEditorUrl }}"
                       target="_blank"
                       class="text-blue-700 font-black mt-1 block break-all">
                        {{ $visualCodeEditorUrl }}
                    </a>
                </div>

                <div class="rounded-2xl bg-yellow-50 border border-yellow-200 p-4 text-sm text-yellow-800 font-bold">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    To make File Manager API work, update password below with the real cPanel password. The controller saves it encrypted.
                </div>
            </div>
        </div>
    </div>

    {{-- Update Forms --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Password --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-xl font-black text-slate-900">Update Password</h2>
                <p class="text-slate-500 text-sm mt-1">
                    Updates cPanel password and saves it for Visual Code Editor.
                </p>
            </div>

            <form method="POST" action="{{ route('servers.cpanel.password', [$server, $user]) }}" class="p-6 space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-black mb-1 text-slate-700">New Password</label>
                    <div class="flex gap-2">
                        <input type="password"
                               name="password"
                               id="accountPassword"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                        <button type="button"
                                onclick="togglePassword('accountPassword')"
                                class="px-4 rounded-xl bg-slate-100 hover:bg-slate-200 font-black text-sm">
                            Show
                        </button>
                    </div>
                    @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <button class="w-full px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    <i class="fa-solid fa-key mr-2"></i>
                    Update Password
                </button>
            </form>
        </div>

        {{-- Package --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-xl font-black text-slate-900">Change Package</h2>
                <p class="text-slate-500 text-sm mt-1">
                    Assign another WHM package.
                </p>
            </div>

            <form method="POST" action="{{ route('servers.cpanel.package', [$server, $user]) }}" class="p-6 space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-black mb-1 text-slate-700">Package</label>
                    <select name="package"
                            class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <option value="">Select Package</option>
                        @foreach($packages as $package)
                            @php
                                $pkgName = $package['name'] ?? $package['pkg'] ?? null;
                            @endphp

                            @if($pkgName)
                                <option value="{{ $pkgName }}" {{ $plan === $pkgName ? 'selected' : '' }}>
                                    {{ $pkgName }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @error('package') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <button class="w-full px-5 py-3 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black">
                    <i class="fa-solid fa-box mr-2"></i>
                    Update Package
                </button>
            </form>
        </div>

        {{-- IP --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-xl font-black text-slate-900">Change IP</h2>
                <p class="text-slate-500 text-sm mt-1">
                    Set site IP address.
                </p>
            </div>

            <form method="POST" action="{{ route('servers.cpanel.ip', [$server, $user]) }}" class="p-6 space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-black mb-1 text-slate-700">IP Address</label>
                    <select name="ip"
                            class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                        <option value="">Select IP</option>
                        @foreach($ips as $ipRow)
                            @php
                                $ipValue = $ipRow['ip'] ?? $ipRow['address'] ?? null;
                            @endphp

                            @if($ipValue)
                                <option value="{{ $ipValue }}" {{ $ip === $ipValue ? 'selected' : '' }}>
                                    {{ $ipValue }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @error('ip') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <button class="w-full px-5 py-3 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black">
                    <i class="fa-solid fa-network-wired mr-2"></i>
                    Update IP
                </button>
            </form>
        </div>
    </div>

    {{-- WordPress + Services --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- WordPress --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-900">WordPress Status</h2>
                    <p class="text-slate-500 mt-1">{{ $wordpressData['status_message'] ?? 'Not checked' }}</p>
                </div>

                <div class="w-14 h-14 rounded-2xl {{ !empty($wordpressData['detected']) ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center">
                    <i class="fa-brands fa-wordpress text-2xl"></i>
                </div>
            </div>

            <div class="p-6 grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">Detected</div>
                    <div class="text-xl font-black mt-1 {{ !empty($wordpressData['detected']) ? 'text-green-600' : 'text-red-600' }}">
                        {{ !empty($wordpressData['detected']) ? 'Yes' : 'No' }}
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">Version</div>
                    <div class="text-xl font-black text-slate-900 mt-1">{{ $wordpressData['version'] ?? '-' }}</div>
                </div>

                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">WP CLI</div>
                    <div class="text-xl font-black mt-1 {{ !empty($wordpressData['wp_cli_available']) ? 'text-green-600' : 'text-yellow-600' }}">
                        {{ !empty($wordpressData['wp_cli_available']) ? 'Yes' : 'No' }}
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">Plugins</div>
                    <div class="text-xl font-black text-slate-900 mt-1">{{ $wordpressData['plugins_total'] ?? 0 }}</div>
                    <div class="text-xs text-slate-500 font-bold">Active: {{ $wordpressData['plugins_active'] ?? 0 }}</div>
                </div>

                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">Plugin Updates</div>
                    <div class="text-xl font-black text-red-600 mt-1">{{ $wordpressData['plugins_update'] ?? 0 }}</div>
                </div>

                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">Theme Updates</div>
                    <div class="text-xl font-black text-red-600 mt-1">{{ $wordpressData['themes_update'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        {{-- Services --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Remote Services</h2>
                <p class="text-slate-500 mt-1">Detected using SSH when available.</p>
            </div>

            <div class="p-6 space-y-3">
                @forelse($remoteServices as $service => $status)
                    @php
                        $isActive = trim(strtolower((string) $status)) === 'active';
                    @endphp

                    <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <div class="font-black text-slate-900">{{ $service }}</div>

                        <span class="px-3 py-1 rounded-full text-xs font-black {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $status ?: 'unknown' }}
                        </span>
                    </div>
                @empty
                    <div class="rounded-2xl bg-yellow-50 border border-yellow-200 p-4 text-yellow-800 font-bold">
                        No service data available. SSH check may have failed.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Email Security + Contact Tools --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- Email Security --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Email Security</h2>
                <p class="text-slate-500 mt-1">SPF, DKIM and DMARC DNS status.</p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach(['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'] as $key => $label)
                    @php
                        $status = $emailSecurityData[$key] ?? 'Unknown';
                        $good = str_contains(strtolower($status), 'configured');
                        $bad = str_contains(strtolower($status), 'missing');
                    @endphp

                    <div class="rounded-2xl border p-4 {{ $good ? 'bg-green-50 border-green-200' : ($bad ? 'bg-red-50 border-red-200' : 'bg-slate-50 border-slate-200') }}">
                        <div class="text-xs uppercase tracking-wider font-black {{ $good ? 'text-green-600' : ($bad ? 'text-red-600' : 'text-slate-500') }}">
                            {{ $label }}
                        </div>
                        <div class="font-black mt-1 {{ $good ? 'text-green-800' : ($bad ? 'text-red-800' : 'text-slate-900') }}">
                            {{ $status }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Contact Tools --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">Contact Tools</h2>
                <p class="text-slate-500 mt-1">Send SMS or email to the client.</p>
            </div>

            <div class="p-6 space-y-6">

                <form method="POST" action="{{ route('servers.cpanel.sms', [$server, $user]) }}" class="space-y-3">
                    @csrf

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Phone</label>
                        <input type="text"
                               name="phone"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="+947XXXXXXXX">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">SMS Message</label>
                        <textarea name="message"
                                  rows="3"
                                  class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Write SMS message..."></textarea>
                    </div>

                    <button class="px-5 py-3 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black">
                        <i class="fa-solid fa-message mr-2"></i>
                        Send SMS
                    </button>
                </form>

                <hr>

                <form method="POST" action="{{ route('servers.cpanel.email', [$server, $user]) }}" class="space-y-3">
                    @csrf

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Email</label>
                        <input type="email"
                               name="email"
                               value="{{ $email !== '-' ? $email : '' }}"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="client@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Subject</label>
                        <input type="text"
                               name="subject"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Subject">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Email Message</label>
                        <textarea name="message"
                                  rows="4"
                                  class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Write email message..."></textarea>
                    </div>

                    <button class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                        <i class="fa-solid fa-envelope mr-2"></i>
                        Send Email
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
    function togglePassword(id) {
        const input = document.getElementById(id);

        if (!input) {
            return;
        }

        input.type = input.type === 'password' ? 'text' : 'password';
    }
</script>

@endsection