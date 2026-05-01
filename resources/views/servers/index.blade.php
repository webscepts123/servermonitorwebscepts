@extends('layouts.app')

@section('content')

@php
    $totalServers = $servers->count();

    $onlineServers = $servers->filter(function ($server) {
        return $server->latestCheck && $server->latestCheck->online;
    })->count();

    $offlineServers = $totalServers - $onlineServers;

    $lastCheck = $servers
        ->pluck('latestCheck.checked_at')
        ->filter()
        ->sortDesc()
        ->first();
@endphp

<div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
    <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
        <p class="text-slate-500 text-sm">Total Servers</p>
        <h3 class="text-3xl font-bold mt-2">{{ $totalServers }}</h3>
    </div>

    <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
        <p class="text-slate-500 text-sm">Online</p>
        <h3 class="text-3xl font-bold text-green-600 mt-2">{{ $onlineServers }}</h3>
    </div>

    <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
        <p class="text-slate-500 text-sm">Offline</p>
        <h3 class="text-3xl font-bold text-red-600 mt-2">{{ $offlineServers }}</h3>
    </div>

    <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
        <p class="text-slate-500 text-sm">Last Check</p>
        <h3 class="text-lg font-semibold mt-2">
            {{ $lastCheck ? \Carbon\Carbon::parse($lastCheck)->diffForHumans() : 'No checks yet' }}
        </h3>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden border border-slate-100">
    <div class="px-6 py-5 flex flex-col md:flex-row md:justify-between md:items-center gap-4 border-b">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Servers</h2>
            <p class="text-sm text-slate-500">Live monitoring overview with SSH, cPanel, Plesk, firewall and security alerts.</p>
        </div>

        <a href="{{ route('servers.create') }}"
           class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-center">
            + Add Server
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-100">
                <tr>
                    <th class="p-4">Server</th>
                    <th class="p-4">Host</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Panels</th>
                    <th class="p-4">Usage</th>
                    <th class="p-4">Firewall</th>
                    <th class="p-4">Security</th>
                    <th class="p-4">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($servers as $server)
                    @php
                        $check = $server->latestCheck;
                        $alerts = [];

                        if ($check && $check->security_alerts) {
                            $decodedAlerts = json_decode($check->security_alerts, true);
                            $alerts = is_array($decodedAlerts) ? $decodedAlerts : [];
                        }
                    @endphp

                    <tr class="border-t hover:bg-slate-50 transition align-top">
                        <td class="p-4">
                            <div class="font-semibold text-slate-900">{{ $server->name }}</div>
                            <div class="text-xs text-slate-500 mt-1">
                                SSH: {{ $server->username }}@{{ $server->host }}:{{ $server->ssh_port }}
                            </div>
                        </td>

                        <td class="p-4 text-slate-600">
                            {{ $server->host }}
                        </td>

                        <td class="p-4">
                            @if($check && $check->online)
                                <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm font-semibold">
                                    Online
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-semibold">
                                    Offline
                                </span>
                            @endif

                            <div class="mt-2 text-xs text-slate-500">
                                SSH:
                                @if($check && $check->ssh_online)
                                    <span class="text-green-600 font-semibold">Connected</span>
                                @else
                                    <span class="text-red-600 font-semibold">Failed</span>
                                @endif
                            </div>

                            <div class="text-xs text-slate-500">
                                {{ $check && $check->checked_at ? \Carbon\Carbon::parse($check->checked_at)->diffForHumans() : 'Not checked yet' }}
                            </div>
                        </td>

                        <td class="p-4">
                            <div class="flex flex-wrap gap-2">
                                @if($check && $check->cpanel_online)
                                    <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-semibold">cPanel</span>
                                @else
                                    <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-500 text-xs font-semibold">cPanel</span>
                                @endif

                                @if($check && $check->plesk_online)
                                    <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-semibold">Plesk</span>
                                @else
                                    <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-500 text-xs font-semibold">Plesk</span>
                                @endif

                                @if($check && $check->website_online)
                                    <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-semibold">Website</span>
                                @else
                                    <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-semibold">Website</span>
                                @endif
                            </div>
                        </td>

                        <td class="p-4">
                            <div class="space-y-2 min-w-40">
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>CPU</span>
                                        <span>{{ $check->cpu_usage ?? '-' }}%</span>
                                    </div>
                                    <div class="h-2 bg-slate-200 rounded-full">
                                        <div class="h-2 bg-blue-600 rounded-full"
                                             style="width: {{ min((float)($check->cpu_usage ?? 0), 100) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>RAM</span>
                                        <span>{{ $check->ram_usage ?? '-' }}%</span>
                                    </div>
                                    <div class="h-2 bg-slate-200 rounded-full">
                                        <div class="h-2 bg-purple-600 rounded-full"
                                             style="width: {{ min((float)($check->ram_usage ?? 0), 100) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>Disk</span>
                                        <span>{{ $check->disk_usage ?? '-' }}%</span>
                                    </div>
                                    <div class="h-2 bg-slate-200 rounded-full">
                                        <div class="h-2 bg-orange-500 rounded-full"
                                             style="width: {{ min((float)($check->disk_usage ?? 0), 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="p-4">
                            @if($check && $check->firewall_status)
                                @if(str_contains(strtolower($check->firewall_status), 'active'))
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">
                                        Active
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-semibold">
                                        {{ $check->firewall_status }}
                                    </span>
                                @endif
                            @else
                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-semibold">
                                    Unknown
                                </span>
                            @endif
                        </td>

                        <td class="p-4">
                            @if(count($alerts))
                                <div class="space-y-1">
                                    @foreach(array_slice($alerts, 0, 2) as $alert)
                                        <div class="text-xs text-red-700 bg-red-50 rounded-lg px-2 py-1">
                                            {{ $alert }}
                                        </div>
                                    @endforeach

                                    @if(count($alerts) > 2)
                                        <div class="text-xs text-slate-500">+{{ count($alerts) - 2 }} more</div>
                                    @endif
                                </div>
                            @else
                                <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">
                                    No alerts
                                </span>
                            @endif
                        </td>

                        <td class="p-4">
                            <div class="flex flex-wrap gap-2 min-w-72">
                                <a href="{{ route('servers.show', $server) }}"
                                   class="px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm">
                                    View
                                </a>

                                <form method="POST" action="{{ route('servers.checkNow', $server) }}">
                                    @csrf
                                    <button class="px-3 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm">
                                        Check
                                    </button>
                                </form>

                                <a href="{{ route('servers.terminal', $server) }}"
                                   class="px-3 py-2 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm">
                                    Terminal
                                </a>

                                <a href="{{ route('servers.edit', $server) }}"
                                   class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white text-sm">
                                    Edit
                                </a>

                                <form method="POST" action="{{ route('servers.destroy', $server) }}"
                                      onsubmit="return confirm('Delete this server?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-10 text-center">
                            <div class="text-5xl mb-3">🖥️</div>
                            <h3 class="text-xl font-bold text-slate-700">No servers added yet</h3>
                            <p class="text-slate-500 mt-1">Add your first server to start monitoring.</p>
                            <a href="{{ route('servers.create') }}"
                               class="inline-block mt-4 px-5 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700">
                                Add Server
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection