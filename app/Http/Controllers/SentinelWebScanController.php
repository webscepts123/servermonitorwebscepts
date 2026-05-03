<?php

namespace App\Http\Controllers;

use App\Models\SentinelWebScan;
use App\Models\Server;
use App\Models\ServerSecurityAlert;
use App\Services\SentinelWebScannerService;
use Illuminate\Http\Request;

class SentinelWebScanController extends Controller
{
    public function index()
    {
        $servers = Server::latest()->get();

        $scans = SentinelWebScan::with('server')
            ->latest()
            ->limit(50)
            ->get();

        $stats = [
            'total' => SentinelWebScan::count(),
            'critical' => SentinelWebScan::where('risk_level', 'critical')->count(),
            'high' => SentinelWebScan::where('risk_level', 'high')->count(),
            'medium' => SentinelWebScan::where('risk_level', 'medium')->count(),
            'low' => SentinelWebScan::where('risk_level', 'low')->count(),
        ];

        return view('technology.web-scanner', compact('servers', 'scans', 'stats'));
    }

    public function scan(Request $request, SentinelWebScannerService $scanner)
    {
        $data = $request->validate([
            'url' => 'required|string|max:500',
            'server_id' => 'nullable|exists:servers,id',
        ]);

        try {
            $result = $scanner->scan($data['url']);

            $scan = SentinelWebScan::create([
                'server_id' => $data['server_id'] ?? null,
                ...$result,
            ]);

            if (in_array($scan->risk_level, ['critical', 'high'])) {
                $this->createAlert(
                    $scan,
                    'web-scan',
                    $scan->risk_level === 'critical' ? 'danger' : 'warning',
                    'High risk website scan detected',
                    $scan->summary
                );
            }

            return redirect()
                ->route('technology.webscanner.show', $scan)
                ->with('success', 'Website scan completed successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Scan failed: ' . $e->getMessage());
        }
    }

    public function show(SentinelWebScan $scan)
    {
        $scan->load('server');

        return view('technology.web-scan-show', compact('scan'));
    }

    public function rescan(SentinelWebScan $scan, SentinelWebScannerService $scanner)
    {
        try {
            $result = $scanner->scan($scan->url);

            $scan->update($result);

            return redirect()
                ->route('technology.webscanner.show', $scan)
                ->with('success', 'Website re-scan completed successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Re-scan failed: ' . $e->getMessage());
        }
    }

    public function destroy(SentinelWebScan $scan)
    {
        $scan->delete();

        return redirect()
            ->route('technology.webscanner.index')
            ->with('success', 'Scan deleted successfully.');
    }

    private function createAlert(
        SentinelWebScan $scan,
        string $type,
        string $level,
        string $title,
        string $message
    ): void {
        if (!class_exists(ServerSecurityAlert::class)) {
            return;
        }

        try {
            ServerSecurityAlert::create([
                'server_id' => $scan->server_id,
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