@extends('layouts.developer')

@section('title', $pageTitle ?? 'Developer Workspace')

@section('developer-content')

@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;

    $developer = auth()->guard('developer')->user();

    $pageTitle = $pageTitle ?? 'Developer Codes Workspace';
    $pageDescription = $pageDescription ?? 'Secure developer portal for project access, browser VS Code, approved commands, Git tools, database access, and safe workspace permissions.';
    $pageIcon = $pageIcon ?? 'fa-solid fa-layer-group';
    $activeDeveloperPage = $activeDeveloperPage ?? 'overview';

    $developerName = $developer->name
        ?? $developer->cpanel_username
        ?? $developer->email
        ?? 'Developer';

    $developerEmail = $developer->email
        ?? $developer->contact_email
        ?? 'No email';

    $developerRole = $developer->role
        ?? 'developer';

    $developerUsername = $developer->ssh_username
        ?? $developer->cpanel_username
        ?? 'developer';

    $domain = $developer->cpanel_domain
        ?? 'developer workspace';

    $framework = $developer->framework
        ?? 'custom';

    $projectRoot = $projectRoot
        ?? $developer->project_root
        ?? $developer->allowed_project_path
        ?? null;

    if (!$projectRoot) {
        $projectRoot = '/home/' . ($developer->cpanel_username ?? $developer->ssh_username ?? 'developer') . '/public_html';
    }

    $projectRoot = rtrim($projectRoot, '/');

    $publicPath = str_contains($projectRoot, '/public_html')
        ? $projectRoot
        : rtrim($projectRoot, '/') . '/public_html';

    $branch = $gitBranch ?? 'unknown';
    $environment = app()->environment();
    $phpVersion = PHP_VERSION;
    $laravelVersion = app()->version();

    $canGitPull = (bool) ($developer->can_git_pull ?? false);
    $canClearCache = (bool) ($developer->can_clear_cache ?? false);
    $canComposer = (bool) ($developer->can_composer ?? false);
    $canNpm = (bool) ($developer->can_npm ?? false);
    $canRunBuild = (bool) ($developer->can_run_build ?? false);
    $canRunPython = (bool) ($developer->can_run_python ?? false);
    $canRestartApp = (bool) ($developer->can_restart_app ?? false);
    $canViewFiles = (bool) ($developer->can_view_files ?? false);
    $canEditFiles = (bool) ($developer->can_edit_files ?? false);
    $canDeleteFiles = (bool) ($developer->can_delete_files ?? false);
    $canMysql = (bool) ($developer->can_mysql ?? false);
    $canPostgresql = (bool) ($developer->can_postgresql ?? false);

    /*
    |--------------------------------------------------------------------------
    | Public Code Editor Route
    |--------------------------------------------------------------------------
    | This is what every developer opens:
    | https://developercodes.webscepts.com/codeditor
    |--------------------------------------------------------------------------
    */

    $codeEditorUrl = Route::has('developer.domain.codeditor')
        ? route('developer.domain.codeditor')
        : url('/codeditor');

    /*
    |--------------------------------------------------------------------------
    | Backend Code Editor URL
    |--------------------------------------------------------------------------
    | This is different for every developer account.
    | Main source:
    | developer_users.code_editor_url
    |
    | Fallback:
    | developer_users.vscode_url
    |
    | Auto fallback:
    | https://code-{cpanel_username}.webscepts.com
    |--------------------------------------------------------------------------
    */

    $editorBackendUrl = $editorBackendUrl
        ?? $developer->code_editor_url
        ?? $developer->vscode_url
        ?? null;

    if (!$editorBackendUrl && !empty($developer->cpanel_username)) {
        $editorBackendUrl = 'https://code-' . strtolower($developer->cpanel_username) . '.webscepts.com';
    }

    if (!$editorBackendUrl && !empty($developer->ssh_username)) {
        $editorBackendUrl = 'https://code-' . strtolower($developer->ssh_username) . '.webscepts.com';
    }

    if (!$editorBackendUrl && !empty($developer->cpanel_domain)) {
        $safeDomain = strtolower($developer->cpanel_domain);
        $safeDomain = preg_replace('#^https?://#', '', $safeDomain);
        $safeDomain = preg_replace('#/.*$#', '', $safeDomain);
        $safeDomain = str_replace([':', '_'], '-', $safeDomain);

        $editorBackendUrl = 'https://code-' . $safeDomain;
    }

    if (!$editorBackendUrl) {
        $editorBackendUrl = config('services.vscode.url') ?: env('VSCODE_WEB_URL');
    }

    if ($editorBackendUrl && !Str::startsWith($editorBackendUrl, ['http://', 'https://'])) {
        $editorBackendUrl = 'https://' . $editorBackendUrl;
    }

    $editorBackendUrl = $editorBackendUrl ? rtrim($editorBackendUrl, '/') : null;

    $permissions = [
        [
            'label' => 'Git Pull',
            'allowed' => $canGitPull,
            'icon' => 'fa-solid fa-code-branch',
        ],
        [
            'label' => 'Clear Cache',
            'allowed' => $canClearCache,
            'icon' => 'fa-solid fa-broom',
        ],
        [
            'label' => 'Composer',
            'allowed' => $canComposer,
            'icon' => 'fa-solid fa-box',
        ],
        [
            'label' => 'NPM Build',
            'allowed' => $canNpm || $canRunBuild,
            'icon' => 'fa-brands fa-node-js',
        ],
        [
            'label' => 'Python',
            'allowed' => $canRunPython,
            'icon' => 'fa-brands fa-python',
        ],
        [
            'label' => 'Restart App',
            'allowed' => $canRestartApp,
            'icon' => 'fa-solid fa-rotate',
        ],
        [
            'label' => 'View Files',
            'allowed' => $canViewFiles,
            'icon' => 'fa-solid fa-folder-open',
        ],
        [
            'label' => 'Edit Files',
            'allowed' => $canEditFiles,
            'icon' => 'fa-solid fa-pen-to-square',
        ],
        [
            'label' => 'Delete Files',
            'allowed' => $canDeleteFiles,
            'icon' => 'fa-solid fa-trash',
        ],
        [
            'label' => 'MySQL',
            'allowed' => $canMysql,
            'icon' => 'fa-solid fa-database',
        ],
        [
            'label' => 'PostgreSQL',
            'allowed' => $canPostgresql,
            'icon' => 'fa-solid fa-server',
        ],
    ];

    $toolCards = [
        [
            'title' => 'Web VS Code',
            'description' => 'Open browser Visual Studio Code through the protected Developer Codes route.',
            'icon' => 'fa-solid fa-code',
            'color' => 'blue',
            'enabled' => $canViewFiles,
        ],
        [
            'title' => 'Project Files',
            'description' => 'View assigned project path and safe file access information.',
            'icon' => 'fa-solid fa-folder-tree',
            'color' => 'green',
            'enabled' => $canViewFiles,
        ],
        [
            'title' => 'Git Tools',
            'description' => 'Run approved Git actions such as Git Pull when permission is enabled.',
            'icon' => 'fa-solid fa-code-branch',
            'color' => 'emerald',
            'enabled' => $canGitPull,
        ],
        [
            'title' => 'Laravel Tools',
            'description' => 'Clear cache, optimize config, and run composer tools for Laravel/PHP projects.',
            'icon' => 'fa-brands fa-laravel',
            'color' => 'red',
            'enabled' => $canClearCache || $canComposer,
        ],
        [
            'title' => 'Frontend Build',
            'description' => 'React, Vue, Angular, Next.js, Nuxt, Svelte and Node build commands.',
            'icon' => 'fa-brands fa-react',
            'color' => 'cyan',
            'enabled' => $canNpm || $canRunBuild,
        ],
        [
            'title' => 'Database Access',
            'description' => 'Assigned MySQL and PostgreSQL connection information.',
            'icon' => 'fa-solid fa-database',
            'color' => 'purple',
            'enabled' => $canMysql || $canPostgresql,
        ],
    ];
