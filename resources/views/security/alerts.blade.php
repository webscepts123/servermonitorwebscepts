@extends('layouts.app')

@section('page-title', 'Security Alerts')

@section('content')

<div class="space-y-6">

    {{-- HEADER --}}
    <div class="bg-white rounded-2xl shadow p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold">Security Alerts</h2>
            <p class="text-slate-500">
                Monitor threats, vulnerabilities, and server risks in real-time
            </p>
        </div>

        {{-- ✅ FIXED BUTTON --}}
        @if(isset($server) && $server)
            <form method="POST" action="{{ route('servers.securityScan', $server) }}">
                @csrf
                <button class="px-5 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700">
                    Run Security Scan
                </button>
            </form>
        @else
            <a href="{{ route('servers.index') }}"
               class="px-5 py-3 bg-slate-700 text-white rounded-xl hover:bg-slate-800">
                Select Server First
            </a>
        @endif
    </div>

    {{-- SUCCESS MESSAGE --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-xl p-4">
            {!! nl2br(e(session('success'))) !!}
        </div>
    @endif

    {{-- ALERT STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

        <div class="bg-red-100 p-5 rounded-xl shadow">
            <p class="text-sm text-red-600">Critical</p>
            <h3 class="text-3xl font-bold text-red-700">
                {{ $critical ?? 0 }}
            </h3>
        </div>

        <div class="bg-yellow-100 p-5 rounded-xl shadow">
            <p class="text-sm text-yellow-600">Warnings</p>
            <h3 class="text-3xl font-bold text-yellow-700">
                {{ $warnings ?? 0 }}
            </h3>
        </div>

        <div class="bg-blue-100 p-5 rounded-xl shadow">
            <p class="text-sm text-blue-600">Info</p>
            <h3 class="text-3xl font-bold text-blue-700">
                {{ $info ?? 0 }}
            </h3>
        </div>

        <div class="bg-green-100 p-5 rounded-xl shadow">
            <p class="text-sm text-green-600">Resolved</p>
            <h3 class="text-3xl font-bold text-green-700">
                {{ $resolved ?? 0 }}
            </h3>
        </div>

    </div>

    {{-- ALERT LIST --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">

        <div class="px-6 py-4 border-b font-bold text-lg">
            Recent Alerts
        </div>

        <div class="divide-y">

            @forelse($alerts ?? [] as $alert)
                <div class="p-4 flex justify-between items-center">

                    <div>
                        <p class="font-bold text-slate-800">
                            {{ $alert['title'] ?? 'Unknown Alert' }}
                        </p>

                        <p class="text-sm text-slate-500">
                            {{ $alert['time'] ?? now() }}
                        </p>
                    </div>

                    <span class="px-3 py-1 rounded-full text-sm font-bold
                        @if(($alert['level'] ?? '') == 'critical') bg-red-100 text-red-700
                        @elseif(($alert['level'] ?? '') == 'warning') bg-yellow-100 text-yellow-700
                        @elseif(($alert['level'] ?? '') == 'info') bg-blue-100 text-blue-700
                        @else bg-green-100 text-green-700
                        @endif
                    ">
                        {{ strtoupper($alert['level'] ?? 'INFO') }}
                    </span>

                </div>
            @empty
                <div class="p-6 text-center text-slate-500">
                    No security alerts found
                </div>
            @endforelse

        </div>
    </div>

    {{-- SYSTEM STATUS --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <div class="bg-white p-5 rounded-xl shadow">
            <h4 class="font-bold">Firewall</h4>
            <p class="text-green-600 font-bold mt-2">
                {{ $firewall ?? 'ACTIVE' }}
            </p>
        </div>

        <div class="bg-white p-5 rounded-xl shadow">
            <h4 class="font-bold">SSH Protection</h4>
            <p class="text-yellow-600 font-bold mt-2">
                {{ $ssh ?? 'CHECK REQUIRED' }}
            </p>
        </div>

        <div class="bg-white p-5 rounded-xl shadow">
            <h4 class="font-bold">cPanel Version</h4>
            <p class="text-red-600 font-bold mt-2">
                {{ $cpanel ?? 'UPDATE REQUIRED' }}
            </p>
        </div>

    </div>

</div>

@endsection