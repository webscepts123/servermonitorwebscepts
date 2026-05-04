@extends('layouts.app')

@section('page-title', 'cPanel Developer Logins')

@section('content')

@php
    $servers = $servers ?? collect();
    $developers = $developers ?? collect();
    $cpanelAccounts = collect($cpanelAccounts ?? []);
    $frameworks = $frameworks ?? [
        'custom' => 'Custom / Other',
        'html' => 'Static HTML / CSS / JS',
        'php' => 'PHP',
        'wordpress' => 'WordPress',
        'laravel' => 'Laravel',
        'react' => 'React',
        'vue' => 'Vue.js',
        'angular' => 'Angular',
        'node' => 'Node.js / Express',
        'python' => 'Python',
        'flask' => 'Flask',
        'django' => 'Django',
        'fastapi' => 'FastAPI',
        'nextjs' => 'Next.js',
        'nuxt' => 'Nuxt',
        'svelte' => 'Svelte',
        'java' => 'Java / Spring Boot',
        'dotnet' => '.NET',
        'ruby' => 'Ruby / Rails',
        'go' => 'Go',
    ];

    $loadedServerId = session('cpanel_accounts_server_id');
    $developerUrl = 'https://developercodes.webscepts.com/login';

    $developerCount = method_exists($developers, 'count') ? $developers->count() : 0;
    $activeDeveloperCount = method_exists($developers, 'filter')
        ? $developers->filter(fn ($item) => !empty($item->is_active))->count()
        : 0;

    $frameworkBadge = function ($framework) {
        return match ($framework) {
            'laravel' => 'bg-red-100 text-red-700 border-red-200',
            'wordpress' => 'bg-blue-100 text-blue-700 border-blue-200',
            'react' => 'bg-cyan-100 text-cyan-700 border-cyan-200',
            'vue' => 'bg-green-100 text-green-700 border-green-200',
            'angular' => 'bg-rose-100 text-rose-700 border-rose-200',
            'node' => 'bg-lime-100 text-lime-700 border-lime-200',
            'python', 'flask', 'django', 'fastapi' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'php' => 'bg-purple-100 text-purple-700 border-purple-200',
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
            'node' => 'fa-brands fa-node-js',
            'python', 'flask', 'django', 'fastapi' => 'fa-brands fa-python',
            'php' => 'fa-brands fa-php',
            'java' => 'fa-brands fa-java',
            'html' => 'fa-brands fa-html5',
            default => 'fa-solid fa-code',
        };
    };
@endphp

