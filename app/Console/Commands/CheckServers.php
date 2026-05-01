<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\ServerCheck;
use Illuminate\Console\Command;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class CheckServers extends Command
{
    protected $signature = 'servers:check';
    protected $description = 'Check all servers health';

    public function handle()
    {
        $servers = Server::where('is_active', true)->get();

        foreach ($servers as $server) {
            try {
                $ssh = new SSH2($server->host, $server->ssh_port);

                if ($server->private_key) {
                    $key = PublicKeyLoader::load($server->private_key);
                    $login = $ssh->login($server->username, $key);
                } else {
                    $login = false;
                }

                if (!$login) {
                    ServerCheck::create([
                        'server_id' => $server->id,
                        'online' => false,
                        'status' => 'SSH login failed',
                        'checked_at' => now(),
                    ]);

                    continue;
                }

                $cpu = trim($ssh->exec("top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}'"));
                $ram = trim($ssh->exec("free | awk '/Mem:/ {printf(\"%.2f\", $3/$2 * 100)}'"));
                $disk = trim($ssh->exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'"));
                $load = trim($ssh->exec("uptime | awk -F'load average:' '{ print $2 }'"));

                $services = [
                    'httpd' => trim($ssh->exec("systemctl is-active httpd 2>/dev/null")),
                    'mysql' => trim($ssh->exec("systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null")),
                    'cpanel' => trim($ssh->exec("systemctl is-active cpanel 2>/dev/null")),
                    'sshd' => trim($ssh->exec("systemctl is-active sshd 2>/dev/null")),
                ];

                ServerCheck::create([
                    'server_id' => $server->id,
                    'online' => true,
                    'status' => 'OK',
                    'cpu_usage' => is_numeric($cpu) ? $cpu : null,
                    'ram_usage' => is_numeric($ram) ? $ram : null,
                    'disk_usage' => is_numeric($disk) ? $disk : null,
                    'load_average' => $load,
                    'services' => json_encode($services),
                    'checked_at' => now(),
                ]);

                $this->info("Checked: {$server->name}");
            } catch (\Throwable $e) {
                ServerCheck::create([
                    'server_id' => $server->id,
                    'online' => false,
                    'status' => $e->getMessage(),
                    'checked_at' => now(),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}