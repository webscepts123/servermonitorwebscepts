@extends('layouts.app')

@section('page-title', 'DNS Records - ' . $domain)

@section('content')

@php
    $records = $records ?? [];
    $error = $error ?? null;
@endphp

<div class="space-y-6">

    {{-- HERO --}}
    <div class="rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black">DNS Records</h1>
                <p class="text-slate-300 mt-2">{{ $domain }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Records: {{ count($records) }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Provider: ClouDNS
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button"
                        onclick="toggleBox('addRecordBox')"
                        class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    <i class="fa-solid fa-plus mr-2"></i>
                    Add Record
                </button>

                <a href="{{ route('domains.index') }}"
                   class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white font-bold text-center">
                    Back to Domains
                </a>
            </div>
        </div>
    </div>

    {{-- SESSION ALERTS --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-2xl p-4 font-bold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error') || $error)
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-bold">
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

    {{-- ADD RECORD --}}
    <div id="addRecordBox" class="bg-white rounded-3xl shadow border p-6">
        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-xl font-black text-slate-900">Add DNS Record</h2>
                <p class="text-sm text-slate-500">Add A, CNAME, TXT, MX and other records to {{ $domain }}.</p>
            </div>

            <button type="button"
                    onclick="toggleBox('addRecordBox')"
                    class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('domains.records.add') }}" class="grid grid-cols-1 lg:grid-cols-6 gap-4">
            @csrf

            <input type="hidden" name="domain" value="{{ $domain }}">

            <div>
                <label class="block text-sm font-black text-slate-700 mb-1">Type</label>
                <select name="type" class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                    <option>A</option>
                    <option>AAAA</option>
                    <option>CNAME</option>
                    <option>MX</option>
                    <option>TXT</option>
                    <option>NS</option>
                    <option>SRV</option>
                    <option>CAA</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-black text-slate-700 mb-1">Host</label>
                <input type="text"
                       name="host"
                       placeholder="@ or www"
                       class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="lg:col-span-2">
                <label class="block text-sm font-black text-slate-700 mb-1">Record Value</label>
                <input type="text"
                       name="record"
                       placeholder="IP address or value"
                       class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500"
                       required>
            </div>

            <div>
                <label class="block text-sm font-black text-slate-700 mb-1">TTL</label>
                <input type="number"
                       name="ttl"
                       value="300"
                       min="60"
                       class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-end">
                <button class="w-full px-5 py-3 rounded-xl bg-blue-600 text-white font-black hover:bg-blue-700">
                    Add
                </button>
            </div>
        </form>
    </div>

    {{-- SEARCH --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-900">DNS Records</h2>
                <p class="text-slate-500 text-sm">Search and manage existing DNS records.</p>
            </div>

            <input type="text"
                   id="recordSearch"
                   oninput="filterTable('recordSearch', '#recordsTable tbody tr')"
                   placeholder="Search records..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    {{-- RECORD TABLE --}}
    <div class="bg-white rounded-3xl shadow border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="recordsTable">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4 text-left">ID</th>
                        <th class="p-4 text-left">Type</th>
                        <th class="p-4 text-left">Host</th>
                        <th class="p-4 text-left">Record</th>
                        <th class="p-4 text-left">TTL</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($records as $record)
                        @php
                            $recordId = $record['id'] ?? $record['record-id'] ?? null;
                            $recordType = $record['type'] ?? $record['record-type'] ?? '-';
                            $recordHost = $record['host'] ?? $record['name'] ?? '@';
                            $recordValue = $record['record'] ?? $record['value'] ?? '-';
                            $recordTtl = $record['ttl'] ?? '-';
                        @endphp

                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4">{{ $recordId ?? '-' }}</td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-black">
                                    {{ strtoupper($recordType) }}
                                </span>
                            </td>

                            <td class="p-4 font-bold">
                                {{ $recordHost ?: '@' }}
                            </td>

                            <td class="p-4 break-all">
                                {{ $recordValue }}
                            </td>

                            <td class="p-4">
                                {{ $recordTtl }}
                            </td>

                            <td class="p-4 text-right">
                                @if($recordId)
                                    <form method="POST"
                                          action="{{ route('domains.records.delete') }}"
                                          onsubmit="return confirm('Delete this DNS record?')">
                                        @csrf

                                        <input type="hidden" name="domain" value="{{ $domain }}">
                                        <input type="hidden" name="record_id" value="{{ $recordId }}">

                                        <button class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-10 text-center text-slate-500">
                                No DNS records found.
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