<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use phpseclib3\Net\SSH2;

class BackupController extends Controller
{
    public function index()
    {
        $servers = Server::latest()->get();

        return view('backups.index', compact('servers'));
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
            'backup_server_id' => 'nullable|exists:servers,id',
            'disk_warning_percent' => 'required|integer|min:1|max:100',
            'disk_transfer_percent' => 'required|integer|min:1|max:100',
            'google_drive_remote' => 'nullable|string|max:255',
            'backup_path' => 'required|string|max:255',
            'local_backup_path' => 'nullable|string|max:255',
            'sync_time' => 'nullable|string|max:20',
        ]);

        $server = Server::findOrFail($request->server_id);

        $server->update([
            'backup_server_id' => $request->backup_server_id,
            'disk_warning_percent' => $request->disk_warning_percent,
            'disk_transfer_percent' => $request->disk_transfer_percent,
            'google_drive_remote' => $request->google_drive_remote ?: 'gdrive',
            'backup_path' => $request->backup_path ?: '/backup',
            'local_backup_path' => $request->local_backup_path,
            'sync_time' => $request->sync_time,
            'auto_transfer' => $request->has('auto_transfer') ? 1 : 0,
            'google_drive_sync' => $request->has('google_drive_sync') ? 1 : 0,
        ]);

        return back()->with('success', 'Backup settings saved successfully.');
    }

    public function pullBackupToMonitor(Request $request)
    {
        $server = Server::findOrFail($request->server_id);

        $result = $this->pullFromRemoteServer($server);

        return back()
            ->with($result['success'] ? 'success' : 'error', $result['message'])
            ->with('output', $result['output']);
    }

    public function uploadToGoogleDrive(Request $request)
    {
        $server = Server::findOrFail($request->server_id);

        $result = $this->uploadLocalBackupToGoogleDrive($server);

        return back()
            ->with($result['success'] ? 'success' : 'error', $result['message'])
            ->with('output', $result['output']);
    }

    public function fullSync(Request $request)
    {
        $server = Server::findOrFail($request->server_id);

        $pull = $this->pullFromRemoteServer($server);

        if (!$pull['success']) {
            return back()
                ->with('error', $pull['message'])
                ->with('output', $pull['output']);
        }

        $drive = $this->uploadLocalBackupToGoogleDrive($server);

        return back()
            ->with($drive['success'] ? 'success' : 'error', $drive['message'])
            ->with('output', $pull['output'] . "\n\n--- GOOGLE DRIVE ---\n\n" . $drive['output']);
    }

    public function transferToBackupServer(Request $request)
    {
        $server = Server::findOrFail($request->server_id);

        if (!$server->backupServer) {
            return back()->with('error', 'No backup server assigned.');
        }

        $localPath = $this->localBackupPath($server);
        $backupServer = $server->backupServer;

        if (!File::exists($localPath)) {
            return back()->with('error', 'Local backup does not exist. Pull backup first.');
        }

        $remotePath = "/backup-transfers/{$server->name}";

        $command = sprintf(
            'rsync -avz --delete -e "ssh -p %d -o StrictHostKeyChecking=no" %s/ %s@%s:%s/',
            (int) $backupServer->ssh_port,
            escapeshellarg($localPath),
            escapeshellarg($backupServer->username),
            escapeshellarg($backupServer->host),
            escapeshellarg($remotePath)
        );

        $process = Process::timeout(3600)->run($command);

        return back()
            ->with($process->successful() ? 'success' : 'error', $process->successful() ? 'Transferred to backup server.' : 'Transfer failed.')
            ->with('output', $process->output() . $process->errorOutput());
    }

    private function pullFromRemoteServer(Server $server): array
    {
        $localPath = $this->localBackupPath($server);

        File::ensureDirectoryExists($localPath);

        $remotePath = $server->backup_path ?: '/backup';

        $command = sprintf(
            'rsync -avz --delete -e "ssh -p %d -o StrictHostKeyChecking=no" %s@%s:%s/ %s/',
            (int) $server->ssh_port,
            escapeshellarg($server->username),
            escapeshellarg($server->host),
            escapeshellarg($remotePath),
            escapeshellarg($localPath)
        );

        $process = Process::timeout(3600)->run($command);

        return [
            'success' => $process->successful(),
            'message' => $process->successful()
                ? 'Backup pulled to monitor server successfully.'
                : 'Failed to pull backup from server.',
            'output' => $process->output() . $process->errorOutput(),
        ];
    }

    private function uploadLocalBackupToGoogleDrive(Server $server): array
    {
        $localPath = $this->localBackupPath($server);

        if (!File::exists($localPath)) {
            return [
                'success' => false,
                'message' => 'Local backup folder not found. Pull backup first.',
                'output' => '',
            ];
        }

        $remote = $server->google_drive_remote ?: 'gdrive';
        $drivePath = "server-backups/{$server->name}";

        $command = sprintf(
            'rclone sync %s %s:%s --progress',
            escapeshellarg($localPath),
            escapeshellarg($remote),
            escapeshellarg($drivePath)
        );

        $process = Process::timeout(3600)->run($command);

        return [
            'success' => $process->successful(),
            'message' => $process->successful()
                ? 'Backup uploaded to Google Drive successfully.'
                : 'Google Drive upload failed.',
            'output' => $process->output() . $process->errorOutput(),
        ];
    }

    private function localBackupPath(Server $server): string
    {
        if ($server->local_backup_path) {
            return rtrim($server->local_backup_path, '/');
        }

        return storage_path('app/server-backups/' . $server->name);
    }

    private function getServerPassword(Server $server): string
    {
        if (!$server->password) {
            return '';
        }

        try {
            return decrypt($server->password);
        } catch (\Throwable $e) {
            return $server->password;
        }
    }

    public function runGoogleDriveBackup(Request $request)
    {
        return $this->uploadToGoogleDrive($request);
    }

    public function autoDiskBackup()
    {
        $servers = \App\Models\Server::latest()->get();
        return view('backups.auto-disk-backup', compact('servers'));
    }

    public function logs()
    {
        $servers = \App\Models\Server::with(['checks', 'securityAlerts'])
            ->latest()
            ->get();

        return view('backups.logs', compact('servers'));
    }

    private function ssh(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, (int) $server->ssh_port);

        if (!$ssh->login($server->username, $this->getServerPassword($server))) {
            abort(422, 'SSH login failed.');
        }

        return $ssh;
    }
}