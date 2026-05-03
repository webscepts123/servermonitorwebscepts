@extends('layouts.app')

@section('page-title', 'Server Accounts')

@section('content')

@php
    use Illuminate\Pagination\LengthAwarePaginator;

    $perPage = 20;
    $currentPage = request()->get('page', 1);
    $accountsCollection = collect($accounts ?? []);
    $searchValue = request('search');

    /*
    |--------------------------------------------------------------------------
    | Panel Detection
    |--------------------------------------------------------------------------
    */
    $serverPanel = strtolower(
        $server->panel_type
        ?? $server->panel
        ?? $server->control_panel
        ?? ''
    );

    if (!$serverPanel) {
        $firstAccount = $accountsCollection->first();

        if (is_array($firstAccount)) {
            if (isset($firstAccount['user']) || isset($firstAccount['plan']) || isset($firstAccount['diskused'])) {
                $serverPanel = 'cpanel';
            } elseif (isset($firstAccount['login']) || isset($firstAccount['subscription']) || isset($firstAccount['webspace'])) {
                $serverPanel = 'plesk';
            } else {
                $serverPanel = 'unknown';
            }
        } else {
            $serverPanel = 'unknown';
        }
    }

    $isCpanel = str_contains($serverPanel, 'cpanel') || str_contains($serverPanel, 'whm');
    $isPlesk = str_contains($serverPanel, 'plesk');

    $panelName = $isPlesk ? 'Plesk' : ($isCpanel ? 'cPanel / WHM' : 'Server');

    /*
    |--------------------------------------------------------------------------
    | Search Filter
    |--------------------------------------------------------------------------
    */
    if ($searchValue) {
        $accountsCollection = $accountsCollection->filter(function ($account) use ($searchValue) {
            $needle = strtolower($searchValue);

            return str_contains(strtolower($account['domain'] ?? ''), $needle)
                || str_contains(strtolower($account['user'] ?? ''), $needle)
                || str_contains(strtolower($account['username'] ?? ''), $needle)
                || str_contains(strtolower($account['login'] ?? ''), $needle)
                || str_contains(strtolower($account['email'] ?? ''), $needle)
                || str_contains(strtolower($account['ip'] ?? ''), $needle)
                || str_contains(strtolower($account['plan'] ?? ''), $needle)
                || str_contains(strtolower($account['package'] ?? ''), $needle);
        })->values();
    }

    $paginatedAccounts = new LengthAwarePaginator(
        $accountsCollection->forPage($currentPage, $perPage),
        $accountsCollection->count(),
        $perPage,
        $currentPage,
        [
            'path' => request()->url(),
            'query' => request()->query(),
        ]
    );

    $totalAccounts = $accountsCollection->count();

    $activeAccounts = $accountsCollection->filter(function ($account) use ($isPlesk) {
        if ($isPlesk) {
            $status = strtolower($account['status'] ?? $account['state'] ?? '');
            return in_array($status, ['active', '0', 'enabled', 'ok']);
        }

        return empty($account['suspended']);
    })->count();

    $suspendedAccounts = max($totalAccounts - $activeAccounts, 0);

    $emailsCount = $accountsCollection->filter(function ($account) {
        return !empty($account['email']) || !empty($account['owner_email']) || !empty($account['mail']);
    })->count();

    $panelColor = $isPlesk ? 'purple' : ($isCpanel ? 'blue' : 'slate');
@endphp