<div class="space-y-6">

    {{-- Alerts --}}
    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-black">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-black">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>{{ session('error') }}
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
                        Copy these passwords now. WHM/cPanel does not expose existing cPanel passwords, so Developer Codes generated temporary passwords.
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
                </div>

                <p class="text-slate-300 mt-3 max-w-5xl">
                    Fetch cPanel accounts from WHM, choose the project framework for each user, tick the accounts you want,
                    and create secure Developer Codes logins for Laravel, WordPress, PHP, React, Vue, Angular, Node.js,
                    Python, Flask, Django, FastAPI, static websites, and custom frameworks.
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

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Active: {{ $activeDeveloperCount }}
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
                    <p class="text-slate-500 font-bold">Frameworks</p>
                    <h2 class="text-3xl font-black text-purple-600 mt-2">{{ count($frameworks) }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-code text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Portal Status</p>
                    <h2 class="text-3xl font-black text-blue-600 mt-2">Ready</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-cyan-100 text-cyan-700 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check text-xl"></i>
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
                  action="{{ route('developers.cpanel.sync') }}"
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
        <form method="POST" action="{{ route('developers.cpanel.bulk.import') }}">
            @csrf

            <input type="hidden" name="server_id" value="{{ $loadedServerId }}">

            <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
                <div class="p-6 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Available cPanel Accounts</h2>
                        <p class="text-slate-500 mt-1">
                            Tick accounts to add/update Developer Codes. Choose each account framework and permissions.
                        </p>
                    </div>

                    <div class="flex flex-col md:flex-row gap-3">
                        <input type="text"
                               id="accountSearch"
                               oninput="filterRows('accountSearch', '.cpanel-row')"
                               placeholder="Search account, domain, email, framework..."
                               class="w-full md:w-96 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">

                        <button type="button"
                                onclick="toggleAllAccounts(true)"
                                class="px-5 py-3 rounded-2xl bg-slate-900 text-white font-black">
                            Tick All
                        </button>

                        <button type="button"
                                onclick="toggleAllAccounts(false)"
                                class="px-5 py-3 rounded-2xl bg-slate-100 text-slate-700 font-black">
                            Untick All
                        </button>

                        <button class="px-6 py-3 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black">
                            Create Selected
                        </button>
                    </div>
                </div>

                <div class="p-6 bg-slate-50 border-b">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                        @foreach(['laravel', 'wordpress', 'react', 'vue', 'angular', 'node', 'python', 'flask', 'django', 'fastapi', 'php', 'custom'] as $quickFramework)
                            <button type="button"
                                    onclick="setVisibleFramework('{{ $quickFramework }}')"
                                    class="px-4 py-3 rounded-2xl border bg-white hover:bg-slate-100 font-black text-sm">
                                <i class="{{ $frameworkIcon($quickFramework) }} mr-2"></i>
                                {{ $frameworks[$quickFramework] ?? ucfirst($quickFramework) }}
                            </button>
                        @endforeach
                    </div>

                    <p class="text-xs text-slate-500 mt-3 font-bold">
                        Quick buttons apply the selected framework to all visible rows only.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1800px] text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-4 text-left">Add</th>
                                <th class="p-4 text-left">cPanel User</th>
                                <th class="p-4 text-left">Domain</th>
                                <th class="p-4 text-left">Contact Email</th>
                                <th class="p-4 text-left">Framework</th>
                                <th class="p-4 text-left">Project Root</th>
                                <th class="p-4 text-left">Commands</th>
                                <th class="p-4 text-left">Permissions</th>
                                <th class="p-4 text-left">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($cpanelAccounts as $account)
                                @php
                                    $username = $account['user'] ?? null;

                                    if (!$username) {
                                        continue;
                                    }

                                    $exists = method_exists($developers, 'has') ? $developers->has($username) : false;
                                    $suspended = !empty($account['suspended']);
                                    $framework = $account['framework'] ?? 'custom';
                                    $domain = $account['domain'] ?? '';
                                    $home = $account['home'] ?? ('/home/' . $username);
                                    $projectRoot = $account['project_root'] ?? ($home . '/public_html');
                                    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $username);
                                @endphp

                                <tr class="cpanel-row border-t hover:bg-slate-50"
                                    data-username="{{ strtolower($username) }}"
                                    data-framework="{{ $framework }}">
                                    <td class="p-4 align-top">
                                        <input type="checkbox"
                                               name="selected[]"
                                               value="{{ $username }}"
                                               class="account-checkbox w-5 h-5 rounded border-slate-300 text-blue-600"
                                               {{ $exists ? 'checked' : '' }}>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="font-black text-slate-900">{{ $username }}</div>

                                        <div class="text-xs text-slate-500 mt-1">
                                            IP: {{ $account['ip'] ?? '-' }}
                                        </div>

                                        <div class="text-xs text-slate-500">
                                            Plan: {{ $account['plan'] ?? '-' }}
                                        </div>

                                        <div class="text-xs text-slate-500">
                                            Disk: {{ $account['diskused'] ?? '-' }} / {{ $account['disklimit'] ?? '-' }}
                                        </div>

                                        @foreach($account as $key => $value)
                                            @if(!is_array($value) && !in_array($key, [
                                                'email',
                                                'framework',
                                                'project_type',
                                                'project_root',
                                                'build_command',
                                                'deploy_command',
                                                'start_command',
                                                'can_view_files',
                                                'can_clear_cache',
                                                'can_git_pull',
                                                'can_composer',
                                                'can_npm',
                                                'can_run_build',
                                                'can_run_python',
                                                'can_restart_app',
                                                'can_edit_files',
                                                'can_delete_files',
                                            ]))
                                                <input type="hidden"
                                                       name="accounts[{{ $username }}][{{ $key }}]"
                                                       value="{{ $value }}">
                                            @endif
                                        @endforeach
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="font-bold text-slate-800">{{ $domain ?: '-' }}</div>
                                        <div class="text-xs text-slate-500 mt-1">{{ $home }}</div>

                                        @if(!empty($account['suspendreason']))
                                            <div class="mt-2 text-xs text-red-600 font-bold">
                                                {{ $account['suspendreason'] }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="p-4 align-top">
                                        <input type="email"
                                               name="accounts[{{ $username }}][email]"
                                               value="{{ $account['email'] ?? '' }}"
                                               placeholder="contact@email.com"
                                               class="w-full min-w-64 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                                    </td>

                                    <td class="p-4 align-top">
                                        <select name="accounts[{{ $username }}][framework]"
                                                id="framework_{{ $safeName }}"
                                                data-safe-name="{{ $safeName }}"
                                                onchange="applyFrameworkDefaults(this, '{{ $safeName }}'); updateRowFramework(this);"
                                                class="framework-select w-full min-w-56 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                                            @foreach($frameworks as $key => $label)
                                                <option value="{{ $key }}" {{ $framework === $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <input type="hidden"
                                               name="accounts[{{ $username }}][project_type]"
                                               id="project_type_{{ $safeName }}"
                                               value="{{ $account['project_type'] ?? 'web' }}">

                                        <div class="mt-2 inline-flex items-center gap-2 px-3 py-1 rounded-full border {{ $frameworkBadge($framework) }} text-xs font-black">
                                            <i class="{{ $frameworkIcon($framework) }}"></i>
                                            <span id="framework_badge_{{ $safeName }}">
                                                {{ $frameworks[$framework] ?? ucfirst($framework) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <input type="text"
                                               name="accounts[{{ $username }}][project_root]"
                                               id="project_root_{{ $safeName }}"
                                               value="{{ $projectRoot }}"
                                               class="w-full min-w-72 px-3 py-2 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">

                                        <div class="text-xs text-slate-500 mt-2">
                                            Example: /home/{{ $username }}/public_html or /home/{{ $username }}/app
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="space-y-2 min-w-96">
                                            <input type="text"
                                                   name="accounts[{{ $username }}][build_command]"
                                                   id="build_command_{{ $safeName }}"
                                                   value="{{ $account['build_command'] ?? '' }}"
                                                   placeholder="Build command"
                                                   class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs outline-none focus:ring-2 focus:ring-blue-500">

                                            <input type="text"
                                                   name="accounts[{{ $username }}][deploy_command]"
                                                   id="deploy_command_{{ $safeName }}"
                                                   value="{{ $account['deploy_command'] ?? '' }}"
                                                   placeholder="Deploy command"
                                                   class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs outline-none focus:ring-2 focus:ring-blue-500">

                                            <input type="text"
                                                   name="accounts[{{ $username }}][start_command]"
                                                   id="start_command_{{ $safeName }}"
                                                   value="{{ $account['start_command'] ?? '' }}"
                                                   placeholder="Start command"
                                                   class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        <div class="grid grid-cols-2 xl:grid-cols-3 gap-2 min-w-80">
                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_view_files]" value="1" {{ !empty($account['can_view_files']) ? 'checked' : '' }}>
                                                View
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_clear_cache]" value="1" {{ !empty($account['can_clear_cache']) ? 'checked' : '' }}>
                                                Cache
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_git_pull]" value="1" {{ !empty($account['can_git_pull']) ? 'checked' : '' }}>
                                                Git
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_composer]" value="1" {{ !empty($account['can_composer']) ? 'checked' : '' }}>
                                                Composer
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_npm]" value="1" {{ !empty($account['can_npm']) ? 'checked' : '' }}>
                                                NPM
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_run_build]" value="1" {{ !empty($account['can_run_build']) ? 'checked' : '' }}>
                                                Build
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_run_python]" value="1" {{ !empty($account['can_run_python']) ? 'checked' : '' }}>
                                                Python
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_restart_app]" value="1" {{ !empty($account['can_restart_app']) ? 'checked' : '' }}>
                                                Restart
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold text-red-700">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_edit_files]" value="1" {{ !empty($account['can_edit_files']) ? 'checked' : '' }}>
                                                Edit
                                            </label>

                                            <label class="flex items-center gap-2 text-xs font-bold text-red-700">
                                                <input type="checkbox" name="accounts[{{ $username }}][can_delete_files]" value="1" {{ !empty($account['can_delete_files']) ? 'checked' : '' }}>
                                                Delete
                                            </label>
                                        </div>
                                    </td>

                                    <td class="p-4 align-top">
                                        @if($exists)
                                            <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-black">
                                                Already Developer
                                            </span>
                                        @elseif($suspended)
                                            <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-black">
                                                Suspended
                                            </span>
                                        @else
                                            <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-black">
                                                Ready
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-6 border-t bg-slate-50 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    <div class="text-sm text-slate-500 font-bold">
                        Selected users will be created as Developer Codes accounts with their chosen framework and permissions.
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

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1400px] text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4 text-left">Developer</th>
                        <th class="p-4 text-left">cPanel</th>
                        <th class="p-4 text-left">Domain</th>
                        <th class="p-4 text-left">Framework</th>
                        <th class="p-4 text-left">Project Root</th>
                        <th class="p-4 text-left">Permissions</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($developers as $developerUser)
                        @php
                            $developerFramework = $developerUser->framework ?: 'custom';
                        @endphp

                        <tr class="developer-row border-t hover:bg-slate-50">
                            <td class="p-4">
                                <div class="font-black text-slate-900">{{ $developerUser->name }}</div>
                                <div class="text-xs text-slate-500">{{ $developerUser->email }}</div>
                            </td>

                            <td class="p-4">
                                <div class="font-black text-slate-900">{{ $developerUser->cpanel_username ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $developerUser->contact_email ?? '-' }}</div>
                            </td>

                            <td class="p-4">
                                {{ $developerUser->cpanel_domain ?? '-' }}
                            </td>

                            <td class="p-4">
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border {{ $frameworkBadge($developerFramework) }} text-xs font-black">
                                    <i class="{{ $frameworkIcon($developerFramework) }}"></i>
                                    {{ $frameworks[$developerFramework] ?? ucfirst($developerFramework) }}
                                </span>

                                <div class="text-xs text-slate-500 mt-2">
                                    Type: {{ $developerUser->project_type ?? 'custom' }}
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="text-xs font-bold text-slate-700 break-all">
                                    {{ $developerUser->project_root ?? $developerUser->allowed_project_path ?? '-' }}
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-wrap gap-1">
                                    @if($developerUser->can_view_files)<span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-xs font-bold">View</span>@endif
                                    @if($developerUser->can_clear_cache)<span class="px-2 py-1 rounded-lg bg-cyan-100 text-cyan-700 text-xs font-bold">Cache</span>@endif
                                    @if($developerUser->can_git_pull)<span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-bold">Git</span>@endif
                                    @if($developerUser->can_composer)<span class="px-2 py-1 rounded-lg bg-purple-100 text-purple-700 text-xs font-bold">Composer</span>@endif
                                    @if($developerUser->can_npm)<span class="px-2 py-1 rounded-lg bg-orange-100 text-orange-700 text-xs font-bold">NPM</span>@endif
                                    @if($developerUser->can_run_build)<span class="px-2 py-1 rounded-lg bg-yellow-100 text-yellow-700 text-xs font-bold">Build</span>@endif
                                    @if($developerUser->can_run_python)<span class="px-2 py-1 rounded-lg bg-yellow-100 text-yellow-800 text-xs font-bold">Python</span>@endif
                                    @if($developerUser->can_restart_app)<span class="px-2 py-1 rounded-lg bg-slate-200 text-slate-800 text-xs font-bold">Restart</span>@endif
                                    @if($developerUser->can_edit_files)<span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-bold">Edit</span>@endif
                                    @if($developerUser->can_delete_files)<span class="px-2 py-1 rounded-lg bg-red-200 text-red-800 text-xs font-bold">Delete</span>@endif
                                </div>
                            </td>

                            <td class="p-4">
                                @if($developerUser->is_active)
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-black">
                                        Active
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-black">
                                        Disabled
                                    </span>
                                @endif

                                @if(!empty($developerUser->password_must_change))
                                    <div class="mt-2">
                                        <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-black">
                                            Must Change Password
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <form method="POST" action="{{ route('developers.reset.password', $developerUser) }}">
                                        @csrf
                                        <button class="px-3 py-2 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-white font-bold"
                                                onclick="return confirm('Reset temporary password for this developer?')">
                                            Reset
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('developers.toggle', $developerUser) }}">
                                        @csrf
                                        <button class="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-bold">
                                            {{ $developerUser->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('developers.destroy', $developerUser) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-3 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold"
                                                onclick="return confirm('Delete this developer login?')">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center text-slate-500">
                                No developer users created yet. Fetch cPanel users above and tick accounts to add.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Security Info --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-2xl font-black text-slate-900 mb-4">Framework Support</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    PHP / Laravel / WordPress
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Supports Composer, Laravel cache commands, PHP projects and WordPress file work.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    React / Vue / Angular
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Supports NPM install, build commands and frontend project roots.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Node.js / Express
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Supports Node project commands, app start command and controlled restart permission.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Python / Flask / Django / FastAPI
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Supports Python virtualenv, requirements installation and framework-specific start commands.
                </p>
            </div>
        </div>
    </div>

</div>

<script>
const frameworkLabels = @json($frameworks);

const frameworkDefaults = {
    custom: {
        project_type: 'custom',
        build: '',
        deploy: '',
        start: ''
    },
    html: {
        project_type: 'static',
        build: '',
        deploy: '',
        start: ''
    },
    php: {
        project_type: 'php',
        build: 'composer install --no-dev',
        deploy: '',
        start: ''
    },
    wordpress: {
        project_type: 'cms',
        build: '',
        deploy: '',
        start: ''
    },
    laravel: {
        project_type: 'php',
        build: 'composer install --no-dev && php artisan optimize:clear',
        deploy: 'php artisan migrate --force && php artisan optimize:clear',
        start: ''
    },
    react: {
        project_type: 'frontend',
        build: 'npm install && npm run build',
        deploy: 'npm run build',
        start: 'npm run dev'
    },
    vue: {
        project_type: 'frontend',
        build: 'npm install && npm run build',
        deploy: 'npm run build',
        start: 'npm run dev'
    },
    angular: {
        project_type: 'frontend',
        build: 'npm install && npm run build',
        deploy: 'npm run build',
        start: 'ng serve'
    },
    nextjs: {
        project_type: 'frontend',
        build: 'npm install && npm run build',
        deploy: 'npm run build',
        start: 'npm run start'
    },
    nuxt: {
        project_type: 'frontend',
        build: 'npm install && npm run build',
        deploy: 'npm run build',
        start: 'npm run start'
    },
    svelte: {
        project_type: 'frontend',
        build: 'npm install && npm run build',
        deploy: 'npm run build',
        start: 'npm run dev'
    },
    node: {
        project_type: 'node',
        build: 'npm install',
        deploy: 'npm install --production',
        start: 'npm start'
    },
    python: {
        project_type: 'python',
        build: 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
        deploy: './venv/bin/pip install -r requirements.txt',
        start: 'python3 app.py'
    },
    flask: {
        project_type: 'python',
        build: 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
        deploy: './venv/bin/pip install -r requirements.txt',
        start: './venv/bin/flask run'
    },
    django: {
        project_type: 'python',
        build: 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
        deploy: './venv/bin/python manage.py migrate && ./venv/bin/python manage.py collectstatic --noinput',
        start: './venv/bin/python manage.py runserver'
    },
    fastapi: {
        project_type: 'python',
        build: 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
        deploy: './venv/bin/pip install -r requirements.txt',
        start: './venv/bin/uvicorn main:app --host 0.0.0.0 --port 8000'
    },
    java: {
        project_type: 'java',
        build: './mvnw clean package',
        deploy: './mvnw clean package -DskipTests',
        start: 'java -jar target/app.jar'
    },
    dotnet: {
        project_type: 'dotnet',
        build: 'dotnet restore && dotnet build',
        deploy: 'dotnet publish -c Release',
        start: 'dotnet run'
    },
    ruby: {
        project_type: 'ruby',
        build: 'bundle install',
        deploy: 'bundle install --deployment',
        start: 'bundle exec rails server'
    },
    go: {
        project_type: 'go',
        build: 'go mod download && go build',
        deploy: 'go build',
        start: './app'
    }
};

function copyText(id) {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    const text = el.innerText || el.textContent || '';

    navigator.clipboard.writeText(text).then(function () {
        alert('Copied');
    }).catch(function () {
        const range = document.createRange();
        range.selectNodeContents(el);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        document.execCommand('copy');
        selection.removeAllRanges();

        alert('Copied');
    });
}

function filterRows(inputId, rowSelector) {
    const input = document.getElementById(inputId);
    const search = input ? input.value.toLowerCase() : '';

    document.querySelectorAll(rowSelector).forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(search) ? '' : 'none';
    });
}

