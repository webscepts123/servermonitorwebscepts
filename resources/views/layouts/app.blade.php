<!DOCTYPE html>
<html lang="en"
      x-data="enterpriseLayout()"
      x-init="initNotifications()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('page-title', 'Dashboard') - Webscepts Monitoring</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        [x-cloak] {
            display: none !important;
        }

        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, .35);
            border-radius: 999px;
        }

        .ws-sidebar {
            background:
                radial-gradient(circle at top right, rgba(0, 96, 255, .12), transparent 32%),
                radial-gradient(circle at bottom left, rgba(210, 0, 0, .08), transparent 35%),
                #071126;
        }

        .ws-logo-mark {
            background: linear-gradient(135deg, #082b70, #0b63ce);
        }

        .ws-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 46px;
            padding: 10px 12px;
            border-radius: 14px;
            color: #cbd5e1;
            font-weight: 700;
            font-size: 14px;
            transition: all .18s ease;
        }

        .ws-menu-item:hover {
            background: rgba(255, 255, 255, .07);
            color: #ffffff;
        }

        .ws-menu-item.active {
            background: linear-gradient(135deg, #063a91, #0b63ce);
            color: #ffffff;
            box-shadow: 0 12px 26px rgba(11, 99, 206, .25);
        }

        .ws-menu-icon {
            width: 34px;
            height: 34px;
            border-radius: 11px;
            background: rgba(255, 255, 255, .08);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }

        .ws-menu-item.active .ws-menu-icon {
            background: rgba(255, 255, 255, .16);
        }

        .ws-menu-label {
            color: #64748b;
            font-size: 11px;
            letter-spacing: .16em;
            font-weight: 900;
            text-transform: uppercase;
        }

        .ws-top-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 14px;
            transition: all .18s ease;
            white-space: nowrap;
        }

        .ws-top-button:hover {
            transform: translateY(-1px);
        }

        .ws-fade {
            animation: wsFade .22s ease-in-out;
        }

        .ws-toast {
            animation: wsToast .28s ease-out;
        }

        @keyframes wsFade {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes wsToast {
            from {
                opacity: 0;
                transform: translateX(30px) scale(.98);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
    </style>
</head>

@php
    $notificationItems = collect();

    if (class_exists(\App\Models\ServerSecurityAlert::class)) {
        try {
            $notificationItems = \App\Models\ServerSecurityAlert::query()
                ->latest()
                ->limit(8)
                ->get();
        } catch (\Throwable $e) {
            $notificationItems = collect();
        }
    }

    $unreadNotificationCount = $notificationItems
        ->filter(fn ($item) => empty($item->is_resolved))
        ->count();

    $latestCriticalNotification = $notificationItems
        ->first(fn ($item) => in_array($item->level ?? '', ['danger', 'critical', 'warning']));
@endphp

<body class="bg-slate-100 min-h-screen text-slate-800">

<div class="flex min-h-screen">

    {{-- Mobile Overlay --}}
    <div x-cloak
         x-show="sidebarOpen"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-slate-950/70 z-40 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="ws-sidebar fixed lg:static z-50 w-[280px] max-w-[86vw] min-h-screen flex flex-col transition-transform duration-300 shadow-2xl">

        {{-- Brand --}}
        <div class="px-5 py-5 border-b border-white/10">
            <div class="flex items-center gap-3">

                <div class="w-11 h-11 rounded-2xl ws-logo-mark flex items-center justify-center shadow-lg">
                    <span class="text-white font-black text-lg tracking-tight">WS</span>
                </div>

                <div class="min-w-0">
                    <h1 class="text-white text-lg font-black leading-tight">
                        Webscepts
                    </h1>
                    <p class="text-slate-400 text-xs font-semibold">
                        Enterprise Monitoring
                    </p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-white/5 border border-white/10 p-2 text-center">
                    <p class="text-white text-sm font-black">24/7</p>
                    <p class="text-[10px] text-slate-400">Live</p>
                </div>

                <div class="rounded-xl bg-white/5 border border-white/10 p-2 text-center">
                    <p class="text-white text-sm font-black">SMS</p>
                    <p class="text-[10px] text-slate-400">Alerts</p>
                </div>

                <div class="rounded-xl bg-white/5 border border-white/10 p-2 text-center">
                    <p class="text-white text-sm font-black">SEC</p>
                    <p class="text-[10px] text-slate-400">Shield</p>
                </div>
            </div>
        </div>

        {{-- Menu --}}
        <nav class="flex-1 px-4 py-5 space-y-6 overflow-y-auto sidebar-scroll">

            @php
                $menuGroups = [
                    'Main' => [
                        ['Dashboard', 'dashboard.index', 'fa-chart-line', ['dashboard.*']],
                        ['All Servers', 'servers.index', 'fa-server', ['servers.index', 'servers.show', 'servers.edit']],
                        ['Add Server', 'servers.create', 'fa-circle-plus', ['servers.create']],
                    ],

                    'Panel Accounts' => [
                        ['cPanel / WHM', 'panel.cpanel', 'fa-cpanel', ['panel.cpanel']],
                        ['Plesk Accounts', 'panel.plesk', 'fa-layer-group', ['panel.plesk']],
                        ['WordPress', 'panel.wordpress', 'fa-wordpress', ['panel.wordpress']],
                    ],

                    'Monitoring' => [
                        ['Uptime Status', 'monitoring.uptime', 'fa-heart-pulse', ['monitoring.uptime']],
                        ['Website / Ports', 'monitoring.ports', 'fa-globe', ['monitoring.ports']],
                        ['Services', 'monitoring.services', 'fa-gears', ['monitoring.services']],
                        ['CPU / RAM / Disk', 'monitoring.resources', 'fa-microchip', ['monitoring.resources']],
                    ],

                    'Security' => [
                        ['Security Alerts', 'security.alerts', 'fa-shield-halved', ['security.alerts']],
                        ['Firewall Checks', 'security.firewall', 'fa-fire-flame-curved', ['security.firewall']],
                        ['Abuse Reports', 'security.abuse', 'fa-triangle-exclamation', ['security.abuse']],
                        ['Email Security', 'security.email', 'fa-envelope-circle-check', ['security.email']],
                        ['SSH Attempts', 'security.ssh', 'fa-key', ['security.ssh']],
                    ],

                    'Backup' => [
                        ['Backup Dashboard', 'backups.index', 'fa-cloud-arrow-up', ['backups.index']],
                        ['Google Drive', 'backups.google', 'fa-folder-open', ['backups.google']],
                        ['Server Transfer', 'backups.transfer', 'fa-right-left', ['backups.transfer']],
                        ['Auto Disk Backup', 'backups.auto', 'fa-hard-drive', ['backups.auto']],
                        ['Backup Logs', 'backups.logs', 'fa-clipboard-list', ['backups.logs']],
                    ],

                    'Tools' => [
                        ['SSH Terminal', 'tools.terminal', 'fa-terminal', ['tools.terminal']],
                        ['Run Checks', 'tools.checks', 'fa-vial-circle-check', ['tools.checks']],
                        ['Logs Viewer', 'tools.logs', 'fa-file-lines', ['tools.logs']],
                    ],

                    'Domains' => [
                        ['Domain Manager', 'domains.index', 'fa-globe', ['domains.*']],
                    ],
                ];
            @endphp

            @foreach($menuGroups as $group => $items)
                @php
                    $visibleItems = collect($items)->filter(fn ($item) => Route::has($item[1]));
                @endphp

                @if($visibleItems->count())
                    <div>
                        <p class="ws-menu-label px-2 mb-2">
                            {{ $group }}
                        </p>

                        <div class="space-y-1">
                            @foreach($visibleItems as [$label, $routeName, $icon, $activePatterns])
                                @php
                                    $active = false;

                                    foreach ($activePatterns as $pattern) {
                                        if (request()->routeIs($pattern)) {
                                            $active = true;
                                            break;
                                        }
                                    }

                                    if (!$active && request()->routeIs($routeName)) {
                                        $active = true;
                                    }
                                @endphp

                                <a href="{{ route($routeName) }}"
                                   @click="sidebarOpen = false"
                                   class="ws-menu-item {{ $active ? 'active' : '' }}">

                                    <span class="ws-menu-icon">
                                        @if($icon === 'fa-cpanel')
                                            <span class="text-xs font-black">cP</span>
                                        @elseif($icon === 'fa-wordpress')
                                            <i class="fa-brands fa-wordpress"></i>
                                        @else
                                            <i class="fa-solid {{ $icon }}"></i>
                                        @endif
                                    </span>

                                    <span class="flex-1 truncate">
                                        {{ $label }}
                                    </span>

                                    @if($active)
                                        <span class="w-1.5 h-6 rounded-full bg-red-500"></span>
                                    @else
                                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

        </nav>

        {{-- Sidebar Footer --}}
        <div class="px-4 py-4 border-t border-white/10">
            <div class="rounded-2xl bg-white/5 border border-white/10 p-3 mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-red-500/15 text-red-300 flex items-center justify-center">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>

                    <div>
                        <p class="text-white text-sm font-black">Enterprise Shield</p>
                        <p class="text-xs text-slate-400">Customer file protection</p>
                    </div>
                </div>
            </div>

            @if(Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-red-600 hover:bg-red-700 transition text-white font-black">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        Logout
                    </button>
                </form>
            @endif
        </div>
    </aside>

    {{-- Main Content --}}
    <main class="flex-1 min-w-0">

        {{-- Top Bar --}}
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-xl border-b border-slate-200 px-4 lg:px-6 py-4">
            <div class="flex items-center justify-between gap-4">

                <div class="flex items-center gap-3 min-w-0">
                    <button @click="sidebarOpen = true"
                            class="lg:hidden w-11 h-11 rounded-xl bg-[#071126] text-white flex items-center justify-center shadow">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl lg:text-2xl font-black text-slate-900 truncate">
                                @yield('page-title', 'Dashboard')
                            </h2>

                            <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-black">
                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                Live
                            </span>
                        </div>

                        <p class="hidden md:block text-sm text-slate-500">
                            Webscepts enterprise server health, security, backups, cPanel, Plesk and alert monitoring.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 lg:gap-3">

                    {{-- Search --}}
                    <button @click="quickSearchOpen = true"
                            class="hidden md:inline-flex ws-top-button bg-slate-100 text-slate-700 hover:bg-slate-200">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Search
                    </button>

                    {{-- Quick Actions --}}
                    <div class="hidden xl:flex gap-2">
                        @if(Route::has('servers.create'))
                            <a href="{{ route('servers.create') }}"
                               class="ws-top-button bg-[#0b63ce] text-white hover:bg-[#084fa6]">
                                <i class="fa-solid fa-plus"></i>
                                Add Server
                            </a>
                        @endif

                        @if(Route::has('backups.index'))
                            <a href="{{ route('backups.index') }}"
                               class="ws-top-button bg-[#071126] text-white hover:bg-[#0b1b3a]">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                Backup
                            </a>
                        @endif

                        @if(Route::has('servers.index'))
                            <a href="{{ route('servers.index') }}"
                               class="ws-top-button bg-[#cf1010] text-white hover:bg-[#a90d0d]">
                                <i class="fa-solid fa-rotate"></i>
                                Run Checks
                            </a>
                        @endif
                    </div>

                    {{-- Notification --}}
                    <div class="relative" @click.outside="notificationOpen = false">
                        <button type="button"
                                @click="toggleNotifications()"
                                class="relative w-11 h-11 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 flex items-center justify-center">
                            <i class="fa-solid fa-bell"></i>

                            @if($unreadNotificationCount > 0)
                                <span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-red-600 text-white text-[10px] font-black flex items-center justify-center border-2 border-white">
                                    {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                                </span>
                            @else
                                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-white"></span>
                            @endif
                        </button>

                        <div x-cloak
                             x-show="notificationOpen"
                             x-transition
                             class="absolute right-0 mt-3 w-[360px] max-w-[92vw] bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden z-50">

                            <div class="p-4 border-b bg-slate-50 flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-black text-slate-900">Notifications</h3>
                                    <p class="text-xs text-slate-500">
                                        Server alerts, security issues and monitoring updates.
                                    </p>
                                </div>

                                <button type="button"
                                        @click="notificationOpen = false"
                                        class="w-9 h-9 rounded-xl bg-white border hover:bg-slate-100">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <div class="max-h-[430px] overflow-y-auto">
                                @forelse($notificationItems as $notice)
                                    @php
                                        $level = strtolower($notice->level ?? 'info');

                                        $levelClass = match($level) {
                                            'danger', 'critical' => 'bg-red-100 text-red-700',
                                            'warning' => 'bg-yellow-100 text-yellow-700',
                                            'success' => 'bg-green-100 text-green-700',
                                            default => 'bg-blue-100 text-blue-700',
                                        };

                                        $iconClass = match($level) {
                                            'danger', 'critical' => 'fa-triangle-exclamation',
                                            'warning' => 'fa-circle-exclamation',
                                            'success' => 'fa-circle-check',
                                            default => 'fa-circle-info',
                                        };
                                    @endphp

                                    <div class="p-4 border-b hover:bg-slate-50">
                                        <div class="flex gap-3">
                                            <div class="w-10 h-10 rounded-2xl {{ $levelClass }} flex items-center justify-center shrink-0">
                                                <i class="fa-solid {{ $iconClass }}"></i>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <p class="font-black text-slate-900 text-sm truncate">
                                                        {{ $notice->title ?? 'Monitoring Alert' }}
                                                    </p>

                                                    <span class="px-2 py-0.5 rounded-full {{ $levelClass }} text-[10px] font-black uppercase">
                                                        {{ $level }}
                                                    </span>
                                                </div>

                                                <p class="text-xs text-slate-500 mt-1 line-clamp-2">
                                                    {{ \Illuminate\Support\Str::limit($notice->message ?? 'No details available.', 120) }}
                                                </p>

                                                <p class="text-[11px] text-slate-400 mt-2">
                                                    {{ optional($notice->created_at)->diffForHumans() ?? 'Just now' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-8 text-center">
                                        <div class="mx-auto w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center mb-3">
                                            <i class="fa-solid fa-circle-check text-xl"></i>
                                        </div>

                                        <h4 class="font-black text-slate-900">No alerts</h4>
                                        <p class="text-sm text-slate-500 mt-1">
                                            Your monitoring system has no new notifications.
                                        </p>
                                    </div>
                                @endforelse
                            </div>

                            <div class="p-3 bg-slate-50 border-t grid grid-cols-2 gap-2">
                                @if(Route::has('security.alerts'))
                                    <a href="{{ route('security.alerts') }}"
                                       class="px-4 py-3 rounded-xl bg-slate-900 text-white text-center font-black text-sm">
                                        View Alerts
                                    </a>
                                @endif

                                @if(Route::has('servers.index'))
                                    <a href="{{ route('servers.index') }}"
                                       class="px-4 py-3 rounded-xl bg-blue-600 text-white text-center font-black text-sm">
                                        Servers
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Profile --}}
                    <div class="relative" @click.outside="profileOpen = false">
                        <button @click="profileOpen = !profileOpen"
                                class="flex items-center gap-3 rounded-xl bg-slate-100 hover:bg-slate-200 px-3 py-2">
                            <div class="w-9 h-9 rounded-xl bg-[#071126] text-white flex items-center justify-center font-black">
                                {{ strtoupper(substr(auth()->user()->name ?? 'W', 0, 1)) }}
                            </div>

                            <div class="hidden md:block text-left">
                                <p class="text-sm font-black text-slate-900">
                                    {{ auth()->user()->name ?? 'Webscepts' }}
                                </p>
                                <p class="text-xs text-slate-500">Admin Panel</p>
                            </div>

                            <i class="fa-solid fa-chevron-down text-xs text-slate-500"></i>
                        </button>

                        <div x-cloak
                             x-show="profileOpen"
                             x-transition
                             class="absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-50">

                            <div class="p-4 border-b bg-slate-50">
                                <p class="font-black text-slate-900">
                                    {{ auth()->user()->name ?? 'Webscepts Admin' }}
                                </p>
                                <p class="text-sm text-slate-500">
                                    {{ auth()->user()->email ?? 'admin@webscepts.com' }}
                                </p>
                            </div>

                            <div class="p-2">
                                @if(Route::has('servers.index'))
                                    <a href="{{ route('servers.index') }}"
                                       class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 font-semibold">
                                        <i class="fa-solid fa-server text-[#0b63ce]"></i>
                                        Servers
                                    </a>
                                @endif

                                @if(Route::has('backups.index'))
                                    <a href="{{ route('backups.index') }}"
                                       class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100 font-semibold">
                                        <i class="fa-solid fa-cloud-arrow-up text-[#071126]"></i>
                                        Backups
                                    </a>
                                @endif

                                @if(Route::has('logout'))
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-red-50 text-red-600 font-semibold">
                                            <i class="fa-solid fa-right-from-bracket"></i>
                                            Logout
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        {{-- Page Body --}}
        <section class="p-4 lg:p-6">

            <div class="ws-fade">
                @yield('content')
            </div>

        </section>

    </main>
</div>

{{-- Toast Notifications --}}
<div class="fixed top-24 right-5 z-[100] space-y-3 w-[380px] max-w-[92vw]">

    @if(session('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 6000)"
             x-show="show"
             x-transition
             class="ws-toast rounded-2xl bg-green-600 text-white shadow-2xl p-4 flex gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div class="flex-1">
                <p class="font-black">Success</p>
                <p class="text-sm text-green-50">{{ session('success') }}</p>
            </div>

            <button @click="show = false" class="text-white/80 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 8000)"
             x-show="show"
             x-transition
             class="ws-toast rounded-2xl bg-red-600 text-white shadow-2xl p-4 flex gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>

            <div class="flex-1">
                <p class="font-black">Error</p>
                <p class="text-sm text-red-50">{{ session('error') }}</p>
            </div>

            <button @click="show = false" class="text-white/80 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    @endif

    @if($latestCriticalNotification)
        <div x-data="{ show: localStorage.getItem('ws_last_alert_id') !== '{{ $latestCriticalNotification->id ?? '0' }}' }"
             x-init="
                if (show) {
                    localStorage.setItem('ws_last_alert_id', '{{ $latestCriticalNotification->id ?? '0' }}');
                    notifyBrowser(
                        @js($latestCriticalNotification->title ?? 'Webscepts Alert'),
                        @js(\Illuminate\Support\Str::limit($latestCriticalNotification->message ?? 'A server alert was detected.', 140))
                    );
                    setTimeout(() => show = false, 10000);
                }
             "
             x-show="show"
             x-transition
             class="ws-toast rounded-2xl bg-slate-950 text-white shadow-2xl p-4 flex gap-3 border border-red-500/30">
            <div class="w-10 h-10 rounded-xl bg-red-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-bell"></i>
            </div>

            <div class="flex-1">
                <p class="font-black">
                    {{ $latestCriticalNotification->title ?? 'Webscepts Alert' }}
                </p>
                <p class="text-sm text-slate-300">
                    {{ \Illuminate\Support\Str::limit($latestCriticalNotification->message ?? 'A server alert was detected.', 140) }}
                </p>

                @if(Route::has('security.alerts'))
                    <a href="{{ route('security.alerts') }}" class="inline-block mt-2 text-sm text-blue-300 font-bold hover:underline">
                        View alerts
                    </a>
                @endif
            </div>

            <button @click="show = false" class="text-white/80 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    @endif
</div>

{{-- Quick Search Modal --}}
<div x-cloak
     x-show="quickSearchOpen"
     x-transition.opacity
     class="fixed inset-0 z-[80] bg-slate-950/70 backdrop-blur-sm flex items-start justify-center pt-24 px-4"
     @keydown.escape.window="quickSearchOpen = false">

    <div @click.outside="quickSearchOpen = false"
         x-transition
         class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden">

        <div class="p-5 border-b bg-slate-50 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">Quick Search</h3>
                <p class="text-sm text-slate-500">Search dashboard, servers, backups and security tools.</p>
            </div>

            <button @click="quickSearchOpen = false"
                    class="w-10 h-10 rounded-xl bg-slate-200 hover:bg-slate-300">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-5">
            <input type="text"
                   id="enterpriseSearchInput"
                   placeholder="Type dashboard, servers, backups, security..."
                   class="w-full border rounded-xl px-5 py-4 outline-none focus:ring-2 focus:ring-[#0b63ce]"
                   oninput="filterEnterpriseLinks(this.value)">

            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3">

                @if(Route::has('dashboard.index'))
                    <a href="{{ route('dashboard.index') }}" class="enterprise-search-link rounded-xl border p-4 hover:bg-slate-50 font-bold">
                        <i class="fa-solid fa-chart-line text-[#0b63ce] mr-2"></i>Dashboard
                    </a>
                @endif

                @if(Route::has('servers.index'))
                    <a href="{{ route('servers.index') }}" class="enterprise-search-link rounded-xl border p-4 hover:bg-slate-50 font-bold">
                        <i class="fa-solid fa-server text-[#071126] mr-2"></i>All Servers
                    </a>
                @endif

                @if(Route::has('servers.create'))
                    <a href="{{ route('servers.create') }}" class="enterprise-search-link rounded-xl border p-4 hover:bg-slate-50 font-bold">
                        <i class="fa-solid fa-circle-plus text-green-600 mr-2"></i>Add Server
                    </a>
                @endif

                @if(Route::has('backups.index'))
                    <a href="{{ route('backups.index') }}" class="enterprise-search-link rounded-xl border p-4 hover:bg-slate-50 font-bold">
                        <i class="fa-solid fa-cloud-arrow-up text-[#0b63ce] mr-2"></i>Backups
                    </a>
                @endif

                @if(Route::has('security.alerts'))
                    <a href="{{ route('security.alerts') }}" class="enterprise-search-link rounded-xl border p-4 hover:bg-slate-50 font-bold">
                        <i class="fa-solid fa-shield-halved text-[#cf1010] mr-2"></i>Security Alerts
                    </a>
                @endif

                @if(Route::has('tools.logs'))
                    <a href="{{ route('tools.logs') }}" class="enterprise-search-link rounded-xl border p-4 hover:bg-slate-50 font-bold">
                        <i class="fa-solid fa-file-lines text-orange-600 mr-2"></i>Logs Viewer
                    </a>
                @endif

                @if(Route::has('domains.index'))
                    <a href="{{ route('domains.index') }}" class="enterprise-search-link rounded-xl border p-4 hover:bg-slate-50 font-bold">
                        <i class="fa-solid fa-globe text-blue-600 mr-2"></i>Domain Manager
                    </a>
                @endif

            </div>
        </div>
    </div>
</div>

<script>
    function enterpriseLayout() {
        return {
            sidebarOpen: false,
            profileOpen: false,
            quickSearchOpen: false,
            notificationOpen: false,

            toggleNotifications() {
                this.notificationOpen = !this.notificationOpen;
                this.profileOpen = false;
                this.quickSearchOpen = false;
            },

            initNotifications() {
                if ('Notification' in window && Notification.permission === 'default') {
                    setTimeout(() => {
                        Notification.requestPermission().catch(() => {});
                    }, 1200);
                }
            },

            notifyBrowser(title, body) {
                if (!('Notification' in window)) {
                    return;
                }

                if (Notification.permission === 'granted') {
                    new Notification(title, {
                        body: body,
                        icon: '/favicon.ico',
                    });
                }
            }
        }
    }

    function notifyBrowser(title, body) {
        if (!('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'granted') {
            new Notification(title, {
                body: body,
                icon: '/favicon.ico',
            });
        }
    }

    function filterEnterpriseLinks(value) {
        const query = value.toLowerCase();
        const links = document.querySelectorAll('.enterprise-search-link');

        links.forEach(link => {
            link.style.display = link.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    }

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();

            const root = document.querySelector('[x-data]');
            if (root && root.__x) {
                root.__x.$data.quickSearchOpen = true;
            }
        }
    });
</script>

</body>
</html>