<div class="space-y-6">

    {{-- Enterprise Header --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 shadow-xl">
        <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-purple-500/20 blur-3xl"></div>

        <div class="relative p-6 lg:p-8">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">

                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="w-14 h-14 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center text-white">
                            @if($isPlesk)
                                <i class="fa-solid fa-layer-group text-2xl"></i>
                            @elseif($isCpanel)
                                <i class="fa-solid fa-server text-2xl"></i>
                            @else
                                <i class="fa-solid fa-network-wired text-2xl"></i>
                            @endif
                        </div>

                        <div>
                            <h2 class="text-3xl font-black text-white tracking-tight">
                                {{ $panelName }} Accounts
                            </h2>
                            <p class="text-slate-300 mt-1">
                                {{ $server->name }} — {{ $server->host }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                            <i class="fa-solid fa-gauge-high mr-1"></i>
                            Panel: {{ $panelName }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-white text-xs font-bold">
                            <i class="fa-solid fa-users mr-1"></i>
                            Total: {{ $totalAccounts }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                            <i class="fa-solid fa-circle-check mr-1"></i>
                            Active: {{ $activeAccounts }}
                        </span>

                        <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            Suspended: {{ $suspendedAccounts }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row flex-wrap gap-3 w-full xl:w-auto">
                    @if(Route::has('servers.show'))
                        <a href="{{ route('servers.show', $server) }}"
                           class="w-full sm:w-auto text-center px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white hover:bg-white/20 font-bold">
                            <i class="fa-solid fa-arrow-left mr-2"></i>Back
                        </a>
                    @endif

                    @if($isCpanel && Route::has('servers.cpanel.create'))
                        <a href="{{ route('servers.cpanel.create', $server) }}"
                           class="w-full sm:w-auto text-center px-5 py-3 rounded-2xl bg-blue-600 text-white hover:bg-blue-700 font-bold shadow-lg">
                            <i class="fa-solid fa-plus mr-2"></i>Create Account
                        </a>
                    @endif

                    @if($isPlesk && Route::has('servers.plesk.create'))
                        <a href="{{ route('servers.plesk.create', $server) }}"
                           class="w-full sm:w-auto text-center px-5 py-3 rounded-2xl bg-purple-600 text-white hover:bg-purple-700 font-bold shadow-lg">
                            <i class="fa-solid fa-plus mr-2"></i>Create Plesk Account
                        </a>
                    @endif

                    <button onclick="location.reload()"
                            class="w-full sm:w-auto text-center px-5 py-3 rounded-2xl bg-emerald-600 text-white hover:bg-emerald-700 font-bold shadow-lg">
                        <i class="fa-solid fa-rotate mr-2"></i>Refresh
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- Messages --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif

    @if(!empty($error))
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>{{ $error }}
        </div>
    @endif

    {{-- Modern Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Panel Type</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-1">{{ $panelName }}</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-server text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Total Accounts</p>
                    <h3 class="text-3xl font-black text-slate-800 mt-1">{{ $totalAccounts }}</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <i class="fa-solid fa-users text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Active Accounts</p>
                    <h3 class="text-3xl font-black text-green-600 mt-1">{{ $activeAccounts }}</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 font-semibold">Suspended / Disabled</p>
                    <h3 class="text-3xl font-black text-red-600 mt-1">{{ $suspendedAccounts }}</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                    <i class="fa-solid fa-user-slash text-xl"></i>
                </div>
            </div>
        </div>

    </div>

    {{-- Tools/Search --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
        <div class="flex flex-col xl:flex-row gap-4 xl:items-center xl:justify-between">

            <div>
                <h3 class="text-xl font-black text-slate-800">Account Explorer</h3>
                <p class="text-sm text-slate-500">
                    Showing {{ $paginatedAccounts->firstItem() ?? 0 }} to {{ $paginatedAccounts->lastItem() ?? 0 }}
                    of {{ $paginatedAccounts->total() }} accounts.
                </p>
            </div>

            <form method="GET" action="{{ request()->url() }}" class="flex flex-col lg:flex-row gap-3 w-full xl:w-auto">
                <div class="relative w-full xl:w-96">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text"
                           name="search"
                           id="liveSearch"
                           value="{{ $searchValue }}"
                           placeholder="Search domain, username, email, IP..."
                           class="w-full border rounded-2xl pl-11 pr-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <button class="px-6 py-3 rounded-2xl bg-slate-900 text-white hover:bg-slate-700 font-bold">
                    Search
                </button>

                @if($searchValue)
                    <a href="{{ request()->url() }}"
                       class="px-6 py-3 rounded-2xl bg-slate-200 text-slate-800 hover:bg-slate-300 text-center font-bold">
                        Clear
                    </a>
                @endif
            </form>

        </div>
    </div>

    {{-- Accounts Table --}}
    <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">

        <div class="px-6 py-5 border-b bg-slate-50 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div>
                <h3 class="text-xl font-black text-slate-800">
                    {{ $panelName }} User Accounts
                </h3>
                <p class="text-sm text-slate-500">
                    Manage hosting accounts, auto-login sessions, emails, files and website access.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="px-3 py-1 rounded-full bg-slate-200 text-slate-700 text-xs font-bold">
                    Page {{ $paginatedAccounts->currentPage() }} of {{ $paginatedAccounts->lastPage() }}
                </span>

                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                    Emails: {{ $emailsCount }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" id="accountsTable">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4">Domain</th>
                        <th class="p-4">Username</th>
                        <th class="p-4">IP</th>
                        <th class="p-4">Package / Subscription</th>
                        <th class="p-4">Email</th>
                        <th class="p-4">Disk Used</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($paginatedAccounts as $account)
                        @php
                            /*
                            |--------------------------------------------------------------------------
                            | cPanel Field Mapping
                            |--------------------------------------------------------------------------
                            */
                            $cpanelDomain = $account['domain'] ?? '-';
                            $cpanelUser = $account['user'] ?? $account['username'] ?? '-';
                            $cpanelIp = $account['ip'] ?? '-';
                            $cpanelPlan = $account['plan'] ?? $account['package'] ?? '-';
                            $cpanelEmail = $account['email'] ?? '-';
                            $cpanelDisk = $account['diskused'] ?? $account['disk_used'] ?? '-';
                            $cpanelSuspended = !empty($account['suspended']);

                            /*
                            |--------------------------------------------------------------------------
                            | Plesk Field Mapping
                            |--------------------------------------------------------------------------
                            */
                            $pleskDomain = $account['domain']
                                ?? $account['name']
                                ?? $account['subscription']
                                ?? $account['webspace']
                                ?? '-';

                            $pleskUser = $account['login']
                                ?? $account['user']
                                ?? $account['username']
                                ?? $account['owner_login']
                                ?? '-';

                            $pleskIp = $account['ip']
                                ?? $account['ip_address']
                                ?? $account['hosting_ip']
                                ?? '-';

                            $pleskPlan = $account['plan']
                                ?? $account['service_plan']
                                ?? $account['package']
                                ?? $account['subscription']
                                ?? '-';

                            $pleskEmail = $account['email']
                                ?? $account['owner_email']
                                ?? $account['mail']
                                ?? '-';

                            $pleskDisk = $account['diskused']
                                ?? $account['disk_usage']
                                ?? $account['disk_space']
                                ?? $account['quota']
                                ?? '-';

                            $pleskStatusRaw = strtolower($account['status'] ?? $account['state'] ?? '');
                            $pleskSuspended = in_array($pleskStatusRaw, ['suspended', 'disabled', 'inactive', '1']);

                            /*
                            |--------------------------------------------------------------------------
                            | Final Display Values
                            |--------------------------------------------------------------------------
                            */
                            if ($isPlesk) {
                                $displayDomain = $pleskDomain;
                                $displayUser = $pleskUser;
                                $displayIp = $pleskIp;
                                $displayPlan = $pleskPlan;
                                $displayEmail = $pleskEmail;
                                $displayDisk = $pleskDisk;
                                $isSuspended = $pleskSuspended;
                            } else {
                                $displayDomain = $cpanelDomain;
                                $displayUser = $cpanelUser;
                                $displayIp = $cpanelIp;
                                $displayPlan = $cpanelPlan;
                                $displayEmail = $cpanelEmail;
                                $displayDisk = $cpanelDisk;
                                $isSuspended = $cpanelSuspended;
                            }

                            $websiteUrl = $displayDomain !== '-' ? 'https://' . $displayDomain : null;
                        @endphp

                        <tr class="border-t hover:bg-slate-50 transition account-row">
                            <td class="p-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-globe"></i>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="font-black text-slate-800 break-all account-search-text">
                                            {{ $displayDomain }}
                                        </div>

                                        <div class="flex flex-wrap gap-2 mt-2">
                                            @if($websiteUrl)
                                                <a href="{{ $websiteUrl }}"
                                                   target="_blank"
                                                   class="text-xs text-blue-600 hover:underline font-semibold">
                                                    Open Website
                                                </a>
                                            @endif

                                            @if($displayDomain !== '-')
                                                <button type="button"
                                                        onclick="copyText('{{ $displayDomain }}')"
                                                        class="text-xs text-slate-500 hover:text-slate-900 font-semibold">
                                                    Copy Domain
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="font-bold text-slate-800 account-search-text">
                                    {{ $displayUser }}
                                </div>

                                @if($displayUser !== '-')
                                    <button type="button"
                                            onclick="copyText('{{ $displayUser }}')"
                                            class="text-xs text-slate-500 hover:text-slate-900 font-semibold mt-1">
                                        Copy Username
                                    </button>
                                @endif
                            </td>

                            <td class="p-4">
                                <span class="font-semibold text-slate-700">
                                    {{ $displayIp }}
                                </span>

                                @if($displayIp !== '-')
                                    <button type="button"
                                            onclick="copyText('{{ $displayIp }}')"
                                            class="block text-xs text-slate-500 hover:text-slate-900 font-semibold mt-1">
                                        Copy IP
                                    </button>
                                @endif
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">
                                    {{ $displayPlan }}
                                </span>
                            </td>

                            <td class="p-4 break-all account-search-text">
                                <div class="font-semibold text-slate-700">
                                    {{ $displayEmail }}
                                </div>

                                @if($displayEmail !== '-')
                                    <button type="button"
                                            onclick="copyText('{{ $displayEmail }}')"
                                            class="text-xs text-slate-500 hover:text-slate-900 font-semibold mt-1">
                                        Copy Email
                                    </button>
                                @endif
                            </td>

                            <td class="p-4">
                                <span class="font-bold text-slate-800">
                                    {{ $displayDisk }}
                                </span>
                            </td>

                            <td class="p-4">
                                @if($isSuspended)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-bold">
                                        <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                        Suspended
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">
                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                        Active
                                    </span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">

                                    @if($isCpanel && !empty($displayUser) && $displayUser !== '-' && Route::has('servers.cpanel.edit'))
                                        <a href="{{ route('servers.cpanel.edit', [$server, $displayUser]) }}"
                                           class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-700 font-bold">
                                            <i class="fa-solid fa-sliders mr-2"></i>Manage
                                        </a>
                                    @endif

                                    @if($isPlesk && !empty($displayUser) && $displayUser !== '-' && Route::has('servers.plesk.edit'))
                                        <a href="{{ route('servers.plesk.edit', [$server, $displayUser]) }}"
                                           class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-700 font-bold">
                                            <i class="fa-solid fa-sliders mr-2"></i>Manage
                                        </a>
                                    @endif

                                    @if($isCpanel && !empty($displayUser) && $displayUser !== '-' && Route::has('servers.cpanel.login'))
                                        <a href="{{ route('servers.cpanel.login', [$server, $displayUser]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 font-bold">
                                            <i class="fa-solid fa-right-to-bracket mr-2"></i>Auto Login
                                        </a>
                                    @elseif($isCpanel && $displayDomain !== '-')
                                        <a href="https://{{ $displayDomain }}:2083"
                                           target="_blank"
                                           class="inline-flex items-center px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 font-bold">
                                            <i class="fa-solid fa-right-to-bracket mr-2"></i>cPanel
                                        </a>
                                    @endif

                                    @if($isCpanel && !empty($displayUser) && $displayUser !== '-' && Route::has('servers.cpanel.login.email'))
                                        <a href="{{ route('servers.cpanel.login.email', [$server, $displayUser]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-4 py-2 rounded-xl bg-green-600 text-white hover:bg-green-700 font-bold">
                                            <i class="fa-solid fa-envelope mr-2"></i>Email
                                        </a>
                                    @endif

                                    @if($isCpanel && !empty($displayUser) && $displayUser !== '-' && Route::has('servers.cpanel.login.files'))
                                        <a href="{{ route('servers.cpanel.login.files', [$server, $displayUser]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-4 py-2 rounded-xl bg-orange-600 text-white hover:bg-orange-700 font-bold">
                                            <i class="fa-solid fa-folder-open mr-2"></i>Files
                                        </a>
                                    @endif

                                    @if($isPlesk && $displayDomain !== '-')
                                        <a href="https://{{ $server->host }}:8443"
                                           target="_blank"
                                           class="inline-flex items-center px-4 py-2 rounded-xl bg-purple-600 text-white hover:bg-purple-700 font-bold">
                                            <i class="fa-solid fa-layer-group mr-2"></i>Plesk
                                        </a>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center text-slate-500">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center text-slate-400">
                                        <i class="fa-solid fa-users-slash text-2xl"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-700">No {{ $panelName }} accounts found.</p>
                                        <p class="text-sm text-slate-500">Try refreshing or checking server panel credentials.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginatedAccounts->hasPages())
            <div class="p-5 border-t bg-slate-50">
                {{ $paginatedAccounts->links() }}
            </div>
        @endif
    </div>

    {{-- Help Box --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-circle-info text-xl"></i>
                </div>

                <div>
                    <h4 class="font-black text-slate-800 mb-2">cPanel / WHM Access</h4>
                    <p class="text-sm text-slate-600 leading-6">
                        cPanel accounts are managed using WHM access from the saved server username and password.
                        The Auto Login button creates a temporary session when the server credentials have WHM permission.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-layer-group text-xl"></i>
                </div>

                <div>
                    <h4 class="font-black text-slate-800 mb-2">Plesk Access</h4>
                    <p class="text-sm text-slate-600 leading-6">
                        Plesk account actions need Plesk-specific controller routes such as
                        <code class="px-1 py-0.5 rounded bg-slate-100">servers.plesk.edit</code>
                        and
                        <code class="px-1 py-0.5 rounded bg-slate-100">servers.plesk.create</code>.
                    </p>
                </div>
            </div>
        </div>

    </div>

</div>

{{-- Toast --}}
<div id="copyToast"
     class="fixed bottom-6 right-6 hidden px-5 py-3 rounded-2xl bg-slate-900 text-white shadow-xl font-bold z-50">
    Copied
</div>

<script>
function copyText(text) {
    if (!text) return;

    navigator.clipboard.writeText(text).then(() => {
        const toast = document.getElementById('copyToast');
        toast.classList.remove('hidden');

        setTimeout(() => {
            toast.classList.add('hidden');
        }, 1600);
    });
}

const liveSearch = document.getElementById('liveSearch');
const rows = document.querySelectorAll('.account-row');

if (liveSearch) {
    liveSearch.addEventListener('input', function () {
        const query = this.value.toLowerCase();

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
}
</script>

@endsection