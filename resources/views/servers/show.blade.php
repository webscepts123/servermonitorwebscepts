@extends('layouts.app')

@section('page-title', $server->name)

@section('content')

@php
    $latest = $server->checks->first();

    if ($latest && is_array($latest->services)) {
        $services = $latest->services;
    } elseif ($latest && is_string($latest->services)) {
        $services = json_decode($latest->services, true) ?: [];
    } else {
        $services = [];
    }

    $securityAlerts = $server->securityAlerts()->latest()->limit(20)->get();

    $cpu = $latest->cpu_usage ?? 0;
    $ram = $latest->ram_usage ?? 0;
    $disk = $latest->disk_usage ?? 0;

    $dangerCount = $securityAlerts->where('level', 'danger')->count();
    $warningCount = $securityAlerts->where('level', 'warning')->count();
@endphp

<div class="space-y-6">

    {{-- SERVER HEADER --}}
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">

            <div>
                <h2 class="text-2xl font-bold text-slate-800">{{ $server->name }}</h2>
                <p class="text-slate-500 mt-1">{{ $server->host }} : {{ $server->ssh_port }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if($latest && $latest->online)
                        <span class="px-4 py-2 rounded-full bg-green-100 text-green-700 font-semibold">Online</span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-red-100 text-red-700 font-semibold">Offline</span>
                    @endif

                    @if($latest && $latest->ssh_online)
                        <span class="px-4 py-2 rounded-full bg-blue-100 text-blue-700 font-semibold">SSH Connected</span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-slate-100 text-slate-600 font-semibold">SSH Unknown</span>
                    @endif
                </div>
            </div>

            {{-- RESPONSIVE BUTTONS --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 w-full xl:w-auto">

                <form method="POST" action="{{ route('servers.checkNow', $server) }}" class="w-full">
                    @csrf
                    <button class="w-full px-4 py-3 rounded-xl bg-green-600 text-white hover:bg-green-700 text-sm font-semibold">
                        Check Now
                    </button>
                </form>

                <form method="POST" action="{{ route('servers.securityScan', $server) }}" class="w-full">
                    @csrf
                    <button class="w-full px-4 py-3 rounded-xl bg-purple-600 text-white hover:bg-purple-700 text-sm font-semibold">
                        Security Scan
                    </button>
                </form>

                <a href="{{ route('servers.cpanel.index', $server) }}"
                   class="w-full text-center px-4 py-3 rounded-xl bg-orange-600 text-white hover:bg-orange-700 text-sm font-semibold">
                    Accounts
                </a>

                <a href="{{ route('servers.terminal', $server) }}"
                   class="w-full text-center px-4 py-3 rounded-xl bg-slate-900 text-white hover:bg-slate-700 text-sm font-semibold">
                    Terminal
                </a>

                <a href="{{ route('servers.edit', $server) }}"
                   class="w-full text-center px-4 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm font-semibold">
                    Edit
                </a>

                <a href="{{ route('backups.index') }}"
                   class="w-full text-center px-4 py-3 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-semibold">
                    Backup
                </a>

            </div>

        </div>
    </div>

    {{-- STATS --}}
    <div class="grid grid-cols-1 xl:grid-cols-4 md:grid-cols-2 gap-5">

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">CPU Usage</p>
            <h3 class="text-3xl font-bold mt-2">{{ $latest->cpu_usage ?? '-' }}%</h3>
            <div class="h-2 bg-slate-200 rounded-full mt-4">
                <div class="h-2 bg-blue-600 rounded-full" style="width: {{ min((float)$cpu, 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">RAM Usage</p>
            <h3 class="text-3xl font-bold mt-2">{{ $latest->ram_usage ?? '-' }}%</h3>
            <div class="h-2 bg-slate-200 rounded-full mt-4">
                <div class="h-2 bg-purple-600 rounded-full" style="width: {{ min((float)$ram, 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Disk Usage</p>
            <h3 class="text-3xl font-bold mt-2 {{ ($latest && $latest->disk_usage >= 90) ? 'text-red-600' : '' }}">
                {{ $latest->disk_usage ?? '-' }}%
            </h3>
            <div class="h-2 bg-slate-200 rounded-full mt-4">
                <div class="h-2 {{ $disk >= 90 ? 'bg-red-600' : 'bg-orange-500' }} rounded-full"
                     style="width: {{ min((float)$disk, 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Last Checked</p>
            <h3 class="text-lg font-bold mt-2 break-words">
                {{ $latest->checked_at ?? 'No checks yet' }}
            </h3>
        </div>

    </div>

    {{-- PANEL STATUS + SERVICES --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-xl font-bold mb-4">Panel & Website Status</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="rounded-xl border p-4">
                    <p class="font-semibold">cPanel / WHM</p>
                    @if($latest && $latest->cpanel_online)
                        <span class="text-green-600 font-bold">Online</span>
                    @else
                        <span class="text-red-600 font-bold">Offline</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port 2087</p>
                </div>

                <div class="rounded-xl border p-4">
                    <p class="font-semibold">Plesk</p>
                    @if($latest && $latest->plesk_online)
                        <span class="text-green-600 font-bold">Online</span>
                    @else
                        <span class="text-slate-500 font-bold">Not Detected</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port 8443</p>
                </div>

                <div class="rounded-xl border p-4">
                    <p class="font-semibold">Website</p>
                    @if($latest && $latest->website_online)
                        <span class="text-green-600 font-bold">Online</span>
                    @else
                        <span class="text-red-600 font-bold">Offline</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port 80 / 443</p>
                </div>

                <div class="rounded-xl border p-4">
                    <p class="font-semibold">SSH</p>
                    @if($latest && $latest->ssh_online)
                        <span class="text-green-600 font-bold">Connected</span>
                    @else
                        <span class="text-red-600 font-bold">Failed</span>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">Port {{ $server->ssh_port }}</p>
                </div>

            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-xl font-bold mb-4">Services</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($services as $name => $status)
                    <div class="rounded-xl border p-4">
                        <p class="font-semibold uppercase">{{ $name }}</p>

                        @if($status === 'active')
                            <span class="text-green-600 font-bold">Active</span>
                        @else
                            <span class="text-red-600 font-bold">{{ $status ?: 'Unknown' }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-slate-500">No service data available.</p>
                @endforelse
            </div>
        </div>


    </div>
    

    {{-- SECURITY SUMMARY --}}
    <div class="grid grid-cols-1 xl:grid-cols-4 md:grid-cols-2 gap-5">

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Firewall Status</p>
            <h3 class="text-lg font-bold mt-2 break-words">
                {{ $latest->firewall_status ?? 'Unknown' }}
            </h3>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Danger Alerts</p>
            <h3 class="text-3xl font-bold text-red-600 mt-2">{{ $dangerCount }}</h3>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Warning Alerts</p>
            <h3 class="text-3xl font-bold text-yellow-600 mt-2">{{ $warningCount }}</h3>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Total Alerts</p>
            <h3 class="text-3xl font-bold mt-2">{{ $securityAlerts->count() }}</h3>
        </div>

    </div>

    {{-- SECURITY ALERTS --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-5 border-b flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
            <div>
                <h3 class="text-xl font-bold">Security Alerts</h3>
                <p class="text-sm text-slate-500">
                    Abuse, firewall, email, SSH, service and disk issues.
                </p>
            </div>

            <form method="POST" action="{{ route('servers.securityScan', $server) }}">
                @csrf
                <button class="w-full lg:w-auto px-5 py-3 rounded-xl bg-purple-600 text-white hover:bg-purple-700">
                    Run Security Scan
                </button>
            </form>
        </div>

        <div class="divide-y">
            @forelse($securityAlerts as $alert)
                <details class="p-5 group">
                    <summary class="cursor-pointer list-none">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap gap-2 items-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold
                                        @if($alert->level === 'danger') bg-red-100 text-red-700
                                        @elseif($alert->level === 'warning') bg-yellow-100 text-yellow-700
                                        @else bg-blue-100 text-blue-700
                                        @endif">
                                        {{ strtoupper($alert->level) }}
                                    </span>

                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold">
                                        {{ strtoupper($alert->type ?? 'SECURITY') }}
                                    </span>

                                    @if($alert->is_resolved)
                                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold">
                                            RESOLVED
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold">
                                            OPEN
                                        </span>
                                    @endif
                                </div>

                                <h4 class="font-bold text-slate-800 mt-3 break-words">
                                    {{ $alert->title }}
                                </h4>

                                <p class="text-sm text-slate-500 mt-1">
                                    Detected: {{ $alert->detected_at ?? $alert->created_at }}
                                </p>
                            </div>

                            <span class="text-sm text-blue-600 font-semibold">
                                View Details
                            </span>
                        </div>
                    </summary>

                    <div class="mt-4">
                        @if($alert->source_ip || $alert->location)
                            <div class="mb-3 text-sm text-slate-600">
                                @if($alert->source_ip)
                                    <div><strong>Source IP:</strong> {{ $alert->source_ip }}</div>
                                @endif

                                @if($alert->location)
                                    <div><strong>Location:</strong> {{ $alert->location }}</div>
                                @endif
                            </div>
                        @endif

                        @if($alert->message)
                            <pre class="bg-slate-950 text-green-400 rounded-xl p-4 overflow-x-auto text-xs max-h-80 whitespace-pre-wrap">{{ $alert->message }}</pre>
                        @else
                            <p class="text-sm text-slate-500">No additional details.</p>
                        @endif
                    </div>
                </details>
            @empty
                <div class="p-8 text-center text-slate-500">
                    No security alerts yet. Run Security Scan.
                </div>
            @endforelse
        </div>
    </div>

    {{-- RECOMMENDED ACTIONS --}}
    <div class="bg-white rounded-2xl shadow p-6">
        <h3 class="text-xl font-bold mb-4">Recommended Security Actions</h3>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <div class="rounded-xl border p-4">
                <h4 class="font-bold text-slate-800">Firewall Protection</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Keep only required ports open: 22, 80, 443, 2083, 2087. Block unused public ports.
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-bold text-slate-800">Email Abuse Protection</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Monitor Exim queue, suspicious forwarders, spam scripts and outgoing mail limits.
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-bold text-slate-800">SSH Security</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Use key login, disable password login if possible, install brute-force protection.
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <h4 class="font-bold text-slate-800">Abuse Monitoring</h4>
                <p class="text-sm text-slate-500 mt-1">
                    Track net-scan, spam, malware and port-scan reports from server abuse logs.
                </p>
            </div>

        </div>
    </div>

    {{-- RECENT CHECKS --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-5 border-b">
            <h3 class="text-xl font-bold">Recent Checks</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4">Time</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">CPU</th>
                        <th class="p-4">RAM</th>
                        <th class="p-4">Disk</th>
                        <th class="p-4">Message</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($server->checks as $check)
                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4 whitespace-nowrap">{{ $check->checked_at }}</td>

                            <td class="p-4">
                                @if($check->online)
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm font-semibold">
                                        Online
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-semibold">
                                        Offline
                                    </span>
                                @endif
                            </td>

                            <td class="p-4">{{ $check->cpu_usage ?? '-' }}%</td>
                            <td class="p-4">{{ $check->ram_usage ?? '-' }}%</td>
                            <td class="p-4">{{ $check->disk_usage ?? '-' }}%</td>
                            <td class="p-4 text-slate-600">{{ $check->status ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-10 text-center text-slate-500">
                                No checks found yet. Click <b>Check Now</b>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection