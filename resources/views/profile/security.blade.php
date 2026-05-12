@extends('layouts.app')

@section('page-title', 'Profile Security')

@section('content')

<div class="max-w-6xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-blue-950 to-slate-900 shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl"></div>

        <div class="relative p-6 lg:p-8 text-white">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-black tracking-tight">
                        Profile Security
                    </h1>

                    <p class="text-slate-300 mt-2">
                        Add fingerprint, Touch ID, Face ID, Windows Hello, phone passkey, or security key login.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-black">
                            <i class="fa-solid fa-fingerprint mr-1"></i>
                            Fingerprint Login
                        </span>

                        <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-black">
                            <i class="fa-solid fa-key mr-1"></i>
                            Passkeys
                        </span>

                        <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-black">
                            <i class="fa-brands fa-chrome mr-1"></i>
                            Chrome Supported
                        </span>
                    </div>
                </div>

                <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}"
                   class="px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white font-black hover:bg-white/20 text-center">
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="rounded-2xl bg-green-100 border border-green-300 text-green-800 p-4 font-black">
            <i class="fa-solid fa-circle-check mr-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-red-100 border border-red-300 text-red-800 p-4 font-black">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Add Passkey --}}
        <div class="xl:col-span-2 bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">
                    <i class="fa-solid fa-fingerprint text-blue-600 mr-2"></i>
                    Add Fingerprint / Passkey
                </h2>

                <p class="text-slate-500 mt-1">
                    First login normally with email and password. Then add this device here. Next time, login page fingerprint/passkey button will work.
                </p>
            </div>

            <div class="p-6 space-y-6">

                <div class="rounded-3xl bg-blue-50 border border-blue-200 p-5">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5">
                        <div>
                            <h3 class="text-xl font-black text-blue-950">
                                Add This Device
                            </h3>

                            <p class="text-blue-700 text-sm font-bold mt-1">
                                On Mac Chrome this opens Touch ID or Mac password. On mobile it opens fingerprint / Face ID.
                            </p>
                        </div>

                        <div class="w-16 h-16 rounded-3xl bg-blue-600 text-white flex items-center justify-center text-3xl">
                            <i class="fa-solid fa-fingerprint"></i>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="block text-sm font-black text-slate-700 mb-2">
                            Passkey Name
                        </label>

                        <input type="text"
                               id="passkeyName"
                               value="My MacBook"
                               class="w-full border border-slate-300 rounded-2xl p-4 outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Example: My MacBook, Office Laptop, iPhone">
                    </div>

                    <button type="button"
                            id="registerPasskeyBtn"
                            class="mt-5 w-full md:w-auto px-7 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black shadow-lg shadow-blue-900/20">
                        <i class="fa-solid fa-plus mr-2"></i>
                        Add Fingerprint / Passkey
                    </button>
                </div>

                <div class="rounded-3xl bg-yellow-50 border border-yellow-200 p-5">
                    <h3 class="font-black text-yellow-900">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                        Important
                    </h3>

                    <p class="text-yellow-800 text-sm font-bold mt-2 leading-6">
                        Passkeys only work on HTTPS domains. Your domain should be:
                        <strong>{{ request()->getHost() }}</strong>.
                        Do not register on another domain and expect it to work here.
                    </p>
                </div>

            </div>
        </div>

        {{-- Account Info --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50">
                <h2 class="text-2xl font-black text-slate-900">
                    Account
                </h2>
                <p class="text-slate-500 mt-1">
                    Current logged-in user.
                </p>
            </div>

            <div class="p-6 space-y-4">
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">
                        Name
                    </div>
                    <div class="font-black text-slate-900 mt-1 break-all">
                        {{ $user->name ?? 'User' }}
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-black">
                        Email
                    </div>
                    <div class="font-black text-slate-900 mt-1 break-all">
                        {{ $user->email ?? '-' }}
                    </div>
                </div>

                <div class="rounded-2xl bg-green-50 border border-green-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-green-600 font-black">
                        Saved Passkeys
                    </div>
                    <div class="font-black text-green-800 text-3xl mt-1">
                        {{ $passkeys->count() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Saved Passkeys --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b bg-slate-50">
            <h2 class="text-2xl font-black text-slate-900">
                Saved Passkeys
            </h2>

            <p class="text-slate-500 mt-1">
                These devices can login without typing password.
            </p>
        </div>

        <div class="p-6">
            <div class="space-y-3" id="passkeyList">
                @forelse($passkeys as $passkey)
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
                                <i class="fa-solid fa-key"></i>
                            </div>

                            <div>
                                <div class="font-black text-slate-900">
                                    {{ $passkey->name ?? 'Passkey' }}
                                </div>

                                <div class="text-xs text-slate-500 mt-1 font-bold">
                                    Added:
                                    {{ optional($passkey->created_at)->format('Y-m-d H:i') ?? '-' }}
                                </div>
                            </div>
                        </div>

                        <button type="button"
                                class="delete-passkey px-5 py-3 rounded-2xl bg-red-100 text-red-700 font-black hover:bg-red-200"
                                data-id="{{ $passkey->id }}">
                            <i class="fa-solid fa-trash mr-2"></i>
                            Delete
                        </button>
                    </div>
                @empty
                    <div class="rounded-3xl bg-slate-50 border border-slate-200 p-8 text-center">
                        <div class="mx-auto w-16 h-16 rounded-3xl bg-slate-200 text-slate-500 flex items-center justify-center text-2xl">
                            <i class="fa-solid fa-fingerprint"></i>
                        </div>

                        <h3 class="text-xl font-black text-slate-900 mt-4">
                            No passkeys added yet
                        </h3>

                        <p class="text-slate-500 mt-2 font-bold">
                            Add your Mac Touch ID / passkey above first.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const registerBtn = document.getElementById('registerPasskeyBtn');

    if (registerBtn) {
        registerBtn.addEventListener('click', async function () {
            const nameInput = document.getElementById('passkeyName');
            const name = nameInput && nameInput.value.trim()
                ? nameInput.value.trim()
                : 'My Passkey';

            const originalText = registerBtn.innerHTML;

            registerBtn.disabled = true;
            registerBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Opening fingerprint / passkey...';

            try {
                if (!window.Passkeys || typeof window.Passkeys.register !== 'function') {
                    throw new Error('Passkeys JavaScript is not loaded. Check resources/js/app.js and npm run build.');
                }

                await window.Passkeys.register({
                    name: name
                });

                alert('Passkey added successfully. Next time you can login using fingerprint / passkey.');
                window.location.reload();
            } catch (error) {
                console.error(error);

                let message = 'Passkey registration failed or cancelled.';

                if (error && error.message) {
                    message = error.message;
                }

                alert(message);
            } finally {
                registerBtn.disabled = false;
                registerBtn.innerHTML = originalText;
            }
        });
    }

    document.querySelectorAll('.delete-passkey').forEach(function (button) {
        button.addEventListener('click', async function () {
            if (!confirm('Delete this passkey?')) {
                return;
            }

            const id = this.dataset.id;
            const originalText = this.innerHTML;

            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Deleting...';

            try {
                const response = await fetch('/user/passkeys/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                if (!response.ok) {
                    throw new Error('Delete failed.');
                }

                window.location.reload();
            } catch (error) {
                console.error(error);
                alert('Passkey delete failed.');
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    });
});
</script>

@endsection