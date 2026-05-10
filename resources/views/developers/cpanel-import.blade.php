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
    $publicCodeEditorUrl = 'https://developercodes.webscepts.com/codeditor';

    $syncRoute = Route::has('developers.cpanel.sync') ? route('developers.cpanel.sync') : '#';
    $bulkImportRoute = Route::has('developers.cpanel.bulk.import') ? route('developers.cpanel.bulk.import') : '#';

    $portalIsActive = function ($developer) {
        return (bool) (
            $developer->developer_portal_access
            ?? $developer->portal_access_enabled
            ?? $developer->developer_portal_enabled
            ?? $developer->is_active
            ?? false
        );
    };

    $editorUrlForUser = function ($username, $domain = null, $account = []) {
        $existing = $account['code_editor_url']
            ?? $account['vscode_url']
            ?? null;

        if ($existing) {
            return $existing;
        }

        $username = strtolower(trim((string) $username));

        if ($username) {
            return 'https://code-' . $username . '.webscepts.com';
        }

        $domain = strtolower(trim((string) $domain));

        if ($domain && $domain !== '-') {
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#/.*$#', '', $domain);
            $domain = str_replace([':', '_'], '-', $domain);

            return 'https://code-' . $domain;
        }

        return '';
    };

    $developerCount = method_exists($developers, 'count') ? $developers->count() : 0;

    $activeDeveloperCount = method_exists($developers, 'filter')
        ? $developers->filter(fn ($item) => $portalIsActive($item))->count()
        : 0;

    $disabledDeveloperCount = max($developerCount - $activeDeveloperCount, 0);

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

    $permissionGroups = [
        'File Access' => [
            'can_view_files' => ['View Files', 'fa-solid fa-eye'],
            'can_edit_files' => ['Edit Files', 'fa-solid fa-pen-to-square'],
            'can_delete_files' => ['Delete Files', 'fa-solid fa-trash'],
        ],
        'Commands' => [
            'can_git_pull' => ['Git Pull', 'fa-solid fa-code-branch'],
            'can_clear_cache' => ['Clear Cache', 'fa-solid fa-broom'],
            'can_composer' => ['Composer', 'fa-solid fa-box'],
            'can_npm' => ['NPM', 'fa-brands fa-node-js'],
            'can_run_build' => ['Build', 'fa-solid fa-cube'],
            'can_run_python' => ['Python', 'fa-brands fa-python'],
            'can_restart_app' => ['Restart', 'fa-solid fa-rotate'],
        ],
        'Database' => [
            'can_mysql' => ['MySQL', 'fa-solid fa-database'],
            'can_postgresql' => ['PostgreSQL', 'fa-solid fa-server'],
        ],
    ];
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
        flex-shrink: 0;
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

    .dev-card-input {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 10px 12px;
        outline: none;
        font-size: 13px;
        font-weight: 700;
        background: #fff;
    }

    .dev-card-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, .15);
    }

    .dev-check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 12px;
        font-weight: 900;
        color: #334155;
        line-height: 1.1;
        min-height: 44px;
    }

    .dev-check input {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }

    .dev-section-title {
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 10px;
    }

    .permission-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 900;
        border: 1px solid;
    }

    .permission-on {
        background: #dcfce7;
        color: #15803d;
        border-color: #bbf7d0;
    }

    .permission-off {
        background: #f8fafc;
        color: #94a3b8;
        border-color: #e2e8f0;
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
Code Editor Public URL: {{ $login['codeditor'] ?? $publicCodeEditorUrl }}
Backend Editor URL: {{ $login['code_editor_url'] ?? '-' }}
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
                        <i class="fa-solid fa-code"></i>
                        Per Account VS Code
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-5xl">
                    Fetch cPanel accounts, choose frameworks, set backend Code Editor URL,
                    assign permissions, and control Developer Portal access.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Portal: {{ $developerUrl }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Public Editor: {{ $publicCodeEditorUrl }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Loaded cPanel Accounts: {{ $cpanelAccounts->count() }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Developer Users: {{ $developerCount }}
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
                            Select accounts and configure framework, editor URL, portal access, commands, permissions and database.
                        </p>
                    </div>

                    <div class="flex flex-col md:flex-row gap-3 w-full xl:w-auto">
                        <input type="text"
                               id="accountSearch"
                               oninput="accountPagination.apply()"
                               placeholder="Search domain, username, email, editor URL..."
                               class="w-full md:w-96 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">

                        <select id="accountPageSize"
                                onchange="accountPagination.changePageSize()"
                                class="w-full md:w-40 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="5">5 / page</option>
                            <option value="10" selected>10 / page</option>
                            <option value="20">20 / page</option>
                            <option value="30">30 / page</option>
                            <option value="50">50 / page</option>
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

                <div id="accountCardList" class="p-5 space-y-5">
                    @foreach($cpanelAccounts as $index => $account)
                        @php
                            $username = $account['user'] ?? 'user_' . $index;
                            $domain = $account['domain'] ?? '-';
                            $email = $account['email'] ?? '';
                            $framework = $account['framework'] ?? 'custom';
                            $existingDeveloper = $developers[$username] ?? null;
                            $alreadyExists = !empty($existingDeveloper);
                            $portalActive = $existingDeveloper ? $portalIsActive($existingDeveloper) : true;

                            $currentEditorUrl = $existingDeveloper
                                ? ($existingDeveloper->code_editor_url ?? $existingDeveloper->vscode_url ?? null)
                                : null;

                            $editorUrl = $currentEditorUrl ?: $editorUrlForUser($username, $domain, $account);

                            $searchText = strtolower(
                                ($domain ?? '') . ' ' .
                                ($username ?? '') . ' ' .
                                ($email ?? '') . ' ' .
                                ($account['ip'] ?? '') . ' ' .
                                ($account['plan'] ?? '') . ' ' .
                                $framework . ' ' .
                                $editorUrl
                            );
                        @endphp

                        <div class="cpanel-account-row rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                             data-search="{{ $searchText }}"
                             data-visible="1">

                            <div class="p-5 bg-slate-50 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <label class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-white border border-slate-200 hover:bg-blue-50 cursor-pointer shrink-0">
                                        <input type="checkbox"
                                               name="selected[]"
                                               value="{{ $username }}"
                                               class="account-select rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                               onchange="updateSelectedAccountCount()">
                                    </label>

                                    <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-globe"></i>
                                    </div>

                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-xl font-black text-slate-900">{{ $domain }}</h3>

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
                                                <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-black">
                                                    Suspended
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500 font-bold">
                                            <span>User: <b class="text-slate-800">{{ $username }}</b></span>
                                            <span>Email: <b class="text-slate-800">{{ $email ?: '-' }}</b></span>
                                            <span>IP: <b class="text-slate-800">{{ $account['ip'] ?? '-' }}</b></span>
                                            <span>Plan: <b class="text-purple-700">{{ $account['plan'] ?? 'default' }}</b></span>
                                        </div>
                                    </div>
                                </div>

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
                            </div>

                            <div class="p-5">
                                <input type="hidden" name="accounts[{{ $username }}][user]" value="{{ $username }}">
                                <input type="hidden" name="accounts[{{ $username }}][name]" value="{{ $account['name'] ?? $username }}">
                                <input type="hidden" name="accounts[{{ $username }}][email]" value="{{ $email }}">
                                <input type="hidden" name="accounts[{{ $username }}][domain]" value="{{ $domain }}">
                                <input type="hidden" name="accounts[{{ $username }}][server_id]" value="{{ $account['server_id'] ?? $loadedServerId }}">

                                <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">

                                    <div class="xl:col-span-4 space-y-4">
                                        <div>
                                            <div class="dev-section-title">Framework</div>
                                            <select name="accounts[{{ $username }}][framework]" class="dev-card-input">
                                                @foreach($frameworks as $key => $label)
                                                    <option value="{{ $key }}" {{ $framework === $key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <input type="hidden" name="accounts[{{ $username }}][project_type]" value="{{ $account['project_type'] ?? 'custom' }}">
                                        </div>

                                        <div>
                                            <div class="dev-section-title">Project Root</div>
                                            <input type="text"
                                                   name="accounts[{{ $username }}][project_root]"
                                                   value="{{ $account['project_root'] ?? '/home/' . $username . '/public_html' }}"
                                                   class="dev-card-input">
                                            <div class="text-xs text-slate-500 mt-2 font-bold">
                                                Home: {{ $account['home'] ?? '/home/' . $username }}
                                            </div>
                                        </div>

                                        <div>
                                            <div class="dev-section-title">Code Editor URL</div>
                                            <input type="text"
                                                   name="accounts[{{ $username }}][code_editor_url]"
                                                   value="{{ $editorUrl }}"
                                                   placeholder="https://code-{{ strtolower($username) }}.webscepts.com"
                                                   class="dev-card-input">
                                            <div class="text-xs text-slate-500 mt-2 font-bold">
                                                Developer opens {{ $publicCodeEditorUrl }}, backend loads this URL.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="xl:col-span-3 space-y-4">
                                        <div>
                                            <div class="dev-section-title">Commands</div>

                                            <div class="space-y-3">
                                                <input type="text"
                                                       name="accounts[{{ $username }}][build_command]"
                                                       value="{{ $account['build_command'] ?? '' }}"
                                                       placeholder="Build command"
                                                       class="dev-card-input">

                                                <input type="text"
                                                       name="accounts[{{ $username }}][deploy_command]"
                                                       value="{{ $account['deploy_command'] ?? '' }}"
                                                       placeholder="Deploy command"
                                                       class="dev-card-input">

                                                <input type="text"
                                                       name="accounts[{{ $username }}][start_command]"
                                                       value="{{ $account['start_command'] ?? '' }}"
                                                       placeholder="Start command"
                                                       class="dev-card-input">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="xl:col-span-3">
                                        <div class="dev-section-title">Permissions</div>

                                        <div class="grid grid-cols-2 gap-2">
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
                                                <label class="dev-check">
                                                    <input type="checkbox"
                                                           name="accounts[{{ $username }}][{{ $permission }}]"
                                                           value="1"
                                                           class="rounded border-slate-300 text-blue-600"
                                                           {{ !empty($account[$permission]) ? 'checked' : '' }}>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="xl:col-span-2 space-y-3">
                                        <div class="dev-section-title">Database</div>

                                        <select name="accounts[{{ $username }}][db_type]" class="dev-card-input">
                                            <option value="mysql" {{ ($account['db_type'] ?? 'mysql') === 'mysql' ? 'selected' : '' }}>MySQL</option>
                                            <option value="postgresql" {{ ($account['db_type'] ?? '') === 'postgresql' ? 'selected' : '' }}>PostgreSQL</option>
                                        </select>

                                        <input type="text"
                                               name="accounts[{{ $username }}][db_host]"
                                               value="{{ $account['db_host'] ?? 'localhost' }}"
                                               placeholder="DB Host"
                                               class="dev-card-input">

                                        <input type="text"
                                               name="accounts[{{ $username }}][db_username]"
                                               value="{{ $account['db_username'] ?? $username }}"
                                               placeholder="DB Username"
                                               class="dev-card-input">

                                        <input type="text"
                                               name="accounts[{{ $username }}][db_name]"
                                               value="{{ $account['db_name'] ?? '' }}"
                                               placeholder="DB Name"
                                               class="dev-card-input">

                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="dev-check">
                                                <input type="checkbox"
                                                       name="accounts[{{ $username }}][can_mysql]"
                                                       value="1"
                                                       class="rounded border-slate-300 text-blue-600"
                                                       {{ !empty($account['can_mysql']) ? 'checked' : '' }}>
                                                MySQL
                                            </label>

                                            <label class="dev-check">
                                                <input type="checkbox"
                                                       name="accounts[{{ $username }}][can_postgresql]"
                                                       value="1"
                                                       class="rounded border-slate-300 text-blue-600"
                                                       {{ !empty($account['can_postgresql']) ? 'checked' : '' }}>
                                                PGSQL
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-6 border-t bg-slate-50 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    <div class="text-sm text-slate-500 font-bold">
                        Selected users will be created/updated with framework, permissions, database access, portal status and per-account Code Editor URL.
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
                    Existing Developer Codes users. You can update VS Code URL, project root, permissions, commands and database access here.
                </p>
            </div>

            <input type="text"
                   id="developerSearch"
                   oninput="filterRows('developerSearch', '.developer-row')"
                   placeholder="Search developers, framework, domain, editor URL..."
                   class="w-full xl:w-96 px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-5 grid grid-cols-1 gap-5">
            @forelse($developers as $developer)
                @php
                    $developerPortalEnabled = $portalIsActive($developer);

                    $developerEditorUrl = $developer->code_editor_url
                        ?? $developer->vscode_url
                        ?? null;

                    $fallbackDeveloperEditorUrl = $developerEditorUrl
                        ?: $editorUrlForUser($developer->cpanel_username ?? '', $developer->cpanel_domain ?? '', []);

                    $developerSearchText = strtolower(
                        ($developer->name ?? '') . ' ' .
                        ($developer->email ?? '') . ' ' .
                        ($developer->cpanel_username ?? '') . ' ' .
                        ($developer->cpanel_domain ?? '') . ' ' .
                        ($developer->framework ?? '') . ' ' .
                        ($fallbackDeveloperEditorUrl ?? '')
                    );

                    $updateRoute = Route::has('developers.settings.update') ? route('developers.settings.update', $developer->id) : '#';
                    $resetRoute = Route::has('developers.reset-password') ? route('developers.reset-password', $developer->id) : '#';
                    $deleteRoute = Route::has('developers.destroy') ? route('developers.destroy', $developer->id) : '#';
                @endphp

                <div class="developer-row rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                     data-search="{{ $developerSearchText }}">

                    <form method="POST" action="{{ $updateRoute }}">
                        @csrf
                        @method('PUT')

                        <div class="p-5 bg-slate-50 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="w-12 h-12 rounded-2xl {{ $developerPortalEnabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} flex items-center justify-center shrink-0">
                                    <i class="fa-solid {{ $developerPortalEnabled ? 'fa-user-check' : 'fa-user-lock' }}"></i>
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-black text-slate-900 text-lg">
                                            {{ $developer->name ?? '-' }}
                                        </div>

                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl border text-xs font-black {{ $frameworkBadge($developer->framework ?? 'custom') }}">
                                            <i class="{{ $frameworkIcon($developer->framework ?? 'custom') }}"></i>
                                            {{ $frameworks[$developer->framework ?? 'custom'] ?? ($developer->framework ?? 'Custom') }}
                                        </span>
                                    </div>

                                    <div class="text-xs text-slate-500 mt-1 font-bold">{{ $developer->email ?? '-' }}</div>

                                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500 font-bold">
                                        <span>cPanel: <b class="text-slate-800">{{ $developer->cpanel_username ?? '-' }}</b></span>
                                        <span>Domain: <b class="text-slate-800">{{ $developer->cpanel_domain ?? '-' }}</b></span>
                                        <span>Last Login: <b class="text-slate-800">{{ !empty($developer->last_login_at) ? \Carbon\Carbon::parse($developer->last_login_at)->diffForHumans() : 'Never' }}</b></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <input type="hidden" name="developer_portal_access" value="0">

                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox"
                                           class="portal-switch-input"
                                           name="developer_portal_access"
                                           value="1"
                                           {{ $developerPortalEnabled ? 'checked' : '' }}
                                           onchange="updateExistingPortalLabel(this)">
                                    <span class="portal-switch"></span>
                                </label>

                                <span class="existing-portal-label px-3 py-1 rounded-full text-xs font-black {{ $developerPortalEnabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    Portal {{ $developerPortalEnabled ? 'ON' : 'OFF' }}
                                </span>
                            </div>
                        </div>

                        <div class="p-5">
                            <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">

                                {{-- Main Settings --}}
                                <div class="xl:col-span-4 space-y-4">
                                    <div>
                                        <div class="dev-section-title">Framework</div>
                                        <select name="framework" class="dev-card-input">
                                            @foreach($frameworks as $key => $label)
                                                <option value="{{ $key }}" {{ ($developer->framework ?? 'custom') === $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <div class="dev-section-title">Project Root</div>
                                        <input type="text"
                                               name="project_root"
                                               value="{{ $developer->project_root ?? $developer->allowed_project_path ?? '' }}"
                                               placeholder="/home/{{ $developer->cpanel_username ?? 'user' }}/public_html"
                                               class="dev-card-input">
                                    </div>

                                    <div>
                                        <div class="dev-section-title">Code Editor Backend URL</div>
                                        <input type="text"
                                               name="code_editor_url"
                                               value="{{ $fallbackDeveloperEditorUrl }}"
                                               placeholder="https://dev.teengirls.lk or https://code-user.webscepts.com"
                                               class="dev-card-input">

                                        <div class="text-xs text-blue-700 mt-2 font-bold break-all">
                                            Public URL: {{ $publicCodeEditorUrl }}
                                        </div>
                                    </div>

                                    <div class="rounded-2xl bg-blue-50 border border-blue-200 p-4">
                                        <a href="{{ $publicCodeEditorUrl }}"
                                           target="_blank"
                                           class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs">
                                            <i class="fa-solid fa-code"></i>
                                            Open Public Editor
                                        </a>

                                        <div class="text-xs text-blue-700 mt-3 font-bold break-all">
                                            Backend: {{ $fallbackDeveloperEditorUrl ?: 'Not configured' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Commands --}}
                                <div class="xl:col-span-3 space-y-4">
                                    <div>
                                        <div class="dev-section-title">Commands</div>

                                        <div class="space-y-3">
                                            <input type="text"
                                                   name="build_command"
                                                   value="{{ $developer->build_command ?? '' }}"
                                                   placeholder="Build command"
                                                   class="dev-card-input">

                                            <input type="text"
                                                   name="deploy_command"
                                                   value="{{ $developer->deploy_command ?? '' }}"
                                                   placeholder="Deploy command"
                                                   class="dev-card-input">

                                            <input type="text"
                                                   name="start_command"
                                                   value="{{ $developer->start_command ?? '' }}"
                                                   placeholder="Start command"
                                                   class="dev-card-input">
                                        </div>
                                    </div>

                                    <div>
                                        <div class="dev-section-title">Database Details</div>

                                        <div class="space-y-3">
                                            <select name="db_type" class="dev-card-input">
                                                <option value="mysql" {{ ($developer->db_type ?? 'mysql') === 'mysql' ? 'selected' : '' }}>MySQL</option>
                                                <option value="postgresql" {{ ($developer->db_type ?? '') === 'postgresql' ? 'selected' : '' }}>PostgreSQL</option>
                                            </select>

                                            <input type="text"
                                                   name="db_host"
                                                   value="{{ $developer->db_host ?? '' }}"
                                                   placeholder="DB Host"
                                                   class="dev-card-input">

                                            <input type="text"
                                                   name="db_username"
                                                   value="{{ $developer->db_username ?? '' }}"
                                                   placeholder="DB Username"
                                                   class="dev-card-input">

                                            <input type="text"
                                                   name="db_name"
                                                   value="{{ $developer->db_name ?? '' }}"
                                                   placeholder="DB Name"
                                                   class="dev-card-input">
                                        </div>
                                    </div>
                                </div>

                                {{-- Editable Permissions --}}
                                <div class="xl:col-span-5">
                                    <div class="dev-section-title">Update Permissions</div>

                                    <div class="space-y-4">
                                        @foreach($permissionGroups as $groupTitle => $permissions)
                                            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                                <div class="text-xs font-black text-slate-500 mb-3">
                                                    {{ $groupTitle }}
                                                </div>

                                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                    @foreach($permissions as $column => $meta)
                                                        @php
                                                            $allowed = !empty($developer->{$column});
                                                            $label = $meta[0];
                                                            $icon = $meta[1];
                                                        @endphp

                                                        <label class="dev-check {{ $allowed ? 'border-green-200 bg-green-50 text-green-700' : '' }}">
                                                            <input type="checkbox"
                                                                   name="{{ $column }}"
                                                                   value="1"
                                                                   class="rounded border-slate-300 text-blue-600"
                                                                   {{ $allowed ? 'checked' : '' }}>
                                                            <i class="{{ $icon }}"></i>
                                                            <span>{{ $label }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-t pt-5">
                                <div class="text-sm text-slate-500 font-bold">
                                    Save changes to update this developer’s editor URL and permissions.
                                </div>

                                <div class="flex justify-end gap-2">
                                    <button type="submit"
                                            class="px-5 py-3 rounded-xl bg-green-600 hover:bg-green-700 text-white font-black text-sm">
                                        <i class="fa-solid fa-floppy-disk mr-1"></i>
                                        Save Changes
                                    </button>
                    </form>

                                    @if(Route::has('developers.reset-password'))
                                        <form method="POST" action="{{ $resetRoute }}">
                                            @csrf
                                            <button type="submit"
                                                    class="px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm">
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
                                                    class="px-5 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black text-sm">
                                                <i class="fa-solid fa-trash mr-1"></i>
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                </div>
            @empty
                <div class="p-10 text-center">
                    <div class="w-20 h-20 mx-auto rounded-3xl bg-slate-100 text-slate-600 flex items-center justify-center">
                        <i class="fa-solid fa-user-shield text-3xl"></i>
                    </div>

                    <h3 class="text-xl font-black text-slate-900 mt-4">No developer users yet</h3>
                    <p class="text-slate-500 mt-2">
                        Fetch cPanel accounts and create Developer Codes logins.
                    </p>
                </div>
            @endforelse
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
        const card = input.closest('.cpanel-account-row');
        const label = card?.querySelector('.portal-inline-label');

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

    function updateExistingPortalLabel(input) {
        const card = input.closest('.developer-row');
        const label = card?.querySelector('.existing-portal-label');

        if (!label) {
            return;
        }

        if (input.checked) {
            label.textContent = 'Portal ON';
            label.className = 'existing-portal-label px-3 py-1 rounded-full text-xs font-black bg-green-100 text-green-700';
        } else {
            label.textContent = 'Portal OFF';
            label.className = 'existing-portal-label px-3 py-1 rounded-full text-xs font-black bg-red-100 text-red-700';
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
        pageSize: 10,

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
            this.pageSize = parseInt(select?.value || '10', 10);
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