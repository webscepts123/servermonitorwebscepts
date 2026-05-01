@extends('layouts.app')

@section('page-title', 'Manage cPanel Account')

@section('content')

@php
    $domain = $account['domain'] ?? 'Unknown domain';
    $email = $account['email'] ?? '-';
    $ip = $account['ip'] ?? '-';
    $package = $account['plan'] ?? '-';
    $diskUsed = $account['diskused'] ?? '-';
    $owner = $account['owner'] ?? '-';
    $theme = $account['theme'] ?? '-';
    $suspended = !empty($account['suspended']);
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-2xl shadow p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Manage Account: {{ $user }}</h2>
            <p class="text-slate-500">{{ $domain }} - {{ $server->host }}</p>

            <div class="mt-3 flex flex-wrap gap-2">
                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                    IP: {{ $ip }}
                </span>

                <span class="px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">
                    Package: {{ $package }}
                </span>

                @if($suspended)
                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-bold">
                        Suspended
                    </span>
                @else
                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">
                        Active
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
            <a href="{{ route('servers.cpanel.index', $server) }}"
               class="w-full sm:w-auto text-center px-5 py-3 rounded-xl bg-slate-200 text-slate-800 hover:bg-slate-300">
                Back to Accounts
            </a>

            @if($domain !== 'Unknown domain')
                <a href="https://{{ $domain }}" target="_blank"
                   class="w-full sm:w-auto text-center px-5 py-3 rounded-xl bg-slate-900 text-white hover:bg-slate-700">
                    Open Website
                </a>
            @endif
        </div>
    </div>

    {{-- Messages --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-xl p-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-xl p-4">
            {{ session('error') }}
        </div>
    @endif

    @if($error)
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-xl p-4">
            {{ $error }}
        </div>
    @endif

    {{-- Account Details Widgets --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">

        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Domain</p>
                    <h3 class="font-bold break-all">{{ $domain }}</h3>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Contact Email</p>
                    <h3 class="font-bold break-all">{{ $email }}</h3>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center">
                    <i class="fa-solid fa-hard-drive"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Disk Used</p>
                    <h3 class="font-bold">{{ $diskUsed }}</h3>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-box"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Owner / Theme</p>
                    <h3 class="font-bold">{{ $owner }} / {{ $theme }}</h3>
                </div>
            </div>
        </div>

    </div>

    {{-- Main Manage Forms --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fa-solid fa-key text-blue-600"></i>
                Update Password
            </h3>

            <form method="POST" action="{{ route('servers.cpanel.password', [$server, $user]) }}">
                @csrf

                <label class="block text-sm font-semibold mb-1">New Password</label>
                <input type="password" name="password" class="w-full border rounded-xl p-3" placeholder="Enter strong password">

                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror

                <button class="mt-4 w-full px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                    Update Password
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-purple-600"></i>
                Change Package
            </h3>

            <form method="POST" action="{{ route('servers.cpanel.package', [$server, $user]) }}">
                @csrf

                <label class="block text-sm font-semibold mb-1">Package</label>
                <select name="package" class="w-full border rounded-xl p-3">
                    @foreach($packages as $packageItem)
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

                <button class="mt-4 w-full px-5 py-3 rounded-xl bg-purple-600 text-white hover:bg-purple-700">
                    Change Package
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fa-solid fa-network-wired text-green-600"></i>
                Change IP Address
            </h3>

            <form method="POST" action="{{ route('servers.cpanel.ip', [$server, $user]) }}">
                @csrf

                <label class="block text-sm font-semibold mb-1">IP Address</label>
                <select name="ip" class="w-full border rounded-xl p-3">
                    @foreach($ips as $ipItem)
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

                <button class="mt-4 w-full px-5 py-3 rounded-xl bg-green-600 text-white hover:bg-green-700">
                    Change IP
                </button>
            </form>
        </div>

    </div>

    {{-- Security Features --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex items-center justify-between gap-3 mb-5">
                <div>
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved text-red-600"></i>
                        Account Security
                    </h3>
                    <p class="text-sm text-slate-500">Useful security checks for this cPanel account.</p>
                </div>
            </div>

            <div class="space-y-3">
                <div class="rounded-xl border p-4 flex items-start gap-3">
                    <i class="fa-solid fa-lock text-green-600 mt-1"></i>
                    <div>
                        <h4 class="font-bold">Password Security</h4>
                        <p class="text-sm text-slate-500">Use a strong password and update it regularly.</p>
                    </div>
                </div>

                <div class="rounded-xl border p-4 flex items-start gap-3">
                    <i class="fa-solid fa-user-shield text-blue-600 mt-1"></i>
                    <div>
                        <h4 class="font-bold">cPanel Access</h4>
                        <p class="text-sm text-slate-500">Check unknown logins from cPanel access logs.</p>
                    </div>
                </div>

                <div class="rounded-xl border p-4 flex items-start gap-3">
                    <i class="fa-solid fa-bug text-red-600 mt-1"></i>
                    <div>
                        <h4 class="font-bold">Malware / Abuse</h4>
                        <p class="text-sm text-slate-500">Scan public_html for suspicious PHP files and mail scripts.</p>
                    </div>
                </div>

                <div class="rounded-xl border p-4 flex items-start gap-3">
                    <i class="fa-solid fa-envelope-circle-check text-purple-600 mt-1"></i>
                    <div>
                        <h4 class="font-bold">Email Security</h4>
                        <p class="text-sm text-slate-500">Check forwarders, autoresponders, mail queue and spam scripts.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- WordPress Widgets --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex items-center justify-between gap-3 mb-5">
                <div>
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-brands fa-wordpress text-blue-700"></i>
                        WordPress Security Widgets
                    </h3>
                    <p class="text-sm text-slate-500">Quick WordPress health and hardening checks.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="rounded-xl border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center">
                            <i class="fa-brands fa-wordpress"></i>
                        </div>
                        <div>
                            <h4 class="font-bold">WP Core</h4>
                            <p class="text-xs text-slate-500">Check version updates</p>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-slate-600">
                        Recommended: keep WordPress core updated.
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center">
                            <i class="fa-solid fa-plug"></i>
                        </div>
                        <div>
                            <h4 class="font-bold">Plugins</h4>
                            <p class="text-xs text-slate-500">Outdated plugins risk</p>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-slate-600">
                        Remove unused plugins and update active plugins.
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center">
                            <i class="fa-solid fa-palette"></i>
                        </div>
                        <div>
                            <h4 class="font-bold">Themes</h4>
                            <p class="text-xs text-slate-500">Theme vulnerability check</p>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-slate-600">
                        Delete old themes and keep active theme updated.
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-100 text-red-700 flex items-center justify-center">
                            <i class="fa-solid fa-file-shield"></i>
                        </div>
                        <div>
                            <h4 class="font-bold">wp-config</h4>
                            <p class="text-xs text-slate-500">Sensitive file protection</p>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-slate-600">
                        Protect wp-config.php and disable file editing.
                    </div>
                </div>

            </div>
        </div>

    </div>
    {{-- WordPress Widgets --}}
<div class="bg-white rounded-2xl shadow p-6">
    <div class="flex items-center justify-between gap-3 mb-5">
        <div>
            <h3 class="text-xl font-bold flex items-center gap-2">
                <i class="fa-brands fa-wordpress text-blue-700"></i>
                WordPress Manager
            </h3>
            <p class="text-sm text-slate-500">
                Live WordPress core, plugins, themes and modules.
            </p>
        </div>

        <a href="{{ route('servers.wordpress.show', [$server, $user]) }}"
           class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm">
            Open Manager
        </a>
    </div>

    @php
        $wpPath = "/home/{$user}/public_html";

        function runWp($cmd, $path) {
            $path = escapeshellarg($path);
            return trim(shell_exec("wp {$cmd} --path={$path} --allow-root 2>&1") ?? '');
        }

        $core = runWp('core version', $wpPath);

        $plugins = json_decode(runWp('plugin list --format=json', $wpPath), true) ?? [];
        $themes = json_decode(runWp('theme list --format=json', $wpPath), true) ?? [];

        $pluginCount = count($plugins);
        $activePlugins = collect($plugins)->where('status', 'active')->count();
        $themeCount = count($themes);
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- CORE --}}
        <div class="rounded-xl border p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-brands fa-wordpress"></i>
                </div>
                <div>
                    <h4 class="font-bold">WP Core</h4>
                    <p class="text-xs text-slate-500">Version</p>
                </div>
            </div>

            <div class="mt-3 text-sm font-bold">
                {{ $core ?: 'Not Detected' }}
            </div>
        </div>

        {{-- PLUGINS --}}
        <div class="rounded-xl border p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-plug"></i>
                </div>
                <div>
                    <h4 class="font-bold">Plugins</h4>
                    <p class="text-xs text-slate-500">Installed</p>
                </div>
            </div>

            <div class="mt-3 text-sm">
                <span class="font-bold">{{ $pluginCount }}</span> Total<br>
                <span class="text-green-600 font-bold">{{ $activePlugins }}</span> Active
            </div>
        </div>

        {{-- THEMES --}}
        <div class="rounded-xl border p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center">
                    <i class="fa-solid fa-palette"></i>
                </div>
                <div>
                    <h4 class="font-bold">Themes</h4>
                    <p class="text-xs text-slate-500">Installed</p>
                </div>
            </div>

            <div class="mt-3 text-sm font-bold">
                {{ $themeCount }}
            </div>
        </div>

        {{-- STATUS --}}
        <div class="rounded-xl border p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-shield"></i>
                </div>
                <div>
                    <h4 class="font-bold">Status</h4>
                    <p class="text-xs text-slate-500">Detection</p>
                </div>
            </div>

            <div class="mt-3 text-sm">
                @if($core && !str_contains(strtolower($core), 'error'))
                    <span class="text-green-600 font-bold">WordPress Detected</span>
                @else
                    <span class="text-red-600 font-bold">Not Detected</span>
                @endif
            </div>
        </div>

    </div>

    {{-- QUICK ACTIONS --}}
    <div class="mt-6 flex flex-wrap gap-3">

        <form method="POST" action="{{ route('servers.wordpress.coreUpdate', [$server, $user]) }}">
            @csrf
            <button class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm">
                Update Core
            </button>
        </form>

        <form method="POST" action="{{ route('servers.wordpress.plugins.updateAll', [$server, $user]) }}">
            @csrf
            <button class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm">
                Update Plugins
            </button>
        </form>

    </div>
</div>
    {{-- Email Features --}}
    <div class="bg-white rounded-2xl shadow p-6">
        <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
            <i class="fa-solid fa-envelope-circle-check text-green-600"></i>
            Email Account & Domain Security
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <div class="rounded-xl border p-4">
                <h4 class="font-bold">SPF Record</h4>
                <p class="text-sm text-slate-500 mt-1">Make sure SPF is configured for outgoing mail protection.</p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-bold">DKIM Record</h4>
                <p class="text-sm text-slate-500 mt-1">Enable DKIM signing to improve email reputation.</p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-bold">DMARC Policy</h4>
                <p class="text-sm text-slate-500 mt-1">Add DMARC policy to reduce spoofing and phishing abuse.</p>
            </div>

        </div>
    </div>

</div>

@endsection