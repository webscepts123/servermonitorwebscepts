@extends('layouts.app')

@section('page-title', 'Webscept Server Monitoring')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 rounded-3xl shadow-xl p-7 text-white">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <div>
                <h1 class="text-3xl font-black">Webscept Server Monitoring</h1>
                <p class="text-slate-300 mt-2">
                    Enterprise server health, uptime, SMS alerts, email alerts, security, backups and performance monitoring.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('servers.create') }}"
                   class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow">
                    <i class="fa-solid fa-plus mr-2"></i>Add Server
                </a>

                @if(Route::has('backups.index'))
                    <a href="{{ route('backups.index') }}"
                       class="px-5 py-3 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-bold shadow">
                        <i class="fa-solid fa-cloud-arrow-up mr-2"></i>Backup
                    </a>
                @endif

                <button onclick="location.reload()"
                        class="px-5 py-3 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold shadow">
                    <i class="fa-solid fa-rotate mr-2"></i>Refresh
                </button>
            </div>
        </div>
    </div>

    {{-- Session Alerts --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-300 text-green-800 rounded-2xl p-4 font-semibold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-300 text-red-800 rounded-2xl p-4 font-semibold">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100">
            <p class="text-slate-500 font-semibold">Total Servers</p>
            <h2 class="text-4xl font-black mt-2">{{ $totalServers ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100">
            <p class="text-slate-500 font-semibold">Online Servers</p>
            <h2 class="text-4xl font-black mt-2 text-green-600">{{ $onlineServers ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100">
            <p class="text-slate-500 font-semibold">Offline Servers</p>
            <h2 class="text-4xl font-black mt-2 text-red-600">{{ $offlineServers ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100">
            <p class="text-slate-500 font-semibold">Average Speed</p>
            <h2 class="text-3xl font-black mt-2 text-indigo-600">
                {{ !empty($avgResponseTime) ? round($avgResponseTime, 2).' ms' : 'N/A' }}
            </h2>
        </div>
    </div>

    {{-- Enterprise Modules --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        @forelse($enterpriseModules ?? [] as $module)
            <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
                        <i class="fa-solid {{ $module['icon'] ?? 'fa-circle' }} text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-500 text-sm">{{ $module['title'] ?? 'Module' }}</p>
                        <h3 class="font-bold text-slate-800">{{ $module['value'] ?? 'Active' }}</h3>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
                <h3 class="font-bold">Server Speed</h3>
                <p class="text-slate-500">N/A</p>
            </div>
            <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
                <h3 class="font-bold">SMS Alerts</h3>
                <p class="text-slate-500">Ready</p>
            </div>
            <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
                <h3 class="font-bold">Security</h3>
                <p class="text-slate-500">Enterprise</p>
            </div>
            <div class="bg-white rounded-2xl shadow p-5 border border-slate-100">
                <h3 class="font-bold">Backup</h3>
                <p class="text-slate-500">Active</p>
            </div>
        @endforelse
    </div>

    {{-- Live Servers --}}
    <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b">
            <div>
                <h2 class="text-2xl font-black text-slate-800">Live Servers</h2>
                <p class="text-slate-500">Real server status with SMS/email alert actions.</p>
            </div>

            <input type="text"
                   id="serverSearch"
                   onkeyup="filterServers()"
                   placeholder="Search servers..."
                   class="w-full lg:w-80 px-4 py-3 rounded-2xl border focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="serversTable">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Host</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-left">Customer</th>
                        <th class="p-4 text-left">Alerts</th>
                        <th class="p-4 text-left">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($servers ?? [] as $server)
                        @php
                            $status = strtolower(trim($server->live_status ?? $server->status ?? 'offline'));
                            $isOnline = $status === 'online';
                        @endphp

                        <tr class="border-b hover:bg-slate-50 transition">
                            <td class="p-4">
                                <div class="font-black text-slate-800">
                                    {{ $server->name ?? 'Unknown Server' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Website: {{ $server->website_url ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Last Check:
                                    {{ optional($server->latest_check->created_at ?? null)->diffForHumans() ?? 'N/A' }}
                                </div>
                            </td>

                            <td class="p-4 font-semibold text-slate-600">
                                {{ $server->host ?? 'N/A' }}
                            </td>

                            <td class="p-4">
                                @if($isOnline)
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 font-bold">
                                        <i class="fa-solid fa-circle mr-1 text-xs"></i>Online
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 font-bold">
                                        <i class="fa-solid fa-circle mr-1 text-xs"></i>Offline
                                    </span>
                                @endif
                            </td>

                            <td class="p-4">
                                <div class="font-semibold text-slate-800">
                                    {{ $server->customer_name ?? 'No customer' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $server->customer_email ?? 'No email' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $server->customer_phone ?? 'No phone' }}
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-col gap-2">
                                    @if(!empty($server->email_alerts_enabled))
                                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full font-bold text-xs">
                                            Email Enabled
                                        </span>
                                    @else
                                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full font-bold text-xs">
                                            Email Disabled
                                        </span>
                                    @endif

                                    @if(!empty($server->sms_alerts_enabled))
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full font-bold text-xs">
                                            SMS Enabled
                                        </span>
                                    @else
                                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full font-bold text-xs">
                                            SMS Disabled
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-wrap gap-2">
                                    @if(Route::has('servers.show'))
                                        <a href="{{ route('servers.show', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-blue-600 text-white font-bold">
                                            View
                                        </a>
                                    @endif

                                    @if(Route::has('servers.checkNow'))
                                        <form method="POST" action="{{ route('servers.checkNow', $server) }}">
                                            @csrf
                                            <button class="px-4 py-2 rounded-xl bg-green-600 text-white font-bold">
                                                Check
                                            </button>
                                        </form>
                                    @endif

                                    @if(Route::has('sms.down'))
                                        <form method="POST" action="{{ route('sms.down', $server) }}">
                                            @csrf
                                            <button onclick="return confirm('Send DOWN SMS alert?')"
                                                    class="px-4 py-2 rounded-xl bg-red-600 text-white font-bold">
                                                Down SMS
                                            </button>
                                        </form>
                                    @endif

                                    @if(Route::has('sms.recovery'))
                                        <form method="POST" action="{{ route('sms.recovery', $server) }}">
                                            @csrf
                                            <button onclick="return confirm('Send RECOVERY SMS alert?')"
                                                    class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold">
                                                Recovery SMS
                                            </button>
                                        </form>
                                    @endif

                                    @if(Route::has('servers.terminal'))
                                        <a href="{{ route('servers.terminal', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold">
                                            Terminal
                                        </a>
                                    @endif

                                    @if(Route::has('servers.edit'))
                                        <a href="{{ route('servers.edit', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-yellow-500 text-white font-bold">
                                            Edit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                No servers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Manual SMS --}}
    @if(Route::has('sms.send'))
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 p-6">
            <h2 class="text-2xl font-black text-slate-800 mb-4">Send Manual SMS</h2>

            <form method="POST" action="{{ route('sms.send') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @csrf

                <div>
                    <label class="font-semibold text-slate-700">Phone Number</label>
                    <input type="text"
                           name="phone"
                           placeholder="947XXXXXXXX"
                           required
                           class="w-full mt-1 px-4 py-3 rounded-2xl border focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div class="lg:col-span-2">
                    <label class="font-semibold text-slate-700">Message</label>
                    <input type="text"
                           name="message"
                           placeholder="Webscept alert message..."
                           required
                           maxlength="500"
                           class="w-full mt-1 px-4 py-3 rounded-2xl border focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div class="lg:col-span-3">
                    <button class="px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Send SMS
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Latest Checks --}}
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-xl font-bold text-slate-800 mb-4">Latest Server Checks</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-slate-500">
                        <th class="py-3">Server</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Speed</th>
                        <th class="py-3">Checked At</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($latestChecks ?? [] as $check)
                        @php
                            $checkStatus = strtolower(trim($check->server->status ?? $check->status ?? 'offline'));
                            $checkOnline = $checkStatus === 'online';
                        @endphp

                        <tr class="border-b">
                            <td class="py-3 font-semibold">
                                {{ $check->server->name ?? 'Unknown Server' }}
                            </td>

                            <td class="py-3">
                                @if($checkOnline)
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full font-bold">
                                        Online
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full font-bold">
                                        Offline
                                    </span>
                                @endif
                            </td>

                            <td class="py-3">
                                {{ $check->response_time ?? 'N/A' }} ms
                            </td>

                            <td class="py-3 text-slate-500">
                                {{ $check->created_at?->diffForHumans() ?? 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-5 text-center text-slate-500">
                                No server checks found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function filterServers() {
    let input = document.getElementById("serverSearch").value.toLowerCase();
    let rows = document.querySelectorAll("#serversTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>
@endsection