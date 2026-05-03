@extends('layouts.app')

@section('page-title', 'Backup Logs')

@section('content')

@php
    $logs = $logs ?? [];
@endphp

<div class="space-y-6">

    <div class="rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black">Backup Logs</h1>
                <p class="text-slate-300 mt-2">
                    Monitor backup transfers, Google Drive sync, failover tasks and Laravel errors.
                </p>
            </div>

            <a href="{{ route('backups.index') }}"
               class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white font-black text-center">
                Back to Backups
            </a>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-900">System Logs</h2>
                <p class="text-slate-500 text-sm">Search and review backup automation logs.</p>
            </div>

            <input type="text"
                   id="logSearch"
                   oninput="filterLogs()"
                   placeholder="Search logs..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @forelse($logs as $name => $content)
            <div class="log-card bg-white rounded-3xl shadow border border-slate-100 overflow-hidden"
                 data-search="{{ strtolower($name.' '.$content) }}">
                <div class="p-5 border-b bg-slate-50 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-900">
                            {{ ucwords(str_replace(['-', '_'], ' ', $name)) }}
                        </h3>
                        <p class="text-sm text-slate-500">
                            Latest 200 lines
                        </p>
                    </div>

                    <button type="button"
                            onclick="copyLog('log-{{ $loop->index }}')"
                            class="px-4 py-2 rounded-xl bg-slate-900 text-white font-black text-sm">
                        Copy
                    </button>
                </div>

                <pre id="log-{{ $loop->index }}"
                     class="bg-slate-950 text-green-400 p-5 text-xs overflow-auto max-h-[520px] whitespace-pre-wrap">{{ $content }}</pre>
            </div>
        @empty
            <div class="xl:col-span-2 bg-white rounded-3xl shadow border border-slate-100 p-10 text-center text-slate-500">
                No logs found.
            </div>
        @endforelse
    </div>

</div>

<script>
function filterLogs() {
    const query = document.getElementById('logSearch')?.value.toLowerCase() || '';

    document.querySelectorAll('.log-card').forEach(function (card) {
        const text = card.getAttribute('data-search') || card.innerText.toLowerCase();
        card.style.display = text.includes(query) ? '' : 'none';
    });
}

function copyLog(id) {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    navigator.clipboard.writeText(el.innerText || el.textContent || '');
    alert('Log copied');
}
</script>

@endsection