@extends('layouts.app')

@section('page-title', 'Developer Workspace')

@section('content')

@php
    $servers = $servers ?? collect();
    $workspace = $workspace ?? [];
    $quickCommands = $quickCommands ?? [];
    $safeFolders = $safeFolders ?? [];
    $developer = $developer ?? auth('developer')->user();

    $projectPath = $workspace['project_path'] ?? base_path();
    $publicPath = $workspace['public_path'] ?? public_path();
    $gitBranch = $workspace['git_branch'] ?? 'unknown';
    $gitStatus = $workspace['git_status'] ?? '';
    $lastCommit = $workspace['last_commit'] ?? 'N/A';
    $developerCodesUrl = $workspace['developer_codes_url'] ?? env('DEVELOPER_CODES_URL', 'https://developercodes.webscepts.com');

    $hostName = request()->getHost();
    $isDeveloperDomain = $hostName === 'developercodes.webscepts.com';

    $routeFor = function ($normal, $domain) use ($isDeveloperDomain) {
        if ($isDeveloperDomain && Route::has($domain)) {
            return route($domain);
        }

        if (Route::has($normal)) {
            return route($normal);
        }

        return '#';
    };

    $openFolderRoute = $routeFor('developers.open.folder', 'developer.domain.open.folder');
    $envExampleRoute = $isDeveloperDomain && Route::has('developer.domain.env.example')
        ? route('developer.domain.env.example')
        : (Route::has('developers.env.example') ? route('developers.env.example') : '#');

    $logoutRoute = Route::has('developer.logout') ? route('developer.logout') : '#';

    $colorMap = [
        'blue' => 'bg-blue-600 hover:bg-blue-700',
        'green' => 'bg-green-600 hover:bg-green-700',
        'purple' => 'bg-purple-600 hover:bg-purple-700',
        'orange' => 'bg-orange-500 hover:bg-orange-600',
        'red' => 'bg-red-600 hover:bg-red-700',
        'slate' => 'bg-slate-900 hover:bg-slate-700',
    ];

    $permissionBadge = function ($enabled) {
        return $enabled
            ? 'bg-green-100 text-green-700 border-green-200'
            : 'bg-red-100 text-red-700 border-red-200';
    };

    $folderItems = collect(session('folder_items', []));
    $folderPath = session('folder_path');
@endphp

