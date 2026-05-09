@extends('layouts.app')

@section('page-title', 'cPanel Developer Logins')

@section('content')

@php
    use Illuminate\Support\Facades\Route;

    $servers = $servers ?? collect();
    $developers = $developers ?? collect();
    $cpanelAccounts = collect($cpanelAccounts ?? []);

    $frameworks = $frameworks ?? [
        'custom' => 'Custom / Other',
        'html' => 'Static HTML / CSS / JS',
        'php' => 'PHP',
        'wordpress' => 'WordPress',
        'laravel' => 'Laravel',
        'react' => 'React.js',
        'vue' => 'Vue.js',
        'angular' => 'Angular',
        'node' => 'Node.js / Express',
        'nextjs' => 'Next.js',
        'nuxt' => 'Nuxt.js',
        'svelte' => 'Svelte',
        'python' => 'Python',
        'flask' => 'Flask',
        'django' => 'Django',
        'fastapi' => 'FastAPI',
        'java' => 'Java',
        'springboot' => 'Spring Boot',
        'dotnet' => '.NET',
        'ruby' => 'Ruby / Rails',
        'go' => 'Go',
    ];

    $loadedServerId = session('cpanel_accounts_server_id');
    $developerUrl = 'https://developercodes.webscepts.com/login';

    $portalIsActive = function ($developer) {
        return (bool) (
            $developer->developer_portal_access
            ?? $developer->portal_access_enabled
            ?? $developer->developer_portal_enabled
            ?? $developer->is_active
            ?? false
        );
    };

    $developerCount = method_exists($developers, 'count') ? $developers->count() : 0;

    $activeDeveloperCount = method_exists($developers, 'filter')
        ? $developers->filter(fn ($item) => $portalIsActive($item))->count()
        : 0;

    $disabledDeveloperCount = max($developerCount - $activeDeveloperCount, 0);

    $syncRoute = Route::has('developers.cpanel.sync') ? route('developers.cpanel.sync') : '#';
    $bulkImportRoute = Route::has('developers.cpanel.bulk.import') ? route('developers.cpanel.bulk.import') : '#';

    $frameworkBadge = function ($framework) {
        return match ($framework) {
            'laravel' => 'bg-red-100 text-red-700 border-red-200',
            'wordpress' => 'bg-blue-100 text-blue-700 border-blue-200',
            'react' => 'bg-cyan-100 text-cyan-700 border-cyan-200',
            'vue' => 'bg-green-100 text-green-700 border-green-200',
            'angular' => 'bg-rose-100 text-rose-700 border-rose-200',
            'node' => 'bg-lime-100 text-lime-700 border-lime-200',
            'nextjs', 'nuxt', 'svelte' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'python', 'flask', 'django', 'fastapi' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'php' => 'bg-purple-100 text-purple-700 border-purple-200',
            'java', 'springboot' => 'bg-orange-100 text-orange-700 border-orange-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };

    $frameworkIcon = function ($framework) {
        return match ($framework) {
            'laravel' => 'fa-brands fa-laravel',
            'wordpress' => 'fa-brands fa-wordpress',
            'react' => 'fa-brands fa-react',
            'vue' => 'fa-brands fa-vuejs',
            'angular' => 'fa-brands fa-angular',
            'node', 'nextjs', 'nuxt', 'svelte' => 'fa-brands fa-node-js',
            'python', 'flask', 'django', 'fastapi' => 'fa-brands fa-python',
            'php' => 'fa-brands fa-php',
            'java', 'springboot' => 'fa-brands fa-java',
            'html' => 'fa-brands fa-html5',
            default => 'fa-solid fa-code',
        };
    };
@endphp

<style>
    .portal-switch-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .portal-switch {
        width: 58px;
        height: 32px;
        border-radius: 999px;
        background: #e2e8f0;
        border: 1px solid #cbd5e1;
        position: relative;
        cursor: pointer;
        transition: all .2s ease;
        display: inline-flex;
        align-items: center;
        padding: 3px;
    }

    .portal-switch::after {
        content: '';
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: #fff;
        box-shadow: 0 6px 14px rgba(15, 23, 42, .18);
        transform: translateX(0);
        transition: all .2s ease;
    }

    .portal-switch-input:checked + .portal-switch {
        background: #16a34a;
        border-color: #15803d;
    }

    .portal-switch-input:checked + .portal-switch::after {
        transform: translateX(26px);
    }

    .soft-scrollbar::-webkit-scrollbar {
        height: 10px;
        width: 10px;
    }

    .soft-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 999px;
    }

    .soft-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }
