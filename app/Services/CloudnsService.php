<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CloudnsService
{
    protected string $baseUrl;
    protected string $authId;
    protected string $authPassword;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.cloudns.api_url', 'https://api.cloudns.net'), '/');
        $this->authId = (string) config('services.cloudns.auth_id');
        $this->authPassword = (string) config('services.cloudns.auth_password');
    }

    private function request(string $path, array $params = []): array
    {
        if (empty($this->authId) || empty($this->authPassword)) {
            throw new \Exception('ClouDNS API credentials are missing.');
        }

        $query = array_merge([
            'auth-id' => $this->authId,
            'auth-password' => $this->authPassword,
        ], $params);

        $response = Http::timeout(25)
            ->asForm()
            ->post($this->baseUrl . $path . '.json', $query);

        if (!$response->successful()) {
            throw new \Exception('ClouDNS HTTP error: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new \Exception('Invalid ClouDNS response.');
        }

        if (isset($data['status']) && strtolower((string) $data['status']) === 'failed') {
            throw new \Exception($data['statusDescription'] ?? $data['statusDescriptionCode'] ?? 'ClouDNS API failed.');
        }

        return $data;
    }

    public function listZones(int $page = 1, int $rowsPerPage = 100, ?string $search = null): array
    {
        $params = [
            'page' => $page,
            'rows-per-page' => $rowsPerPage,
        ];

        if (!empty($search)) {
            $params['search'] = $search;
        }

        return $this->request('/dns/list-zones', $params);
    }

    public function records(string $domain): array
    {
        return $this->request('/dns/records', [
            'domain-name' => $domain,
        ]);
    }

    public function addRecord(string $domain, string $type, string $host, string $record, int $ttl = 300): array
    {
        return $this->request('/dns/add-record', [
            'domain-name' => $domain,
            'record-type' => strtoupper($type),
            'host' => $host,
            'record' => $record,
            'ttl' => $ttl,
        ]);
    }

    public function updateRecord(string $domain, string $recordId, string $type, string $host, string $record, int $ttl = 300): array
    {
        return $this->request('/dns/mod-record', [
            'domain-name' => $domain,
            'record-id' => $recordId,
            'record-type' => strtoupper($type),
            'host' => $host,
            'record' => $record,
            'ttl' => $ttl,
        ]);
    }

    public function deleteRecord(string $domain, string $recordId): array
    {
        return $this->request('/dns/delete-record', [
            'domain-name' => $domain,
            'record-id' => $recordId,
        ]);
    }

    public function registerZone(string $domain, string $zoneType = 'master'): array
    {
        return $this->request('/dns/register', [
            'domain-name' => $domain,
            'zone-type' => $zoneType,
        ]);
    }

    public function updateARecordsToIp(string $domain, string $newIp): array
    {
        $records = $this->records($domain);

        $updated = [];
        $hasRootA = false;

        foreach ($records as $record) {
            $recordId = $record['id'] ?? $record['record-id'] ?? null;
            $type = strtoupper($record['type'] ?? $record['record-type'] ?? '');
            $host = $record['host'] ?? $record['name'] ?? '';
            $ttl = (int) ($record['ttl'] ?? 300);

            if (!$recordId || $type !== 'A') {
                continue;
            }

            $cleanHost = trim((string) $host);

            if ($cleanHost === '' || $cleanHost === '@') {
                $hasRootA = true;

                $updated[] = $this->updateRecord(
                    domain: $domain,
                    recordId: (string) $recordId,
                    type: 'A',
                    host: '',
                    record: $newIp,
                    ttl: $ttl ?: 300
                );
            }

            if ($cleanHost === 'www') {
                $updated[] = $this->updateRecord(
                    domain: $domain,
                    recordId: (string) $recordId,
                    type: 'A',
                    host: 'www',
                    record: $newIp,
                    ttl: $ttl ?: 300
                );
            }
        }

        if (!$hasRootA) {
            $updated[] = $this->addRecord($domain, 'A', '', $newIp, 300);
        }

        return $updated;
    }
}