<div class="space-y-6">

    {{-- FLASH ALERTS --}}
    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-bold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-bold">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif

    @if(session('command_output'))
        <div class="rounded-3xl bg-white shadow border border-slate-100 overflow-hidden">
            <div class="p-5 border-b bg-slate-50 flex items-center justify-between gap-4">
                <div>
                    <h3 class="font-black text-slate-900">Command Output</h3>
                    <p class="text-sm text-slate-500">Latest executed developer command result.</p>
                </div>

                <button type="button"
                        onclick="copyText('commandOutputBox')"
                        class="px-4 py-2 rounded-xl bg-slate-900 text-white font-black text-sm">
                    Copy Output
                </button>
            </div>

            <pre id="commandOutputBox" class="bg-slate-950 text-green-400 p-5 text-xs overflow-auto whitespace-pre-wrap max-h-[520px]">{{ session('command_output') }}</pre>
        </div>
    @endif

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-red-950 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-7">
            <div class="min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-3xl lg:text-5xl font-black tracking-tight">
                        Developer Codes Workspace
                    </h1>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        <i class="fa-solid fa-code"></i>
                        VS Code Remote SSH
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold">
                        <i class="fa-solid fa-shield-virus"></i>
                        Webscepts SentinelCore
                    </span>

                    @if($developer)
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                            <i class="fa-solid fa-user-shield"></i>
                            {{ $developer->name }}
                        </span>
                    @endif
                </div>

                <p class="text-slate-300 mt-3 max-w-5xl">
                    Secure developer portal for editing with Visual Studio Code Remote SSH, running approved Laravel commands,
                    viewing safe folders, checking Git status, and managing project deployment without cPanel File Manager.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Domain: {{ $hostName }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Branch: {{ $gitBranch ?: 'unknown' }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Environment: {{ $workspace['app_env'] ?? app()->environment() }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        PHP: {{ $workspace['php_version'] ?? PHP_VERSION }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Laravel: {{ $workspace['laravel_version'] ?? app()->version() }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row xl:flex-col gap-3 shrink-0">
                @foreach($quickCommands as $command)
                    @php
                        $buttonColor = $colorMap[$command['color'] ?? 'slate'] ?? $colorMap['slate'];
                        $route = $command['route'] ?? '#';
                    @endphp

                    <form method="POST"
                          action="{{ $route }}"
                          onsubmit="return confirm('Run {{ $command['title'] ?? 'command' }}?')">
                        @csrf
                        <button class="w-full px-6 py-4 rounded-2xl {{ $buttonColor }} text-white font-black text-center">
                            <i class="fa-solid {{ $command['icon'] ?? 'fa-terminal' }} mr-2"></i>
                            {{ $command['title'] ?? 'Run Command' }}
                        </button>
                    </form>
                @endforeach

                @if($isDeveloperDomain && Route::has('developer.logout'))
                    <form method="POST" action="{{ $logoutRoute }}">
                        @csrf
                        <button class="w-full px-6 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                            <i class="fa-solid fa-right-from-bracket mr-2"></i>
                            Developer Logout
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- PROJECT INFO --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Project Path</p>
                    <h2 class="text-sm font-black mt-2 break-all">{{ $projectPath }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-folder-tree text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Public Path</p>
                    <h2 class="text-sm font-black mt-2 break-all">{{ $publicPath }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-globe text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">Git Branch</p>
                    <h2 class="text-2xl font-black mt-2 text-blue-600">{{ $gitBranch ?: 'unknown' }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-code-branch text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-slate-500 font-bold">File Access</p>
                    <h2 class="text-2xl font-black mt-2 text-green-600">VS Code</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-cyan-100 text-cyan-700 flex items-center justify-center">
                    <i class="fa-solid fa-code text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- DEVELOPER ACCOUNT + PERMISSIONS --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-2xl font-black text-slate-900 mb-4">
                Developer Account
            </h2>

            @if($developer)
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 font-bold">Name</span>
                        <span class="font-black text-slate-900">{{ $developer->name }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 font-bold">Email</span>
                        <span class="font-black text-slate-900 break-all">{{ $developer->email }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 font-bold">Role</span>
                        <span class="font-black text-blue-600">{{ $developer->role ?? 'developer' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 font-bold">SSH User</span>
                        <span class="font-black text-slate-900">{{ $developer->ssh_username ?? 'Not set' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 font-bold">Last Login</span>
                        <span class="font-black text-slate-900">
                            {{ $developer->last_login_at ? $developer->last_login_at->diffForHumans() : 'First login' }}
                        </span>
                    </div>
                </div>
            @else
                <div class="rounded-2xl bg-yellow-50 border border-yellow-200 p-5 text-yellow-700 font-bold">
                    Admin workspace mode. No developer guard user detected.
                </div>
            @endif
        </div>

        <div class="xl:col-span-2 bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-2xl font-black text-slate-900 mb-4">
                Developer Permissions
            </h2>

            @php
                $permissions = [
                    'Git Pull' => $developer?->can_git_pull ?? true,
                    'Clear Cache' => $developer?->can_clear_cache ?? true,
                    'Composer' => $developer?->can_composer ?? true,
                    'NPM Build' => $developer?->can_npm ?? true,
                    'View Files' => $developer?->can_view_files ?? true,
                    'Edit Files' => $developer?->can_edit_files ?? false,
                    'Delete Files' => $developer?->can_delete_files ?? false,
                    'Active Account' => $developer?->is_active ?? true,
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach($permissions as $label => $enabled)
                    <div class="rounded-2xl border {{ $permissionBadge($enabled) }} p-4">
                        <p class="font-black">{{ $label }}</p>
                        <p class="text-xs mt-1 font-bold">
                            {{ $enabled ? 'Allowed' : 'Disabled' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- VISUAL STUDIO CODE REMOTE SSH --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">
                    Visual Studio Code Remote SSH
                </h2>
                <p class="text-slate-500 mt-1">
                    Developers can open this Laravel project directly in VS Code without using cPanel editor.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button"
                        onclick="copyText('vscodeCommands')"
                        class="px-4 py-2 rounded-xl bg-slate-900 text-white font-black text-sm">
                    Copy Setup
                </button>

                <a href="https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-ssh"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm text-center">
                    Remote SSH Extension
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-0">
            <div class="p-6 border-b xl:border-b-0 xl:border-r border-slate-100">
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-download text-xl"></i>
                </div>
                <h3 class="font-black text-slate-900 text-lg">1. Install Extension</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Install the official Microsoft Remote SSH extension in Visual Studio Code.
                </p>
            </div>

            <div class="p-6 border-b xl:border-b-0 xl:border-r border-slate-100">
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-terminal text-xl"></i>
                </div>
                <h3 class="font-black text-slate-900 text-lg">2. Connect SSH</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Use the SSH command from the server list below and connect through VS Code.
                </p>
            </div>

            <div class="p-6">
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-folder-open text-xl"></i>
                </div>
                <h3 class="font-black text-slate-900 text-lg">3. Open Folder</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Open <code class="bg-slate-100 px-2 py-1 rounded">{{ $projectPath }}</code> inside VS Code.
                </p>
            </div>
        </div>

        <pre id="vscodeCommands" class="bg-slate-950 text-green-400 p-6 text-xs overflow-auto whitespace-pre-wrap"># Install VS Code extension:
Remote - SSH

# Connect from developer computer:
ssh developer1@YOUR_SERVER_IP

# Open this Laravel project folder in VS Code:
{{ $projectPath }}

# Useful Laravel commands:
cd {{ $projectPath }}
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
composer dump-autoload

# Developer portal:
{{ $developerCodesUrl }}</pre>
    </div>

    {{-- SERVERS SSH ACCESS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Server VS Code Access</h2>
                <p class="text-slate-500 mt-1">
                    Copy SSH commands or open the VS Code remote link.
                </p>
            </div>

            <input type="text"
                   id="serverSearch"
                   oninput="filterRows('serverSearch', '.server-row')"
                   placeholder="Search servers..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Host</th>
                        <th class="p-4 text-left">SSH Port</th>
                        <th class="p-4 text-left">Username</th>
                        <th class="p-4 text-left">SSH Command</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($servers as $server)
                        @php
                            $sshUser = $developer?->ssh_username ?: ($server->developer_username ?? $server->username ?? 'developer1');
                            $sshPort = $server->ssh_port ?? 22;
                            $sshHost = $server->host ?? '';
                            $sshCommand = 'ssh -p '.$sshPort.' '.$sshUser.'@'.$sshHost;
                            $remoteAuthority = $sshUser . '@' . $sshHost;
                            $vscodeLink = 'vscode://vscode-remote/ssh-remote+' . rawurlencode($remoteAuthority) . rawurlencode($projectPath);
                        @endphp

                        <tr class="server-row border-t hover:bg-slate-50">
                            <td class="p-4">
                                <div class="font-black text-slate-900">{{ $server->name }}</div>
                                <div class="text-xs text-slate-500">{{ $server->status ?? 'unknown' }}</div>
                            </td>

                            <td class="p-4 font-bold">{{ $sshHost }}</td>
                            <td class="p-4">{{ $sshPort }}</td>
                            <td class="p-4">{{ $sshUser }}</td>

                            <td class="p-4">
                                <code id="sshCommand{{ $server->id }}" class="bg-slate-100 px-3 py-2 rounded-xl text-xs block break-all">
                                    {{ $sshCommand }}
                                </code>
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="button"
                                            onclick="copyText('sshCommand{{ $server->id }}')"
                                            class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs">
                                        Copy SSH
                                    </button>

                                    <a href="{{ $vscodeLink }}"
                                       class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-black text-xs">
                                        Open VS Code
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-10 text-center text-slate-500">
                                No servers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- SAFE FOLDERS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-2xl font-black text-slate-900">Safe Project Folders</h2>
            <p class="text-slate-500 mt-1">
                View approved project folders. Developers should edit through VS Code Remote SSH.
            </p>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($safeFolders as $folder)
                <form method="POST" action="{{ $openFolderRoute }}">
                    @csrf
                    <input type="hidden" name="folder" value="{{ $folder['path'] }}">

                    <button class="w-full text-left rounded-3xl border border-slate-100 p-5 hover:shadow-lg hover:bg-slate-50 transition">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                                <i class="fa-solid {{ $folder['icon'] ?? 'fa-folder' }}"></i>
                            </div>

                            <div class="min-w-0">
                                <h3 class="font-black text-slate-900">{{ $folder['label'] }}</h3>
                                <p class="text-xs text-slate-500 mt-1 break-all">{{ $folder['path'] }}</p>
                            </div>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    {{-- LOADED FOLDER ITEMS --}}
    @if($folderPath)
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-900">Folder Items</h2>
                    <p class="text-slate-500 mt-1">
                        {{ $folderPath }}
                    </p>
                </div>

                <button type="button"
                        onclick="copyText('folderPathBox')"
                        class="px-4 py-2 rounded-xl bg-slate-900 text-white font-black text-sm">
                    Copy Path
                </button>
            </div>

            <div id="folderPathBox" class="hidden">{{ $projectPath }}/{{ $folderPath }}</div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="p-4 text-left">Type</th>
                            <th class="p-4 text-left">Name</th>
                            <th class="p-4 text-left">Path</th>
                            <th class="p-4 text-left">Size</th>
                            <th class="p-4 text-left">Modified</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($folderItems as $item)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="p-4">
                                    @if(($item['type'] ?? '') === 'folder')
                                        <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-black">Folder</span>
                                    @else
                                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-black">File</span>
                                    @endif
                                </td>

                                <td class="p-4 font-black text-slate-900">{{ $item['name'] ?? '-' }}</td>
                                <td class="p-4 text-slate-600 break-all">{{ $item['path'] ?? '-' }}</td>
                                <td class="p-4 text-slate-600">
                                    {{ !empty($item['size']) ? number_format(($item['size'] / 1024), 2) . ' KB' : '-' }}
                                </td>
                                <td class="p-4 text-slate-600">{{ $item['modified'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-10 text-center text-slate-500">
                                    Folder is empty.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- GIT STATUS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Git Status</h2>
                <p class="text-slate-500 mt-1">
                    Latest local project changes.
                </p>
            </div>

            <button type="button"
                    onclick="copyText('gitStatusBox')"
                    class="px-4 py-2 rounded-xl bg-slate-900 text-white font-black text-sm">
                Copy Git Status
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-0">
            <div class="p-6 border-b xl:border-b-0 xl:border-r border-slate-100">
                <p class="text-slate-500 font-bold">Current Branch</p>
                <h3 class="text-2xl font-black text-blue-600 mt-2">{{ $gitBranch ?: 'unknown' }}</h3>
            </div>

            <div class="p-6 border-b xl:border-b-0 xl:border-r border-slate-100">
                <p class="text-slate-500 font-bold">Last Commit</p>
                <h3 class="text-sm font-black text-slate-900 mt-2">{{ $lastCommit }}</h3>
            </div>

            <div class="p-6">
                <p class="text-slate-500 font-bold">Composer Status</p>
                <h3 class="text-sm font-black text-slate-900 mt-2">{{ $workspace['composer_status'] ?? 'Unknown' }}</h3>
            </div>
        </div>

        <pre id="gitStatusBox" class="bg-slate-950 text-green-400 p-6 text-xs overflow-auto whitespace-pre-wrap max-h-[460px]">{{ $gitStatus ?: 'Working tree clean or git status unavailable.' }}</pre>
    </div>

    {{-- SYSTEM HEALTH --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Storage Writable</p>
            <h2 class="text-2xl font-black mt-2 {{ !empty($workspace['storage_writable']) ? 'text-green-600' : 'text-red-600' }}">
                {{ !empty($workspace['storage_writable']) ? 'Yes' : 'No' }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Cache Writable</p>
            <h2 class="text-2xl font-black mt-2 {{ !empty($workspace['cache_writable']) ? 'text-green-600' : 'text-red-600' }}">
                {{ !empty($workspace['cache_writable']) ? 'Yes' : 'No' }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">PHP-FPM Socket</p>
            <h2 class="text-sm font-black mt-2 break-all">{{ $workspace['php_fpm_socket'] ?? 'Unknown' }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">ENV Status</p>
            <h2 class="text-2xl font-black mt-2 text-green-600">{{ $workspace['env_status'] ?? '.env protected' }}</h2>
        </div>
    </div>

    {{-- SECURITY RULES --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Developer Security Rules</h2>
                <p class="text-slate-500 mt-1">
                    Keep production safe while allowing developers to work faster.
                </p>
            </div>

            <a href="{{ $envExampleRoute }}"
               class="px-4 py-2 rounded-xl bg-slate-900 text-white font-black text-sm text-center">
                Download ENV Example
            </a>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    No cPanel Editor
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Developers should use VS Code Remote SSH or Git. Avoid editing production files through cPanel File Manager.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Separate Developer Accounts
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Every developer must use their own login and SSH key. Do not share server passwords.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Protect Sensitive Files
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Never expose .env, SQL dumps, backups, private keys, composer files, or customer files in public directories.
                </p>
            </div>
        </div>
    </div>

</div>

<script>
function copyText(id) {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    const text = el.innerText || el.value || el.textContent || '';

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
</script>

@endsection