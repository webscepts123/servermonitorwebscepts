@extends('layouts.developer')

@section('title', 'Web VS Code')

@section('developer-content')

@php
    $developerName = $developer->name
        ?? $developer->cpanel_username
        ?? $developer->email
        ?? 'Developer';

    $developerEmail = $developer->email
        ?? $developer->contact_email
        ?? 'No email';

    $domain = $developer->cpanel_domain
        ?? $developer->domain
        ?? 'developer workspace';

    $projectRoot = $projectRoot
        ?? $developer->project_root
        ?? $developer->allowed_project_path
        ?? null;

    if (!$projectRoot) {
        $projectRoot = '/home/' . ($developer->cpanel_username ?? $developer->ssh_username ?? 'developer') . '/public_html';
    }

    $projectRoot = rtrim($projectRoot, '/');

    /*
    |--------------------------------------------------------------------------
    | Backend editor URL
    |--------------------------------------------------------------------------
    | Must be code-server / VS Code backend URL:
    | https://code-devteengirls.webscepts.com
    |
    | Not normal website:
    | https://dev.teengirls.lk
    |--------------------------------------------------------------------------
    */

    $editorBackendUrl = $editorBackendUrl
        ?? $developer->code_editor_url
        ?? $developer->vscode_url
        ?? null;

    if (!$editorBackendUrl && !empty($developer->cpanel_username)) {
        $editorBackendUrl = 'https://code-' . strtolower($developer->cpanel_username) . '.webscepts.com';
    }

    if ($editorBackendUrl && !str_starts_with($editorBackendUrl, 'http://') && !str_starts_with($editorBackendUrl, 'https://')) {
        $editorBackendUrl = 'https://' . $editorBackendUrl;
    }

    $editorBackendUrl = $editorBackendUrl ? rtrim($editorBackendUrl, '/') : null;
@endphp

<div class="h-screen flex flex-col bg-slate-950 overflow-hidden">

    {{-- Top Bar --}}
    <div class="h-16 bg-slate-950 border-b border-white/10 px-5 lg:px-6 flex items-center justify-between text-white shrink-0">

        <div class="flex items-center gap-4 min-w-0">
            <div class="w-11 h-11 rounded-2xl bg-blue-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-code text-lg"></i>
            </div>

            <div class="min-w-0">
                <h1 class="font-black text-lg leading-tight">
                    Web VS Code
                </h1>

                <p class="text-xs text-slate-400 truncate">
                    Developer: {{ $developerName }}
                </p>
            </div>
        </div>

        <div class="hidden xl:flex items-center gap-3 min-w-0">
            <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black max-w-[320px] truncate">
                Domain: {{ $domain }}
            </span>

            <span class="px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black max-w-[420px] truncate">
                {{ $projectRoot }}
            </span>

            <a href="{{ route('developer.domain.workspace') }}"
               class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-black text-sm">
                <i class="fa-solid fa-arrow-left mr-2"></i>
                Workspace
            </a>
        </div>

        <div class="xl:hidden flex items-center gap-2">
            <a href="{{ route('developer.domain.workspace') }}"
               class="w-10 h-10 rounded-xl bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>
    </div>

    {{-- Info Bar --}}
    <div class="bg-slate-900 border-b border-white/10 px-5 lg:px-6 py-3 text-white shrink-0">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1 rounded-full bg-blue-500/20 text-blue-200 border border-blue-400/30 text-xs font-black">
                    Domain: {{ $domain }}
                </span>

                <span class="px-3 py-1 rounded-full bg-green-500/20 text-green-200 border border-green-400/30 text-xs font-black">
                    User: {{ $developer->cpanel_username ?? $developer->ssh_username ?? '-' }}
                </span>

                <span class="px-3 py-1 rounded-full bg-purple-500/20 text-purple-200 border border-purple-400/30 text-xs font-black">
                    Framework: {{ strtoupper($developer->framework ?? 'custom') }}
                </span>
            </div>

            <div class="text-xs text-slate-400 font-bold break-all">
                VS Code Backend:
                <span class="text-slate-200">{{ $editorBackendUrl ?: 'Not configured' }}</span>
            </div>
        </div>
    </div>

    {{-- Editor Frame --}}
    <div class="flex-1 bg-slate-100 relative">

        @if($editorBackendUrl)
            <iframe
                src="{{ $editorBackendUrl }}"
                class="w-full h-full border-0 bg-white"
                allow="clipboard-read; clipboard-write; fullscreen"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        @else
            <div class="absolute inset-0 flex items-center justify-center p-6">
                <div class="max-w-xl w-full rounded-3xl bg-white border border-red-200 shadow-xl p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
                    </div>

                    <h2 class="text-2xl font-black text-slate-900 mt-5">
                        Code Editor URL Missing
                    </h2>

                    <p class="text-slate-500 mt-2 font-bold">
                        Add a code-server backend URL for this developer account.
                    </p>

                    <div class="mt-5 rounded-2xl bg-slate-100 border border-slate-200 p-4 text-left text-sm font-bold text-slate-700">
                        Example:
                        <div class="mt-1 text-blue-700 break-all">
                            https://code-{{ strtolower($developer->cpanel_username ?? 'username') }}.webscepts.com
                        </div>
                    </div>

                    <a href="{{ route('developer.domain.workspace') }}"
                       class="inline-flex mt-6 px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                        Back to Workspace
                    </a>
                </div>
            </div>
        @endif

    </div>

</div>

@endsection