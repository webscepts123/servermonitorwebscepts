@extends('layouts.app')

@section('page-title', 'Web Scan Report')

@section('content')

@php
    $riskClass = match($scan->risk_level) {
        'critical' => 'bg-red-100 text-red-700',
        'high' => 'bg-orange-100 text-orange-700',
        'medium' => 'bg-yellow-100 text-yellow-700',
        default => 'bg-green-100 text-green-700',
    };
@endphp

<div class="space-y-6">

    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-bold">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
            <div>
                <h1 class="text-3xl lg:text-4xl font-black">SentinelCore Web Scan Report</h1>
                <p class="text-slate-300 mt-2">{{ $scan->url }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        Domain: {{ $scan->domain }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        IP: {{ $scan->ip ?? '-' }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-white/10 border border-white/20 text-xs font-bold">
                        HTTP: {{ $scan->http_status ?? 'N/A' }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-xs font-bold">
                        Risk: {{ strtoupper($scan->risk_level) }} / {{ $scan->risk_score }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('technology.webscanner.rescan', $scan) }}">
                    @csrf
                    <button class="px-5 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                        Re-scan
                    </button>
                </form>

                <a href="{{ route('technology.webscanner.index') }}"
                   class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white font-black">
                    Back
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">Risk Level</p>
            <span class="inline-block mt-3 px-4 py-2 rounded-full {{ $riskClass }} font-black uppercase">
                {{ $scan->risk_level }}
            </span>
        </div>

        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">Risk Score</p>
            <h2 class="text-4xl font-black mt-2">{{ $scan->risk_score }}/100</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">SSL Status</p>
            <h2 class="text-2xl font-black mt-2 {{ $scan->ssl_valid ? 'text-green-600' : 'text-red-600' }}">
                {{ $scan->ssl_valid ? 'Valid' : 'Invalid' }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow border p-6">
            <p class="text-slate-500 font-bold">Response Time</p>
            <h2 class="text-2xl font-black mt-2">{{ $scan->response_time_ms ?? 'N/A' }} ms</h2>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow border p-6">
        <h2 class="text-2xl font-black mb-3">Summary</h2>
        <p class="text-slate-600 font-semibold">{{ $scan->summary }}</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b">
                <h2 class="text-xl font-black">Detected Technologies</h2>
            </div>
            <div class="p-5 space-y-3">
                @forelse($scan->detected_technologies ?? [] as $tech)
                    <div class="rounded-2xl border p-4">
                        <p class="font-black">{{ $tech['name'] ?? '-' }}</p>
                        <p class="text-sm text-slate-500">{{ $tech['value'] ?? '-' }}</p>
                    </div>
                @empty
                    <p class="text-slate-500">No technologies detected.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b">
                <h2 class="text-xl font-black">Missing Security Headers</h2>
            </div>
            <div class="p-5 flex flex-wrap gap-2">
                @forelse($scan->missing_headers ?? [] as $header)
                    <span class="px-3 py-2 rounded-xl bg-red-100 text-red-700 text-xs font-black">
                        {{ $header }}
                    </span>
                @empty
                    <span class="px-3 py-2 rounded-xl bg-green-100 text-green-700 text-xs font-black">
                        All important headers detected
                    </span>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b">
                <h2 class="text-xl font-black">Exposed Files</h2>
            </div>
            <div class="p-5 space-y-3">
                @forelse($scan->exposed_files ?? [] as $file)
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                        <p class="font-black text-red-700">{{ $file['path'] ?? '-' }}</p>
                        <p class="text-sm text-red-600">{{ $file['risk'] ?? '-' }}</p>
                    </div>
                @empty
                    <p class="text-slate-500">No exposed sensitive files found.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b">
                <h2 class="text-xl font-black">Database Risks</h2>
            </div>
            <div class="p-5 space-y-3">
                @forelse($scan->database_risks ?? [] as $risk)
                    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                        <p class="font-black text-orange-700">{{ $risk['title'] ?? '-' }}</p>
                        <p class="text-sm text-orange-600">{{ $risk['risk'] ?? '-' }}</p>
                    </div>
                @empty
                    <p class="text-slate-500">No database risks detected.</p>
                @endforelse
            </div>
        </div>

        <div class="xl:col-span-2 bg-white rounded-3xl shadow border overflow-hidden">
            <div class="p-5 border-b">
                <h2 class="text-xl font-black">Framework Security Risks</h2>
            </div>
            <div class="p-5 grid grid-cols-1 xl:grid-cols-2 gap-4">
                @forelse($scan->framework_risks ?? [] as $risk)
                    <div class="rounded-2xl border p-4">
                        <p class="font-black">{{ $risk['framework'] ?? 'Framework' }} — {{ $risk['title'] ?? '-' }}</p>
                        <p class="text-sm text-slate-500 mt-1">{{ $risk['risk'] ?? '-' }}</p>
                    </div>
                @empty
                    <p class="text-slate-500">No framework-specific risks detected.</p>
                @endforelse
            </div>
        </div>

    </div>
</div>

@endsection