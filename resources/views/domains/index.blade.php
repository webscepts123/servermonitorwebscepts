@extends('layouts.app')

@section('page-title', 'Domains (CloudDNS)')

@section('content')

@php
    $servers = $servers ?? collect();
    $zones = $zones ?? collect();
    $apiConnected = $apiConnected ?? false;
    $error = $error ?? null;

    $linkedCount = $servers->sum(function ($server) {
        return isset($server->domains) ? $server->domains->count() : 0;
    });

    $unlinkedServers = $servers->filter(function ($server) {
        return !isset($server->domains) || $server->domains->count() === 0;
    })->count();
@endphp

<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-red-500/10 rounded-full blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
            <div>
                <h1 class="text-3xl lg:text-4xl font-black">
                    ClouDNS Domain Manager
                </h1>

                <p class="text-slate-300 mt-2">
                    Manage DNS zones, records, linked servers, multiple domains and automated DNS failover.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if($apiConnected)
                        <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                            <i class="fa-solid fa-circle mr-1 text-[10px]"></i> ClouDNS API Connected
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                            <i class="fa-solid fa-circle mr-1 text-[10px]"></i> ClouDNS API Failed
                        </span>
                    @endif

                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Zones: {{ $zones->count() }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                        Servers: {{ $servers->count() }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Linked Domains: {{ $linkedCount }}
                    </span>
                </div>
            </div>

            <button type="button"
                    onclick="toggleBox('createZoneBox')"
                    class="px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                <i class="fa-solid fa-plus mr-2"></i>
                Create DNS Zone
            </button>
        </div>
    </div>

    {{-- SESSION ALERTS --}}
    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-bold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error') || $error)
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-bold">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>{{ session('error') ?? $error }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4">
            <div class="font-black mb-2">Please fix these errors:</div>
            <ul class="list-disc ml-5 text-sm font-semibold">
                @foreach($errors->all() as $errorItem)
                    <li>{{ $errorItem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- CREATE ZONE --}}
    <div id="createZoneBox" class="hidden bg-white rounded-3xl shadow border border-slate-100 p-6">
        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-xl font-black text-slate-900">Create DNS Zone</h2>
                <p class="text-sm text-slate-500">Create a new DNS zone in your ClouDNS account.</p>
            </div>

            <button type="button"
                    onclick="toggleBox('createZoneBox')"
                    class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('domains.zone.create') }}" class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            @csrf

            <div class="lg:col-span-2">
                <label class="block text-sm font-black text-slate-700 mb-1">Domain Name</label>
                <input type="text"
                       name="domain"
                       value="{{ old('domain') }}"
                       placeholder="example.com"
                       required
                       class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-black text-slate-700 mb-1">Zone Type</label>
                <select name="zone_type"
                        class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="master">Master</option>
                    <option value="slave">Slave</option>
                    <option value="parked">Parked</option>
                    <option value="geodns">GeoDNS</option>
                </select>
            </div>

            <div class="flex items-end">
                <button class="w-full px-5 py-3 rounded-xl bg-blue-600 text-white font-black hover:bg-blue-700">
                    Create Zone
                </button>
            </div>
        </form>
    </div>

    {{-- STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">DNS Zones</p>
            <h2 class="text-4xl font-black mt-2">{{ $zones->count() }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Linked Domains</p>
            <h2 class="text-4xl font-black mt-2 text-green-600">
                {{ $linkedCount }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Unlinked Servers</p>
            <h2 class="text-4xl font-black mt-2 text-red-600">
                {{ $unlinkedServers }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">API Status</p>
            <h2 class="text-2xl font-black mt-2 {{ $apiConnected ? 'text-green-600' : 'text-red-600' }}">
                {{ $apiConnected ? 'Connected' : 'Failed' }}
            </h2>
        </div>
    </div>

    {{-- SERVER DOMAIN LINKING --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-800">
                    Server Domain Linking
                </h2>
                <p class="text-slate-500">
                    Link multiple ClouDNS zones to each monitored server.
                </p>
            </div>

            <input type="text"
                   id="serverSearch"
                   oninput="filterCards('serverSearch', '.server-domain-card')"
                   placeholder="Search servers or domains..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6 grid grid-cols-1 xl:grid-cols-2 gap-5">
            @forelse($servers as $server)
                @php
                    $linkedDomains = $server->domains ?? collect();

                    $searchText = strtolower(
                        $server->name . ' ' .
                        $server->host . ' ' .
                        $linkedDomains->pluck('domain')->implode(' ')
                    );
                @endphp

                <div class="server-domain-card rounded-3xl border border-slate-200 p-5 hover:shadow-lg transition bg-white"
                     data-search="{{ $searchText }}">
                    <div class="flex flex-col gap-5">

                        {{-- SERVER INFO --}}
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-black text-slate-900">
                                        {{ $server->name }}
                                    </h3>

                                    <p class="text-slate-500 font-semibold mt-1">
                                        {{ $server->host }}
                                    </p>
                                </div>

                                <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-server"></i>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">
                                    {{ $server->is_active ? 'Active' : 'Inactive' }}
                                </span>

                                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                                    {{ strtoupper($server->panel_type ?? 'AUTO') }}
                                </span>

                                <span class="px-3 py-1 rounded-full {{ strtolower($server->status ?? '') === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} text-xs font-bold">
                                    {{ ucfirst($server->status ?? 'offline') }}
                                </span>

                                <span class="px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">
                                    Domains: {{ $linkedDomains->count() }}
                                </span>
                            </div>
                        </div>

                        {{-- ADD NEW DOMAIN --}}
                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                            <h4 class="font-black text-slate-900 mb-3">Add Domain to Server</h4>

                            <form method="POST" action="{{ route('domains.servers.link', $server) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                @csrf

                                <select name="linked_domain"
                                        class="md:col-span-2 w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <option value="">Select domain to add</option>

                                    @foreach($zones as $zone)
                                        @php
                                            $zoneName = $zone['name'] ?? null;
                                            $alreadyLinked = $zoneName ? $linkedDomains->contains('domain', $zoneName) : false;
                                        @endphp

                                        @if($zoneName)
                                            <option value="{{ $zoneName }}" {{ $alreadyLinked ? 'disabled' : '' }}>
                                                {{ $zoneName }} {{ $alreadyLinked ? '(already linked)' : '' }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>

                                <button class="w-full px-4 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                                    Add Domain
                                </button>

                                <label class="md:col-span-3 flex items-center gap-2 text-sm font-bold text-slate-600">
                                    <input type="checkbox" name="is_primary" value="1">
                                    Set as primary website domain
                                </label>
                            </form>
                        </div>

                        {{-- LINKED DOMAINS LIST --}}
                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h4 class="font-black text-slate-900">
                                    Linked Domains
                                </h4>

                                <span class="px-3 py-1 rounded-full bg-slate-200 text-slate-700 text-xs font-black">
                                    {{ $linkedDomains->count() }}
                                </span>
                            </div>

                            @if($linkedDomains->count())
                                <div class="space-y-3">
                                    @foreach($linkedDomains as $domainLink)
                                        <div class="rounded-2xl bg-white border p-4">
                                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <p class="font-black text-green-700 text-lg break-all">
                                                            {{ $domainLink->domain }}
                                                        </p>

                                                        @if($domainLink->is_primary)
                                                            <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black">
                                                                PRIMARY
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <p class="text-xs text-slate-500 mt-1">
                                                        Active DNS IP: {{ $domainLink->active_dns_ip ?? $server->host }}
                                                    </p>

                                                    <p class="text-xs text-slate-400 mt-1">
                                                        Last DNS Update:
                                                        {{ $domainLink->last_dns_update_at ? $domainLink->last_dns_update_at->diffForHumans() : 'Never' }}
                                                    </p>
                                                </div>

                                                <div class="flex flex-wrap gap-2">
                                                    <a href="{{ route('domains.records', $domainLink->domain) }}"
                                                       class="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-700 text-white text-xs font-black">
                                                        Records
                                                    </a>

                                                    @if(!$domainLink->is_primary && Route::has('domains.servers.domains.primary'))
                                                        <form method="POST" action="{{ route('domains.servers.domains.primary', [$server, $domainLink]) }}">
                                                            @csrf
                                                            <button class="px-3 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-black">
                                                                Make Primary
                                                            </button>
                                                        </form>
                                                    @endif

                                                    <form method="POST"
                                                          action="{{ route('domains.servers.unlink', $server) }}"
                                                          onsubmit="return confirm('Unlink {{ $domainLink->domain }} from this server?')">
                                                        @csrf

                                                        <input type="hidden" name="domain" value="{{ $domainLink->domain }}">

                                                        <button class="px-3 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-black">
                                                            Unlink
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl bg-white border border-dashed p-5 text-center text-slate-500 font-semibold">
                                    No domains linked yet.
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            @empty
                <div class="col-span-2 text-center text-slate-500 p-10">
                    No servers found.
                </div>
            @endforelse
        </div>
    </div>

    {{-- DNS ZONES --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-800">
                    ClouDNS Zones
                </h2>
                <p class="text-slate-500">
                    Zones loaded from your ClouDNS API account.
                </p>
            </div>

            <input type="text"
                   id="zoneSearch"
                   oninput="filterTable('zoneSearch', '#zonesTable tbody tr')"
                   placeholder="Search DNS zones..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="zonesTable">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4 text-left">Domain</th>
                        <th class="p-4 text-left">Type</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-left">Linked Servers</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($zones as $zone)
                        @php
                            $zoneName = $zone['name'] ?? null;

                            $linkedServers = $zoneName
                                ? $servers->filter(function ($server) use ($zoneName) {
                                    return isset($server->domains) && $server->domains->contains('domain', $zoneName);
                                })
                                : collect();
                        @endphp

                        @if($zoneName)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="p-4">
                                    <div class="font-black text-slate-900">
                                        {{ $zoneName }}
                                    </div>
                                </td>

                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                                        {{ strtoupper($zone['type'] ?? 'MASTER') }}
                                    </span>
                                </td>

                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">
                                        {{ ucfirst($zone['status'] ?? 'active') }}
                                    </span>
                                </td>

                                <td class="p-4">
                                    @if($linkedServers->count())
                                        <div class="space-y-1">
                                            @foreach($linkedServers as $linkedServer)
                                                <div>
                                                    <span class="font-bold text-slate-800">
                                                        {{ $linkedServer->name }}
                                                    </span>
                                                    <span class="text-xs text-slate-500">
                                                        — {{ $linkedServer->host }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-400 font-bold">Not linked</span>
                                    @endif
                                </td>

                                <td class="p-4 text-right">
                                    <a href="{{ route('domains.records', $zoneName) }}"
                                       class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold">
                                        Records
                                    </a>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center text-slate-500">
                                No DNS zones found. Check ClouDNS API credentials and allowed IP.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function toggleBox(id) {
    const box = document.getElementById(id);

    if (box) {
        box.classList.toggle('hidden');
    }
}

function filterCards(inputId, cardSelector) {
    const input = document.getElementById(inputId);
    const value = input ? input.value.toLowerCase() : '';
    const cards = document.querySelectorAll(cardSelector);

    cards.forEach(function (card) {
        const text = card.getAttribute('data-search') || card.innerText.toLowerCase();
        card.style.display = text.includes(value) ? '' : 'none';
    });
}

function filterTable(inputId, rowSelector) {
    const input = document.getElementById(inputId);
    const value = input ? input.value.toLowerCase() : '';
    const rows = document.querySelectorAll(rowSelector);

    rows.forEach(function (row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
}
</script>

@endsection