@extends('layouts.app')

@section('page-title', 'Create cPanel Account')

@section('content')

@php
    $frameworks = $frameworks ?? [
        'custom' => 'Custom / Other',
        'html' => 'Static HTML / CSS / JS',
        'php' => 'PHP',
        'wordpress' => 'WordPress',
        'laravel' => 'Laravel',
        'react' => 'React.js',
        'vue' => 'Vue.js',
        'angular' => 'Angular',
        'node' => 'Node.js / Express',
        'nextjs' => 'Next.js',
        'nuxt' => 'Nuxt.js',
        'svelte' => 'Svelte',
        'python' => 'Python',
        'flask' => 'Flask',
        'django' => 'Django',
        'fastapi' => 'FastAPI',
        'java' => 'Java',
        'springboot' => 'Spring Boot',
        'dotnet' => '.NET',
        'ruby' => 'Ruby / Rails',
        'go' => 'Go',
    ];
@endphp

<div class="max-w-6xl mx-auto space-y-6">

    @if(session('success'))
        <div class="bg-green-100 text-green-700 border border-green-300 rounded-2xl p-4 font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-bold">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4">
            <div class="font-black mb-2">Please fix these errors:</div>
            <ul class="list-disc ml-5 text-sm font-bold">
                @foreach($errors->all() as $errorItem)
                    <li>{{ $errorItem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">

        <div class="p-6 border-b bg-slate-50">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900">Create cPanel Account</h2>
                    <p class="text-slate-500 mt-1">
                        {{ $server->name }} - {{ $server->host }}
                    </p>
                </div>

                <a href="{{ route('servers.cpanel.index', $server) }}"
                   class="inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-slate-200 text-slate-800 hover:bg-slate-300 font-black">
                    Back to cPanel Accounts
                </a>
            </div>
        </div>

        @if($error)
            <div class="m-6 bg-red-100 text-red-700 border border-red-300 rounded-2xl p-4 font-bold">
                {{ $error }}
            </div>
        @endif

        <form method="POST" action="{{ route('servers.cpanel.store', $server) }}" class="p-6 space-y-8">
            @csrf

            {{-- cPanel Account --}}
            <div>
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                        <i class="fa-solid fa-server"></i>
                    </div>

                    <div>
                        <h3 class="text-xl font-black text-slate-900">cPanel Account Details</h3>
                        <p class="text-slate-500 text-sm">
                            This will create the account in WHM/cPanel.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Domain</label>
                        <input type="text"
                               name="domain"
                               value="{{ old('domain') }}"
                               placeholder="example.com"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        @error('domain') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Username</label>
                        <input type="text"
                               name="username"
                               value="{{ old('username') }}"
                               placeholder="example"
                               maxlength="16"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        @error('username') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Password</label>
                        <div class="flex gap-2">
                            <input type="password"
                                   name="password"
                                   id="cpanelPassword"
                                   class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button"
                                    onclick="togglePassword()"
                                    class="px-4 rounded-xl bg-slate-100 hover:bg-slate-200 font-black text-sm">
                                Show
                            </button>
                            <button type="button"
                                    onclick="generatePassword()"
                                    class="px-4 rounded-xl bg-blue-100 hover:bg-blue-200 text-blue-700 font-black text-sm">
                                Generate
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 font-bold">
                            This real cPanel password is also saved encrypted for the Visual Code Editor File Manager API.
                        </p>
                        @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Contact Email</label>
                        <input type="email"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="client@example.com"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Package</label>
                        <select name="package"
                                class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Package</option>
                            @foreach($packages as $package)
                                @php
                                    $pkgName = $package['name'] ?? $package['pkg'] ?? null;
                                @endphp

                                @if($pkgName)
                                    <option value="{{ $pkgName }}" {{ old('package') == $pkgName ? 'selected' : '' }}>
                                        {{ $pkgName }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('package') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">IP Address</label>
                        <select name="ip"
                                class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Auto / Shared IP</option>
                            @foreach($ips as $ip)
                                @php
                                    $ipValue = $ip['ip'] ?? $ip['address'] ?? null;
                                @endphp

                                @if($ipValue)
                                    <option value="{{ $ipValue }}" {{ old('ip') == $ipValue ? 'selected' : '' }}>
                                        {{ $ipValue }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('ip') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Developer Codes --}}
            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-blue-600 text-white flex items-center justify-center">
                            <i class="fa-solid fa-code"></i>
                        </div>

                        <div>
                            <h3 class="text-xl font-black text-slate-900">Developer Codes + Visual Code Editor</h3>
                            <p class="text-blue-700 text-sm font-bold mt-1">
                                This saves the real cPanel password encrypted so
                                https://developercodes.webscepts.com/codeditor can load File Manager files.
                            </p>
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-white border border-blue-200 font-black text-blue-700">
                        <input type="checkbox"
                               name="create_developer_login"
                               value="1"
                               class="rounded border-slate-300 text-blue-600"
                               {{ old('create_developer_login', '1') ? 'checked' : '' }}>
                        Create Developer Login
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Portal Access</label>
                        <select name="developer_portal_access"
                                class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1" {{ old('developer_portal_access', '1') == '1' ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ old('developer_portal_access') == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Framework</label>
                        <select name="framework"
                                class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach($frameworks as $key => $label)
                                <option value="{{ $key }}" {{ old('framework', 'custom') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-black mb-1 text-slate-700">Project Root</label>
                        <input type="text"
                               name="project_root"
                               value="{{ old('project_root') }}"
                               placeholder="/home/username/public_html"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-slate-500 mt-1 font-bold">
                            Leave empty to auto use /home/username/public_html.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Developer Login URL</label>
                        <input type="text"
                               value="https://developercodes.webscepts.com/login"
                               readonly
                               class="w-full border border-slate-200 rounded-xl p-3 bg-white text-slate-600 font-bold">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Visual Code Editor URL</label>
                        <input type="text"
                               value="https://developercodes.webscepts.com/codeditor"
                               readonly
                               class="w-full border border-slate-200 rounded-xl p-3 bg-white text-slate-600 font-bold">
                    </div>
                </div>
            </div>

            {{-- Permissions --}}
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                <h3 class="text-xl font-black text-slate-900 mb-5">Developer Permissions</h3>

                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach([
                        'can_view_files' => 'View Files',
                        'can_edit_files' => 'Edit Files',
                        'can_delete_files' => 'Delete Files',
                        'can_git_pull' => 'Git Pull',
                        'can_clear_cache' => 'Clear Cache',
                        'can_composer' => 'Composer',
                        'can_npm' => 'NPM',
                        'can_run_build' => 'Build',
                        'can_run_python' => 'Python',
                        'can_restart_app' => 'Restart App',
                        'can_mysql' => 'MySQL',
                        'can_postgresql' => 'PostgreSQL',
                    ] as $permission => $label)
                        @php
                            $defaultChecked = in_array($permission, [
                                'can_view_files',
                                'can_edit_files',
                                'can_clear_cache',
                                'can_mysql',
                            ], true);
                        @endphp

                        <label class="flex items-center gap-2 bg-white border border-slate-200 rounded-2xl px-4 py-3 font-black text-sm text-slate-700">
                            <input type="checkbox"
                                   name="{{ $permission }}"
                                   value="1"
                                   class="rounded border-slate-300 text-blue-600"
                                   {{ old($permission, $defaultChecked ? '1' : null) ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Database --}}
            <div class="rounded-3xl border border-slate-200 bg-white p-6">
                <h3 class="text-xl font-black text-slate-900 mb-5">Database Details</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">Database Type</label>
                        <select name="db_type"
                                class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="mysql" {{ old('db_type', 'mysql') === 'mysql' ? 'selected' : '' }}>MySQL</option>
                            <option value="postgresql" {{ old('db_type') === 'postgresql' ? 'selected' : '' }}>PostgreSQL</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">DB Host</label>
                        <input type="text"
                               name="db_host"
                               value="{{ old('db_host', 'localhost') }}"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">DB Username</label>
                        <input type="text"
                               name="db_username"
                               value="{{ old('db_username') }}"
                               placeholder="Usually cPanel username"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-black mb-1 text-slate-700">DB Password</label>
                        <input type="password"
                               name="db_password"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-black mb-1 text-slate-700">DB Name</label>
                        <input type="text"
                               name="db_name"
                               value="{{ old('db_name') }}"
                               class="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-blue-600 text-white hover:bg-blue-700 font-black">
                    Create Account + Save Developer Login
                </button>

                <a href="{{ route('servers.cpanel.index', $server) }}"
                   class="w-full sm:w-auto text-center px-8 py-4 rounded-2xl bg-slate-200 text-slate-800 hover:bg-slate-300 font-black">
                    Cancel
                </a>
            </div>
        </form>

    </div>

</div>

<script>
    function togglePassword() {
        const input = document.getElementById('cpanelPassword');
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    function generatePassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        let password = '';

        for (let i = 0; i < 18; i++) {
            password += chars[Math.floor(Math.random() * chars.length)];
        }

        password += 'A1!';

        const input = document.getElementById('cpanelPassword');
        input.value = password;
        input.type = 'text';
    }
</script>

@endsection