@extends('layouts.app')

@section('page-title', 'SSH Terminal')

@section('content')

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @forelse($servers as $server)
        <div class="bg-white p-5 rounded-2xl shadow border">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-11 h-11 bg-slate-900 text-white rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-terminal"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg">{{ $server->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $server->host }}</p>
                </div>
            </div>

            <a href="{{ route('tools.terminal.connect', $server->id) }}"
               class="block text-center bg-slate-900 text-white py-3 rounded-xl hover:bg-slate-700 transition">
                Open Terminal
            </a>
        </div>
    @empty
        <div class="bg-white p-6 rounded-2xl shadow">
            No active servers found.
        </div>
    @endforelse
</div>

@endsection