@extends('layouts.app')

@section('page-title', 'Domains (CloudDNS)')

@section('content')

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
                    Manage DNS zones, records, linked servers and domain monitoring from one enterprise panel.
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
                </div>
            </div>

            <button onclick="toggleBox('createZoneBox')"
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

    {{-- CREATE ZONE --}}
    <div id="createZoneBox" class="hidden bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-xl font-black mb-4">Create DNS Zone</h2>

        <form method="POST" action="{{ route('domains.zone.create') }}" class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            @csrf

            <input type="text"
                   name="domain"
                   placeholder="example.com"
                   required
                   class="px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">

            <select name="zone_type"
                    class="px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                <option value="master">Master</option>
                <option value="slave">Slave</option>
                <option value="parked">Parked</option>
                <option value="geodns">GeoDNS</option>
            </select>

            <button class="px-5 py-3 rounded-xl bg-blue-600 text-white font-black hover:bg-blue-700">
                Create Zone
            </button>

            <button type="button"
                    onclick="toggleBox('createZoneBox')"
                    class="px-5 py-3 rounded-xl bg-slate-200 text-slate-800 font-black hover:bg-slate-300">
                Cancel
            </button>
        </form>
    </div>

    {{-- STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">DNS Zones</p>
            <h2 class="text-4xl font-black mt-2">{{ $zones->count() }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Linked Servers</p>
            <h2 class="text-4xl font-black mt-2 text-green-600">
                {{ $servers->whereNotNull('linked_domain')->count() }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Unlinked Servers</p>
            <h2 class="text-4xl font-black mt-2 text-red-600">
                {{ $servers->whereNull('linked_domain')->count() }}
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
                    Link ClouDNS zones to your monitored servers.
                </p>
            </div>

            <input type="text"
                   id="serverSearch"
                   oninput="filterCards('serverSearch', '.server-domain-card')"
                   placeholder="Search servers..."
                   class="w-full lg:w-80 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6 grid grid-cols-1 xl:grid-cols-2 gap-5">
            @forelse($servers as $server)
                <div class="server-domain-card rounded-3xl border border-slate-200 p-5 hover:shadow-lg transition bg-white">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-black text-slate-900">
                                {{ $server->name }}
                            </h3>

                            <p class="text-slate-500 font-semibold mt-1">
                                {{ $server->host }}
                            </p>

                            <div class="mt-4">
                                <p class="text-xs text-slate-500 font-bold uppercase tracking-wide">
                                    Linked Domain
                                </p>

                                @if($server->linked_domain)
                                    <p class="text-lg font-black text-green-700 mt-1">
                                        {{ $server->linked_domain }}
                                    </p>
                                @else
                                    <p class="text-lg font-black text-slate-900 mt-1">
                                        Not linked
                                    </p>
                                @endif
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
                            </div>
                        </div>

                        <div class="w-full lg:w-72">
                            <form method="POST" action="{{ route('domains.servers.link', $server) }}" class="space-y-3">
                                @csrf

                                <select name="linked_domain"
                                        class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <option value="">Select domain</option>

                                    @foreach($zones as $zone)
                                        <option value="{{ $zone['name'] }}"
                                            {{ $server->linked_domain === $zone['name'] ? 'selected' : '' }}>
                                            {{ $zone['name'] }}
                                        </option>
                                    @endforeach
                                </select>

                                <button class="w-full px-4 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                                    Link Domain
                                </button>
                            </form>

                            @if($server->linked_domain)
                                <form method="POST"
                                      action="{{ route('domains.servers.unlink', $server) }}"
                                      class="mt-3"
                                      onsubmit="return confirm('Unlink this domain from server?')">
                                    @csrf

                                    <button class="w-full px-4 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black">
                                        Unlink
                                    </button>
                                </form>

                                <a href="{{ route('domains.records', $server->linked_domain) }}"
                                   class="block text-center mt-3 px-4 py-3 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-black">
                                    DNS Records
                                </a>
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
                   class="w-full lg:w-80 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="zonesTable">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4 text-left">Domain</th>
                        <th class="p-4 text-left">Type</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-left">Linked Server</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($zones as $zone)
                        @php
                            $linkedServer = $servers->firstWhere('linked_domain', $zone['name']);
                        @endphp

                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4">
                                <div class="font-black text-slate-900">
                                    {{ $zone['name'] }}
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
                                @if($linkedServer)
                                    <span class="font-bold text-slate-800">
                                        {{ $linkedServer->name }}
                                    </span>
                                    <div class="text-xs text-slate-500">
                                        {{ $linkedServer->host }}
                                    </div>
                                @else
                                    <span class="text-slate-400 font-bold">Not linked</span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <a href="{{ route('domains.records', $zone['name']) }}"
                                   class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold">
                                    Records
                                </a>
                            </td>
                        </tr>
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
    const value = document.getElementById(inputId).value.toLowerCase();
    const cards = document.querySelectorAll(cardSelector);

    cards.forEach(card => {
        card.style.display = card.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
}

function filterTable(inputId, rowSelector) {
    const value = document.getElementById(inputId).value.toLowerCase();
    const rows = document.querySelectorAll(rowSelector);

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
}
</script>

@endsection