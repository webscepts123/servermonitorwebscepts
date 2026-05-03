<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerDomain;
use App\Services\CloudnsService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(CloudnsService $cloudns)
    {
        $error = null;
        $zones = [];
        $apiConnected = false;

        $servers = Server::with('domains')->latest()->get();
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
            'is_primary' => 'nullable|boolean',
        ]);

        $domain = strtolower(trim($data['linked_domain']));

        ServerDomain::updateOrCreate(
            [
                'server_id' => $server->id,
                'domain' => $domain,
            ],
            [
                'dns_provider' => 'cloudns',
                'active_dns_ip' => $server->host,
                'is_primary' => $request->has('is_primary'),
            ]
        );

        if ($request->has('is_primary')) {
            ServerDomain::where('server_id', $server->id)
                ->where('domain', '!=', $domain)
                ->update(['is_primary' => false]);

            $server->update([
                'linked_domain' => $domain,
                'website_url' => 'https://' . $domain,
            ]);
        } elseif (empty($server->linked_domain)) {
            $server->update([
                'linked_domain' => $domain,
                'website_url' => 'https://' . $domain,
            ]);

            ServerDomain::where('server_id', $server->id)
                ->where('domain', $domain)
                ->update(['is_primary' => true]);
        }

        return back()->with('success', $domain . ' linked to ' . $server->name . ' successfully.');
    }

    public function unlinkServer(Request $request, Server $server)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $domain = strtolower(trim($data['domain']));

        ServerDomain::where('server_id', $server->id)
            ->where('domain', $domain)
            ->delete();

        $nextPrimary = ServerDomain::where('server_id', $server->id)
            ->latest()
            ->first();

        if ($server->linked_domain === $domain) {
            if ($nextPrimary) {
                $nextPrimary->update(['is_primary' => true]);

                $server->update([
                    'linked_domain' => $nextPrimary->domain,
                    'website_url' => 'https://' . $nextPrimary->domain,
                ]);
            } else {
                $server->update([
                    'linked_domain' => null,
                    'website_url' => null,
                ]);
            }
        }

        return back()->with('success', $domain . ' unlinked successfully.');
    }

    public function makePrimary(Server $server, ServerDomain $domain)
    {
        if ((int) $domain->server_id !== (int) $server->id) {
            abort(404);
        }

        ServerDomain::where('server_id', $server->id)->update([
            'is_primary' => false,
        ]);

        $domain->update([
            'is_primary' => true,
        ]);

        $server->update([
            'linked_domain' => $domain->domain,
            'website_url' => 'https://' . $domain->domain,
        ]);

        return back()->with('success', $domain->domain . ' set as primary domain.');
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