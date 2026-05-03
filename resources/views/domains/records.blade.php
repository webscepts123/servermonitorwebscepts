@extends('layouts.app')

@section('page-title', 'DNS Records - ' . $domain)

@section('content')

<div class="space-y-6">

    <div class="rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black">DNS Records</h1>
                <p class="text-slate-300 mt-2">{{ $domain }}</p>
            </div>

            <a href="{{ route('domains.index') }}"
               class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white font-bold text-center">
                Back to Domains
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-2xl p-4 font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error') || $error)
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-bold">
            {{ session('error') ?? $error }}
        </div>
    @endif

    <div class="bg-white rounded-3xl shadow border p-6">
        <h2 class="text-xl font-black mb-4">Add DNS Record</h2>

        <form method="POST" action="{{ route('domains.records.add') }}" class="grid grid-cols-1 lg:grid-cols-6 gap-4">
            @csrf

            <input type="hidden" name="domain" value="{{ $domain }}">

            <select name="type" class="px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
                <option>A</option>
                <option>AAAA</option>
                <option>CNAME</option>
                <option>MX</option>
                <option>TXT</option>
                <option>NS</option>
                <option>SRV</option>
                <option>CAA</option>
            </select>

            <input type="text"
                   name="host"
                   placeholder="@ or www"
                   class="px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">

            <input type="text"
                   name="record"
                   placeholder="Record value"
                   class="lg:col-span-2 px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500"
                   required>

            <input type="number"
                   name="ttl"
                   value="3600"
                   class="px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">

            <button class="px-5 py-3 rounded-xl bg-blue-600 text-white font-black hover:bg-blue-700">
                Add
            </button>
        </form>
    </div>

    <div class="bg-white rounded-3xl shadow border overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-xl font-black">Records</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
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
                        @endphp

                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4">{{ $recordId ?? '-' }}</td>
                            <td class="p-4 font-black">{{ $record['type'] ?? $record['record-type'] ?? '-' }}</td>
                            <td class="p-4">{{ $record['host'] ?? $record['name'] ?? '@' }}</td>
                            <td class="p-4 break-all">{{ $record['record'] ?? $record['value'] ?? '-' }}</td>
                            <td class="p-4">{{ $record['ttl'] ?? '-' }}</td>
                            <td class="p-4 text-right">
                                @if($recordId)
                                    <form method="POST"
                                          action="{{ route('domains.records.delete') }}"
                                          onsubmit="return confirm('Delete this DNS record?')">
                                        @csrf

                                        <input type="hidden" name="domain" value="{{ $domain }}">
                                        <input type="hidden" name="record_id" value="{{ $recordId }}">

                                        <button class="px-4 py-2 rounded-xl bg-red-600 text-white font-bold">
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

@endsection