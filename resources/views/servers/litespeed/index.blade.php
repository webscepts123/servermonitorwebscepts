@extends('layouts.app')

@section('page-title', 'LiteSpeed Manager')

@section('content')

<div class="space-y-6">

    <div class="rounded-3xl bg-gradient-to-r from-slate-950 via-red-950 to-slate-900 p-6 text-white shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black">
                    LiteSpeed Manager
                </h1>
                <p class="text-slate-300 mt-2">
                    Manage LiteSpeed/OpenLiteSpeed for {{ $server->name }} - {{ $server->host }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if(Route::has('servers.show'))
                    <a href="{{ route('servers.show', $server) }}"
                       class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white font-bold">
                        Back to Server
                    </a>
                @endif

                <a href="{{ $data['webAdmin'] }}"
                   target="_blank"
                   class="px-5 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-bold">
                    WebAdmin :7080
                </a>
            </div>
        </div>
    </div>

    @if(!empty($data['message']))
        <div class="rounded-2xl bg-red-100 text-red-700 border border-red-300 p-4 font-semibold">
            {{ $data['message'] }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">

        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">Installation</p>
            @if($data['installed'])
                <h2 class="text-2xl font-black text-green-600 mt-2">Installed</h2>
            @else
                <h2 class="text-2xl font-black text-red-600 mt-2">Not Installed</h2>
            @endif
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">Type</p>
            <h2 class="text-xl font-black text-slate-800 mt-2">
                {{ $data['label'] }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">Status</p>
            @php $active = str_contains(strtolower($data['status']), 'active') || str_contains(strtolower($data['status']), 'running'); @endphp
            <h2 class="text-2xl font-black mt-2 {{ $active ? 'text-green-600' : 'text-red-600' }}">
                {{ trim($data['status']) ?: 'Unknown' }}
            </h2>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border">
            <p class="text-slate-500 font-semibold">Version</p>
            <h2 class="text-sm font-black text-slate-800 mt-2 break-words">
                {{ $data['version'] }}
            </h2>
        </div>

    </div>

    <div class="bg-white rounded-3xl shadow border p-6">
        <h2 class="text-2xl font-black text-slate-800 mb-4">LiteSpeed Actions</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">

            <form method="POST" action="{{ route('servers.litespeed.activate', $server) }}">
                @csrf
                <button onclick="return confirm('Activate/start LiteSpeed?')"
                        class="w-full px-5 py-4 rounded-2xl bg-green-600 hover:bg-green-700 text-white font-black">
                    Activate
                </button>
            </form>

            <form method="POST" action="{{ route('servers.litespeed.restart', $server) }}">
                @csrf
                <button onclick="return confirm('Restart LiteSpeed?')"
                        class="w-full px-5 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                    Restart
                </button>
            </form>

            <form method="POST" action="{{ route('servers.litespeed.reload', $server) }}">
                @csrf
                <button onclick="return confirm('Reload LiteSpeed config?')"
                        class="w-full px-5 py-4 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black">
                    Reload
                </button>
            </form>

            <form method="POST" action="{{ route('servers.litespeed.configTest', $server) }}">
                @csrf
                <button class="w-full px-5 py-4 rounded-2xl bg-slate-900 hover:bg-slate-700 text-white font-black">
                    Config Test
                </button>
            </form>

            <form method="POST" action="{{ route('servers.litespeed.stop', $server) }}">
                @csrf
                <button onclick="return confirm('Stop LiteSpeed? This may take websites offline.')"
                        class="w-full px-5 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black">
                    Stop
                </button>
            </form>

        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <div class="bg-white rounded-3xl shadow border p-6">
            <h2 class="text-xl font-black text-slate-800 mb-4">
                Listening Ports
            </h2>

            @if(!empty($data['ports']))
                <pre class="bg-slate-950 text-green-400 rounded-2xl p-4 overflow-x-auto text-xs max-h-80">{{ $data['ports'] }}</pre>
            @else
                <p class="text-slate-500">No LiteSpeed ports detected.</p>
            @endif
        </div>

        <div class="bg-white rounded-3xl shadow border p-6">
            <h2 class="text-xl font-black text-slate-800 mb-4">
                Tools
            </h2>

            <div class="space-y-3">
                <form method="POST" action="{{ route('servers.litespeed.logs', $server) }}">
                    @csrf
                    <button class="w-full px-5 py-3 rounded-2xl bg-orange-600 hover:bg-orange-700 text-white font-black">
                        Check Recent LiteSpeed Logs
                    </button>
                </form>

                <a href="{{ $data['webAdmin'] }}"
                   target="_blank"
                   class="block text-center w-full px-5 py-3 rounded-2xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-black">
                    Open LiteSpeed WebAdmin
                </a>
            </div>
        </div>

    </div>

    <div class="bg-white rounded-3xl shadow border p-6">
        <h2 class="text-xl font-black text-slate-800 mb-4">
            Notes
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-slate-600">
            <div class="rounded-2xl border p-4">
                <h3 class="font-black text-slate-800">Enterprise License</h3>
                <p class="mt-1">
                    LiteSpeed Enterprise usually requires a valid license, especially on cPanel servers.
                </p>
            </div>

            <div class="rounded-2xl border p-4">
                <h3 class="font-black text-slate-800">OpenLiteSpeed</h3>
                <p class="mt-1">
                    OpenLiteSpeed can run without WHM integration but may need manual virtual host setup.
                </p>
            </div>

            <div class="rounded-2xl border p-4">
                <h3 class="font-black text-slate-800">Safe Activation</h3>
                <p class="mt-1">
                    This tool only starts/restarts LiteSpeed if already installed. It does not overwrite Apache config.
                </p>
            </div>
        </div>
    </div>

</div>

@endsection