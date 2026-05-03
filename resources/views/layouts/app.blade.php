<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Monitor</title>

    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .sidebar-scroll::-webkit-scrollbar { width: 6px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.18); border-radius: 999px; }
        .menu-item.active {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
            box-shadow: 0 10px 25px rgba(37, 99, 235, .35);
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">

<div x-data="{ sidebarOpen: false }" class="flex min-h-screen">

    {{-- Mobile Overlay --}}
    <div x-show="sidebarOpen"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/50 z-40 md:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
           class="fixed md:static z-50 w-80 bg-slate-950 text-white min-h-screen flex flex-col transition-transform duration-300">

        {{-- Logo --}}
        <div class="p-6 border-b border-white/10 bg-gradient-to-r from-slate-950 to-slate-900">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/30">
                    <i class="fa-solid fa-server text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold leading-tight">Server Monitor</h1>
                    <p class="text-sm text-slate-400">Webscepts Monitoring</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-5 overflow-y-auto sidebar-scroll">

            @php
                $menuGroups = [
                    'Main' => [
                        ['Dashboard', 'dashboard.index', 'fa-chart-line'],
                        ['All Servers', 'servers.index', 'fa-server'],
                        ['Add Server', 'servers.create', 'fa-circle-plus'],
                    ],
                    'Monitoring' => [
                        ['Uptime Status', 'monitoring.uptime', 'fa-heart-pulse'],
                        ['Website / Ports', 'monitoring.ports', 'fa-globe'],
                        ['Services', 'monitoring.services', 'fa-gears'],
                        ['CPU / RAM / Disk', 'monitoring.resources', 'fa-microchip'],
                    ],
                    'Security' => [
                        ['Security Alerts', 'security.alerts', 'fa-shield-halved'],
                        ['Firewall Checks', 'security.firewall', 'fa-fire-flame-curved'],
                        ['Abuse Reports', 'security.abuse', 'fa-triangle-exclamation'],
                        ['Email Security', 'security.email', 'fa-envelope-circle-check'],
                        ['SSH Login Attempts', 'security.ssh', 'fa-key'],
                    ],
                    'Backup' => [
                        ['Backup Dashboard', 'backups.index', 'fa-cloud-arrow-up'],
                        ['Google Drive Config', 'backups.google', 'fa-folder-open'],
                        ['Server Transfer', 'backups.transfer', 'fa-right-left'],
                        ['Auto Disk Backup', 'backups.auto', 'fa-hard-drive'],
                        ['Backup Logs', 'backups.logs', 'fa-clipboard-list'],
                    ],
                 
                    'Tools' => [
                        ['SSH Terminal', 'tools.terminal', 'fa-terminal'], // now safe
                        ['Run Checks', 'tools.checks', 'fa-vial-circle-check'],
                        ['Logs Viewer', 'tools.logs', 'fa-file-lines'],
                    ],

                    'CloudDNS' => [
                        ['Domains', 'domains.index', 'fa-globe'],
                    ],
                ];
            @endphp

            @foreach($menuGroups as $group => $items)
                <div>
                    <p class="px-4 mb-2 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                        {{ $group }}
                    </p>

                    <div class="space-y-1">
                        @foreach($items as [$label, $routeName, $icon])
                            <a href="{{ route($routeName) }}"
                               class="menu-item group flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200
                               {{ request()->routeIs($routeName) ? 'active' : 'hover:bg-white/10 hover:translate-x-1' }}">

                                <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center group-hover:bg-white/20 transition">
                                    <i class="fa-solid {{ $icon }}"></i>
                                </span>

                                <span class="font-medium flex-1">{{ $label }}</span>

                                <i class="fa-solid fa-chevron-right text-xs opacity-40 group-hover:opacity-100 transition"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

        </nav>

        {{-- Logout --}}
        <div class="p-4 border-t border-white/10">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-2xl bg-red-600 hover:bg-red-700 transition shadow-lg shadow-red-600/20">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main class="flex-1 md:ml-0">

        {{-- Top Bar --}}
        <header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200 px-6 py-4">
            <div class="flex justify-between items-center gap-4">

                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true"
                            class="md:hidden w-11 h-11 rounded-xl bg-slate-900 text-white flex items-center justify-center">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>
                        <h2 class="text-xl font-bold text-slate-800">
                            @yield('page-title', 'Dashboard')
                        </h2>
                        <p class="text-sm text-slate-500">
                            Monitor server health, backups, security and services
                        </p>
                    </div>
                </div>

                <div class="hidden lg:flex gap-3">
                    <a href="{{ route('servers.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm">
                        <i class="fa-solid fa-plus"></i>
                        Add Server
                    </a>

                    <a href="{{ route('backups.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-green-600 text-white hover:bg-green-700 transition shadow-sm">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        Backup
                    </a>

                    <a href="{{ route('servers.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-purple-600 text-white hover:bg-purple-700 transition shadow-sm">
                        <i class="fa-solid fa-rotate"></i>
                        Run Checks
                    </a>
                </div>
            </div>
        </header>

        <section class="p-6">

            @if(session('success'))
                <div class="mb-5 rounded-2xl bg-green-100 border border-green-300 text-green-800 px-5 py-4 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-5 rounded-2xl bg-red-100 border border-red-300 text-red-800 px-5 py-4 flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <div class="animate-[fadeIn_.3s_ease-in-out]">
                @yield('content')
            </div>

        </section>

    </main>

</div>

{{-- Alpine JS for mobile sidebar --}}
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

</body>
</html>