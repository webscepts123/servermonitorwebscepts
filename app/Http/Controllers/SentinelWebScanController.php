<?php

namespace App\Http\Controllers;

use App\Models\SentinelWebScan;
use App\Models\Server;
use App\Models\ServerSecurityAlert;
use App\Services\SentinelWebScannerService;
use Illuminate\Http\Request;
use App\Services\SentinelPythonSecurityService;

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

    public function scan(Request $request, SentinelPythonSecurityService $pythonScanner)
    {
        $data = $request->validate([
            'url' => 'required|string|max:500',
            'server_id' => 'nullable|exists:servers,id',
        ]);
    
        try {
            $result = $pythonScanner->scanWebsite($data['url']);
    
            $scan = SentinelWebScan::create([
                'server_id' => $data['server_id'] ?? null,
                'url' => $result['url'] ?? $data['url'],
                'domain' => $result['domain'] ?? null,
                'ip' => $result['ip'] ?? null,
                'http_status' => $result['http_status'] ?? null,
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'ssl_valid' => $result['ssl']['valid'] ?? false,
                'ssl_expires_at' => $result['ssl']['expires_at'] ?? null,
                'detected_technologies' => $result['detected_technologies'] ?? [],
                'security_headers' => $result['security_headers'] ?? [],
                'missing_headers' => $result['missing_headers'] ?? [],
                'exposed_files' => $result['exposed_files'] ?? [],
                'database_risks' => $result['database_risks'] ?? [],
                'framework_risks' => $result['framework_risks'] ?? [],
                'risk_score' => $result['risk_score'] ?? 0,
                'risk_level' => $result['risk_level'] ?? 'low',
                'summary' => $result['summary'] ?? null,
            ]);
    
            if (in_array($scan->risk_level, ['critical', 'high'])) {
                $this->createAlert(
                    $scan,
                    'web-scan',
                    $scan->risk_level === 'critical' ? 'danger' : 'warning',
                    'SentinelCore smart web scan detected risk',
                    $scan->summary
                );
            }
    
            return redirect()
                ->route('technology.webscanner.show', $scan)
                ->with('success', 'SentinelCore smart scan completed successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Smart scan failed: ' . $e->getMessage());
        }
    }

    public function show(SentinelWebScan $scan)
    {
        $scan->load('server');

        return view('technology.web-scan-show', compact('scan'));
    }

    public function rescan(SentinelWebScan $scan, SentinelPythonSecurityService $pythonScanner)
    {
        try {
            $result = $pythonScanner->scanWebsite($scan->url);
    
            $scan->update([
                'url' => $result['url'] ?? $scan->url,
                'domain' => $result['domain'] ?? null,
                'ip' => $result['ip'] ?? null,
                'http_status' => $result['http_status'] ?? null,
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'ssl_valid' => $result['ssl']['valid'] ?? false,
                'ssl_expires_at' => $result['ssl']['expires_at'] ?? null,
                'detected_technologies' => $result['detected_technologies'] ?? [],
                'security_headers' => $result['security_headers'] ?? [],
                'missing_headers' => $result['missing_headers'] ?? [],
                'exposed_files' => $result['exposed_files'] ?? [],
                'database_risks' => $result['database_risks'] ?? [],
                'framework_risks' => $result['framework_risks'] ?? [],
                'risk_score' => $result['risk_score'] ?? 0,
                'risk_level' => $result['risk_level'] ?? 'low',
                'summary' => $result['summary'] ?? null,
            ]);
    
            return redirect()
                ->route('technology.webscanner.show', $scan)
                ->with('success', 'SentinelCore smart re-scan completed successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Smart re-scan failed: ' . $e->getMessage());
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