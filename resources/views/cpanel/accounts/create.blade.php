@extends('layouts.app')

@section('page-title', 'Create cPanel Account')

@section('content')

<div class="max-w-4xl mx-auto">

    <div class="bg-white rounded-2xl shadow p-6">

        <h2 class="text-2xl font-bold mb-1">Create cPanel Account</h2>
        <p class="text-slate-500 mb-6">{{ $server->name }} - {{ $server->host }}</p>

        @if($error)
            <div class="mb-5 bg-red-100 text-red-700 border border-red-300 rounded-xl p-4">
                {{ $error }}
            </div>
        @endif

        <form method="POST" action="{{ route('servers.cpanel.store', $server) }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label class="block text-sm font-semibold mb-1">Domain</label>
                    <input type="text" name="domain" value="{{ old('domain') }}"
                           placeholder="example.com"
                           class="w-full border rounded-xl p-3">
                    @error('domain') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Username</label>
                    <input type="text" name="username" value="{{ old('username') }}"
                           placeholder="example"
                           class="w-full border rounded-xl p-3">
                    @error('username') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Password</label>
                    <input type="password" name="password"
                           class="w-full border rounded-xl p-3">
                    @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Contact Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full border rounded-xl p-3">
                    @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Package</label>
                    <select name="package" class="w-full border rounded-xl p-3">
                        <option value="">Select Package</option>
                        @foreach($packages as $package)
                            @php
                                $pkgName = $package['name'] ?? $package['pkg'] ?? null;
                            @endphp

                            @if($pkgName)
                                <option value="{{ $pkgName }}" {{ old('package') == $pkgName ? 'selected' : '' }}>
                                    {{ $pkgName }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @error('package') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">IP Address</label>
                    <select name="ip" class="w-full border rounded-xl p-3">
                        <option value="">Auto / Shared IP</option>
                        @foreach($ips as $ip)
                            @php
                                $ipValue = $ip['ip'] ?? null;
                            @endphp

                            @if($ipValue)
                                <option value="{{ $ipValue }}" {{ old('ip') == $ipValue ? 'selected' : '' }}>
                                    {{ $ipValue }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @error('ip') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <button class="w-full sm:w-auto px-6 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                    Create Account
                </button>

                <a href="{{ route('servers.cpanel.index', $server) }}"
                   class="w-full sm:w-auto text-center px-6 py-3 rounded-xl bg-slate-200 text-slate-800 hover:bg-slate-300">
                    Cancel
                </a>
            </div>
        </form>

    </div>

</div>

@endsection