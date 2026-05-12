@extends('layouts.app')

@section('page-title', 'SentinelCore Web Scanner')

@section('content')

@php
    $servers = $servers ?? collect();
    $scans = $scans ?? collect();
    $stats = $stats ?? [];
@endphp

<div class="space-y-6">

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

    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <h1 class="text-3xl lg:text-5xl font-black">
                    SentinelCore Web Scanner
                </h1>
                <p class="text-slate-300 mt-3 max-w-4xl">
                    Scan websites for WordPress, Laravel, Angular, Node.js, PHP, database exposure,
                    missing security headers, SSL issues and public sensitive files.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">WordPress</span>
                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">Laravel</span>
                    <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">Angular</span>
                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">Node.js</span>
                    <span class="px-4 py-2 rounded-full bg-yellow-500/20 border border-yellow-400/40 text-yellow-100 text-xs font-bold">MySQL / PostgreSQL</span>
                </div>
            </div>

            @if(Route::has('technology.index'))
                <a href="{{ route('technology.index') }}"
                   class="px-6 py-4 rounded-2xl bg-white/10 border border-white/20 text-white font-black text-center">
                    SentinelCore
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Total Scans</p>
            <h2 class="text-4xl font-black mt-2">{{ $stats['total'] ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Critical</p>
            <h2 class="text-4xl font-black mt-2 text-red-700">{{ $stats['critical'] ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">High</p>
            <h2 class="text-4xl font-black mt-2 text-orange-600">{{ $stats['high'] ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Medium</p>
            <h2 class="text-4xl font-black mt-2 text-yellow-600">{{ $stats['medium'] ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Low</p>
            <h2 class="text-4xl font-black mt-2 text-green-600">{{ $stats['low'] ?? 0 }}</h2>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-2xl font-black text-slate-900 mb-2">Run Website Scan</h2>
        <p class="text-slate-500 mb-5">
            Enter a website or domain to detect frameworks and security issues.
        </p>

        <form method="POST" action="{{ route('technology.webscanner.scan') }}" class="grid grid-cols-1 xl:grid-cols-5 gap-4">
            @csrf

            <div class="xl:col-span-2">
                <label class="block text-sm font-black text-slate-700 mb-1">Website URL / Domain</label>
                <input type="text"
                       name="url"
                       value="{{ old('url') }}"
                       placeholder="https://example.com"
                       required
                       class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="xl:col-span-2">
                <label class="block text-sm font-black text-slate-700 mb-1">Link to Server</label>
                <select name="server_id" class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No server link</option>
                    @foreach($servers as $server)
                        <option value="{{ $server->id }}">
                            {{ $server->name }} - {{ $server->host }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button class="w-full px-5 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black">
                    <i class="fa-solid fa-shield-virus mr-2"></i>
                    Scan Now
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Latest Web Scans</h2>
                <p class="text-slate-500">Review detected technologies and risk levels.</p>
            </div>

            <input id="scanSearch"
                   oninput="filterScans()"
                   placeholder="Search scans..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4 text-left">Website</th>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-left">Risk</th>
                        <th class="p-4 text-left">Technologies</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($scans as $scan)
                        <tr class="scan-row border-t hover:bg-slate-50">
                            <td class="p-4">
                                <div class="font-black text-slate-900">{{ $scan->domain ?? $scan->url }}</div>
                                <div class="text-xs text-slate-500">{{ $scan->ip ?? '-' }}</div>
                            </td>

                            <td class="p-4">
                                {{ $scan->server?->name ?? 'Not linked' }}
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-black">
                                    HTTP {{ $scan->http_status ?? 'N/A' }}
                                </span>
                            </td>

                            <td class="p-4">
                                @php
                                    $riskClass = match($scan->risk_level) {
                                        'critical' => 'bg-red-100 text-red-700',
                                        'high' => 'bg-orange-100 text-orange-700',
                                        'medium' => 'bg-yellow-100 text-yellow-700',
                                        default => 'bg-green-100 text-green-700',
                                    };
                                @endphp

                                <span class="px-3 py-1 rounded-full {{ $riskClass }} text-xs font-black uppercase">
                                    {{ $scan->risk_level }} / {{ $scan->risk_score }}
                                </span>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-wrap gap-1 max-w-md">
                                    @foreach(collect($scan->detected_technologies ?? [])->take(5) as $tech)
                                        <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-[10px] font-black">
                                            {{ $tech['name'] ?? '-' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <td class="p-4 text-right">
                                <a href="{{ route('technology.webscanner.show', $scan) }}"
                                   class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-10 text-center text-slate-500">
                                No web scans found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function filterScans() {
    const query = document.getElementById('scanSearch')?.value.toLowerCase() || '';

    document.querySelectorAll('.scan-row').forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
}
</script>

@endsection