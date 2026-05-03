@extends('layouts.app')

@section('page-title', 'Plesk Accounts')

@section('content')

<div class="space-y-6">

    <div class="rounded-3xl bg-gradient-to-r from-slate-950 via-purple-950 to-slate-900 p-6 text-white shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black">Plesk Accounts</h1>
                <p class="text-slate-300 mt-2">
                    Manage Plesk subscriptions, domains, customers, hosting plans and panel access.
                </p>
            </div>

            @if(Route::has('servers.create'))
                <a href="{{ route('servers.create') }}"
                   class="px-5 py-3 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-center">
                    <i class="fa-solid fa-plus mr-2"></i>Add Plesk Server
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">Plesk Servers</p>
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
        <div class="p-6 border-b">
            <h2 class="text-2xl font-black text-slate-800">Available Plesk Servers</h2>
            <p class="text-slate-500">Open Plesk panel or server details.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Host</th>
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
                                @if($status === 'online')
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">Online</span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-bold">Offline</span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex justify-end flex-wrap gap-2">
                                    <a href="https://{{ $server->host }}:8443"
                                       target="_blank"
                                       class="px-4 py-2 rounded-xl bg-purple-600 text-white font-bold">
                                        Open Plesk
                                    </a>

                                    @if(Route::has('servers.show'))
                                        <a href="{{ route('servers.show', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold">
                                            Server
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-10 text-center text-slate-500">
                                No Plesk servers found. Set server panel type to <b>plesk</b>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection