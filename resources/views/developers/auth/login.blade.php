<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Developer Login - Webscepts Developer Codes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .glass-card {
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .grid-bg {
            background-image:
                linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 34px 34px;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-950">

<div class="min-h-screen relative overflow-hidden flex items-center justify-center p-4">

    {{-- Background --}}
    <div class="absolute inset-0 grid-bg"></div>
    <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-blue-600/25 blur-3xl"></div>
    <div class="absolute -bottom-32 -left-32 w-[520px] h-[520px] rounded-full bg-red-600/20 blur-3xl"></div>
    <div class="absolute top-1/3 left-1/3 w-[360px] h-[360px] rounded-full bg-cyan-500/10 blur-3xl"></div>

    <div class="relative w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 bg-white rounded-[2rem] overflow-hidden shadow-2xl">

        {{-- Left Enterprise Panel --}}
        <div class="relative hidden lg:flex flex-col justify-between p-10 bg-gradient-to-br from-slate-950 via-blue-950 to-red-950 text-white overflow-hidden">
            <div class="absolute -top-24 -right-24 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-red-500/20 rounded-full blur-3xl"></div>

            <div class="relative">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-black">
                    <span class="w-2 h-2 bg-cyan-300 rounded-full"></span>
                    Webscepts Developer Codes
                </div>

                <h1 class="text-5xl font-black mt-8 leading-tight tracking-tight">
                    Secure Developer Workspace
                </h1>

                <p class="text-slate-300 mt-5 max-w-xl">
                    Separate developer login for cPanel users, contact emails, VS Code Remote SSH, Git, Laravel cache,
                    Composer, NPM and safe project workspace access.
                </p>

                <div class="mt-8 grid grid-cols-2 gap-4">
                    <div class="rounded-3xl bg-white/10 border border-white/10 p-5 glass-card">
                        <div class="w-11 h-11 rounded-2xl bg-blue-500/20 flex items-center justify-center mb-4">
                            <span class="text-xl">💻</span>
                        </div>
                        <p class="text-sm text-slate-300">Editor</p>
                        <p class="text-2xl font-black">VS Code</p>
                    </div>

                    <div class="rounded-3xl bg-white/10 border border-white/10 p-5 glass-card">
                        <div class="w-11 h-11 rounded-2xl bg-green-500/20 flex items-center justify-center mb-4">
                            <span class="text-xl">🔐</span>
                        </div>
                        <p class="text-sm text-slate-300">Access</p>
                        <p class="text-2xl font-black">cPanel Users</p>
                    </div>

                    <div class="rounded-3xl bg-white/10 border border-white/10 p-5 glass-card">
                        <div class="w-11 h-11 rounded-2xl bg-purple-500/20 flex items-center justify-center mb-4">
                            <span class="text-xl">⚙️</span>
                        </div>
                        <p class="text-sm text-slate-300">Commands</p>
                        <p class="text-2xl font-black">Controlled</p>
                    </div>

                    <div class="rounded-3xl bg-white/10 border border-white/10 p-5 glass-card">
                        <div class="w-11 h-11 rounded-2xl bg-red-500/20 flex items-center justify-center mb-4">
                            <span class="text-xl">🛡️</span>
                        </div>
                        <p class="text-sm text-slate-300">Security</p>
                        <p class="text-2xl font-black">SentinelCore</p>
                    </div>
                </div>
            </div>

            <div class="relative rounded-3xl bg-white/10 border border-white/10 p-5 glass-card">
                <p class="text-sm text-slate-300">
                    Developer portal:
                </p>
                <p class="font-black break-all mt-1">
                    {{ request()->getSchemeAndHttpHost() }}
                </p>
            </div>
        </div>

        {{-- Login Form --}}
        <div class="p-6 sm:p-8 lg:p-12 bg-white">

            <div class="mb-8">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center text-2xl">
                        &lt;/&gt;
                    </div>

                    <div>
                        <h2 class="text-3xl font-black text-slate-900">
                            Developer Login
                        </h2>
                        <p class="text-slate-500 text-sm">
                            Login to Webscepts Developer Codes
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl bg-blue-50 border border-blue-100 p-4">
                    <p class="text-sm text-blue-700 font-bold">
                        You can login using your cPanel username, cPanel contact email, or developer email.
                    </p>
                </div>
            </div>

            {{-- Alerts --}}
            @if(session('success'))
                <div class="bg-green-100 text-green-700 border border-green-300 rounded-2xl p-4 mb-5 font-bold">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 mb-5 font-bold">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 mb-5">
                    <div class="font-black mb-2">Please fix these errors:</div>
                    <ul class="list-disc ml-5 text-sm font-bold">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('developer.login.submit') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-black text-slate-700 mb-2">
                        Developer Login
                    </label>

                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A8.966 8.966 0 0112 15c2.21 0 4.236.8 5.879 2.129M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>

                        <input id="login"
                               type="text"
                               name="login"
                               value="{{ old('login') }}"
                               placeholder="cPanel username, contact email, or developer email"
                               required
                               autofocus
                               autocomplete="username"
                               class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 bg-slate-50 outline-none focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>

                    <p class="text-xs text-slate-400 mt-2">
                        Example: <strong>cpaneluser</strong> or <strong>developer@example.com</strong>
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-black text-slate-700">
                            Password
                        </label>

                        <button type="button"
                                onclick="togglePassword()"
                                class="text-xs font-black text-blue-600 hover:text-blue-700">
                            Show / Hide
                        </button>
                    </div>

                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c.828 0 1.5-.672 1.5-1.5S12.828 8 12 8s-1.5.672-1.5 1.5S11.172 11 12 11z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 11V8a5 5 0 10-10 0v3M5 11h14v10H5V11z" />
                            </svg>
                        </div>

                        <input id="password"
                               type="password"
                               name="password"
                               placeholder="Developer temporary password"
                               required
                               autocomplete="current-password"
                               class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 bg-slate-50 outline-none focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>

                    <p class="text-xs text-slate-400 mt-2">
                        Use the temporary password generated by the admin from cPanel Developer Logins.
                    </p>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-600 font-bold">
                        <input type="checkbox"
                               name="remember"
                               value="1"
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Remember this login
                    </label>

                    <span class="text-xs text-slate-400 font-bold">
                        Separate from admin login
                    </span>
                </div>

                <button class="w-full px-5 py-4 rounded-2xl bg-slate-900 hover:bg-slate-700 text-white font-black transition shadow-lg shadow-slate-900/20">
                    Login to Developer Codes
                </button>
            </form>

            <div class="my-7 flex items-center gap-4">
                <div class="h-px bg-slate-200 flex-1"></div>
                <span class="text-xs font-black text-slate-400">SECURE ACCESS</span>
                <div class="h-px bg-slate-200 flex-1"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs text-slate-500 font-bold">Login Type</p>
                    <p class="font-black text-slate-900 mt-1">Developer</p>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs text-slate-500 font-bold">Source</p>
                    <p class="font-black text-slate-900 mt-1">cPanel</p>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs text-slate-500 font-bold">Workspace</p>
                    <p class="font-black text-slate-900 mt-1">VS Code</p>
                </div>
            </div>

            <p class="text-xs text-slate-400 mt-6 text-center">
                If you cannot login, ask the admin to reset your Developer Codes password.
            </p>
        </div>

    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');

    if (!input) {
        return;
    }

    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>