</style>

<div class="space-y-6">

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

    @if($errors->any())
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4">
            <div class="font-black mb-2">Please fix these errors:</div>
            <ul class="list-disc ml-5 text-sm font-bold">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Created Passwords --}}
    @if(session('created_logins'))
        <div class="rounded-3xl bg-slate-950 text-white p-6 shadow-xl overflow-hidden relative">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-green-500/10 blur-3xl"></div>

            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black">Developer Logins Created / Updated</h2>
                    <p class="text-slate-300 mt-1">
                        Copy these passwords now. Developer Codes generated temporary passwords for the portal.
                    </p>
                </div>

                <button type="button"
                        onclick="copyText('createdPasswordsBox')"
                        class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    <i class="fa-solid fa-copy mr-2"></i>
                    Copy All Logins
                </button>
            </div>

            <pre id="createdPasswordsBox" class="relative mt-5 bg-black/40 border border-white/10 rounded-2xl p-5 overflow-auto text-xs whitespace-pre-wrap">@foreach(session('created_logins') as $login)
URL: {{ $login['url'] ?? $developerUrl }}
Login: {{ $login['login'] ?? '-' }}
Email: {{ $login['email'] ?? '-' }}
Domain: {{ $login['domain'] ?? '-' }}
Framework: {{ $login['framework'] ?? '-' }}
Project Root: {{ $login['project_root'] ?? '-' }}
Portal Access: {{ $login['portal_access'] ?? 'Enabled' }}
Password: {{ $login['password'] ?? '-' }}

