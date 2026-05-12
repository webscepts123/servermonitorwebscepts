@extends('layouts.app')

@section('page-title', 'Fingerprint / Passkeys')

@section('content')

<div class="max-w-4xl mx-auto space-y-6">

    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 bg-slate-950 text-white">
            <h1 class="text-3xl font-black">Fingerprint / Passkey Login</h1>
            <p class="text-slate-300 mt-2">
                First add your Mac Touch ID / passkey here. Then you can login using fingerprint.
            </p>
        </div>

        <div class="p-6 space-y-6">

            <div class="rounded-2xl bg-blue-50 border border-blue-200 p-5">
                <h2 class="text-xl font-black text-blue-900">Add This Device</h2>
                <p class="text-blue-700 mt-1 text-sm font-semibold">
                    On Mac Chrome, this will open Touch ID or Mac password.
                </p>

                <div class="mt-4">
                    <label class="block text-sm font-black text-slate-700 mb-2">Passkey Name</label>
                    <input type="text"
                           id="passkeyName"
                           value="My MacBook"
                           class="w-full border border-slate-300 rounded-xl p-3"
                           placeholder="My MacBook">
                </div>

                <button type="button"
                        id="registerPasskeyBtn"
                        class="mt-4 px-6 py-3 rounded-xl bg-blue-600 text-white font-black hover:bg-blue-700">
                    Add Fingerprint / Passkey
                </button>
            </div>

            <div>
                <h2 class="text-xl font-black text-slate-900 mb-4">Saved Passkeys</h2>

                <div class="space-y-3">
                    @forelse($passkeys as $passkey)
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 p-4">
                            <div>
                                <div class="font-black text-slate-900">
                                    {{ $passkey->name ?? 'Passkey' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    Added: {{ optional($passkey->created_at)->format('Y-m-d H:i') }}
                                </div>
                            </div>

                            <button type="button"
                                    class="delete-passkey px-4 py-2 rounded-xl bg-red-100 text-red-700 font-bold hover:bg-red-200"
                                    data-id="{{ $passkey->id }}">
                                Delete
                            </button>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5 text-slate-500 font-bold">
                            No passkeys added yet. Login with password first and add one here.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const registerBtn = document.getElementById('registerPasskeyBtn');

    registerBtn?.addEventListener('click', async function () {
        const name = document.getElementById('passkeyName')?.value || 'My Passkey';

        registerBtn.disabled = true;
        registerBtn.innerText = 'Opening fingerprint/passkey...';

        try {
            if (!window.Passkeys || typeof window.Passkeys.register !== 'function') {
                throw new Error('Passkeys JavaScript is not loaded.');
            }

            await window.Passkeys.register({ name });

            alert('Passkey added successfully. You can now login with fingerprint.');
            window.location.reload();
        } catch (error) {
            console.error(error);
            alert(error.message || 'Passkey registration failed or cancelled.');
        } finally {
            registerBtn.disabled = false;
            registerBtn.innerText = 'Add Fingerprint / Passkey';
        }
    });

    document.querySelectorAll('.delete-passkey').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this passkey?')) {
                return;
            }

            try {
                const response = await fetch('/user/passkeys/' + this.dataset.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Delete failed');
                }

                window.location.reload();
            } catch (error) {
                alert('Passkey delete failed.');
            }
        });
    });
});
</script>

@endsection