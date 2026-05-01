<?php

namespace App\Services;

use App\Models\Server;
use phpseclib3\Net\SSH2;
use Exception;

class ServerMonitorService
{
    public function check(Server $server): array
    {
        try {
            $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
            $ssh->setTimeout(10);

            if (!$ssh->login($server->username, $server->password)) {
                throw new Exception('SSH login failed');
            }

            $metrics = [
                'uptime' => trim($ssh->exec('uptime -p')),
                'load' => trim($ssh->exec("cat /proc/loadavg | awk '{print $1,$2,$3}'")),
                'cpu' => $this->cpu($ssh),
                'ram' => $this->ram($ssh),
                'disk' => $this->disk($ssh),
            ];

            return [
                'status' => 'online',
                'metrics' => $metrics
            ];

        } catch (Exception $e) {
            return [
                'status' => 'offline',
                'error' => $e->getMessage()
            ];
        }
    }

    private function cpu($ssh)
    {
        $cmd = "top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}'";
        return (int) round((float) trim($ssh->exec($cmd)));
    }

    private function ram($ssh)
    {
        $cmd = "free -m | awk 'NR==2{printf \"%s,%s\", $2,$3}'";
        [$total, $used] = explode(',', trim($ssh->exec($cmd)));

        return [
            'total' => (int) $total,
            'used' => (int) $used,
            'percent' => $total > 0 ? round(($used / $total) * 100) : 0,
        ];
    }

    private function disk($ssh)
    {
        $cmd = "df -h / | awk 'NR==2{print $5}'";
        $percent = str_replace('%', '', trim($ssh->exec($cmd)));

        return (int) $percent;
    }
}