function toggleAllAccounts(status) {
    document.querySelectorAll('.account-checkbox').forEach(function(box) {
        if (box.closest('tr').style.display !== 'none') {
            box.checked = status;
        }
    });
}

function applyFrameworkDefaults(select, safeName) {
    const framework = select.value;
    const defaults = frameworkDefaults[framework] || frameworkDefaults.custom;

    const projectType = document.getElementById('project_type_' + safeName);
    const build = document.getElementById('build_command_' + safeName);
    const deploy = document.getElementById('deploy_command_' + safeName);
    const start = document.getElementById('start_command_' + safeName);

    if (projectType) {
        projectType.value = defaults.project_type;
    }

    if (build) {
        build.value = defaults.build;
    }

    if (deploy) {
        deploy.value = defaults.deploy;
    }

    if (start) {
        start.value = defaults.start;
    }
}

function updateRowFramework(select) {
    const row = select.closest('tr');

    if (!row) {
        return;
    }

    row.dataset.framework = select.value;

    const safeName = select.dataset.safeName;
    const badge = document.getElementById('framework_badge_' + safeName);

    if (badge) {
        badge.innerText = frameworkLabels[select.value] || select.value;
    }
}

function setVisibleFramework(framework) {
    document.querySelectorAll('.cpanel-row').forEach(function(row) {
        if (row.style.display === 'none') {
            return;
        }

        const select = row.querySelector('.framework-select');

        if (!select) {
            return;
        }

        select.value = framework;
        const safeName = select.dataset.safeName;

        applyFrameworkDefaults(select, safeName);
        updateRowFramework(select);
    });
}
</script>

@endsection