@endphp

<div class="p-5 lg:p-8 space-y-6">

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

    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-red-950 text-white shadow-xl">
        <div class="absolute inset-0 opacity-20"
             style="background-image: linear-gradient(rgba(255,255,255,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.12) 1px, transparent 1px); background-size: 34px 34px;">
        </div>

        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-blue-500/30 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-red-500/20 blur-3xl"></div>

        <div class="relative p-7 lg:p-10 grid grid-cols-1 xl:grid-cols-[1fr_330px] gap-8 items-center">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-black">
                        <i class="fa-solid fa-code"></i>
                        Web VS Code Ready
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-black">
                        <i class="fa-solid fa-shield-halved"></i>
                        Secure Developer Portal
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-black">
                        <i class="fa-solid fa-layer-group"></i>
                        {{ strtoupper($framework) }}
                    </span>
                </div>

                <h1 class="mt-5 text-4xl lg:text-6xl font-black tracking-tight">
                    {{ $pageTitle }}
                </h1>

                <p class="mt-4 text-slate-300 max-w-4xl text-lg">
                    {{ $pageDescription }}
                </p>

                <div class="mt-6 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-black">
                        Domain: {{ $domain }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-black">
                        Branch: {{ $branch }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-black">
                        Environment: {{ $environment }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-black">
                        PHP: {{ $phpVersion }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-black">
                        Laravel: {{ $laravelVersion }}
                    </span>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="space-y-3">
                @if($canViewFiles)
                    <a href="{{ $codeEditorUrl }}"
                       target="_blank"
                       class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black transition shadow-lg shadow-blue-950/20">
                        <i class="fa-solid fa-code"></i>
                        Open Web VS Code
                    </a>
                @else
                    <button type="button"
                            disabled
                            class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-slate-500 text-white font-black opacity-60 cursor-not-allowed">
                        <i class="fa-solid fa-lock"></i>
                        Web VS Code Disabled
                    </button>
                @endif

                @if($canGitPull && Route::has('developer.domain.git.pull'))
                    <form method="POST" action="{{ route('developer.domain.git.pull') }}">
                        @csrf
                        <button class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black transition">
                            <i class="fa-solid fa-code-branch"></i>
                            Git Pull
                        </button>
                    </form>
                @endif

                @if($canClearCache && Route::has('developer.domain.clear.cache'))
                    <form method="POST" action="{{ route('developer.domain.clear.cache') }}">
                        @csrf
                        <button class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-cyan-600 hover:bg-cyan-700 text-white font-black transition">
                            <i class="fa-solid fa-broom"></i>
                            Clear Cache
                        </button>
                    </form>
                @endif

                @if($canComposer && Route::has('developer.domain.composer.dump'))
                    <form method="POST" action="{{ route('developer.domain.composer.dump') }}">
                        @csrf
                        <button class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black transition">
                            <i class="fa-solid fa-box"></i>
                            Composer Dump
                        </button>
                    </form>
                @endif

                @if(($canNpm || $canRunBuild) && Route::has('developer.domain.npm.build'))
                    <form method="POST" action="{{ route('developer.domain.npm.build') }}">
                        @csrf
                        <button class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-orange-500 hover:bg-orange-600 text-white font-black transition">
                            <i class="fa-brands fa-node-js"></i>
                            NPM Build
                        </button>
                    </form>
                @endif

                @if(Route::has('developer.logout'))
                    <form method="POST" action="{{ route('developer.logout') }}">
                        @csrf
                        <button class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black transition">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Developer Logout
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    {{-- Main Stats --}}
    <section id="project-section" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-slate-500 font-bold">Project Path</p>
                    <h3 class="mt-2 text-sm font-black text-slate-900 break-all">{{ $projectRoot }}</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-folder-tree text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-slate-500 font-bold">Public Path</p>
                    <h3 class="mt-2 text-sm font-black text-slate-900 break-all">{{ $publicPath }}</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-globe text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Git Branch</p>
                    <h3 class="mt-2 text-2xl font-black text-blue-600">{{ $branch }}</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-code-branch text-xl"></i>
                </div>
            </div>
        </div>

        <div id="vscode-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">File Access</p>
                    <h3 class="mt-2 text-2xl font-black {{ $canViewFiles ? 'text-green-600' : 'text-red-600' }}">
                        {{ $canViewFiles ? 'Allowed' : 'Blocked' }}
                    </h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-cyan-100 text-cyan-700 flex items-center justify-center">
                    <i class="fa-solid fa-code text-xl"></i>
                </div>
            </div>
        </div>
    </section>

    {{-- Web VS Code --}}
    <section class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Web Visual Studio Code</h2>
                <p class="text-slate-500 mt-1">
                    Developer opens the secure public route. Backend editor URL is loaded from this developer account.
                </p>
            </div>

            @if($canViewFiles)
                <a href="{{ $codeEditorUrl }}"
                   target="_blank"
                   class="px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    <i class="fa-solid fa-up-right-from-square mr-2"></i>
                    Open Editor
                </a>
            @else
                <button type="button"
                        disabled
                        class="px-6 py-3 rounded-2xl bg-slate-400 text-white font-black cursor-not-allowed opacity-70">
                    <i class="fa-solid fa-lock mr-2"></i>
                    Editor Disabled
                </button>
            @endif
        </div>

        <div class="p-6 grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="rounded-2xl bg-blue-50 border border-blue-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-code"></i>
                </div>
                <h3 class="font-black text-slate-900">Public Editor Route</h3>
                <p class="text-sm text-slate-500 mt-2 break-all">
                    {{ $codeEditorUrl }}
                </p>
            </div>

            <div class="rounded-2xl bg-green-50 border border-green-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-server"></i>
                </div>
                <h3 class="font-black text-slate-900">Backend Editor URL</h3>
                <p class="text-sm text-slate-500 mt-2 break-all">
                    {{ $editorBackendUrl ?: 'Not configured' }}
                </p>
            </div>

            <div class="rounded-2xl bg-purple-50 border border-purple-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="font-black text-slate-900">Protected Access</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Code editor route is protected by developer login and file-access permission.
                </p>
            </div>
        </div>
    </section>

    {{-- Tool Cards --}}
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($toolCards as $tool)
            <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-{{ $tool['color'] }}-100 text-{{ $tool['color'] }}-700 flex items-center justify-center mb-4">
                            <i class="{{ $tool['icon'] }} text-xl"></i>
                        </div>

                        <h3 class="text-xl font-black text-slate-900">{{ $tool['title'] }}</h3>
                        <p class="text-sm text-slate-500 mt-2">{{ $tool['description'] }}</p>
                    </div>

                    <span class="px-3 py-1 rounded-full text-xs font-black {{ $tool['enabled'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $tool['enabled'] ? 'ON' : 'OFF' }}
                    </span>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Account + Permissions --}}
    <section id="commands-section" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div id="account-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-2xl font-black text-slate-900">Developer Account</h2>

            <div class="mt-5 space-y-4 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-500">Name</span>
                    <span class="font-black text-slate-900 text-right">{{ $developerName }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-500">Email</span>
                    <span class="font-black text-slate-900 text-right break-all">{{ $developerEmail }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-500">Role</span>
                    <span class="font-black text-blue-600 text-right">{{ $developerRole }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-500">SSH User</span>
                    <span class="font-black text-slate-900 text-right">{{ $developerUsername }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-500">Framework</span>
                    <span class="font-black text-purple-600 text-right">{{ strtoupper($framework) }}</span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-500">Last Login</span>
                    <span class="font-black text-slate-900 text-right">
                        {{ !empty($developer->last_login_at) ? \Carbon\Carbon::parse($developer->last_login_at)->diffForHumans() : 'Never' }}
                    </span>
                </div>
            </div>
        </div>

        <div id="permissions-section" class="xl:col-span-2 bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-2xl font-black text-slate-900">Developer Permissions</h2>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach($permissions as $permission)
                    <div class="rounded-2xl border p-4 {{ $permission['allowed'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-black {{ $permission['allowed'] ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $permission['label'] }}
                                </h3>
                                <p class="text-xs font-bold mt-1 {{ $permission['allowed'] ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $permission['allowed'] ? 'Allowed' : 'Disabled' }}
                                </p>
                            </div>

                            <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $permission['allowed'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                <i class="{{ $permission['icon'] }}"></i>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Git + Database --}}
    <section id="git-section" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-2xl font-black text-slate-900">Git & Project Commands</h2>

            <div class="mt-5 space-y-3">
                @if($canGitPull && Route::has('developer.domain.git.pull'))
                    <form method="POST" action="{{ route('developer.domain.git.pull') }}">
                        @csrf
                        <button class="w-full px-5 py-4 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black">
                            <i class="fa-solid fa-code-branch mr-2"></i>
                            Run Git Pull
                        </button>
                    </form>
                @else
                    <div class="rounded-2xl bg-red-50 border border-red-200 p-5 text-red-700 font-black">
                        Git Pull permission is disabled.
                    </div>
                @endif

                @if($canClearCache && Route::has('developer.domain.clear.cache'))
                    <form method="POST" action="{{ route('developer.domain.clear.cache') }}">
                        @csrf
                        <button class="w-full px-5 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                            <i class="fa-solid fa-broom mr-2"></i>
                            Clear Project Cache
                        </button>
                    </form>
                @endif

                @if($canComposer && Route::has('developer.domain.composer.dump'))
                    <form method="POST" action="{{ route('developer.domain.composer.dump') }}">
                        @csrf
                        <button class="w-full px-5 py-4 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black">
                            <i class="fa-solid fa-box mr-2"></i>
                            Composer Dump
                        </button>
                    </form>
                @endif

                @if(($canNpm || $canRunBuild) && Route::has('developer.domain.npm.build'))
                    <form method="POST" action="{{ route('developer.domain.npm.build') }}">
                        @csrf
                        <button class="w-full px-5 py-4 rounded-2xl bg-orange-500 hover:bg-orange-600 text-white font-black">
                            <i class="fa-brands fa-node-js mr-2"></i>
                            NPM Build
                        </button>
                    </form>
                @endif

                @if(session('command_output'))
                    <div class="rounded-2xl bg-slate-950 text-slate-100 p-5 mt-4 overflow-x-auto">
                        <div class="font-black text-green-400 mb-3">Command Output</div>
                        <pre class="text-xs whitespace-pre-wrap">{{ session('command_output') }}</pre>
                    </div>
                @endif
            </div>
        </div>

        <div id="database-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-2xl font-black text-slate-900">Database Access</h2>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-2xl border p-5 {{ $canMysql ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl {{ $canMysql ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} flex items-center justify-center">
                            <i class="fa-solid fa-database"></i>
                        </div>
                        <div>
                            <h3 class="font-black {{ $canMysql ? 'text-green-700' : 'text-red-700' }}">MySQL</h3>
                            <p class="text-xs font-bold {{ $canMysql ? 'text-green-600' : 'text-red-600' }}">
                                {{ $canMysql ? 'Allowed' : 'Disabled' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border p-5 {{ $canPostgresql ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl {{ $canPostgresql ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} flex items-center justify-center">
                            <i class="fa-solid fa-server"></i>
                        </div>
                        <div>
                            <h3 class="font-black {{ $canPostgresql ? 'text-green-700' : 'text-red-700' }}">PostgreSQL</h3>
                            <p class="text-xs font-bold {{ $canPostgresql ? 'text-green-600' : 'text-red-600' }}">
                                {{ $canPostgresql ? 'Allowed' : 'Disabled' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 rounded-2xl bg-slate-50 border border-slate-200 p-5 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-500">DB Type</span>
                    <span class="font-black text-slate-900">{{ $developer->db_type ?? '-' }}</span>
                </div>

                <div class="flex justify-between gap-4 mt-3">
                    <span class="font-bold text-slate-500">DB Host</span>
                    <span class="font-black text-slate-900">{{ $developer->db_host ?? '-' }}</span>
                </div>

                <div class="flex justify-between gap-4 mt-3">
                    <span class="font-bold text-slate-500">DB User</span>
                    <span class="font-black text-slate-900">{{ $developer->db_username ?? '-' }}</span>
                </div>

                <div class="flex justify-between gap-4 mt-3">
                    <span class="font-bold text-slate-500">DB Name</span>
                    <span class="font-black text-slate-900">{{ $developer->db_name ?? '-' }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Extra Developer Tool Sections --}}
    <section id="env-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-2xl font-black text-slate-900">ENV Manager</h2>
        <p class="text-slate-500 mt-1">Safe environment configuration notes and example file tools.</p>

        <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-2xl bg-yellow-50 border border-yellow-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-yellow-100 text-yellow-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-file-code"></i>
                </div>
                <h3 class="font-black text-slate-900">.env Example</h3>
                <p class="text-sm text-slate-500 mt-2">Download or review safe environment examples.</p>

                @if(Route::has('developer.domain.env.example'))
                    <a href="{{ route('developer.domain.env.example') }}"
                       class="inline-flex mt-4 px-4 py-2 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-white font-black text-sm">
                        Download Example
                    </a>
                @endif
            </div>

            <div class="rounded-2xl bg-blue-50 border border-blue-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-key"></i>
                </div>
                <h3 class="font-black text-slate-900">Secret Protection</h3>
                <p class="text-sm text-slate-500 mt-2">Never expose database passwords, API keys or app secrets.</p>
            </div>

            <div class="rounded-2xl bg-green-50 border border-green-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <h3 class="font-black text-slate-900">Configuration Check</h3>
                <p class="text-sm text-slate-500 mt-2">Check framework environment requirements before deployment.</p>
            </div>
        </div>
    </section>

    <section id="logs-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-2xl font-black text-slate-900">Error Logs</h2>
        <p class="text-slate-500 mt-1">Developer-friendly log access area for safe debugging.</p>

        <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-2xl bg-red-50 border border-red-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-bug"></i>
                </div>
                <h3 class="font-black text-red-700">Laravel Logs</h3>
                <p class="text-sm text-red-600 mt-2">View app errors and exception traces safely.</p>
            </div>

            <div class="rounded-2xl bg-orange-50 border border-orange-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-server"></i>
                </div>
                <h3 class="font-black text-orange-700">Server Logs</h3>
                <p class="text-sm text-orange-600 mt-2">Monitor server-level errors and runtime warnings.</p>
            </div>

            <div class="rounded-2xl bg-blue-50 border border-blue-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <h3 class="font-black text-blue-700">Access Logs</h3>
                <p class="text-sm text-blue-600 mt-2">Review safe request logs and route activity.</p>
            </div>
        </div>
    </section>

    <section id="terminal-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-2xl font-black text-slate-900">Safe Terminal</h2>
        <p class="text-slate-500 mt-1">Only approved commands are available for developers.</p>

        <div class="mt-5 rounded-2xl bg-slate-950 text-slate-200 p-5 font-mono text-sm overflow-x-auto">
            <div class="text-green-400">developer@workspace:~$</div>
            <div class="mt-2">Allowed commands: git pull, composer dump-autoload, npm build, cache clear</div>
            <div class="mt-2 text-slate-500">Direct unrestricted shell access should stay disabled.</div>
        </div>
    </section>

    <section id="health-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-2xl font-black text-slate-900">Health Check</h2>

        <div class="mt-5 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-green-50 border border-green-200 p-5">
                <h3 class="font-black text-green-700">Website</h3>
                <p class="text-sm text-green-600 mt-2">Check public site status.</p>
            </div>

            <div class="rounded-2xl bg-blue-50 border border-blue-200 p-5">
                <h3 class="font-black text-blue-700">SSL</h3>
                <p class="text-sm text-blue-600 mt-2">Check HTTPS certificate.</p>
            </div>

            <div class="rounded-2xl bg-purple-50 border border-purple-200 p-5">
                <h3 class="font-black text-purple-700">Disk</h3>
                <p class="text-sm text-purple-600 mt-2">Monitor storage usage.</p>
            </div>

            <div class="rounded-2xl bg-orange-50 border border-orange-200 p-5">
                <h3 class="font-black text-orange-700">Performance</h3>
                <p class="text-sm text-orange-600 mt-2">Check load and response time.</p>
            </div>
        </div>
    </section>

</div>

@endsection