@extends('layouts.developer')

@section('title', 'Code Editor')

@section('developer-content')

@php
    $developerName = $developer->name
        ?? $developer->cpanel_username
        ?? $developer->email
        ?? 'Developer';

    $projectRoot = $developer->project_root
        ?? $developer->allowed_project_path
        ?? '/home/project/public_html';
@endphp

<div class="h-screen flex flex-col bg-slate-950">

    {{-- Top Bar --}}
    <div class="h-16 bg-slate-950 border-b border-white/10 px-6 flex items-center justify-between text-white shrink-0">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center">
                <i class="fa-solid fa-code"></i>
            </div>

            <div>
                <h1 class="font-black text-lg">Web VS Code</h1>
                <p class="text-xs text-slate-400">
                    Developer: {{ $developerName }}
                </p>
            </div>
        </div>

        <div class="hidden lg:flex items-center gap-3">
            <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black max-w-[600px] truncate">
                {{ $projectRoot }}
            </span>

            <a href="{{ route('developer.domain.workspace') }}"
               class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-black text-sm">
                <i class="fa-solid fa-arrow-left mr-2"></i>
                Workspace
            </a>
        </div>
    </div>

    {{-- Editor Frame --}}
    <div class="flex-1 bg-slate-900">
        <iframe
            src="{{ $editorBackendUrl }}"
            class="w-full h-full border-0"
            allow="clipboard-read; clipboard-write; fullscreen"
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>

</div>

@endsection