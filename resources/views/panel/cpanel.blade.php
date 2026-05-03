@extends('layouts.app')

@section('page-title', 'cPanel / WHM Accounts')

@section('content')

<div class="space-y-6">

    <div class="rounded-3xl bg-gradient-to-r from-slate-950 via-blue-950 to-slate-900 p-6 text-white shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black">cPanel / WHM Accounts</h1>
                <p class="text-slate-300 mt-2">
                    Manage cPanel accounts, auto-login sessions, packages, IPs, emails, files and WordPress.
                </p>
            </div>

            @if(Route::has('servers.create'))
                <a href="{{ route('servers.create') }}"
                   class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-center">
                    <i class="fa-solid fa-plus mr-2"></i>Add cPanel Server
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">cPanel Servers</p>
            <h2 class="text-4xl font-black mt-2">{{ $servers->count() }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">Online</p>
            <h2 class="text-4xl font-black mt-2 text-green-600">
                {{ $servers->where('status', 'online')->count() }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">Offline</p>
            <h2 class="text-4xl font-black mt-2 text-red-600">
                {{ $servers->where('status', 'offline')->count() }}
            </h2>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow border overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-800">Available cPanel / WHM Servers</h2>
                <p class="text-slate-500">Open account manager for each WHM/cPanel server.</p>
            </div>

            <input type="text"
                   id="serverSearch"
                   onkeyup="filterPanelServers()"
                   placeholder="Search server..."
                   class="w-full lg:w-80 px-4 py-3 rounded-2xl border focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="serversTable">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Host</th>
                        <th class="p-4 text-left">Panel</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($servers as $server)
                        @php
                            $status = strtolower($server->status ?? 'offline');
                        @endphp

                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4">
                                <div class="font-black text-slate-800">{{ $server->name }}</div>
                                <div class="text-xs text-slate-500">{{ $server->website_url ?? 'No website URL' }}</div>
                            </td>

                            <td class="p-4 font-semibold">{{ $server->host }}</td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                                    cPanel / WHM
                                </span>
                            </td>

                            <td class="p-4">
                                @if($status === 'online')
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">Online</span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-bold">Offline</span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex justify-end flex-wrap gap-2">
                                    @if(Route::has('servers.cpanel.index'))
                                        <a href="{{ route('servers.cpanel.index', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold">
                                            Accounts
                                        </a>
                                    @endif

                                    @if(Route::has('servers.show'))
                                        <a href="{{ route('servers.show', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-blue-600 text-white font-bold">
                                            Server
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center text-slate-500">
                                No cPanel / WHM servers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function filterPanelServers() {
    const input = document.getElementById('serverSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#serversTable tbody tr');

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}
</script>

@endsection