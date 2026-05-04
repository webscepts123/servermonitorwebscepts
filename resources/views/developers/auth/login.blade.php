<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Developer Login - Webscepts Code Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4">

<div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 bg-white rounded-3xl overflow-hidden shadow-2xl">

    <div class="relative bg-gradient-to-br from-slate-950 via-blue-950 to-red-950 p-10 text-white hidden lg:flex flex-col justify-between">
        <div>
            <div class="inline-flex px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-black">
                Webscepts Developer Codes
            </div>

            <h1 class="text-5xl font-black mt-8 leading-tight">
                Secure Developer Workspace
            </h1>

            <p class="text-slate-300 mt-5">
                Separate developer login for VS Code, Git, Laravel cache, Composer, NPM and file workspace access.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-2xl bg-white/10 border border-white/10 p-4">
                <p class="text-sm text-slate-300">Access</p>
                <p class="text-2xl font-black">Developer</p>
            </div>

            <div class="rounded-2xl bg-white/10 border border-white/10 p-4">
                <p class="text-sm text-slate-300">Editor</p>
                <p class="text-2xl font-black">VS Code</p>
            </div>
        </div>
    </div>

    <div class="p-8 lg:p-12">
        <div class="mb-8">
            <h2 class="text-3xl font-black text-slate-900">Developer Login</h2>
            <p class="text-slate-500 mt-2">
                Login to access the developer code portal.
            </p>
        </div>

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
                <ul class="list-disc ml-5 text-sm font-bold">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('developer.login.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-black text-slate-700 mb-1">
                    Developer Email
                </label>
                <input type="email"
                       name="email"
                       value="{{ old('email') }}"
                       placeholder="developer@webscepts.com"
                       required
                       class="w-full px-4 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-black text-slate-700 mb-1">
                    Password
                </label>
                <input type="password"
                       name="password"
                       placeholder="Developer password"
                       required
                       class="w-full px-4 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 font-bold">
                <input type="checkbox" name="remember" value="1" class="rounded">
                Remember this developer login
            </label>

            <button class="w-full px-5 py-4 rounded-2xl bg-slate-900 hover:bg-slate-700 text-white font-black">
                Login to Developer Codes
            </button>
        </form>

        <p class="text-xs text-slate-400 mt-5 text-center">
            This login is separate from the main admin dashboard.
        </p>
    </div>

</div>

</body>
</html>