@extends('layouts.app')

@section('page-title', 'Run Checks Logs')

@section('content')
@php
    use Illuminate\Support\Str;
    use Illuminate\Pagination\AbstractPaginator;

    $rawChecks = $checks ?? $logs ?? collect();

    if ($rawChecks instanceof AbstractPaginator) {
        $checkCollection = collect($rawChecks->items());
        $paginator = $rawChecks;
    } else {
        $checkCollection = collect($rawChecks);
        $paginator = null;
    }

    $toNumber = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        $value = is_numeric($value) ? $value : preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $value === '' ? null : (float) $value;
    };

    $onlineCount = $checkCollection->filter(function ($item) {
        $status = strtolower((string) ($item->status ?? $item['status'] ?? ''));
        return Str::contains($status, 'online');
    })->count();

    $failedCount = $checkCollection->filter(function ($item) {
        $status = strtolower((string) ($item->status ?? $item['status'] ?? ''));
        return Str::contains($status, ['offline', 'failed', 'error']);
    })->count();

    $avgCpu = round($checkCollection->pluck('cpu')->map($toNumber)->filter(fn ($v) => $v !== null)->avg() ?? 0, 2);
    $avgRam = round($checkCollection->pluck('ram')->map($toNumber)->filter(fn ($v) => $v !== null)->avg() ?? 0, 2);
    $avgDisk = round($checkCollection->pluck('disk')->map($toNumber)->filter(fn ($v) => $v !== null)->avg() ?? 0, 2);

    $latestCheck = $checkCollection->first();
    $latestDate = $latestCheck->created_at ?? $latestCheck->checked_at ?? $latestCheck->date ?? null;

    $serverNames = $checkCollection
        ->map(fn ($item) => $item->server_name ?? optional($item->server)->name ?? $item->name ?? $item['server_name'] ?? $item['name'] ?? null)
        ->filter()
        ->unique()
        ->sort()
        ->values();

    $statusBadge = function ($status) {
        $status = strtolower((string) $status);

        if (Str::contains($status, 'online')) {
            return 'bg-green-100 text-green-700 border-green-200';
        }

        if (Str::contains($status, ['offline', 'failed', 'error'])) {
            return 'bg-red-100 text-red-700 border-red-200';
        }

        if (Str::contains($status, ['warning', 'slow'])) {
            return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        }

        return 'bg-slate-100 text-slate-700 border-slate-200';
    };

    $usageBarClass = function ($value) use ($toNumber) {
        $number = $toNumber($value) ?? 0;

        if ($number >= 90) return 'bg-red-500';
        if ($number >= 70) return 'bg-orange-500';
        if ($number >= 40) return 'bg-yellow-500';
        return 'bg-blue-600';
    };
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="rounded-3xl overflow-hidden bg-gradient-to-r from-slate-950 via-blue-950 to-indigo-900 shadow-xl">
        <div class="px-6 py-7 md:px-8 md:py-8 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl md:text-4xl font-black text-white">Run Checks Logs</h1>
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-green-500/20 text-green-200 border border-green-400/30 text-sm font-bold">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                        Live Monitoring
                    </span>
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-cyan-500/20 text-cyan-200 border border-cyan-400/30 text-sm font-bold">
                        <i class="fa-solid fa-shield-halved"></i>
                        Webscepts SentinelCore
                    </span>
                </div>
                <p class="text-slate-300 mt-3 max-w-4xl">
                    Real-time server health logs with CPU, RAM, disk, SSH result tracking, filtering, export,
                    and enterprise monitoring insights.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <button id="toggleAutoRefresh"
                        type="button"
                        class="px-5 py-3 rounded-2xl bg-white/10 text-white font-bold border border-white/10 hover:bg-white/20 transition">
                    <i class="fa-solid fa-rotate mr-2"></i>
                    Auto Refresh: Off
                </button>

                <button id="exportCsv"
                        type="button"
                        class="px-5 py-3 rounded-2xl bg-blue-600 text-white font-bold hover:bg-blue-700 transition">
                    <i class="fa-solid fa-file-arrow-down mr-2"></i>
                    Export CSV
                </button>

                <a href="{{ url()->current() }}"
                   class="px-5 py-3 rounded-2xl bg-red-600 text-white font-bold hover:bg-red-700 transition">
                    <i class="fa-solid fa-arrows-rotate mr-2"></i>
                    Refresh Now
                </a>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 text-green-700 px-5 py-4 font-medium">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 px-5 py-4 font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Total Logs</p>
            <h2 class="text-4xl font-black text-slate-900 mt-2">{{ number_format($checkCollection->count()) }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Online Checks</p>
            <h2 class="text-4xl font-black text-green-600 mt-2">{{ number_format($onlineCount) }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Failed / Offline</p>
            <h2 class="text-4xl font-black text-red-600 mt-2">{{ number_format($failedCount) }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Average CPU</p>
            <h2 class="text-4xl font-black text-blue-600 mt-2">{{ $avgCpu }}%</h2>
            <div class="mt-4 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-blue-600 rounded-full" style="width: {{ min($avgCpu, 100) }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-semibold">Latest Check</p>
            <h2 class="text-xl font-black text-slate-900 mt-2">
                {{ $latestDate ? \Illuminate\Support\Carbon::parse($latestDate)->diffForHumans() : 'N/A' }}
            </h2>
            <p class="text-sm text-slate-500 mt-2">
                {{ $latestDate ? \Illuminate\Support\Carbon::parse($latestDate)->format('Y-m-d H:i:s') : 'No logs yet' }}
            </p>
        </div>
    </div>

    {{-- Extra insights --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-900">Resource Insights</h3>
                <span class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-chart-line"></i>
                </span>
            </div>

            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm font-semibold text-slate-600 mb-1">
                        <span>CPU Average</span>
                        <span>{{ $avgCpu }}%</span>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 rounded-full" style="width: {{ min($avgCpu, 100) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-sm font-semibold text-slate-600 mb-1">
                        <span>RAM Average</span>
                        <span>{{ $avgRam }}%</span>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-purple-600 rounded-full" style="width: {{ min($avgRam, 100) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-sm font-semibold text-slate-600 mb-1">
                        <span>Disk Average</span>
                        <span>{{ $avgDisk }}%</span>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-500 rounded-full" style="width: {{ min($avgDisk, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-900">Log Health Summary</h3>
                <span class="w-12 h-12 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center">
                    <i class="fa-solid fa-heart-pulse"></i>
                </span>
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Monitoring Health</span>
                    <span class="font-black {{ $failedCount > 0 ? 'text-yellow-600' : 'text-green-600' }}">
                        {{ $failedCount > 0 ? 'Needs Attention' : 'Stable' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Server Coverage</span>
                    <span class="font-black text-slate-900">{{ $serverNames->count() }} Server(s)</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Framework Protection</span>
                    <span class="font-black text-cyan-700">SentinelCore Active</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Auto Alert Ready</span>
                    <span class="font-black text-green-700">Enabled</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-black text-slate-900">Quick Actions</h3>
                <span class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center">
                    <i class="fa-solid fa-bolt"></i>
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('dashboard') }}"
                   class="rounded-2xl border border-slate-200 px-4 py-3 font-bold text-slate-700 hover:bg-slate-50 transition">
                    <i class="fa-solid fa-chart-pie mr-2"></i> Dashboard
                </a>

                <a href="{{ route('servers.index') }}"
                   class="rounded-2xl border border-slate-200 px-4 py-3 font-bold text-slate-700 hover:bg-slate-50 transition">
                    <i class="fa-solid fa-server mr-2"></i> All Servers
                </a>

                <a href="{{ url('/tools/checks') }}"
                   class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 font-bold text-blue-700 hover:bg-blue-100 transition">
                    <i class="fa-solid fa-list-check mr-2"></i> Run Logs
                </a>

                @if(Route::has('technology.index'))
                    <a href="{{ route('technology.index') }}"
                       class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 font-bold text-red-700 hover:bg-red-100 transition">
                        <i class="fa-solid fa-shield-halved mr-2"></i> SentinelCore
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Filters + Table --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Run Check History</h2>
                <p class="text-slate-500 mt-1">Search, filter and review all health check logs.</p>
            </div>

            <div class="flex flex-col md:flex-row gap-3 w-full xl:w-auto">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input id="tableSearch"
                           type="text"
                           placeholder="Search server, status, date..."
                           class="w-full md:w-72 pl-11 pr-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-blue-500 focus:ring-0">
                </div>

                <select id="statusFilter"
                        class="px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="failed">Failed</option>
                    <option value="error">Error</option>
                </select>

                <select id="serverFilter"
                        class="px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-blue-500">
                    <option value="">All Servers</option>
                    @foreach($serverNames as $serverName)
                        <option value="{{ strtolower($serverName) }}">{{ $serverName }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="checksTable" class="w-full min-w-[1100px] text-sm">
                <thead class="bg-slate-50 sticky top-0 z-10">
                    <tr class="text-slate-600">
                        <th class="px-6 py-4 text-left font-black">Server</th>
                        <th class="px-6 py-4 text-left font-black">Status</th>
                        <th class="px-6 py-4 text-left font-black">CPU</th>
                        <th class="px-6 py-4 text-left font-black">RAM</th>
                        <th class="px-6 py-4 text-left font-black">Disk</th>
                        <th class="px-6 py-4 text-left font-black">Checked At</th>
                        <th class="px-6 py-4 text-left font-black">Sentinel Insight</th>
                        <th class="px-6 py-4 text-right font-black">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($checkCollection as $check)
                        @php
                            $serverName = $check->server_name ?? optional($check->server)->name ?? $check->name ?? '-';
                            $status = $check->status ?? '-';
                            $cpu = $check->cpu ?? null;
                            $ram = $check->ram ?? null;
                            $disk = $check->disk ?? null;
                            $checkedAt = $check->created_at ?? $check->checked_at ?? $check->date ?? null;

                            $cpuVal = $toNumber($cpu);
                            $ramVal = $toNumber($ram);
                            $diskVal = $toNumber($disk);

                            $insights = [];
                            if (($cpuVal ?? 0) >= 90) $insights[] = 'CPU critical';
                            if (($ramVal ?? 0) >= 85) $insights[] = 'RAM high';
                            if (($diskVal ?? 0) >= 90) $insights[] = 'Disk critical';
                            if (str_contains(strtolower((string) $status), 'failed')) $insights[] = 'SSH issue';
                            if (str_contains(strtolower((string) $status), 'offline')) $insights[] = 'Server unavailable';

                            if (empty($insights)) {
                                $insights[] = 'Healthy';
                            }
                        @endphp

                        <tr class="border-t border-slate-100 hover:bg-slate-50/80 transition check-row"
                            data-server="{{ strtolower($serverName) }}"
                            data-status="{{ strtolower($status) }}">
                            <td class="px-6 py-4">
                                <div class="font-black text-slate-900">{{ $serverName }}</div>
                                <div class="text-xs text-slate-500 mt-1">
                                    Powered by Webscepts SentinelCore
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex px-3 py-1.5 rounded-full border text-xs font-black {{ $statusBadge($status) }}">
                                    {{ $status }}
                                </span>
                            </td>

                            <td class="px-6 py-4 min-w-[140px]">
                                <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                                    <span>{{ $cpu !== null && $cpu !== '' ? $cpu : '%' }}</span>
                                </div>
                                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full {{ $usageBarClass($cpu) }} rounded-full"
                                         style="width: {{ min(($toNumber($cpu) ?? 0), 100) }}%"></div>
                                </div>
                            </td>

                            <td class="px-6 py-4 min-w-[140px]">
                                <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                                    <span>{{ $ram !== null && $ram !== '' ? $ram : '%' }}</span>
                                </div>
                                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full {{ $usageBarClass($ram) }} rounded-full"
                                         style="width: {{ min(($toNumber($ram) ?? 0), 100) }}%"></div>
                                </div>
                            </td>

                            <td class="px-6 py-4 min-w-[140px]">
                                <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                                    <span>{{ $disk !== null && $disk !== '' ? $disk : '%' }}</span>
                                </div>
                                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full {{ $usageBarClass($disk) }} rounded-full"
                                         style="width: {{ min(($toNumber($disk) ?? 0), 100) }}%"></div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-800">
                                    {{ $checkedAt ? \Illuminate\Support\Carbon::parse($checkedAt)->format('Y-m-d H:i:s') : '-' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    {{ $checkedAt ? \Illuminate\Support\Carbon::parse($checkedAt)->diffForHumans() : '' }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @foreach($insights as $insight)
                                        @php
                                            $insightClass = 'bg-green-100 text-green-700';
                                            if (Str::contains(strtolower($insight), ['critical', 'issue', 'unavailable'])) {
                                                $insightClass = 'bg-red-100 text-red-700';
                                            } elseif (Str::contains(strtolower($insight), ['high'])) {
                                                $insightClass = 'bg-yellow-100 text-yellow-700';
                                            }
                                        @endphp
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $insightClass }}">
                                            {{ $insight }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right">
                                @if(!empty($check->server_id) && Route::has('servers.show'))
                                    <a href="{{ route('servers.show', $check->server_id) }}"
                                       class="inline-flex items-center px-4 py-2 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 transition">
                                        View Server
                                    </a>
                                @else
                                    <button type="button"
                                            class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-700 transition">
                                        Log Entry
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                No check logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginator)
            <div class="px-6 py-5 border-t border-slate-100 bg-slate-50">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('tableSearch');
    const statusFilter = document.getElementById('statusFilter');
    const serverFilter = document.getElementById('serverFilter');
    const rows = Array.from(document.querySelectorAll('.check-row'));
    const exportBtn = document.getElementById('exportCsv');
    const autoRefreshBtn = document.getElementById('toggleAutoRefresh');

    let autoRefresh = false;
    let autoRefreshInterval = null;

    function filterRows() {
        const search = (searchInput?.value || '').toLowerCase().trim();
        const status = (statusFilter?.value || '').toLowerCase();
        const server = (serverFilter?.value || '').toLowerCase();

        rows.forEach(row => {
            const rowText = row.innerText.toLowerCase();
            const rowStatus = row.dataset.status || '';
            const rowServer = row.dataset.server || '';

            const matchesSearch = !search || rowText.includes(search);
            const matchesStatus = !status || rowStatus.includes(status);
            const matchesServer = !server || rowServer === server;

            row.style.display = (matchesSearch && matchesStatus && matchesServer) ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', filterRows);
    statusFilter?.addEventListener('change', filterRows);
    serverFilter?.addEventListener('change', filterRows);

    exportBtn?.addEventListener('click', function () {
        const table = document.getElementById('checksTable');
        const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');

        let csv = [];
        csv.push(['Server', 'Status', 'CPU', 'RAM', 'Disk', 'Checked At', 'Sentinel Insight'].join(','));

        visibleRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 7) {
                const data = [
                    `"${cells[0].innerText.replace(/\n/g, ' ').trim()}"`,
                    `"${cells[1].innerText.replace(/\n/g, ' ').trim()}"`,
                    `"${cells[2].innerText.replace(/\n/g, ' ').trim()}"`,
                    `"${cells[3].innerText.replace(/\n/g, ' ').trim()}"`,
                    `"${cells[4].innerText.replace(/\n/g, ' ').trim()}"`,
                    `"${cells[5].innerText.replace(/\n/g, ' ').trim()}"`,
                    `"${cells[6].innerText.replace(/\n/g, ' ').trim()}"`,
                ];
                csv.push(data.join(','));
            }
        });

        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', 'run-check-logs.csv');
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    autoRefreshBtn?.addEventListener('click', function () {
        autoRefresh = !autoRefresh;

        if (autoRefresh) {
            autoRefreshBtn.innerHTML = '<i class="fa-solid fa-rotate mr-2"></i>Auto Refresh: On';
            autoRefreshBtn.classList.remove('bg-white/10');
            autoRefreshBtn.classList.add('bg-green-600');

            autoRefreshInterval = setInterval(() => {
                window.location.reload();
            }, 30000);
        } else {
            autoRefreshBtn.innerHTML = '<i class="fa-solid fa-rotate mr-2"></i>Auto Refresh: Off';
            autoRefreshBtn.classList.add('bg-white/10');
            autoRefreshBtn.classList.remove('bg-green-600');

            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
    });
});
</script>
@endsection