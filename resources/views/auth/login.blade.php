<!DOCTYPE html>
<html lang="en" x-data="{ showPassword: false, loading: false, passkeyLoading: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Webscepts Monitoring</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    {{-- Passkeys JS --}}
    @vite(['resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }

        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .ws-bg {
            background:
                radial-gradient(circle at 20% 20%, rgba(11, 99, 206, .30), transparent 28%),
                radial-gradient(circle at 85% 30%, rgba(207, 16, 16, .18), transparent 25%),
                radial-gradient(circle at 50% 90%, rgba(8, 43, 112, .35), transparent 32%),
                #071126;
        }

        .glass-card {
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(18px);
            box-shadow: 0 30px 80px rgba(2, 6, 23, .35);
        }

        .input-modern {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 14px 16px 14px 48px;
            outline: none;
            transition: all .2s ease;
            background: #ffffff;
        }

        .input-modern:focus {
            border-color: #0b63ce;
            box-shadow: 0 0 0 4px rgba(11, 99, 206, .12);
        }

        .feature-card {
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .12);
            backdrop-filter: blur(16px);
        }

        .passkey-btn {
            background: linear-gradient(135deg, #0f172a 0%, #0b63ce 100%);
            box-shadow: 0 16px 34px rgba(11, 99, 206, .24);
        }

        .passkey-btn:hover {
            background: linear-gradient(135deg, #020617 0%, #084fa6 100%);
            transform: translateY(-1px);
        }
    </style>
</head>

<body class="ws-bg min-h-screen">

<div class="min-h-screen grid grid-cols-1 xl:grid-cols-2">

    {{-- Left Brand Panel --}}
    <div class="hidden xl:flex relative overflow-hidden items-center justify-center p-12 text-white">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-red-500/10 rounded-full blur-3xl"></div>

        <div class="relative max-w-xl">
            <div class="flex items-center gap-4 mb-10">
                <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-[#082b70] to-[#0b63ce] flex items-center justify-center shadow-xl shadow-blue-900/40">
                    <span class="text-white font-black text-2xl tracking-tight">WS</span>
                </div>

                <div>
                    <h1 class="text-3xl font-black leading-tight">Webscepts</h1>
                    <p class="text-slate-300 font-semibold">Enterprise Server Monitoring</p>
                </div>
            </div>

            <h2 class="text-5xl font-black leading-tight">
                Secure access to your monitoring command center.
            </h2>

            <p class="text-slate-300 mt-5 text-lg leading-8">
                Monitor servers, cPanel accounts, Plesk, backups, security alerts, SMS notifications and customer protection from one enterprise dashboard.
            </p>

            <div class="grid grid-cols-3 gap-4 mt-10">
                <div class="feature-card rounded-3xl p-5">
                    <div class="w-11 h-11 rounded-2xl bg-blue-500/20 flex items-center justify-center mb-4">
                        <i class="fa-solid fa-server text-blue-200"></i>
                    </div>
                    <p class="font-black">24/7</p>
                    <p class="text-sm text-slate-300 mt-1">Live Monitoring</p>
                </div>

                <div class="feature-card rounded-3xl p-5">
                    <div class="w-11 h-11 rounded-2xl bg-red-500/20 flex items-center justify-center mb-4">
                        <i class="fa-solid fa-shield-halved text-red-200"></i>
                    </div>
                    <p class="font-black">Secure</p>
                    <p class="text-sm text-slate-300 mt-1">File Protection</p>
                </div>

                <div class="feature-card rounded-3xl p-5">
                    <div class="w-11 h-11 rounded-2xl bg-green-500/20 flex items-center justify-center mb-4">
                        <i class="fa-solid fa-message text-green-200"></i>
                    </div>
                    <p class="font-black">SMS</p>
                    <p class="text-sm text-slate-300 mt-1">Instant Alerts</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Login Panel --}}
    <div class="flex items-center justify-center p-5 lg:p-10">
        <div class="w-full max-w-md">

            {{-- Mobile Logo --}}
            <div class="xl:hidden flex items-center justify-center gap-3 mb-8 text-white">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#082b70] to-[#0b63ce] flex items-center justify-center shadow-xl">
                    <span class="text-white font-black text-xl">WS</span>
                </div>
                <div>
                    <h1 class="text-2xl font-black">Webscepts</h1>
                    <p class="text-sm text-slate-300">Enterprise Monitoring</p>
                </div>
            </div>

            <div class="glass-card rounded-[2rem] p-7 lg:p-8">

                <div class="text-center mb-7">
                    <div class="mx-auto w-14 h-14 rounded-2xl bg-blue-100 text-[#0b63ce] flex items-center justify-center mb-4">
                        <i class="fa-solid fa-lock text-xl"></i>
                    </div>

                    <h2 class="text-3xl font-black text-slate-900">
                        Welcome Back
                    </h2>

                    <p class="text-slate-500 mt-2">
                        Sign in to continue to Webscepts Monitoring.
                    </p>
                </div>

                @if(session('success'))
                    <div class="bg-green-100 text-green-700 border border-green-300 rounded-2xl p-4 mb-5 flex gap-3">
                        <i class="fa-solid fa-circle-check mt-1"></i>
                        <span class="font-semibold">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 mb-5 flex gap-3">
                        <i class="fa-solid fa-circle-exclamation mt-1"></i>
                        <span class="font-semibold">{{ session('error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 mb-5">
                        <div class="font-bold mb-1">Please fix these errors:</div>
                        <ul class="list-disc ml-5 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Fingerprint / Passkey Login --}}
                <div class="mb-6">
                    <button type="button"
                            id="passkeyLoginBtn"
                            x-bind:disabled="passkeyLoading"
                            class="passkey-btn w-full rounded-2xl text-white py-4 font-black transition flex items-center justify-center gap-2 disabled:opacity-70">
                        <span x-show="!passkeyLoading">
                            <i class="fa-solid fa-fingerprint mr-2"></i>
                            Login with Fingerprint / Passkey
                        </span>

                        <span x-cloak x-show="passkeyLoading">
                            <i class="fa-solid fa-circle-notch fa-spin mr-2"></i>
                            Opening passkey...
                        </span>
                    </button>

                    <p class="text-xs text-slate-500 text-center mt-2 font-semibold">
                        Works with Mac Touch ID, Chrome passkey, Windows Hello, iPhone Face ID and Android fingerprint.
                    </p>
                </div>

                <div class="flex items-center gap-4 mb-6">
                    <div class="h-px bg-slate-200 flex-1"></div>
                    <span class="text-[11px] font-black text-slate-400">OR LOGIN WITH PASSWORD</span>
                    <div class="h-px bg-slate-200 flex-1"></div>
                </div>

                {{-- Normal Password Login --}}
                <form method="POST"
                      action="{{ Route::has('login.submit') ? route('login.submit') : url('/login') }}"
                      @submit="loading = true">
                    @csrf

                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-black text-slate-700 mb-2">
                                Email Address
                            </label>

                            <div class="relative">
                                <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="email"
                                       name="email"
                                       value="{{ old('email') }}"
                                       placeholder="admin@webscepts.com"
                                       autocomplete="email"
                                       class="input-modern"
                                       required
                                       autofocus>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-black text-slate-700">
                                    Password
                                </label>

                                <span class="text-xs text-slate-400 font-semibold">
                                    Secure login
                                </span>
                            </div>

                            <div class="relative">
                                <i class="fa-solid fa-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>

                                <input :type="showPassword ? 'text' : 'password'"
                                       name="password"
                                       placeholder="Enter your password"
                                       autocomplete="current-password"
                                       class="input-modern pr-12"
                                       required>

                                <button type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-900">
                                    <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-slate-600 font-semibold">
                                <input type="checkbox"
                                       name="remember"
                                       value="1"
                                       class="rounded border-slate-300 text-[#0b63ce] focus:ring-[#0b63ce]">
                                Remember me
                            </label>

                            @if(Route::has('password.request'))
                                <a href="{{ route('password.request') }}"
                                   class="text-sm font-bold text-[#0b63ce] hover:underline">
                                    Forgot password?
                                </a>
                            @endif
                        </div>

                        <button type="submit"
                                :disabled="loading"
                                class="w-full rounded-2xl bg-[#0b63ce] hover:bg-[#084fa6] disabled:opacity-70 text-white py-4 font-black shadow-lg shadow-blue-900/20 transition flex items-center justify-center gap-2">
                            <span x-show="!loading">
                                <i class="fa-solid fa-right-to-bracket mr-2"></i>
                                Login
                            </span>

                            <span x-cloak x-show="loading">
                                <i class="fa-solid fa-circle-notch fa-spin mr-2"></i>
                                Signing in...
                            </span>
                        </button>
                    </div>
                </form>

                <div class="mt-7 pt-6 border-t border-slate-200">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <i class="fa-solid fa-shield-halved text-[#cf1010]"></i>
                            <p class="text-[11px] font-bold text-slate-500 mt-1">Secure</p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-3">
                            <i class="fa-solid fa-server text-[#0b63ce]"></i>
                            <p class="text-[11px] font-bold text-slate-500 mt-1">Servers</p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-3">
                            <i class="fa-solid fa-bell text-green-600"></i>
                            <p class="text-[11px] font-bold text-slate-500 mt-1">Alerts</p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-center text-slate-400 text-sm mt-6">
                © {{ date('Y') }} Webscepts. Enterprise Monitoring System.
            </p>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const passkeyButton = document.getElementById('passkeyLoginBtn');

        if (!passkeyButton) {
            return;
        }

        passkeyButton.addEventListener('click', async function () {
            const alpineRoot = document.documentElement.__x
                ? document.documentElement
                : document.querySelector('[x-data]');

            try {
                if (typeof Alpine !== 'undefined' && alpineRoot) {
                    Alpine.$data(alpineRoot).passkeyLoading = true;
                }

                if (!window.Passkeys || typeof window.Passkeys.verify !== 'function') {
                    throw new Error('Passkeys JavaScript is not loaded. Run npm install @laravel/passkeys and npm run build.');
                }

                await window.Passkeys.verify();

                window.location.href = "{{ Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}";
            } catch (error) {
                console.error(error);

                let message = 'Passkey login failed or cancelled.';

                if (error && error.message) {
                    message = error.message;
                }

                alert(message);
            } finally {
                if (typeof Alpine !== 'undefined' && alpineRoot) {
                    Alpine.$data(alpineRoot).passkeyLoading = false;
                }
            }
        });
    });
</script>

</body>
</html>