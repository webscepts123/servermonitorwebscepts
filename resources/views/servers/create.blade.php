@extends('layouts.app')

@section('page-title', 'Add Server')

@section('content')

<form method="POST" action="{{ route('servers.store') }}">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- BASIC --}}
        <div class="bg-white p-6 rounded-2xl shadow">
            <h3 class="font-semibold mb-4">Basic Info</h3>

            <div class="space-y-4">

                <div>
                    <label>Name</label>
                    <input type="text" name="name"
                           value="{{ old('name') }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>Host / IP</label>
                    <input type="text" name="host"
                           value="{{ old('host') }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>SSH Port</label>
                    <input type="number" name="ssh_port"
                           value="{{ old('ssh_port', 22) }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>Username</label>
                    <input type="text" name="username"
                           value="{{ old('username') }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>Password</label>
                    <input type="password" name="password"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="is_active" checked>
                        Enable Monitoring
                    </label>
                </div>

            </div>
        </div>

        {{-- BACKUP --}}
        <div class="bg-white p-6 rounded-2xl shadow">
            <h3 class="font-semibold mb-4">Backup Settings</h3>

            <div class="space-y-4">

                <div>
                    <label>Backup Path</label>
                    <input type="text" name="backup_path"
                           value="{{ old('backup_path') }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>Local Backup Path</label>
                    <input type="text" name="local_backup_path"
                           value="{{ old('local_backup_path') }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>Google Drive Remote</label>
                    <input type="text" name="google_drive_remote"
                           value="{{ old('google_drive_remote') }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>Disk Warning %</label>
                    <input type="number" name="disk_warning_percent"
                           value="{{ old('disk_warning_percent', 80) }}"
                           class="w-full border rounded-xl p-2">
                </div>

                <div>
                    <label>Disk Transfer %</label>
                    <input type="number" name="disk_transfer_percent"
                           value="{{ old('disk_transfer_percent', 90) }}"
                           class="w-full border rounded-xl p-2">
                </div>

            </div>
        </div>

    </div>

    <div class="mt-6">
        <button class="bg-blue-600 text-white px-6 py-3 rounded-xl">
            Save Server
        </button>
    </div>

</form>

@endsection