@endforeach</pre>
        </div>
    @endif

    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-red-950 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl lg:text-5xl font-black tracking-tight">
                        cPanel Developer Logins
                    </h1>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-black">
                        <i class="fa-solid fa-users-gear"></i>
                        Auto Import from WHM
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-black">
                        <i class="fa-solid fa-layer-group"></i>
                        Multi Framework
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-black">
                        <i class="fa-solid fa-toggle-on"></i>
                        Portal Access Control
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-5xl">
                    Fetch cPanel accounts, choose project frameworks, assign permissions, and turn Developer Portal access
                    ON or OFF for each developer account.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Portal: {{ $developerUrl }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Loaded cPanel Accounts: {{ $cpanelAccounts->count() }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Developer Users: {{ $developerCount }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/30 text-xs font-bold">
                        Active: {{ $activeDeveloperCount }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/30 text-xs font-bold">
                        Disabled: {{ $disabledDeveloperCount }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row xl:flex-col gap-3 shrink-0">
                <a href="{{ $developerUrl }}"
                   target="_blank"
                   class="px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                    <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>
                    Open Developer Portal
                </a>

                <button type="button"
                        onclick="document.getElementById('fetchUsersCard').scrollIntoView({behavior: 'smooth'});"
                        class="px-6 py-4 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-black text-center">
                    <i class="fa-solid fa-rotate mr-2"></i>
                    Fetch cPanel Users
                </button>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Loaded Accounts</p>
                    <h2 class="text-3xl font-black text-slate-900 mt-2">{{ $cpanelAccounts->count() }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-server text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Developer Users</p>
                    <h2 class="text-3xl font-black text-green-600 mt-2">{{ $developerCount }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-user-shield text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Portal Active</p>
                    <h2 class="text-3xl font-black text-blue-600 mt-2">{{ $activeDeveloperCount }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-cyan-100 text-cyan-700 flex items-center justify-center">
                    <i class="fa-solid fa-toggle-on text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Portal Disabled</p>
                    <h2 class="text-3xl font-black text-red-600 mt-2">{{ $disabledDeveloperCount }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                    <i class="fa-solid fa-toggle-off text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Fetch Users --}}
    <div id="fetchUsersCard" class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Fetch cPanel Users</h2>
                <p class="text-slate-500 mt-1">
                    Select a WHM/cPanel server and load all cPanel accounts automatically.
                </p>
            </div>

            <form method="POST"
                  action="{{ $syncRoute }}"
                  class="flex flex-col md:flex-row gap-3 w-full xl:w-auto">
                @csrf

                <select name="server_id"
                        required
                        class="w-full md:w-96 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select WHM / cPanel Server</option>

                    @foreach($servers as $server)
                        <option value="{{ $server->id }}" {{ (string)$loadedServerId === (string)$server->id ? 'selected' : '' }}>
                            {{ $server->name }} — {{ $server->host }}
                        </option>
                    @endforeach
                </select>

                <button class="px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    <i class="fa-solid fa-rotate mr-2"></i>
                    Fetch Users
                </button>
            </form>
        </div>

        <div class="p-6 bg-blue-50 border-b border-blue-100">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>
                    <p class="text-sm text-blue-800 font-black">
                        cPanel password note
                    </p>
                    <p class="text-sm text-blue-700 font-bold mt-1">
                        WHM can provide cPanel username, domain, IP and contact email, but it cannot reveal existing cPanel passwords.
                        Developer Codes creates a separate temporary password for the developer portal.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Imported cPanel Accounts --}}
    @if($cpanelAccounts->count())
        <form method="POST" action="{{ $bulkImportRoute }}" id="bulkDeveloperImportForm">
            @csrf

            <input type="hidden" name="server_id" value="{{ $loadedServerId }}">

            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Available cPanel Accounts</h2>
                        <p class="text-slate-500 mt-1">
                            Tick accounts, choose framework, permissions, and set Developer Portal access ON/OFF.
                        </p>
                    </div>

                    <div class="flex flex-col md:flex-row gap-3 w-full xl:w-auto">
                        <input type="text"
                               id="accountSearch"
                               oninput="accountPagination.apply()"
                               placeholder="Search domain, username, email..."
                               class="w-full md:w-80 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">

                        <select id="accountPageSize"
                                onchange="accountPagination.changePageSize()"
                                class="w-full md:w-40 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="10">10 / page</option>
                            <option value="20" selected>20 / page</option>
                            <option value="30">30 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>

                        <button type="button"
                                onclick="selectVisibleAccounts(true)"
                                class="px-5 py-3 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-black">
                            Select Visible
                        </button>

                        <button type="button"
                                onclick="selectVisibleAccounts(false)"
                                class="px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black">
                            Clear Visible
                        </button>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-black">
                        <span class="px-3 py-2 rounded-full bg-blue-100 text-blue-700">
                            Total: {{ $cpanelAccounts->count() }}
                        </span>
                        <span class="px-3 py-2 rounded-full bg-green-100 text-green-700">
                            Selected: <span id="selectedAccountCount">0</span>
                        </span>
                        <span class="px-3 py-2 rounded-full bg-purple-100 text-purple-700">
                            Showing: <span id="accountShowingCount">0</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                id="accountPrevBtn"
                                onclick="accountPagination.prev()"
                                class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 font-black hover:bg-slate-100">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>

                        <span id="accountPageInfo" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 font-black text-sm">
                            Page 1
                        </span>

                        <button type="button"
                                id="accountNextBtn"
                                onclick="accountPagination.next()"
                                class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 font-black hover:bg-slate-100">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto soft-scrollbar">
                    <table class="w-full min-w-[1900px] text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-4 text-left w-16">Select</th>
                                <th class="p-4 text-left">Account</th>
                                <th class="p-4 text-left">Server</th>
                                <th class="p-4 text-left">Framework</th>
                                <th class="p-4 text-left">Project Root</th>
                                <th class="p-4 text-left">Developer Portal</th>
                                <th class="p-4 text-left">Commands</th>
                                <th class="p-4 text-left">Permissions</th>
                                <th class="p-4 text-left">Database</th>
                                <th class="p-4 text-left">Status</th>
                            </tr>
                        </thead>

                        <tbody id="accountTableBody" class="divide-y divide-slate-100">
                            @foreach($cpanelAccounts as $index => $account)
                                @php
                                    $username = $account['user'] ?? 'user_' . $index;
                                    $domain = $account['domain'] ?? '-';
                                    $email = $account['email'] ?? '';
                                    $framework = $account['framework'] ?? 'custom';
                                    $existingDeveloper = $developers[$username] ?? null;
                                    $alreadyExists = !empty($existingDeveloper);
                                    $portalActive = $existingDeveloper ? $portalIsActive($existingDeveloper) : true;
                                    $searchText = strtolower(($domain ?? '') . ' ' . ($username ?? '') . ' ' . ($email ?? '') . ' ' . ($account['ip'] ?? '') . ' ' . ($account['plan'] ?? '') . ' ' . $framework);
                                @endphp

                                <tr class="cpanel-account-row hover:bg-slate-50 transition"
                                    data-search="{{ $searchText }}"
                                    data-visible="1">
                                    <td class="p-4 align-top">
                                        <label class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-100 hover:bg-blue-100 cursor-pointer border border-slate-200">
                                            <input type="checkbox"
                                                   name="selected[]"
                                                   value="{{ $username }}"
                                                   class="account-select rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                   onchange="updateSelectedAccountCount()">
                                        </label>

                                        <input type="hidden" name="accounts[{{ $username }}][user]" value="{{ $username }}">
                                        <input type="hidden" name="accounts[{{ $username }}][name]" value="{{ $account['name'] ?? $username }}">
                                        <input type="hidden" name="accounts[{{ $username }}][email]" value="{{ $email }}">
                                        <input type="hidden" name="accounts[{{ $username }}][domain]" value="{{ $domain }}">
                                        <input type="hidden" name="accounts[{{ $username }}][server_id]" value="{{ $account['server_id'] ?? $loadedServerId }}">
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="flex items-start gap-3">
                                            <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                                                <i class="fa-solid fa-globe"></i>
                                            </div>

                                            <div>
                                                <div class="font-black text-slate-900">{{ $domain }}</div>

                                                <div class="text-xs text-slate-500 mt-1">
                                                    <span class="font-black">User:</span> {{ $username }}
                                                </div>

                                                <div class="text-xs text-slate-500 mt-1">
                                                    <span class="font-black">Email:</span> {{ $email ?: '-' }}
                                                </div>

                                                <div class="text-xs text-slate-500 mt-1">
                                                    <span class="font-black">IP:</span> {{ $account['ip'] ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="font-black text-slate-900">{{ $account['server_name'] ?? '-' }}</div>
                                        <div class="text-xs text-slate-500 mt-1">{{ $account['server_host'] ?? '-' }}</div>
                                        <div class="mt-2 inline-flex px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-black">
                                            {{ $account['plan'] ?? 'default' }}
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <select name="accounts[{{ $username }}][framework]"
                                                class="w-48 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                                            @foreach($frameworks as $key => $label)
                                                <option value="{{ $key }}" {{ $framework === $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <input type="hidden" name="accounts[{{ $username }}][project_type]" value="{{ $account['project_type'] ?? 'custom' }}">
                                    </td>

                                    <td class="p-4 align-top">
                                        <input type="text"
                                               name="accounts[{{ $username }}][project_root]"
                                               value="{{ $account['project_root'] ?? '/home/' . $username . '/public_html' }}"
                                               class="w-80 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 font-bold text-xs">

                                        <div class="text-xs text-slate-500 mt-2">
                                            Home: {{ $account['home'] ?? '/home/' . $username }}
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="flex items-center gap-3">
                                            <input type="hidden" name="accounts[{{ $username }}][developer_portal_access]" value="0">

                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox"
                                                       class="portal-switch-input account-portal-toggle"
                                                       name="accounts[{{ $username }}][developer_portal_access]"
                                                       value="1"
                                                       {{ $portalActive ? 'checked' : '' }}
                                                       onchange="updateInlinePortalLabel(this)">
                                                <span class="portal-switch"></span>
                                            </label>

                                            <span class="portal-inline-label px-3 py-1 rounded-full text-xs font-black {{ $portalActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                {{ $portalActive ? 'ON' : 'OFF' }}
                                            </span>
                                        </div>

                                        <div class="text-xs text-slate-500 mt-2 max-w-[220px]">
                                            Controls whether the developer can login to Developer Portal after import.
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="grid grid-cols-1 gap-2">
                                            <input type="text"
                                                   name="accounts[{{ $username }}][build_command]"
                                                   value="{{ $account['build_command'] ?? '' }}"
                                                   placeholder="Build command"
                                                   class="w-80 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 text-xs">

                                            <input type="text"
                                                   name="accounts[{{ $username }}][deploy_command]"
                                                   value="{{ $account['deploy_command'] ?? '' }}"
                                                   placeholder="Deploy command"
                                                   class="w-80 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 text-xs">

                                            <input type="text"
                                                   name="accounts[{{ $username }}][start_command]"
                                                   value="{{ $account['start_command'] ?? '' }}"
                                                   placeholder="Start command"
                                                   class="w-80 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 text-xs">
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                                            @foreach([
                                                'can_view_files' => 'View Files',
                                                'can_edit_files' => 'Edit Files',
                                                'can_delete_files' => 'Delete',
                                                'can_git_pull' => 'Git Pull',
                                                'can_clear_cache' => 'Clear Cache',
                                                'can_composer' => 'Composer',
                                                'can_npm' => 'NPM',
                                                'can_run_build' => 'Build',
                                                'can_run_python' => 'Python',
                                                'can_restart_app' => 'Restart',
                                            ] as $permission => $label)
                                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-50 border border-slate-200">
                                                    <input type="checkbox"
                                                           name="accounts[{{ $username }}][{{ $permission }}]"
                                                           value="1"
                                                           class="rounded border-slate-300 text-blue-600"
                                                           {{ !empty($account[$permission]) ? 'checked' : '' }}>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="grid grid-cols-1 gap-2">
                                            <select name="accounts[{{ $username }}][db_type]"
                                                    class="w-44 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 text-xs font-bold">
                                                <option value="mysql" {{ ($account['db_type'] ?? 'mysql') === 'mysql' ? 'selected' : '' }}>MySQL</option>
                                                <option value="postgresql" {{ ($account['db_type'] ?? '') === 'postgresql' ? 'selected' : '' }}>PostgreSQL</option>
                                            </select>

                                            <input type="text"
                                                   name="accounts[{{ $username }}][db_host]"
                                                   value="{{ $account['db_host'] ?? 'localhost' }}"
                                                   placeholder="DB Host"
                                                   class="w-44 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 text-xs">

                                            <input type="text"
                                                   name="accounts[{{ $username }}][db_username]"
                                                   value="{{ $account['db_username'] ?? $username }}"
                                                   placeholder="DB Username"
                                                   class="w-44 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 text-xs">

                                            <input type="text"
                                                   name="accounts[{{ $username }}][db_name]"
                                                   value="{{ $account['db_name'] ?? '' }}"
                                                   placeholder="DB Name"
                                                   class="w-44 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500 text-xs">

                                            <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-50 border border-slate-200">
                                                    <input type="checkbox"
                                                           name="accounts[{{ $username }}][can_mysql]"
                                                           value="1"
                                                           class="rounded border-slate-300 text-blue-600"
                                                           {{ !empty($account['can_mysql']) ? 'checked' : '' }}>
                                                    MySQL
                                                </label>

                                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-50 border border-slate-200">
                                                    <input type="checkbox"
                                                           name="accounts[{{ $username }}][can_postgresql]"
                                                           value="1"
                                                           class="rounded border-slate-300 text-blue-600"
                                                           {{ !empty($account['can_postgresql']) ? 'checked' : '' }}>
                                                    PGSQL
                                                </label>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        @if($alreadyExists)
                                            <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-black">
                                                Existing Developer
                                            </span>
                                        @else
                                            <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-black">
                                                Ready
                                            </span>
                                        @endif

                                        @if(!empty($account['suspended']))
                                            <div class="mt-2">
                                                <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-black">
                                                    Suspended
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-6 border-t bg-slate-50 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    <div class="text-sm text-slate-500 font-bold">
                        Selected users will be created as Developer Codes accounts with their chosen framework, permissions, database access and portal access status.
                    </div>

                    <button class="px-8 py-4 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black">
                        <i class="fa-solid fa-user-plus mr-2"></i>
                        Create / Update Selected Developer Logins
                    </button>
                </div>
            </div>
        </form>
    @else
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-10 text-center">
            <div class="w-20 h-20 mx-auto rounded-3xl bg-slate-100 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-users-gear text-3xl"></i>
            </div>

            <h2 class="text-2xl font-black text-slate-900 mt-5">No cPanel accounts loaded yet</h2>
            <p class="text-slate-500 mt-2">
                Select a WHM/cPanel server above and click Fetch Users.
            </p>
        </div>
    @endif

    {{-- Existing Developers --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Developer Portal Users</h2>
                <p class="text-slate-500 mt-1">
                    Existing Developer Codes users created from cPanel accounts.
                </p>
            </div>

            <input type="text"
                   id="developerSearch"
                   oninput="filterRows('developerSearch', '.developer-row')"
                   placeholder="Search developers, framework, domain..."
                   class="w-full xl:w-96 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="overflow-x-auto soft-scrollbar">
            <table class="w-full min-w-[1500px] text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4 text-left">Developer</th>
                        <th class="p-4 text-left">cPanel</th>
                        <th class="p-4 text-left">Domain</th>
                        <th class="p-4 text-left">Framework</th>
                        <th class="p-4 text-left">Project Root</th>
                        <th class="p-4 text-left">Permissions</th>
                        <th class="p-4 text-left">Portal Access</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($developers as $developer)
                        @php
                            $developerPortalEnabled = $portalIsActive($developer);
                            $developerSearchText = strtolower(
                                ($developer->name ?? '') . ' ' .
                                ($developer->email ?? '') . ' ' .
                                ($developer->cpanel_username ?? '') . ' ' .
                                ($developer->cpanel_domain ?? '') . ' ' .
                                ($developer->framework ?? '')
                            );

                            $toggleRoute = Route::has('developers.toggle') ? route('developers.toggle', $developer->id) : '#';
                            $resetRoute = Route::has('developers.reset-password') ? route('developers.reset-password', $developer->id) : '#';
                            $deleteRoute = Route::has('developers.destroy') ? route('developers.destroy', $developer->id) : '#';
                        @endphp

                        <tr class="developer-row hover:bg-slate-50 transition" data-search="{{ $developerSearchText }}">
                            <td class="p-4 align-top">
                                <div class="flex items-start gap-3">
                                    <div class="w-11 h-11 rounded-2xl {{ $developerPortalEnabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} flex items-center justify-center shrink-0">
                                        <i class="fa-solid {{ $developerPortalEnabled ? 'fa-user-check' : 'fa-user-lock' }}"></i>
                                    </div>

                                    <div>
                                        <div class="font-black text-slate-900">{{ $developer->name ?? '-' }}</div>
                                        <div class="text-xs text-slate-500 mt-1">{{ $developer->email ?? '-' }}</div>
                                        <div class="text-xs text-slate-500 mt-1">
                                            Last login:
                                            {{ !empty($developer->last_login_at) ? \Carbon\Carbon::parse($developer->last_login_at)->diffForHumans() : 'Never' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="p-4 align-top">
                                <div class="font-black text-slate-900">{{ $developer->cpanel_username ?? '-' }}</div>
                                <div class="text-xs text-slate-500 mt-1">{{ $developer->ssh_username ?? '-' }}</div>
                            </td>

                            <td class="p-4 align-top">
                                <div class="font-black text-slate-900">{{ $developer->cpanel_domain ?? '-' }}</div>
                                <div class="text-xs text-slate-500 mt-1">{{ $developer->contact_email ?? '-' }}</div>
                            </td>

                            <td class="p-4 align-top">
                                <span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-black {{ $frameworkBadge($developer->framework ?? 'custom') }}">
                                    <i class="{{ $frameworkIcon($developer->framework ?? 'custom') }}"></i>
                                    {{ $frameworks[$developer->framework ?? 'custom'] ?? ($developer->framework ?? 'Custom') }}
                                </span>
                            </td>

                            <td class="p-4 align-top">
                                <div class="text-xs font-bold text-slate-700 max-w-[300px] break-all">
                                    {{ $developer->project_root ?? $developer->allowed_project_path ?? '-' }}
                                </div>
                            </td>

                            <td class="p-4 align-top">
                                <div class="flex flex-wrap gap-2 text-xs font-black">
                                    @foreach([
                                        'can_view_files' => 'Files',
                                        'can_edit_files' => 'Edit',
                                        'can_git_pull' => 'Git',
                                        'can_clear_cache' => 'Cache',
                                        'can_composer' => 'Composer',
                                        'can_npm' => 'NPM',
                                        'can_run_build' => 'Build',
                                        'can_run_python' => 'Python',
                                        'can_mysql' => 'MySQL',
                                        'can_postgresql' => 'PGSQL',
                                    ] as $permission => $label)
                                        @if(!empty($developer->{$permission}))
                                            <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700">
                                                {{ $label }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>

                            <td class="p-4 align-top">
                                <div class="flex items-center gap-3">
                                    @if(Route::has('developers.toggle'))
                                        <form method="POST"
                                              action="{{ $toggleRoute }}"
                                              class="developer-toggle-form"
                                              data-enabled="{{ $developerPortalEnabled ? '1' : '0' }}">
                                            @csrf

                                            <button type="submit"
                                                    class="group flex items-center gap-3 px-4 py-3 rounded-2xl border transition {{ $developerPortalEnabled ? 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100' : 'bg-red-50 border-red-200 text-red-700 hover:bg-red-100' }}"
                                                    title="{{ $developerPortalEnabled ? 'Turn Off Portal Access' : 'Turn On Portal Access' }}">
                                                <span class="relative inline-flex h-8 w-14 rounded-full transition {{ $developerPortalEnabled ? 'bg-green-600' : 'bg-slate-300' }}">
                                                    <span class="absolute top-1 h-6 w-6 rounded-full bg-white shadow transition {{ $developerPortalEnabled ? 'left-7' : 'left-1' }}"></span>
                                                </span>

                                                <span class="font-black text-xs">
                                                    {{ $developerPortalEnabled ? 'ON' : 'OFF' }}
                                                </span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="px-4 py-2 rounded-2xl text-xs font-black {{ $developerPortalEnabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $developerPortalEnabled ? 'ON' : 'OFF' }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-2 text-xs font-bold {{ $developerPortalEnabled ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $developerPortalEnabled ? 'Developer can login' : 'Developer login blocked' }}
                                </div>
                            </td>

                            <td class="p-4 align-top text-right">
                                <div class="flex justify-end gap-2">
                                    @if(Route::has('developers.reset-password'))
                                        <form method="POST" action="{{ $resetRoute }}">
                                            @csrf
                                            <button type="submit"
                                                    class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs">
                                                <i class="fa-solid fa-key mr-1"></i>
                                                Reset
                                            </button>
                                        </form>
                                    @endif

                                    @if(Route::has('developers.destroy'))
                                        <form method="POST"
                                              action="{{ $deleteRoute }}"
                                              onsubmit="return confirm('Delete this developer login?')">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black text-xs">
                                                <i class="fa-solid fa-trash mr-1"></i>
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center">
                                <div class="w-20 h-20 mx-auto rounded-3xl bg-slate-100 text-slate-600 flex items-center justify-center">
                                    <i class="fa-solid fa-user-shield text-3xl"></i>
                                </div>

                                <h3 class="text-xl font-black text-slate-900 mt-4">No developer users yet</h3>
                                <p class="text-slate-500 mt-2">
                                    Fetch cPanel accounts and create Developer Codes logins.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    function copyText(elementId) {
        const element = document.getElementById(elementId);

        if (!element) {
            return;
        }

        navigator.clipboard.writeText(element.innerText || element.textContent || '');
    }

    function filterRows(inputId, rowSelector) {
        const input = document.getElementById(inputId);
        const query = (input?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll(rowSelector);

        rows.forEach(row => {
            const text = (row.dataset.search || row.innerText || '').toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }

    function updateInlinePortalLabel(input) {
        const row = input.closest('tr');
        const label = row?.querySelector('.portal-inline-label');

        if (!label) {
            return;
        }

        if (input.checked) {
            label.textContent = 'ON';
            label.className = 'portal-inline-label px-3 py-1 rounded-full text-xs font-black bg-green-100 text-green-700';
        } else {
            label.textContent = 'OFF';
            label.className = 'portal-inline-label px-3 py-1 rounded-full text-xs font-black bg-red-100 text-red-700';
        }
    }

    function updateSelectedAccountCount() {
        const selected = document.querySelectorAll('.account-select:checked').length;
        const selectedBox = document.getElementById('selectedAccountCount');

        if (selectedBox) {
            selectedBox.textContent = selected;
        }
    }

    function selectVisibleAccounts(checked) {
        document.querySelectorAll('.cpanel-account-row').forEach(row => {
            if (row.style.display !== 'none') {
                const checkbox = row.querySelector('.account-select');
                if (checkbox) {
                    checkbox.checked = checked;
                }
            }
        });

        updateSelectedAccountCount();
    }

    const accountPagination = {
        page: 1,
        pageSize: 20,

        rows() {
            return Array.from(document.querySelectorAll('.cpanel-account-row'));
        },

        filteredRows() {
            const search = (document.getElementById('accountSearch')?.value || '').toLowerCase().trim();

            return this.rows().filter(row => {
                const text = (row.dataset.search || row.innerText || '').toLowerCase();
                return text.includes(search);
            });
        },

        totalPages() {
            const total = this.filteredRows().length;
            return Math.max(Math.ceil(total / this.pageSize), 1);
        },

        apply() {
            const rows = this.rows();
            const filtered = this.filteredRows();

            const totalPages = this.totalPages();

            if (this.page > totalPages) {
                this.page = totalPages;
            }

            if (this.page < 1) {
                this.page = 1;
            }

            rows.forEach(row => {
                row.style.display = 'none';
                row.dataset.visible = '0';
            });

            const start = (this.page - 1) * this.pageSize;
            const end = start + this.pageSize;

            filtered.slice(start, end).forEach(row => {
                row.style.display = '';
                row.dataset.visible = '1';
            });

            const pageInfo = document.getElementById('accountPageInfo');
            const showingCount = document.getElementById('accountShowingCount');
            const prevBtn = document.getElementById('accountPrevBtn');
            const nextBtn = document.getElementById('accountNextBtn');

            if (pageInfo) {
                pageInfo.textContent = `Page ${this.page} of ${totalPages}`;
            }

            if (showingCount) {
                const showing = filtered.slice(start, end).length;
                showingCount.textContent = `${showing} / ${filtered.length}`;
            }

            if (prevBtn) {
                prevBtn.disabled = this.page <= 1;
                prevBtn.classList.toggle('opacity-50', this.page <= 1);
                prevBtn.classList.toggle('cursor-not-allowed', this.page <= 1);
            }

            if (nextBtn) {
                nextBtn.disabled = this.page >= totalPages;
                nextBtn.classList.toggle('opacity-50', this.page >= totalPages);
                nextBtn.classList.toggle('cursor-not-allowed', this.page >= totalPages);
            }

            updateSelectedAccountCount();
        },

        next() {
            if (this.page < this.totalPages()) {
                this.page++;
                this.apply();
            }
        },

        prev() {
            if (this.page > 1) {
                this.page--;
                this.apply();
            }
        },

        changePageSize() {
            const select = document.getElementById('accountPageSize');
            this.pageSize = parseInt(select?.value || '20', 10);
            this.page = 1;
            this.apply();
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        accountPagination.changePageSize();
        updateSelectedAccountCount();
    });
</script>

@endsection