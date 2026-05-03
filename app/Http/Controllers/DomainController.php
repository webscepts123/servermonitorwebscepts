<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\CloudnsService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(CloudnsService $cloudns)
    {
        $error = null;
        $zones = [];
        $apiConnected = false;

        $servers = Server::latest()->get();

        try {
            $zones = $cloudns->listZones();
            $apiConnected = true;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $normalizedZones = collect($zones)->map(function ($zone) {
            return [
                'name' => $zone['name']
                    ?? $zone['domain']
                    ?? $zone['domain-name']
                    ?? null,

                'type' => $zone['type']
                    ?? $zone['zone-type']
                    ?? 'master',

                'status' => $zone['status']
                    ?? 'active',

                'raw' => $zone,
            ];
        })->filter(fn ($zone) => !empty($zone['name']))->values();

        return view('domains.index', [
            'servers' => $servers,
            'zones' => $normalizedZones,
            'apiConnected' => $apiConnected,
            'error' => $error,
        ]);
    }

    public function linkServer(Request $request, Server $server)
    {
        $data = $request->validate([
            'linked_domain' => 'required|string|max:255',
        ]);

        $server->update([
            'linked_domain' => $data['linked_domain'],
            'website_url' => 'https://' . $data['linked_domain'],
        ]);

        return back()->with('success', 'Domain linked to server successfully.');
    }

    public function unlinkServer(Server $server)
    {
        $server->update([
            'linked_domain' => null,
        ]);

        return back()->with('success', 'Domain unlinked from server successfully.');
    }

    public function createZone(Request $request, CloudnsService $cloudns)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'zone_type' => 'nullable|string|in:master,slave,parked,geodns',
        ]);

        try {
            $cloudns->registerZone($data['domain'], $data['zone_type'] ?? 'master');

            return back()->with('success', 'DNS zone created successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to create DNS zone: ' . $e->getMessage());
        }
    }

    public function records(string $domain, CloudnsService $cloudns)
    {
        $error = null;
        $records = [];

        try {
            $records = $cloudns->records($domain);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('domains.records', compact('domain', 'records', 'error'));
    }

    public function addRecord(Request $request, CloudnsService $cloudns)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'type' => 'required|string|max:20',
            'host' => 'nullable|string|max:255',
            'record' => 'required|string|max:500',
            'ttl' => 'nullable|integer|min:60|max:604800',
        ]);

        try {
            $cloudns->addRecord(
                $data['domain'],
                $data['type'],
                $data['host'] ?? '',
                $data['record'],
                $data['ttl'] ?? 3600
            );

            return back()->with('success', 'DNS record added successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to add DNS record: ' . $e->getMessage());
        }
    }

    public function deleteRecord(Request $request, CloudnsService $cloudns)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'record_id' => 'required|string|max:100',
        ]);

        try {
            $cloudns->deleteRecord($data['domain'], $data['record_id']);

            return back()->with('success', 'DNS record deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete DNS record: ' . $e->getMessage());
        }
    }
}