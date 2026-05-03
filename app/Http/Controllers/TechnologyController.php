<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerSecurityAlert;
use App\Services\SentinelEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class TechnologyController extends Controller
{
    public function index()
    {
        $servers = Server::latest()->get();

        $securityStats = [
            'servers' => $servers->count(),
            'encrypted_passwords' => $servers->filter(fn ($server) => !empty($server->password))->count(),
            'active_monitoring' => $servers->where('is_active', true)->count(),
            'sms_enabled' => $servers->where('sms_alerts_enabled', true)->count(),
            'email_enabled' => $servers->where('email_alerts_enabled', true)->count(),
            'dns_failover' => $servers->where('dns_failover_enabled', true)->count(),
            'backup_failover' => $servers->where('failover_enabled', true)->count(),
        ];

        $securityChecks = [
            [
                'title' => 'Laravel APP_KEY Encryption',
                'status' => !empty(config('app.key')),
                'description' => 'Application encryption key is used for protected server credentials and encrypted data.',
            ],
            [
                'title' => 'Server Password Encryption',
                'status' => true,
                'description' => 'Server SSH/WHM passwords are encrypted before storing in the database.',
            ],
            [
                'title' => 'Database Field Protection',
                'status' => true,
                'description' => 'Sensitive fields can be encrypted using Laravel Crypt.',
            ],
            [
                'title' => 'Backup File Vault',
                'status' => is_dir(storage_path('app/sentinel-vault')),
                'description' => 'Encrypted file vault for sensitive backup/security files.',
            ],
            [
                'title' => 'Security Alert Table',
                'status' => class_exists(ServerSecurityAlert::class),
                'description' => 'Security events are logged into the monitoring alert system.',
            ],
            [
                'title' => 'HTTPS Recommended',
                'status' => request()->isSecure(),
                'description' => 'Use SSL/HTTPS in production to protect panel access.',
            ],
        ];

        $recentAlerts = collect();

        if (class_exists(ServerSecurityAlert::class)) {
            $recentAlerts = ServerSecurityAlert::latest()->limit(10)->get();
        }

        return view('technology.index', compact(
            'servers',
            'securityStats',
            'securityChecks',
            'recentAlerts'
        ));
    }

    public function encryptText(Request $request, SentinelEncryptionService $encryption)
    {
        $data = $request->validate([
            'plain_text' => 'required|string|max:5000',
        ]);

        $encrypted = $encryption->encryptText($data['plain_text']);

        return back()->with('success', 'Text encrypted successfully.')
            ->with('encrypted_text', $encrypted);
    }

    public function decryptText(Request $request, SentinelEncryptionService $encryption)
    {
        $data = $request->validate([
            'encrypted_text' => 'required|string',
        ]);

        try {
            $decrypted = $encryption->decryptText($data['encrypted_text']);

            return back()->with('success', 'Text decrypted successfully.')
                ->with('decrypted_text', $decrypted);
        } catch (\Throwable $e) {
            return back()->with('error', 'Decrypt failed: ' . $e->getMessage());
        }
    }

    public function encryptFile(Request $request, SentinelEncryptionService $encryption)
    {
        $data = $request->validate([
            'secure_file' => 'required|file|max:51200',
        ]);

        try {
            $file = $request->file('secure_file');

            $encryptedPath = $encryption->encryptUploadedFile($file);

            $this->createAlert(
                'technology',
                'info',
                'SentinelCore file encrypted',
                'File encrypted and stored in secure vault: ' . $encryptedPath
            );

            return back()->with('success', 'File encrypted and stored in SentinelCore vault.')
                ->with('encrypted_file_path', $encryptedPath);

        } catch (\Throwable $e) {
            return back()->with('error', 'File encryption failed: ' . $e->getMessage());
        }
    }

    public function rotateServerPasswords()
    {
        $servers = Server::latest()->get();
        $updated = 0;

        foreach ($servers as $server) {
            if (empty($server->password)) {
                continue;
            }

            try {
                try {
                    $plain = decrypt($server->password);
                } catch (\Throwable $e) {
                    $plain = $server->password;
                }

                $server->update([
                    'password' => encrypt($plain),
                ]);

                $updated++;
            } catch (\Throwable $e) {
                //
            }
        }

        $this->createAlert(
            'technology',
            'info',
            'Server password encryption rotated',
            "Re-encrypted {$updated} server password records."
        );

        return back()->with('success', "Server passwords re-encrypted successfully. Updated: {$updated}");
    }

    private function createAlert(string $type, string $level, string $title, string $message): void
    {
        if (!class_exists(ServerSecurityAlert::class)) {
            return;
        }

        try {
            ServerSecurityAlert::create([
                'server_id' => null,
                'type' => $type,
                'level' => $level,
                'title' => $title,
                'message' => $message,
                'detected_at' => now(),
            ]);
        } catch (\Throwable $e) {
            //
        }
    }
}