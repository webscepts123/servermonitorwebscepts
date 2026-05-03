@extends('layouts.app')

@section('page-title', 'Webscepts SentinelCore')

@section('content')

@php
    $securityStats = $securityStats ?? [];
    $securityChecks = $securityChecks ?? [];
    $recentAlerts = $recentAlerts ?? collect();
@endphp

<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <h1 class="text-3xl lg:text-5xl font-black tracking-tight">
                    Webscepts SentinelCore
                </h1>

                <p class="text-slate-300 mt-3 max-w-3xl">
                    Enterprise security technology core for encrypted server credentials, protected backup files,
                    DNS failover safety, customer file protection and database privacy.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        <i class="fa-solid fa-lock mr-1"></i> Encryption Enabled
                    </span>

                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Laravel Crypt
                    </span>

                    <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                        Secure Vault
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                        Customer File Shield
                    </span>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('technology.rotate.passwords') }}"
                  onsubmit="return confirm('Re-encrypt all server password records?')">
                @csrf

                <button class="px-6 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                    <i class="fa-solid fa-arrows-rotate mr-2"></i>
                    Rotate Encryption
                </button>
            </form>
        </div>
    </div>

    {{-- ALERTS --}}
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

    @if(session('encrypted_text'))
        <div class="rounded-3xl bg-white shadow border border-slate-100 p-6">
            <h3 class="text-lg font-black text-slate-900 mb-3">Encrypted Output</h3>
            <textarea readonly class="w-full min-h-32 rounded-xl border p-4 text-xs font-mono bg-slate-50">{{ session('encrypted_text') }}</textarea>
        </div>
    @endif

    @if(session('decrypted_text'))
        <div class="rounded-3xl bg-white shadow border border-slate-100 p-6">
            <h3 class="text-lg font-black text-slate-900 mb-3">Decrypted Output</h3>
            <textarea readonly class="w-full min-h-32 rounded-xl border p-4 text-sm bg-slate-50">{{ session('decrypted_text') }}</textarea>
        </div>
    @endif

    @if(session('encrypted_file_path'))
        <div class="rounded-2xl bg-blue-100 border border-blue-300 text-blue-800 p-4 font-bold">
            <i class="fa-solid fa-file-shield mr-2"></i>
            Encrypted file saved: {{ session('encrypted_file_path') }}
        </div>
    @endif

    {{-- STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Protected Servers</p>
            <h2 class="text-4xl font-black mt-2">{{ $securityStats['servers'] ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Encrypted Credentials</p>
            <h2 class="text-4xl font-black mt-2 text-green-600">{{ $securityStats['encrypted_passwords'] ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">DNS Failover</p>
            <h2 class="text-4xl font-black mt-2 text-blue-600">{{ $securityStats['dns_failover'] ?? 0 }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Backup Failover</p>
            <h2 class="text-4xl font-black mt-2 text-red-600">{{ $securityStats['backup_failover'] ?? 0 }}</h2>
        </div>
    </div>

    {{-- TECHNOLOGY CHECKS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-2xl font-black text-slate-900">SentinelCore Technology Checks</h2>
            <p class="text-slate-500 mt-1">Encryption, database privacy and server file protection status.</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 p-6">
            @foreach($securityChecks as $check)
                <div class="rounded-2xl border p-5 hover:shadow transition">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl {{ $check['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} flex items-center justify-center">
                            <i class="fa-solid {{ $check['status'] ? 'fa-shield-check' : 'fa-triangle-exclamation' }}"></i>
                        </div>

                        <div class="min-w-0">
                            <h3 class="font-black text-slate-900">{{ $check['title'] }}</h3>
                            <p class="text-sm text-slate-500 mt-1">{{ $check['description'] }}</p>

                            <span class="inline-flex mt-3 px-3 py-1 rounded-full text-xs font-black {{ $check['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $check['status'] ? 'Protected' : 'Action Needed' }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ENCRYPTION TOOLS --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- TEXT ENCRYPT --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-xl font-black text-slate-900 mb-2">
                Encrypt Sensitive Text
            </h2>
            <p class="text-sm text-slate-500 mb-5">
                Encrypt API keys, credentials, database secrets or private notes using Laravel Crypt.
            </p>

            <form method="POST" action="{{ route('technology.encrypt.text') }}" class="space-y-4">
                @csrf

                <textarea name="plain_text"
                          rows="6"
                          required
                          placeholder="Paste sensitive text here..."
                          class="w-full rounded-2xl border p-4 outline-none focus:ring-2 focus:ring-blue-500"></textarea>

                <button class="px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    <i class="fa-solid fa-lock mr-2"></i>
                    Encrypt Text
                </button>
            </form>
        </div>

        {{-- TEXT DECRYPT --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <h2 class="text-xl font-black text-slate-900 mb-2">
                Decrypt Protected Text
            </h2>
            <p class="text-sm text-slate-500 mb-5">
                Decrypt only inside authenticated admin panel. Never expose decrypted secrets to public users.
            </p>

            <form method="POST" action="{{ route('technology.decrypt.text') }}" class="space-y-4">
                @csrf

                <textarea name="encrypted_text"
                          rows="6"
                          required
                          placeholder="Paste encrypted payload..."
                          class="w-full rounded-2xl border p-4 outline-none focus:ring-2 focus:ring-purple-500"></textarea>

                <button class="px-5 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-black">
                    <i class="fa-solid fa-unlock-keyhole mr-2"></i>
                    Decrypt Text
                </button>
            </form>
        </div>

    </div>

    {{-- FILE ENCRYPTION --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
            <div>
                <h2 class="text-xl font-black text-slate-900">
                    SentinelCore File Vault
                </h2>
                <p class="text-sm text-slate-500">
                    Encrypt uploaded files before storing them in Laravel storage. Use this for database exports,
                    backup config files, security notes and server credentials.
                </p>
            </div>

            <div class="px-4 py-2 rounded-full bg-slate-100 text-slate-700 text-xs font-black">
                storage/app/sentinel-vault
            </div>
        </div>

        <form method="POST"
              action="{{ route('technology.encrypt.file') }}"
              enctype="multipart/form-data"
              class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @csrf

            <div class="lg:col-span-2">
                <input type="file"
                       name="secure_file"
                       required
                       class="w-full px-4 py-3 rounded-xl border outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <button class="px-5 py-3 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-black">
                <i class="fa-solid fa-file-shield mr-2"></i>
                Encrypt File
            </button>
        </form>
    </div>

    {{-- SECURITY RULES --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <h2 class="text-xl font-black text-slate-900 mb-4">
            Enterprise Protection Rules
        </h2>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Database Protection
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Never store raw passwords, API tokens, customer private data or server credentials directly.
                    Use encryption before saving.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    File Protection
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Store sensitive files only in storage/app. Do not place database exports, .env files or backups
                    inside public_html/public.
                </p>
            </div>

            <div class="rounded-2xl border p-5">
                <h3 class="font-black text-slate-900">
                    Access Protection
                </h3>
                <p class="text-sm text-slate-500 mt-2">
                    Restrict this panel to trusted admins, force HTTPS, rotate passwords and keep APP_KEY secret.
                </p>
            </div>
        </div>
    </div>

    {{-- RECENT ALERTS --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-xl font-black text-slate-900">Recent SentinelCore Alerts</h2>
        </div>

        <div class="divide-y">
            @forelse($recentAlerts as $alert)
                <div class="p-5 hover:bg-slate-50">
                    <div class="flex items-start gap-4">
                        <div class="w-11 h-11 rounded-2xl
                            @if($alert->level === 'danger') bg-red-100 text-red-700
                            @elseif($alert->level === 'warning') bg-yellow-100 text-yellow-700
                            @else bg-blue-100 text-blue-700
                            @endif
                            flex items-center justify-center">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-black text-slate-900">{{ $alert->title }}</h3>
                                <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black uppercase">
                                    {{ $alert->level }}
                                </span>
                            </div>

                            <p class="text-sm text-slate-500 mt-1">
                                {{ \Illuminate\Support\Str::limit($alert->message, 160) }}
                            </p>

                            <p class="text-xs text-slate-400 mt-2">
                                {{ $alert->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-slate-500">
                    No security alerts found.
                </div>
            @endforelse
        </div>
    </div>

</div>

@endsection