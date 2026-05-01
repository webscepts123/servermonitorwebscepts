@extends('layouts.app')

@section('page-title', 'System Logs')

@section('content')

<div class="space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-2xl shadow p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">System Logs</h2>
            <p class="text-slate-500">View server logs, security logs, cPanel logs and application logs.</p>
        </div>

        <a href="{{ route('dashboard') }}"
           class="px-5 py-3 rounded-xl bg-slate-200 text-slate-800 hover:bg-slate-300 text-center">
            Back to Dashboard
        </a>
    </div>

    {{-- Messages --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-xl p-4">
            {!! nl2br(e(session('success'))) !!}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-xl p-4">
            {!! nl2br(e(session('error'))) !!}
        </div>
    @endif

    {{-- Log Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Security Logs</p>
            <h3 class="text-3xl font-bold mt-2">{{ $securityCount ?? 0 }}</h3>
            <p class="text-xs text-slate-400 mt-2">Auth, SSH, firewall and brute-force events</p>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">cPanel Logs</p>
            <h3 class="text-3xl font-bold mt-2">{{ $cpanelCount ?? 0 }}</h3>
            <p class="text-xs text-slate-400 mt-2">WHM, cPanel and account activity</p>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">Email Logs</p>
            <h3 class="text-3xl font-bold mt-2">{{ $emailCount ?? 0 }}</h3>
            <p class="text-xs text-slate-400 mt-2">Exim queue, mail errors and spam activity</p>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-slate-500 text-sm">App Logs</p>
            <h3 class="text-3xl font-bold mt-2">{{ $appCount ?? 0 }}</h3>
            <p class="text-xs text-slate-400 mt-2">Laravel application logs</p>
        </div>

    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow p-6">
        <form method="GET" action="{{ route('tools.logs') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div>
                <label class="block text-sm font-semibold mb-1">Server</label>
                <select name="server_id" class="w-full border rounded-xl p-3">
                    <option value="">All Servers</option>

                    @foreach($servers ?? [] as $server)
                        <option value="{{ $server->id }}" {{ request('server_id') == $server->id ? 'selected' : '' }}>
                            {{ $server->name }} - {{ $server->host }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Log Type</label>
                <select name="type" class="w-full border rounded-xl p-3">
                    <option value="">All Types</option>
                    <option value="security" {{ request('type') == 'security' ? 'selected' : '' }}>Security</option>
                    <option value="cpanel" {{ request('type') == 'cpanel' ? 'selected' : '' }}>cPanel / WHM</option>
                    <option value="email" {{ request('type') == 'email' ? 'selected' : '' }}>Email</option>
                    <option value="ssh" {{ request('type') == 'ssh' ? 'selected' : '' }}>SSH</option>
                    <option value="app" {{ request('type') == 'app' ? 'selected' : '' }}>Laravel App</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Level</label>
                <select name="level" class="w-full border rounded-xl p-3">
                    <option value="">All Levels</option>
                    <option value="danger" {{ request('level') == 'danger' ? 'selected' : '' }}>Danger</option>
                    <option value="warning" {{ request('level') == 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="info" {{ request('level') == 'info' ? 'selected' : '' }}>Info</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button class="w-full px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                    Filter
                </button>

                <a href="{{ route('tools.logs') }}"
                   class="px-5 py-3 rounded-xl bg-slate-200 text-slate-800 hover:bg-slate-300">
                    Reset
                </a>
            </div>

        </form>
    </div>

    {{-- Quick Log Commands --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">

        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-800">SSH Auth Logs</h3>
            <p class="text-sm text-slate-500 mt-1">Recent SSH login and failed login attempts.</p>
            <code class="block mt-3 bg-slate-900 text-green-400 rounded-xl p-3 text-xs overflow-x-auto">
                tail -n 100 /var/log/secure
            </code>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-800">cPanel Login Logs</h3>
            <p class="text-sm text-slate-500 mt-1">cPanel and WHM login activity.</p>
            <code class="block mt-3 bg-slate-900 text-green-400 rounded-xl p-3 text-xs overflow-x-auto">
                tail -n 100 /usr/local/cpanel/logs/login_log
            </code>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-800">Exim Main Log</h3>
            <p class="text-sm text-slate-500 mt-1">Mail delivery and spam debugging.</p>
            <code class="block mt-3 bg-slate-900 text-green-400 rounded-xl p-3 text-xs overflow-x-auto">
                tail -n 100 /var/log/exim_mainlog
            </code>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-800">Laravel Log</h3>
            <p class="text-sm text-slate-500 mt-1">Application errors and debug logs.</p>
            <code class="block mt-3 bg-slate-900 text-green-400 rounded-xl p-3 text-xs overflow-x-auto">
                tail -n 100 storage/logs/laravel.log
            </code>
        </div>

    </div>

    {{-- Logs Table --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">

        <div class="px-6 py-5 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h3 class="text-xl font-bold">Recent Log Entries</h3>
                <p class="text-sm text-slate-500">Security, server and application log history.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4">Time</th>
                        <th class="p-4">Server</th>
                        <th class="p-4">Type</th>
                        <th class="p-4">Level</th>
                        <th class="p-4">Message</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs ?? [] as $log)
                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4 whitespace-nowrap">
                                {{ $log['time'] ?? $log->created_at ?? '-' }}
                            </td>

                            <td class="p-4">
                                {{ $log['server'] ?? $log->server->name ?? '-' }}
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">
                                    {{ strtoupper($log['type'] ?? $log->type ?? 'LOG') }}
                                </span>
                            </td>

                            <td class="p-4">
                                @php
                                    $level = $log['level'] ?? $log->level ?? 'info';
                                @endphp

                                <span class="px-3 py-1 rounded-full text-xs font-bold
                                    @if($level === 'danger') bg-red-100 text-red-700
                                    @elseif($level === 'warning') bg-yellow-100 text-yellow-700
                                    @else bg-blue-100 text-blue-700
                                    @endif">
                                    {{ strtoupper($level) }}
                                </span>
                            </td>

                            <td class="p-4 text-slate-600 break-words">
                                {{ $log['message'] ?? $log->message ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center text-slate-500">
                                No logs found yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    {{-- Raw Output --}}
    @if(isset($rawOutput) && $rawOutput)
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="font-bold">Raw Log Output</h3>
            </div>

            <pre class="bg-slate-950 text-green-400 p-5 overflow-x-auto text-xs max-h-[600px] whitespace-pre-wrap">{{ $rawOutput }}</pre>
        </div>
    @endif

</div>

@endsection