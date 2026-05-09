@extends('layouts.developer')

@section('title', 'Developer Workspace')

@section('developer-content')

@php
    use Illuminate\Support\Facades\Route;

    $developer = auth()->guard('developer')->user();

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

    $projectRoot = $developer->project_root
        ?? $developer->allowed_project_path
        ?? '/home/project/public_html';

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
    | Code Editor URL
    |--------------------------------------------------------------------------
    | This opens:
    | https://developercodes.webscepts.com/codeditor
    |
    | If route is not cached/added yet, fallback keeps page from breaking.
    |--------------------------------------------------------------------------
    */
    $codeEditorUrl = Route::has('developer.domain.codeditor')
        ? route('developer.domain.codeditor')
        : url('/codeditor');

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
            'icon' => 'fa-solid fa-cube',
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
                    Developer Codes Workspace
                </h1>

                <p class="mt-4 text-slate-300 max-w-4xl text-lg">
                    Secure developer portal for project access, browser VS Code, approved commands,
                    Git tools, database access, and safe workspace permissions.
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
                <a href="{{ $codeEditorUrl }}"
                   target="_blank"
                   class="w-full flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black transition shadow-lg shadow-blue-950/20">
                    <i class="fa-solid fa-code"></i>
                    Open Web VS Code
                </a>

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
                            <i class="fa-solid fa-cube"></i>
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

    {{-- Stats --}}
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
                    Open your browser code editor through Developer Codes.
                </p>
            </div>

            <a href="{{ $codeEditorUrl }}"
               target="_blank"
               class="px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                <i class="fa-solid fa-up-right-from-square mr-2"></i>
                Open Editor
            </a>
        </div>

        <div class="p-6 grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-code"></i>
                </div>
                <h3 class="font-black text-slate-900">Browser VS Code</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Editor opens using:
                    <span class="font-black text-slate-700">https://developercodes.webscepts.com/codeditor</span>
                </p>
            </div>

            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-folder-lock"></i>
                </div>
                <h3 class="font-black text-slate-900">Restricted Path</h3>
                <p class="text-sm text-slate-500 mt-2 break-all">
                    Workspace should open only: {{ $projectRoot }}
                </p>
            </div>

            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5">
                <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="font-black text-slate-900">Secure Access</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Code editor route is protected by developer login.
                </p>
            </div>
        </div>
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
                    <span class="font-black text-slate-900 text-right">{{ $developerEmail }}</span>
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
    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div id="git-section" class="bg-white rounded-3xl shadow border border-slate-100 p-6">
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
                            <i class="fa-solid fa-cube mr-2"></i>
                            NPM Build
                        </button>
                    </form>
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

</div>

@endsection