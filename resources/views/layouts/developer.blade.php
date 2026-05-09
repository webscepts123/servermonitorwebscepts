<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Developer Workspace') - Webscepts Developer Codes</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Tailwind CDN - No Vite Needed --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Font Awesome CDN --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          referrerpolicy="no-referrer">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        soft: '0 18px 50px rgba(15, 23, 42, 0.10)',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            background:
                linear-gradient(rgba(15, 23, 42, .92), rgba(15, 23, 42, .92)),
                radial-gradient(circle at top right, rgba(37, 99, 235, .28), transparent 35%),
                radial-gradient(circle at bottom left, rgba(220, 38, 38, .20), transparent 35%);
        }

        .developer-sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .developer-sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .developer-sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, .4);
            border-radius: 999px;
        }

        .dev-nav-active {
            background: rgba(37, 99, 235, .18);
            color: #ffffff;
            border-color: rgba(96, 165, 250, .45);
        }

        .dev-nav-link {
            border: 1px solid transparent;
        }

        .dev-nav-link:hover {
            background: rgba(255, 255, 255, .08);
            border-color: rgba(255, 255, 255, .08);
        }

        .glass-card {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            backdrop-filter: blur(16px);
        }

        html {
            scroll-behavior: smooth;
        }
    </style>

    @stack('styles')
</head>

<body class="min-h-screen text-slate-900 antialiased">

@php
    $developer = auth()->guard('developer')->user();

    $developerName = $developer->name
        ?? $developer->cpanel_username
        ?? $developer->email
        ?? 'Developer';

    $developerEmail = $developer->email
        ?? $developer->contact_email
        ?? 'No email';

    $framework = $developer->framework ?? 'custom';

    $projectRoot = $developer->project_root
        ?? $developer->allowed_project_path
        ?? '/home/project/public_html';

    $workspaceRoute = route('developer.domain.workspace');

    $codeEditorRoute = \Illuminate\Support\Facades\Route::has('developer.domain.codeditor')
        ? route('developer.domain.codeditor')
        : url('/codeditor');
@endphp

<div class="min-h-screen flex">

    {{-- Developer Sidebar --}}
    <aside class="hidden lg:flex lg:w-80 bg-slate-950/95 text-white border-r border-white/10 flex-col fixed inset-y-0 left-0 z-40">

        {{-- Brand --}}
        <div class="p-6 border-b border-white/10">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg">
                    <span class="font-black text-xl">WS</span>
                </div>

                <div>
                    <h1 class="font-black text-xl leading-tight">Developer Codes</h1>
                    <p class="text-xs text-slate-400 font-bold mt-1">
                        Webscepts Secure Workspace
                    </p>
                </div>
            </div>

            <div class="mt-5 rounded-2xl bg-white/5 border border-white/10 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-green-500/20 text-green-300 flex items-center justify-center">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="font-black text-sm truncate">{{ $developerName }}</div>
                        <div class="text-xs text-slate-400 truncate">{{ $developerEmail }}</div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="px-3 py-1 rounded-full bg-green-500/15 text-green-300 border border-green-500/25 text-xs font-black">
                        Active
                    </span>

                    <span class="px-3 py-1 rounded-full bg-blue-500/15 text-blue-300 border border-blue-500/25 text-xs font-black">
                        {{ strtoupper($framework) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Menu --}}
        <div class="flex-1 overflow-y-auto developer-sidebar-scroll p-5">

            <div class="mb-6">
                <p class="px-3 mb-3 text-[11px] tracking-[0.3em] text-slate-500 font-black uppercase">
                    Workspace
                </p>

                <nav class="space-y-2">
                    <a href="{{ $workspaceRoute }}"
                       class="dev-nav-link {{ request()->routeIs('developer.domain.workspace') ? 'dev-nav-active' : '' }} flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
                                <i class="fa-solid fa-layer-group"></i>
                            </span>
                            Overview
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>

                    <a href="#project-section"
                       class="dev-nav-link flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
                                <i class="fa-solid fa-folder-tree"></i>
                            </span>
                            Project Files
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>

                    <a href="#commands-section"
                       class="dev-nav-link flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
                                <i class="fa-solid fa-terminal"></i>
                            </span>
                            Commands
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                </nav>
            </div>

            <div class="mb-6">
                <p class="px-3 mb-3 text-[11px] tracking-[0.3em] text-slate-500 font-black uppercase">
                    Developer Tools
                </p>

                <nav class="space-y-2">
                    <a href="{{ $codeEditorRoute }}"
                       target="_blank"
                       class="dev-nav-link flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-blue-500/20 text-blue-300 flex items-center justify-center">
                                <i class="fa-solid fa-code"></i>
                            </span>
                            Web VS Code
                        </span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-slate-400"></i>
                    </a>

                    <a href="#git-section"
                       class="dev-nav-link flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-green-500/20 text-green-300 flex items-center justify-center">
                                <i class="fa-solid fa-code-branch"></i>
                            </span>
                            Git Tools
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>

                    <a href="#database-section"
                       class="dev-nav-link flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center">
                                <i class="fa-solid fa-database"></i>
                            </span>
                            Database Access
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                </nav>
            </div>

            <div class="mb-6">
                <p class="px-3 mb-3 text-[11px] tracking-[0.3em] text-slate-500 font-black uppercase">
                    Settings
                </p>

                <nav class="space-y-2">
                    <a href="#permissions-section"
                       class="dev-nav-link flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-orange-500/20 text-orange-300 flex items-center justify-center">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            Permissions
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>

                    <a href="#account-section"
                       class="dev-nav-link flex items-center justify-between px-4 py-3 rounded-2xl transition">
                        <span class="flex items-center gap-3 font-black">
                            <span class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-300 flex items-center justify-center">
                                <i class="fa-solid fa-user-gear"></i>
                            </span>
                            Account Settings
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400"></i>
                    </a>
                </nav>
            </div>

            <div class="rounded-2xl glass-card p-4">
                <p class="text-xs text-slate-400 font-bold">Project Root</p>
                <p class="text-xs text-white font-black mt-2 break-all">{{ $projectRoot }}</p>
            </div>
        </div>

        {{-- Logout --}}
        <div class="p-5 border-t border-white/10">
            <form method="POST" action="{{ route('developer.logout') }}">
                @csrf

                <button type="submit"
                        class="w-full flex items-center justify-center gap-3 px-5 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black transition">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Developer Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- Mobile Top --}}
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-slate-950 text-white border-b border-white/10 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center font-black">
                    WS
                </div>
                <div>
                    <div class="font-black">Developer Codes</div>
                    <div class="text-xs text-slate-400">{{ $developerName }}</div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ $codeEditorRoute }}"
                   target="_blank"
                   class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-code"></i>
                </a>

                <form method="POST" action="{{ route('developer.logout') }}">
                    @csrf
                    <button class="w-10 h-10 rounded-xl bg-red-600 flex items-center justify-center">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Main --}}
    <main class="flex-1 lg:ml-80 pt-20 lg:pt-0">
        <div class="min-h-screen bg-slate-100">
            @yield('developer-content')
        </div>
    </main>

</div>

@stack('scripts')

</body>
</html>