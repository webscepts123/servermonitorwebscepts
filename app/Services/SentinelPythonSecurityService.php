<?php

namespace App\Services;

class SentinelPythonSecurityService
{
    public function scanWebsite(string $url): array
    {
        $script = base_path('sentinelcore/python/sentinel_smart_scan.py');

        if (!file_exists($script)) {
            throw new \Exception('SentinelCore Python scanner not found: ' . $script);
        }

        $python = $this->pythonBinary();

        $command = escapeshellcmd($python) . ' ' .
            escapeshellarg($script) . ' ' .
            escapeshellarg($url) . ' 2>&1';

        $output = shell_exec($command);

        if (!$output) {
            throw new \Exception('No output from SentinelCore Python scanner.');
        }

        $jsonStart = strpos($output, '{');

        if ($jsonStart !== false) {
            $output = substr($output, $jsonStart);
        }

        $data = json_decode($output, true);

        if (!is_array($data)) {
            throw new \Exception('Invalid Python scanner JSON output: ' . $output);
        }

        if (($data['success'] ?? false) === false) {
            throw new \Exception($data['error'] ?? 'Python scanner failed.');
        }

        return $data;
    }

    private function pythonBinary(): string
    {
        $candidates = [
            env('SENTINEL_PYTHON_BIN'),
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            'python3',
        ];

        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                return $candidate;
            }
        }

        return 'python3';
    }
}