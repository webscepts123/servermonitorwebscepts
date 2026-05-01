@extends('layouts.app')

@section('page-title', 'WordPress Manager')

@section('content')

<div class="space-y-6">

    {{-- HEADER --}}
    <div class="bg-white rounded-2xl shadow p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">
                WordPress Manager
            </h2>
            <p class="text-slate-500">
                User: {{ $user }} | Server: {{ $server->host }}
            </p>
            <p class="text-xs text-slate-400 mt-1">
                Path: {{ $wpPath }}
            </p>
        </div>

        <a href="{{ route('servers.show', $server) }}"
           class="px-5 py-3 rounded-xl bg-slate-200 text-slate-800 hover:bg-slate-300 text-center">
            Back to Server
        </a>
    </div>

    {{-- SUCCESS --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-xl p-4">
            {!! nl2br(e(session('success'))) !!}
        </div>
    @endif

    {{-- CORE INFO --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-sm text-slate-500">WordPress Version</p>
            <h3 class="text-2xl font-bold mt-2">
                {{ $coreVersion ?: 'Not Detected' }}
            </h3>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-sm text-slate-500">Plugins</p>
            <h3 class="text-2xl font-bold mt-2">
                {{ count($plugins) }}
            </h3>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <p class="text-sm text-slate-500">Themes</p>
            <h3 class="text-2xl font-bold mt-2">
                {{ count($themes) }}
            </h3>
        </div>

    </div>

    {{-- ACTIONS --}}
    <div class="bg-white rounded-2xl shadow p-5 flex flex-wrap gap-3">

        <form method="POST" action="{{ route('servers.wordpress.coreUpdate', [$server, $user]) }}">
            @csrf
            <button class="px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                Update Core
            </button>
        </form>

        <form method="POST" action="{{ route('servers.wordpress.plugins.updateAll', [$server, $user]) }}">
            @csrf
            <button class="px-5 py-3 rounded-xl bg-green-600 text-white hover:bg-green-700">
                Update All Plugins
            </button>
        </form>

    </div>

    {{-- PLUGINS --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">

        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-bold">Plugins</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4">Plugin</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Version</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($plugins as $plugin)
                        <tr class="border-t hover:bg-slate-50">

                            <td class="p-4 font-semibold">
                                {{ $plugin['name'] ?? '-' }}
                            </td>

                            <td class="p-4">
                                @if(($plugin['status'] ?? '') === 'active')
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-bold">
                                        Active
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-sm font-bold">
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            <td class="p-4">
                                {{ $plugin['version'] ?? '-' }}
                            </td>

                            <td class="p-4 text-right space-x-2">

                                @if(($plugin['status'] ?? '') === 'active')
                                    <form method="POST"
                                          action="{{ route('servers.wordpress.plugin.deactivate', [$server, $user]) }}"
                                          class="inline">
                                        @csrf
                                        <input type="hidden" name="plugin" value="{{ $plugin['name'] }}">
                                        <button class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm">
                                            Deactivate
                                        </button>
                                    </form>
                                @else
                                    <form method="POST"
                                          action="{{ route('servers.wordpress.plugin.activate', [$server, $user]) }}"
                                          class="inline">
                                        @csrf
                                        <input type="hidden" name="plugin" value="{{ $plugin['name'] }}">
                                        <button class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm">
                                            Activate
                                        </button>
                                    </form>
                                @endif

                                <form method="POST"
                                      action="{{ route('servers.wordpress.plugin.update', [$server, $user]) }}"
                                      class="inline">
                                    @csrf
                                    <input type="hidden" name="plugin" value="{{ $plugin['name'] }}">
                                    <button class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm">
                                        Update
                                    </button>
                                </form>

                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-6 text-center text-slate-500">
                                No plugins found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    {{-- THEMES --}}
    <div class="bg-white rounded-2xl shadow p-6">

        <h3 class="text-lg font-bold mb-4">Themes</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            @forelse($themes as $theme)
                <div class="border rounded-xl p-4">

                    <h4 class="font-bold">{{ $theme['name'] ?? '-' }}</h4>

                    <p class="text-sm text-slate-500 mt-1">
                        Version: {{ $theme['version'] ?? '-' }}
                    </p>

                    <div class="mt-3">
                        @if(($theme['status'] ?? '') === 'active')
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-bold">
                                Active
                            </span>
                        @else
                            <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-sm font-bold">
                                Installed
                            </span>

                            <form method="POST"
                                  action="{{ route('servers.wordpress.theme.activate', [$server, $user]) }}"
                                  class="mt-3">
                                @csrf
                                <input type="hidden" name="theme" value="{{ $theme['name'] }}">
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">
                                    Activate
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            @empty
                <p class="text-slate-500">No themes found</p>
            @endforelse

        </div>

    </div>

</div>

@endsection