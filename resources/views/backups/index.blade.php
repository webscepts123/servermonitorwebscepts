@extends('layouts.app')

@section('content')

<div class="space-y-6">

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-2xl font-bold text-slate-800">Backup & Google Drive Sync</h2>
        <p class="text-slate-500 mt-1">
            Backups are pulled to this monitoring system first, then uploaded to Google Drive using local rclone.
        </p>
    </div>

    @if(session('output'))
        <div class="bg-slate-950 rounded-2xl shadow p-5">
            <h3 class="text-white font-bold mb-3">Command Output</h3>
            <pre class="text-green-400 text-xs overflow-x-auto max-h-96 whitespace-pre-wrap">{{ session('output') }}</pre>
        </div>
    @endif

    @foreach($servers as $server)
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="px-6 py-5 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-slate-800">{{ $server->name }}</h3>
                    <p class="text-slate-500">{{ $server->username }}@{{ $server->host }}:{{ $server->ssh_port }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if($server->google_drive_sync)
                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm font-bold">
                            Google Drive Enabled
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-sm font-bold">
                            Google Drive Disabled
                        </span>
                    @endif

                    @if($server->auto_transfer)
                        <span class="px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-sm font-bold">
                            Auto Transfer Enabled
                        </span>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('backups.settings') }}" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                @csrf

                <input type="hidden" name="server_id" value="{{ $server->id }}">

                <div>
                    <label class="block font-semibold mb-1">Remote Backup Path on cPanel Server</label>
                    <input name="backup_path"
                           value="{{ old('backup_path', $server->backup_path ?? '/backup') }}"
                           class="w-full rounded-xl border p-3"
                           placeholder="/backup or /home">
                    <p class="text-xs text-slate-500 mt-1">Example: /backup, /home, /var/cpanel/backups</p>
                </div>

                <div>
                    <label class="block font-semibold mb-1">Local Backup Path on Monitor System</label>
                    <input name="local_backup_path"
                           value="{{ old('local_backup_path', $server->local_backup_path) }}"
                           class="w-full rounded-xl border p-3"
                           placeholder="{{ storage_path('app/server-backups/'.$server->name) }}">
                </div>

                <div>
                    <label class="block font-semibold mb-1">Google Drive Remote Name</label>
                    <input name="google_drive_remote"
                           value="{{ old('google_drive_remote', $server->google_drive_remote ?? 'gdrive') }}"
                           class="w-full rounded-xl border p-3"
                           placeholder="gdrive">
                    <p class="text-xs text-slate-500 mt-1">This is your local rclone remote name.</p>
                </div>

                <div>
                    <label class="block font-semibold mb-1">Daily Sync Time</label>
                    <input type="time"
                           name="sync_time"
                           value="{{ old('sync_time', $server->sync_time) }}"
                           class="w-full rounded-xl border p-3">
                </div>

                <div>
                    <label class="block font-semibold mb-1">Warning Disk Percentage</label>
                    <input type="number"
                           name="disk_warning_percent"
                           value="{{ old('disk_warning_percent', $server->disk_warning_percent ?? 60) }}"
                           class="w-full rounded-xl border p-3">
                </div>

                <div>
                    <label class="block font-semibold mb-1">Transfer Disk Percentage</label>
                    <input type="number"
                           name="disk_transfer_percent"
                           value="{{ old('disk_transfer_percent', $server->disk_transfer_percent ?? 90) }}"
                           class="w-full rounded-xl border p-3">
                </div>

                <div>
                    <label class="block font-semibold mb-1">Assign Backup Server</label>
                    <select name="backup_server_id" class="w-full rounded-xl border p-3">
                        <option value="">No backup server</option>
                        @foreach($servers->where('id', '!=', $server->id) as $backupServer)
                            <option value="{{ $backupServer->id }}"
                                {{ $server->backup_server_id == $backupServer->id ? 'selected' : '' }}>
                                {{ $backupServer->name }} - {{ $backupServer->host }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col justify-center gap-3">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="google_drive_sync" value="1"
                               {{ $server->google_drive_sync ? 'checked' : '' }}>
                        <span class="font-semibold">Enable Google Drive Sync</span>
                    </label>

                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="auto_transfer" value="1"
                               {{ $server->auto_transfer ? 'checked' : '' }}>
                        <span class="font-semibold">Auto transfer if disk is full</span>
                    </label>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 pt-4 border-t">
                    <button class="px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                        Save Settings
                    </button>
            </form>

                    <form method="POST" action="{{ route('backups.pull') }}">
                        @csrf
                        <input type="hidden" name="server_id" value="{{ $server->id }}">
                        <button class="px-5 py-3 rounded-xl bg-slate-900 text-white hover:bg-slate-700">
                            Pull Backup To Monitor
                        </button>
                    </form>

                    <form method="POST" action="{{ route('backups.google') }}">
                        @csrf
                        <input type="hidden" name="server_id" value="{{ $server->id }}">
                        <button class="px-5 py-3 rounded-xl bg-green-600 text-white hover:bg-green-700">
                            Upload To Google Drive
                        </button>
                    </form>

                    <form method="POST" action="{{ route('backups.fullSync') }}">
                        @csrf
                        <input type="hidden" name="server_id" value="{{ $server->id }}">
                        <button class="px-5 py-3 rounded-xl bg-purple-600 text-white hover:bg-purple-700">
                            Pull + Upload Now
                        </button>
                    </form>

                    <form method="POST" action="{{ route('backups.transfer') }}">
                        @csrf
                        <input type="hidden" name="server_id" value="{{ $server->id }}">
                        <button class="px-5 py-3 rounded-xl bg-orange-600 text-white hover:bg-orange-700">
                            Transfer To Backup Server
                        </button>
                    </form>
                </div>
        </div>
    @endforeach

</div>

@endsection