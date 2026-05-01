<div class="bg-white p-6 rounded-2xl shadow mt-6">
    <h3 class="text-lg font-semibold mb-4">Server Connection</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label>Host / IP</label>
            <input type="text" name="host"
                   value="{{ old('host', $server->host ?? '') }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div>
            <label>SSH Port</label>
            <input type="number" name="ssh_port"
                   value="{{ old('ssh_port', $server->ssh_port ?? 22) }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div>
            <label>Username</label>
            <input type="text" name="username"
                   value="{{ old('username', $server->username ?? '') }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div>
            <label>Password</label>
            <input type="password" name="password"
                   value="{{ old('password', $server->password ?? '') }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div class="md:col-span-2">
            <label>
                <input type="checkbox" name="is_active"
                       {{ old('is_active', $server->is_active ?? 1) ? 'checked' : '' }}>
                Enable Monitoring
            </label>
        </div>

    </div>
</div>

{{-- BACKUP SETTINGS --}}
<div class="bg-white p-6 rounded-2xl shadow mt-6">
    <h3 class="text-lg font-semibold mb-4">Backup Settings</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label>Backup Path</label>
            <input type="text" name="backup_path"
                   value="{{ old('backup_path', $server->backup_path ?? '') }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div>
            <label>Local Backup Path</label>
            <input type="text" name="local_backup_path"
                   value="{{ old('local_backup_path', $server->local_backup_path ?? '') }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div>
            <label>Google Drive Remote</label>
            <input type="text" name="google_drive_remote"
                   value="{{ old('google_drive_remote', $server->google_drive_remote ?? '') }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div>
            <label>Disk Warning %</label>
            <input type="number" name="disk_warning_percent"
                   value="{{ old('disk_warning_percent', $server->disk_warning_percent ?? 80) }}"
                   class="w-full border rounded-xl p-2">
        </div>

        <div>
            <label>Disk Transfer %</label>
            <input type="number" name="disk_transfer_percent"
                   value="{{ old('disk_transfer_percent', $server->disk_transfer_percent ?? 90) }}"
                   class="w-full border rounded-xl p-2">
        </div>

    </div>
</div>