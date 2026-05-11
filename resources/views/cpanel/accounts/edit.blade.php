@extends('layouts.app')

@section('page-title', 'Manage cPanel Account')

@section('content')

@php
    use Illuminate\Support\Facades\Route;

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

    /*
    |--------------------------------------------------------------------------
    | Saved Alert Contacts
    |--------------------------------------------------------------------------
    | This reads alert contact data from:
    | 1. Old submitted values
    | 2. Current account array if available
    | 3. Session saved by updateAlertContacts()
    | 4. Server fallback values
    |--------------------------------------------------------------------------
    */

    $savedAlertContacts = session("cpanel_alert_contacts.{$server->id}.{$user}", []);

    $alertAdminPhone = old('admin_phone',
        $account['admin_phone']
        ?? $savedAlertContacts['admin_phone']
        ?? $server->admin_phone
        ?? ''
    );

    $alertAdminEmail = old('admin_email',
        $account['admin_email']
        ?? $savedAlertContacts['admin_email']
        ?? $server->admin_email
        ?? ''
    );

    $alertCustomerPhone = old('customer_phone',
        $account['customer_phone']
        ?? $savedAlertContacts['customer_phone']
        ?? $server->customer_phone
        ?? ''
    );

    $alertCustomerEmail = old('customer_email',
        $account['customer_email']
        ?? $savedAlertContacts['customer_email']
        ?? ($email !== '-' ? $email : '')
        ?? $server->customer_email
        ?? ''
    );

    $alertExtraPhones = old('alert_phones',
        $account['alert_phones']
        ?? $savedAlertContacts['alert_phones']
        ?? ''
    );

    $alertExtraEmails = old('alert_emails',
        $account['alert_emails']
        ?? $savedAlertContacts['alert_emails']
        ?? ''
    );

    $monitorWebsite = old('monitor_website',
        $account['monitor_website']
        ?? $savedAlertContacts['monitor_website']
        ?? 1
    );

    $monitorCpanel = old('monitor_cpanel',
        $account['monitor_cpanel']
        ?? $savedAlertContacts['monitor_cpanel']
        ?? 1
    );

    $monitorFrameworks = old('monitor_frameworks',
        $account['monitor_frameworks']
        ?? $savedAlertContacts['monitor_frameworks']
        ?? 1
    );

    $sendRecoveryAlert = old('send_recovery_alert',
        $account['send_recovery_alert']
        ?? $savedAlertContacts['send_recovery_alert']
        ?? 1
    );

    $developerLoginUrl = 'https://developercodes.webscepts.com/login';
    $visualCodeEditorUrl = 'https://developercodes.webscepts.com/codeditor';

    $alertRoute = Route::has('servers.cpanel.alerts.update')
        ? route('servers.cpanel.alerts.update', [$server, $user])
        : url('/servers/' . $server->id . '/cpanel/' . $user . '/alerts');
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
                    Manage cPanel account settings, login shortcuts, package, IP, password, WordPress status, email security, monitoring alerts, and Developer Codes access.
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

    {{-- Monitoring Alert Contacts --}}
    <div class="bg-white rounded-3xl shadow border-2 border-blue-100 overflow-hidden">
        <div class="p-6 border-b bg-gradient-to-r from-blue-50 via-white to-green-50">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-900">
                        <i class="fa-solid fa-bell text-blue-600 mr-2"></i>
                        Monitoring Alert Contacts
                    </h2>
                    <p class="text-slate-500 mt-1">
                        Enter SMS mobile numbers and email addresses for website down, cPanel down, uptime issue, CMS/framework issue, and recovery alerts.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-100 text-green-700 border border-green-200 text-xs font-black">
                        <i class="fa-solid fa-clock"></i>
                        30 min 24/7
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-100 text-blue-700 border border-blue-200 text-xs font-black">
                        <i class="fa-solid fa-envelope"></i>
                        SMS + Email
                    </span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ $alertRoute }}" id="alertContactForm">
            @csrf

            <div class="p-6">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                    <div class="xl:col-span-2 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                            <div>
                                <label class="block text-sm font-black mb-1 text-slate-700">
                                    Admin Mobile Number
                                </label>
                                <div class="relative">
                                    <i class="fa-solid fa-mobile-screen-button absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="text"
                                           name="admin_phone"
                                           id="alertAdminPhone"
                                           value="{{ $alertAdminPhone }}"
                                           class="alert-live-field w-full border border-slate-200 rounded-xl p-3 pl-11 outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="947XXXXXXXX">
                                </div>
                                <p class="text-xs text-slate-500 mt-2 font-bold">
                                    Webscepts/admin mobile number for SMS alerts.
                                </p>
                                @error('admin_phone') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-black mb-1 text-slate-700">
                                    Admin Email Address
                                </label>
                                <div class="relative">
                                    <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="email"
                                           name="admin_email"
                                           id="alertAdminEmail"
                                           value="{{ $alertAdminEmail }}"
                                           class="alert-live-field w-full border border-slate-200 rounded-xl p-3 pl-11 outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="admin@webscepts.com">
                                </div>
                                <p class="text-xs text-slate-500 mt-2 font-bold">
                                    Webscepts/admin email address for email alerts.
                                </p>
                                @error('admin_email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-black mb-1 text-slate-700">
                                    Customer Mobile Number
                                </label>
                                <div class="relative">
                                    <i class="fa-solid fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="text"
                                           name="customer_phone"
                                           id="alertCustomerPhone"
                                           value="{{ $alertCustomerPhone }}"
                                           class="alert-live-field w-full border border-slate-200 rounded-xl p-3 pl-11 outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="947XXXXXXXX">
                                </div>
                                <p class="text-xs text-slate-500 mt-2 font-bold">
                                    Customer/client mobile number for down and recovery SMS.
                                </p>
                                @error('customer_phone') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-black mb-1 text-slate-700">
                                    Customer Email Address
                                </label>
                                <div class="relative">
                                    <i class="fa-solid fa-at absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="email"
                                           name="customer_email"
                                           id="alertCustomerEmail"
                                           value="{{ $alertCustomerEmail }}"
                                           class="alert-live-field w-full border border-slate-200 rounded-xl p-3 pl-11 outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="client@example.com">
                                </div>
                                <p class="text-xs text-slate-500 mt-2 font-bold">
                                    Customer/client email address for alert emails.
                                </p>
                                @error('customer_email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-black mb-1 text-slate-700">
                                    Extra Alert Mobile Numbers
                                </label>
                                <textarea name="alert_phones"
                                          id="alertExtraPhones"
                                          rows="3"
                                          class="alert-live-field w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="947XXXXXXXX, 947YYYYYYYY">{{ $alertExtraPhones }}</textarea>
                                <p class="text-xs text-slate-500 mt-2 font-bold">
                                    Optional. Add multiple mobile numbers separated by commas.
                                </p>
                                @error('alert_phones') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-black mb-1 text-slate-700">
                                    Extra Alert Email Addresses
                                </label>
                                <textarea name="alert_emails"
                                          id="alertExtraEmails"
                                          rows="3"
                                          class="alert-live-field w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="support@example.com, owner@example.com">{{ $alertExtraEmails }}</textarea>
                                <p class="text-xs text-slate-500 mt-2 font-bold">
                                    Optional. Add multiple email addresses separated by commas.
                                </p>
                                @error('alert_emails') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                            <label class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 cursor-pointer">
                                <div>
                                    <div class="text-sm font-black text-slate-900">Website Uptime</div>
                                    <div class="text-xs font-bold text-slate-500 mt-1">Down/recovery alerts.</div>
                                </div>
                                <input type="checkbox"
                                       name="monitor_website"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ $monitorWebsite ? 'checked' : '' }}>
                            </label>

                            <label class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 cursor-pointer">
                                <div>
                                    <div class="text-sm font-black text-slate-900">cPanel / WHM</div>
                                    <div class="text-xs font-bold text-slate-500 mt-1">Ports 2083/2087.</div>
                                </div>
                                <input type="checkbox"
                                       name="monitor_cpanel"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ $monitorCpanel ? 'checked' : '' }}>
                            </label>

                            <label class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 cursor-pointer">
                                <div>
                                    <div class="text-sm font-black text-slate-900">CMS / Framework</div>
                                    <div class="text-xs font-bold text-slate-500 mt-1">Laravel, PHP, Magento, Drupal.</div>
                                </div>
                                <input type="checkbox"
                                       name="monitor_frameworks"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ $monitorFrameworks ? 'checked' : '' }}>
                            </label>

                            <label class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 cursor-pointer">
                                <div>
                                    <div class="text-sm font-black text-slate-900">Recovery Alert</div>
                                    <div class="text-xs font-bold text-slate-500 mt-1">Notify when fixed.</div>
                                </div>
                                <input type="checkbox"
                                       name="send_recovery_alert"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ $sendRecoveryAlert ? 'checked' : '' }}>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button type="submit"
                                    class="md:col-span-2 px-5 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                                <i class="fa-solid fa-floppy-disk mr-2"></i>
                                Save Alert Contacts
                            </button>

                            <button type="button"
                                    onclick="prepareAlertTest()"
                                    class="px-5 py-4 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-black">
                                <i class="fa-solid fa-wand-magic-sparkles mr-2"></i>
                                Prepare Test
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="rounded-3xl bg-slate-950 text-white p-6 sticky top-6">
                            <div class="flex items-center justify-between gap-4 mb-5">
                                <h3 class="text-xl font-black">Live Alert Preview</h3>
                                <span class="w-12 h-12 rounded-2xl bg-blue-500/20 text-blue-200 flex items-center justify-center">
                                    <i class="fa-solid fa-satellite-dish"></i>
                                </span>
                            </div>

                            <div class="space-y-3">
                                <div class="flex justify-between gap-4 border-b border-white/10 pb-3">
                                    <span class="text-slate-400 font-bold">Domain</span>
                                    <span class="font-black text-right break-all" id="previewAlertDomain">{{ $domain }}</span>
                                </div>

                                <div class="flex justify-between gap-4 border-b border-white/10 pb-3">
                                    <span class="text-slate-400 font-bold">SMS To</span>
                                    <span class="font-black text-right break-all" id="previewAlertPhones">No mobile numbers</span>
                                </div>

                                <div class="flex justify-between gap-4 border-b border-white/10 pb-3">
                                    <span class="text-slate-400 font-bold">Email To</span>
                                    <span class="font-black text-right break-all" id="previewAlertEmails">No email addresses</span>
                                </div>

                                <div class="flex justify-between gap-4 border-b border-white/10 pb-3">
                                    <span class="text-slate-400 font-bold">Schedule</span>
                                    <span class="font-black text-green-300 text-right">Every 30 min</span>
                                </div>

                                <div class="rounded-2xl bg-red-500/15 border border-red-400/30 p-4 text-sm leading-relaxed">
                                    <strong>Sample SMS:</strong><br>
                                    WEBSITE ISSUE: {{ $domain }} is down / cPanel down / framework error detected.
                                </div>

                                <div class="rounded-2xl bg-green-500/15 border border-green-400/30 p-4 text-sm leading-relaxed">
                                    <strong>Recovery SMS:</strong><br>
                                    RECOVERED: {{ $domain }} is back online.
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>
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
                               id="manualSmsPhone"
                               value="{{ $alertCustomerPhone ?: $alertAdminPhone }}"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="+947XXXXXXXX">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">SMS Message</label>
                        <textarea name="message"
                                  id="manualSmsMessage"
                                  rows="3"
                                  class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Write SMS message...">Test alert from Webscepts Monitoring for {{ $domain }}.</textarea>
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
                               id="manualEmailAddress"
                               value="{{ $alertCustomerEmail ?: ($email !== '-' ? $email : '') }}"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="client@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Subject</label>
                        <input type="text"
                               name="subject"
                               id="manualEmailSubject"
                               value="Test Alert - {{ $domain }}"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Subject">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Email Message</label>
                        <textarea name="message"
                                  id="manualEmailMessage"
                                  rows="4"
                                  class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Write email message...">Test email alert from Webscepts Monitoring for {{ $domain }}.</textarea>
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

    function splitAlertValues(value) {
        if (!value) {
            return [];
        }

        return value
            .split(',')
            .map(function (item) {
                return item.trim();
            })
            .filter(function (item) {
                return item.length > 0;
            });
    }

    function uniqueAlertValues(values) {
        return values.filter(function (value, index, self) {
            return self.indexOf(value) === index;
        });
    }

    function updateAlertPreview() {
        const phoneFields = [
            document.getElementById('alertAdminPhone'),
            document.getElementById('alertCustomerPhone'),
            document.getElementById('alertExtraPhones')
        ];

        const emailFields = [
            document.getElementById('alertAdminEmail'),
            document.getElementById('alertCustomerEmail'),
            document.getElementById('alertExtraEmails')
        ];

        let phones = [];
        let emails = [];

        phoneFields.forEach(function (field) {
            if (field) {
                phones = phones.concat(splitAlertValues(field.value));
            }
        });

        emailFields.forEach(function (field) {
            if (field) {
                emails = emails.concat(splitAlertValues(field.value));
            }
        });

        phones = uniqueAlertValues(phones);
        emails = uniqueAlertValues(emails);

        const previewPhones = document.getElementById('previewAlertPhones');
        const previewEmails = document.getElementById('previewAlertEmails');

        if (previewPhones) {
            previewPhones.innerText = phones.length ? phones.join(', ') : 'No mobile numbers';
        }

        if (previewEmails) {
            previewEmails.innerText = emails.length ? emails.join(', ') : 'No email addresses';
        }
    }

    function prepareAlertTest() {
        const adminPhone = document.getElementById('alertAdminPhone')?.value || '';
        const customerPhone = document.getElementById('alertCustomerPhone')?.value || '';
        const adminEmail = document.getElementById('alertAdminEmail')?.value || '';
        const customerEmail = document.getElementById('alertCustomerEmail')?.value || '';

        const manualSmsPhone = document.getElementById('manualSmsPhone');
        const manualSmsMessage = document.getElementById('manualSmsMessage');
        const manualEmailAddress = document.getElementById('manualEmailAddress');
        const manualEmailSubject = document.getElementById('manualEmailSubject');
        const manualEmailMessage = document.getElementById('manualEmailMessage');

        const domain = @json($domain);
        const targetPhone = customerPhone || adminPhone;
        const targetEmail = customerEmail || adminEmail;

        if (manualSmsPhone) {
            manualSmsPhone.value = targetPhone;
        }

        if (manualSmsMessage) {
            manualSmsMessage.value = 'Test alert from Webscepts Monitoring. Domain: ' + domain + '. Website/cPanel/CMS framework monitoring is enabled.';
        }

        if (manualEmailAddress) {
            manualEmailAddress.value = targetEmail;
        }

        if (manualEmailSubject) {
            manualEmailSubject.value = 'Test Alert - ' + domain;
        }

        if (manualEmailMessage) {
            manualEmailMessage.value =
                'This is a test email alert from Webscepts Monitoring.\\n\\n' +
                'Domain: ' + domain + '\\n' +
                'Checks: Website uptime, cPanel/WHM, CMS/framework issues, and recovery monitoring.\\n\\n' +
                'If this was a real issue, the system would send down/recovery alerts automatically.';
        }

        updateAlertPreview();

        const contactTools = document.getElementById('manualSmsPhone');
        if (contactTools) {
            contactTools.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert-live-field').forEach(function (field) {
            field.addEventListener('input', updateAlertPreview);
            field.addEventListener('change', updateAlertPreview);
        });

        updateAlertPreview();
    });
</script>

@endsection