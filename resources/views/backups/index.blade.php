@extends('layouts.app')

@section('page-title', 'Backup Manager')

@section('content')

@php
    $servers = $servers ?? collect();
    $serverAccounts = $serverAccounts ?? [];

    $totalServers = $servers->count();
    $googleEnabled = $servers->where('google_drive_sync', true)->count();
    $autoTransferEnabled = $servers->where('auto_transfer', true)->count();
    $failoverEnabled = $servers->where('failover_enabled', true)->count();
@endphp

<div class="space-y-6">

    {{-- SESSION ALERTS --}}
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

    @if($errors->any())
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4">
            <div class="font-black mb-2">Please fix these errors:</div>
            <ul class="list-disc ml-5 text-sm font-semibold">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <h1 class="text-3xl lg:text-4xl font-black">
                    Enterprise Backup Manager
                </h1>

                <p class="text-slate-300 mt-2 max-w-3xl">
                    Pull backups from cPanel servers, transfer selected accounts, sync to Google Drive, and trigger ClouDNS failover when disk usage reaches the limit.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Servers: {{ $totalServers }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Google Drive: {{ $googleEnabled }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-orange-500/20 border border-orange-400/40 text-orange-100 text-xs font-bold">
                        Auto Transfer: {{ $autoTransferEnabled }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                        DNS Failover: {{ $failoverEnabled }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                @if(Route::has('backups.auto'))
                    <a href="{{ route('backups.auto') }}"
                       class="px-5 py-3 rounded-2xl bg-orange-600 hover:bg-orange-700 text-white font-black">
                        <i class="fa-solid fa-hard-drive mr-2"></i>
                        Run Auto Disk Backup
                    </a>
                @endif

                @if(Route::has('backups.google'))
                    <a href="{{ route('backups.google') }}"
                       class="px-5 py-3 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black">
                        <i class="fa-brands fa-google-drive mr-2"></i>
                        Run Google Sync
                    </a>
                @endif

                @if(Route::has('backups.logs'))
                    <a href="{{ route('backups.logs') }}"
                       class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/20 text-white font-black">
                        <i class="fa-solid fa-file-lines mr-2"></i>
                        Logs
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">Total Servers</p>
                    <h2 class="text-4xl font-black mt-2">{{ $totalServers }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-server text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">Google Drive Sync</p>
                    <h2 class="text-4xl font-black mt-2 text-green-600">{{ $googleEnabled }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">Auto Transfer</p>
                    <h2 class="text-4xl font-black mt-2 text-orange-600">{{ $autoTransferEnabled }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-orange-100 text-orange-700 flex items-center justify-center">
                    <i class="fa-solid fa-right-left text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 font-bold">DNS Failover</p>
                    <h2 class="text-4xl font-black mt-2 text-red-600">{{ $failoverEnabled }}</h2>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                    <i class="fa-solid fa-globe text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- GLOBAL SEARCH --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-900">Backup Servers</h2>
                <p class="text-slate-500 text-sm">
                    Configure backup paths, Google Drive, selected cPanel accounts and automatic DNS failover.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <input type="text"
                       id="backupSearch"
                       oninput="filterBackupCards()"
                       placeholder="Search server, host, domain, account..."
                       class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">

                <select id="backupFilter"
                        onchange="filterBackupCards()"
                        class="px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all">All</option>
                    <option value="google">Google Enabled</option>
                    <option value="auto">Auto Transfer</option>
                    <option value="failover">DNS Failover</option>
                    <option value="linked">Linked Domain</option>
                </select>
            </div>
        </div>
    </div>

    {{-- SERVER BACKUP CARDS --}}
    <div class="space-y-6">
        @forelse($servers as $server)
            @php
                $accountsForServer = $serverAccounts[$server->id] ?? [];
                $selectedAccounts = old('backup_selected_accounts', $server->backup_selected_accounts ?? []);

                if (!is_array($selectedAccounts)) {
                    $selectedAccounts = [];
                }

                $backupServerOptions = $servers->where('id', '!=', $server->id);

                $linkedDomain = $server->linked_domain ?? null;
                $googleOn = !empty($server->google_drive_sync);
                $autoOn = !empty($server->auto_transfer);
                $failoverOn = !empty($server->dns_failover_enabled);
                $selectedCount = count($selectedAccounts);
            @endphp

            <div class="backup-card bg-white rounded-3xl shadow border border-slate-100 overflow-hidden"
                 data-search="{{ strtolower($server->name.' '.$server->host.' '.$server->linked_domain.' '.collect($accountsForServer)->pluck('user')->implode(' ')) }}"
                 data-google="{{ $googleOn ? '1' : '0' }}"
                 data-auto="{{ $autoOn ? '1' : '0' }}"
                 data-failover="{{ $failoverOn ? '1' : '0' }}"
                 data-linked="{{ $linkedDomain ? '1' : '0' }}">

                {{-- CARD HEADER --}}
                <div class="p-6 border-b bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                        <div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
                                    <i class="fa-solid fa-server"></i>
                                </div>

                                <div>
                                    <h3 class="text-2xl font-black text-slate-900">
                                        {{ $server->name }}
                                    </h3>

                                    <p class="text-slate-500 font-semibold">
                                        {{ $server->username ?? 'root' }}@{{ $server->host }}:{{ $server->ssh_port ?? 22 }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="px-3 py-1 rounded-full text-xs font-black {{ strtolower($server->status ?? '') === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ ucfirst($server->status ?? 'offline') }}
                                </span>

                                <span class="px-3 py-1 rounded-full text-xs font-black {{ $googleOn ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                    Google Drive {{ $googleOn ? 'Enabled' : 'Disabled' }}
                                </span>

                                <span class="px-3 py-1 rounded-full text-xs font-black {{ $autoOn ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-600' }}">
                                    Auto Transfer {{ $autoOn ? 'Enabled' : 'Disabled' }}
                                </span>

                                <span class="px-3 py-1 rounded-full text-xs font-black {{ $failoverOn ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' }}">
                                    DNS Failover {{ $failoverOn ? 'Enabled' : 'Disabled' }}
                                </span>

                                <span class="px-3 py-1 rounded-full text-xs font-black {{ $linkedDomain ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                                    Domain: {{ $linkedDomain ?: 'Not linked' }}
                                </span>

                                <span class="px-3 py-1 rounded-full text-xs font-black bg-purple-100 text-purple-700">
                                    Selected Accounts: {{ $selectedCount }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 w-full xl:w-auto">
                            @if(Route::has('backups.pull'))
                                <form method="POST" action="{{ route('backups.pull') }}">
                                    @csrf
                                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                                    <button class="w-full px-4 py-3 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-black text-sm">
                                        <i class="fa-solid fa-download mr-1"></i>
                                        Pull
                                    </button>
                                </form>
                            @endif

                            @if(Route::has('backups.google.upload'))
                                <form method="POST" action="{{ route('backups.google.upload') }}">
                                    @csrf
                                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                                    <button class="w-full px-4 py-3 rounded-xl bg-green-600 hover:bg-green-700 text-white font-black text-sm">
                                        <i class="fa-solid fa-cloud-arrow-up mr-1"></i>
                                        Drive
                                    </button>
                                </form>
                            @endif

                            @if(Route::has('backups.fullSync'))
                                <form method="POST" action="{{ route('backups.fullSync') }}">
                                    @csrf
                                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                                    <button class="w-full px-4 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-black text-sm">
                                        <i class="fa-solid fa-arrows-rotate mr-1"></i>
                                        Sync
                                    </button>
                                </form>
                            @endif

                            @if(Route::has('backups.transfer'))
                                <form method="POST" action="{{ route('backups.transfer') }}">
                                    @csrf
                                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                                    <button class="w-full px-4 py-3 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-black text-sm">
                                        <i class="fa-solid fa-right-left mr-1"></i>
                                        Transfer
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- SETTINGS FORM --}}
                <form method="POST" action="{{ route('backups.settings') }}">
                    @csrf
                    <input type="hidden" name="server_id" value="{{ $server->id }}">

                    <div class="p-6 space-y-6">

                        {{-- PATH SETTINGS --}}
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-lg font-black text-slate-900">
                                        Backup Configuration
                                    </h4>
                                    <p class="text-sm text-slate-500">
                                        Remote paths, local paths, Google Drive remote and disk thresholds.
                                    </p>
                                </div>

                                <button type="button"
                                        onclick="toggleBox('config-{{ $server->id }}')"
                                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-sm">
                                    <i class="fa-solid fa-sliders mr-1"></i>
                                    Toggle
                                </button>
                            </div>

                            <div id="config-{{ $server->id }}" class="grid grid-cols-1 xl:grid-cols-2 gap-5">

                                <div>
                                    <label class="block text-sm font-black text-slate-700 mb-1">
                                        Remote Backup Path on cPanel Server
                                    </label>
                                    <input type="text"
                                           name="backup_path"
                                           value="{{ old('backup_path', $server->backup_path ?? '/backup') }}"
                                           class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-slate-400 mt-1">
                                        Example: /backup, /home, /var/cpanel/backups
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-black text-slate-700 mb-1">
                                        Local Backup Path on Monitor System
                                    </label>
                                    <input type="text"
                                           name="local_backup_path"
                                           value="{{ old('local_backup_path', $server->local_backup_path ?? '/backup') }}"
                                           class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-black text-slate-700 mb-1">
                                        Google Drive Remote Name
                                    </label>
                                    <input type="text"
                                           name="google_drive_remote"
                                           value="{{ old('google_drive_remote', $server->google_drive_remote) }}"
                                           placeholder="gdrive"
                                           class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-slate-400 mt-1">
                                        Your rclone remote name. Example: gdrive
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-black text-slate-700 mb-1">
                                        Daily Sync Time
                                    </label>
                                    <input type="time"
                                           name="daily_sync_time"
                                           value="{{ old('daily_sync_time', $server->daily_sync_time) }}"
                                           class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-black text-slate-700 mb-1">
                                        Warning Disk Percentage
                                    </label>
                                    <input type="number"
                                           min="1"
                                           max="100"
                                           name="disk_warning_percent"
                                           value="{{ old('disk_warning_percent', $server->disk_warning_percent ?? 80) }}"
                                           class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-yellow-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-black text-slate-700 mb-1">
                                        Transfer Disk Percentage
                                    </label>
                                    <input type="number"
                                           min="1"
                                           max="100"
                                           name="disk_transfer_percent"
                                           value="{{ old('disk_transfer_percent', $server->disk_transfer_percent ?? 90) }}"
                                           class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-red-500">
                                </div>

                                <div class="xl:col-span-2">
                                    <label class="block text-sm font-black text-slate-700 mb-1">
                                        Assign Backup Server
                                    </label>
                                    <select name="backup_server_id"
                                            class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">No backup server</option>
                                        @foreach($backupServerOptions as $backupServer)
                                            <option value="{{ $backupServer->id }}"
                                                {{ (string) old('backup_server_id', $server->backup_server_id) === (string) $backupServer->id ? 'selected' : '' }}>
                                                {{ $backupServer->name }} - {{ $backupServer->host }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>
                        </div>

                        {{-- TOGGLES --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                            <label class="flex items-center gap-3 rounded-2xl border p-4 cursor-pointer hover:border-green-400 hover:bg-green-50">
                                <input type="checkbox"
                                       name="google_drive_sync"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ old('google_drive_sync', $server->google_drive_sync) ? 'checked' : '' }}>
                                <div>
                                    <p class="font-black text-slate-900">Google Drive Sync</p>
                                    <p class="text-xs text-slate-500">Upload backups to rclone remote</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 rounded-2xl border p-4 cursor-pointer hover:border-orange-400 hover:bg-orange-50">
                                <input type="checkbox"
                                       name="auto_transfer"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ old('auto_transfer', $server->auto_transfer) ? 'checked' : '' }}>
                                <div>
                                    <p class="font-black text-slate-900">Auto Transfer</p>
                                    <p class="text-xs text-slate-500">Transfer when disk is full</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 rounded-2xl border p-4 cursor-pointer hover:border-purple-400 hover:bg-purple-50">
                                <input type="checkbox"
                                       name="failover_enabled"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ old('failover_enabled', $server->failover_enabled) ? 'checked' : '' }}>
                                <div>
                                    <p class="font-black text-slate-900">Backup Failover</p>
                                    <p class="text-xs text-slate-500">Run selected account transfer</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 rounded-2xl border p-4 cursor-pointer hover:border-red-400 hover:bg-red-50">
                                <input type="checkbox"
                                       name="dns_failover_enabled"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ old('dns_failover_enabled', $server->dns_failover_enabled) ? 'checked' : '' }}>
                                <div>
                                    <p class="font-black text-slate-900">ClouDNS Failover</p>
                                    <p class="text-xs text-slate-500">Change A record to backup IP</p>
                                </div>
                            </label>
                        </div>

                        {{-- ACCOUNT SELECTOR --}}
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 mb-5">
                                <div>
                                    <h4 class="text-lg font-black text-slate-900">
                                        Select cPanel Accounts to Transfer
                                    </h4>
                                    <p class="text-sm text-slate-500">
                                        Choose only the customer accounts you want to transfer. If nothing is selected, full backup path transfer can be used.
                                    </p>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input type="text"
                                           id="accountSearch-{{ $server->id }}"
                                           oninput="filterAccounts('{{ $server->id }}')"
                                           placeholder="Search accounts..."
                                           class="px-4 py-2 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">

                                    <button type="button"
                                            onclick="toggleAllAccounts('{{ $server->id }}', true)"
                                            class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-black">
                                        Select All
                                    </button>

                                    <button type="button"
                                            onclick="toggleAllAccounts('{{ $server->id }}', false)"
                                            class="px-4 py-2 rounded-xl bg-slate-700 text-white text-sm font-black">
                                        Clear
                                    </button>
                                </div>
                            </div>

                            @if(count($accountsForServer))
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[360px] overflow-y-auto pr-1">
                                    @foreach($accountsForServer as $account)
                                        @php
                                            $user = $account['user'];
                                            $checked = in_array($user, $selectedAccounts);
                                            $suspended = !empty($account['suspended']) && $account['suspended'] !== '0';
                                        @endphp

                                        <label class="account-card-{{ $server->id }} flex items-start gap-3 rounded-2xl border bg-white p-4 cursor-pointer hover:border-blue-400 transition"
                                               data-account-search="{{ strtolower(($account['user'] ?? '').' '.($account['domain'] ?? '').' '.($account['ip'] ?? '').' '.($account['plan'] ?? '')) }}">
                                            <input type="checkbox"
                                                   name="backup_selected_accounts[]"
                                                   value="{{ $user }}"
                                                   class="mt-1 account-checkbox-{{ $server->id }}"
                                                   {{ $checked ? 'checked' : '' }}>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <p class="font-black text-slate-900">
                                                        {{ $user }}
                                                    </p>

                                                    @if($suspended)
                                                        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-black">
                                                            Suspended
                                                        </span>
                                                    @else
                                                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-black">
                                                            Active
                                                        </span>
                                                    @endif
                                                </div>

                                                <p class="text-sm text-slate-600 truncate mt-1">
                                                    {{ $account['domain'] ?? 'No domain' }}
                                                </p>

                                                <div class="text-xs text-slate-400 mt-2 space-y-1">
                                                    <p>IP: {{ $account['ip'] ?? '-' }}</p>
                                                    <p>Disk: {{ $account['diskused'] ?? '-' }}</p>
                                                    <p>Plan: {{ $account['plan'] ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl bg-yellow-50 border border-yellow-200 p-5 text-yellow-800">
                                    <div class="font-black">
                                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                        No cPanel accounts loaded
                                    </div>
                                    <p class="text-sm mt-1">
                                        Check WHM login/API access for this server, or save backup settings and try again.
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- FAILOVER STATUS --}}
                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                            <div class="rounded-2xl border p-4">
                                <p class="text-sm text-slate-500 font-bold">Linked Domain</p>
                                <p class="font-black text-slate-900 mt-1">
                                    {{ $server->linked_domain ?? 'Not linked' }}
                                </p>
                            </div>

                            <div class="rounded-2xl border p-4">
                                <p class="text-sm text-slate-500 font-bold">Active DNS IP</p>
                                <p class="font-black text-slate-900 mt-1">
                                    {{ $server->active_dns_ip ?? $server->host }}
                                </p>
                            </div>

                            <div class="rounded-2xl border p-4">
                                <p class="text-sm text-slate-500 font-bold">Last Failover</p>
                                <p class="font-black text-slate-900 mt-1">
                                    {{ $server->last_failover_at ? $server->last_failover_at->diffForHumans() : 'Never' }}
                                </p>
                            </div>
                        </div>

                    </div>

                    {{-- SAVE FOOTER --}}
                    <div class="px-6 py-5 border-t bg-slate-50 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                        <div class="text-sm text-slate-500">
                            <span class="font-black text-slate-700">Tip:</span>
                            For ClouDNS failover, link a domain in Domain Manager and assign a backup server.
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button class="px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                                <i class="fa-solid fa-floppy-disk mr-2"></i>
                                Save Settings
                            </button>

                            @if(Route::has('domains.index'))
                                <a href="{{ route('domains.index') }}"
                                   class="px-5 py-3 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-black">
                                    <i class="fa-solid fa-globe mr-2"></i>
                                    Domain Manager
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        @empty
            <div class="bg-white rounded-3xl shadow border border-slate-100 p-10 text-center">
                <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-600 mx-auto flex items-center justify-center mb-4">
                    <i class="fa-solid fa-server text-2xl"></i>
                </div>

                <h3 class="text-xl font-black text-slate-900">No servers found</h3>
                <p class="text-slate-500 mt-1">Add a server first to configure backup automation.</p>

                @if(Route::has('servers.create'))
                    <a href="{{ route('servers.create') }}"
                       class="inline-flex mt-5 px-5 py-3 rounded-xl bg-blue-600 text-white font-black">
                        Add Server
                    </a>
                @endif
            </div>
        @endforelse
    </div>

</div>

<script>
function toggleBox(id) {
    const box = document.getElementById(id);

    if (!box) {
        return;
    }

    box.classList.toggle('hidden');
}

function toggleAllAccounts(serverId, checked) {
    document.querySelectorAll('.account-checkbox-' + serverId).forEach(function (checkbox) {
        checkbox.checked = checked;
    });
}

function filterAccounts(serverId) {
    const input = document.getElementById('accountSearch-' + serverId);
    const query = input ? input.value.toLowerCase() : '';

    document.querySelectorAll('.account-card-' + serverId).forEach(function (card) {
        const value = card.getAttribute('data-account-search') || card.innerText.toLowerCase();
        card.style.display = value.includes(query) ? '' : 'none';
    });
}

function filterBackupCards() {
    const search = document.getElementById('backupSearch')?.value.toLowerCase() || '';
    const filter = document.getElementById('backupFilter')?.value || 'all';

    document.querySelectorAll('.backup-card').forEach(function (card) {
        const text = card.getAttribute('data-search') || card.innerText.toLowerCase();

        let show = text.includes(search);

        if (filter === 'google') {
            show = show && card.getAttribute('data-google') === '1';
        }

        if (filter === 'auto') {
            show = show && card.getAttribute('data-auto') === '1';
        }

        if (filter === 'failover') {
            show = show && card.getAttribute('data-failover') === '1';
        }

        if (filter === 'linked') {
            show = show && card.getAttribute('data-linked') === '1';
        }

        card.style.display = show ? '' : 'none';
    });
}
</script>

@endsection