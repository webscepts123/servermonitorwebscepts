@extends('layouts.app')

@section('page-title', 'Add Server')

@section('content')

@if ($errors->any())
    <div class="bg-red-100 text-red-700 border border-red-300 rounded-xl p-4 mb-6">
        <strong>Please fix these errors:</strong>
        <ul class="list-disc ml-5 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('servers.store') }}">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white p-6 rounded-2xl shadow">
            <h3 class="font-semibold mb-4">Basic Info</h3>

            <div class="space-y-4">
                <div>
                    <label class="block mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full border rounded-xl p-2" required>
                </div>

                <div>
                    <label class="block mb-1">Host / IP</label>
                    <input type="text" name="host" value="{{ old('host') }}" class="w-full border rounded-xl p-2" required>
                </div>

                <div>
                    <label class="block mb-1">SSH Port</label>
                    <input type="number" name="ssh_port" value="{{ old('ssh_port', 22) }}" class="w-full border rounded-xl p-2" required>
                </div>

                <div>
                    <label class="block mb-1">Username</label>
                    <input type="text" name="username" value="{{ old('username') }}" class="w-full border rounded-xl p-2" required>
                </div>

                <div>
                    <label class="block mb-1">Password</label>
                    <input type="password" name="password" class="w-full border rounded-xl p-2">
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    Enable Monitoring
                </label>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow">
            <h3 class="font-semibold mb-4">Backup Settings</h3>

            <div class="space-y-4">
                <div>
                    <label class="block mb-1">Backup Path</label>
                    <input type="text" name="backup_path" value="{{ old('backup_path') }}" class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label class="block mb-1">Local Backup Path</label>
                    <input type="text" name="local_backup_path" value="{{ old('local_backup_path') }}" class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label class="block mb-1">Google Drive Remote</label>
                    <input type="text" name="google_drive_remote" value="{{ old('google_drive_remote') }}" class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label class="block mb-1">Disk Warning %</label>
                    <input type="number" name="disk_warning_percent" value="{{ old('disk_warning_percent', 80) }}" class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label class="block mb-1">Disk Transfer %</label>
                    <input type="number" name="disk_transfer_percent" value="{{ old('disk_transfer_percent', 90) }}" class="w-full border rounded-xl p-2">
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-xl">
            Save Server
        </button>

        <a href="{{ route('servers.index') }}" class="bg-gray-200 text-gray-800 px-6 py-3 rounded-xl">
            Cancel
        </a>
    </div>
</form>

@endsection