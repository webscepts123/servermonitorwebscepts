<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerSecurityAlert;
use Illuminate\Http\Request;
use phpseclib3\Net\SSH2;

class LiteSpeedController extends Controller
{
    public function index(Server $server)
    {
        $data = $this->getLiteSpeedData($server);

        return view('servers.litespeed.index', compact('server', 'data'));
    }

    public function activate(Server $server)
    {
        return $this->runLiteSpeedAction($server, 'activate');
    }

    public function restart(Server $server)
    {
        return $this->runLiteSpeedAction($server, 'restart');
    }

    public function stop(Server $server)
    {
        return $this->runLiteSpeedAction($server, 'stop');
    }

    public function reload(Server $server)
    {
        return $this->runLiteSpeedAction($server, 'reload');
    }

    public function configTest(Server $server)
    {
        try {
            $ssh = $this->ssh($server);
            $detected = $this->detectLiteSpeed($ssh);

            if ($detected['type'] === 'not_installed') {
                return back()->with('error', 'LiteSpeed is not installed on this server.');
            }

            $output = $ssh->exec("
                if [ -x /usr/local/lsws/bin/lswsctrl ]; then
                    /usr/local/lsws/bin/lswsctrl configtest 2>&1 || true
                elif [ -x /usr/local/lsws/bin/openlitespeed ]; then
                    /usr/local/lsws/bin/openlitespeed -t 2>&1 || true
                else
                    echo 'Config test command not available for detected service.'
                fi
            ");

            $this->saveLiteSpeedAlert($server, 'LiteSpeed config test executed', $output);

            return back()->with('success', 'LiteSpeed config test completed: ' . trim($output));

        } catch (\Throwable $e) {
            return back()->with('error', 'LiteSpeed config test failed: ' . $e->getMessage());
        }
    }

    public function logs(Server $server)
    {
        try {
            $ssh = $this->ssh($server);

            $logs = $ssh->exec("
                echo '===== LiteSpeed Error Log ====='
                tail -n 80 /usr/local/lsws/logs/error.log 2>/dev/null || echo 'No /usr/local/lsws/logs/error.log found'

                echo ''
                echo '===== LiteSpeed Access Log ====='
                tail -n 50 /usr/local/lsws/logs/access.log 2>/dev/null || echo 'No /usr/local/lsws/logs/access.log found'

                echo ''
                echo '===== Systemd LiteSpeed Logs ====='
                journalctl -u lsws -u lshttpd -u openlitespeed -u litespeed -n 80 --no-pager 2>/dev/null || echo 'No systemd logs found'
            ");

            $this->saveLiteSpeedAlert($server, 'LiteSpeed logs checked', $logs);

            return back()->with('success', 'LiteSpeed logs checked. Check Security Alerts / server logs for details.');

        } catch (\Throwable $e) {
            return back()->with('error', 'LiteSpeed logs failed: ' . $e->getMessage());
        }
    }

    private function runLiteSpeedAction(Server $server, string $action)
    {
        try {
            $ssh = $this->ssh($server);
            $detected = $this->detectLiteSpeed($ssh);

            if ($detected['type'] === 'not_installed') {
                return back()->with(
                    'error',
                    'LiteSpeed is not installed on this server. Install LiteSpeed Enterprise/OpenLiteSpeed first.'
                );
            }

            $service = $detected['service'];
            $output = '';

            if ($action === 'activate') {
                if ($detected['type'] === 'lswsctrl') {
                    $output = $ssh->exec("
                        /usr/local/lsws/bin/lswsctrl start 2>&1
                        /usr/local/lsws/bin/lswsctrl restart 2>&1
                        echo 'LiteSpeed started/restarted using lswsctrl.'
                    ");
                } else {
                    $output = $ssh->exec("
                        systemctl enable {$service} 2>&1
                        systemctl restart {$service} 2>&1
                        systemctl is-active {$service} 2>&1
                    ");
                }
            }

            if ($action === 'restart') {
                if ($detected['type'] === 'lswsctrl') {
                    $output = $ssh->exec("/usr/local/lsws/bin/lswsctrl restart 2>&1");
                } else {
                    $output = $ssh->exec("systemctl restart {$service} 2>&1 && systemctl is-active {$service} 2>&1");
                }
            }

            if ($action === 'stop') {
                if ($detected['type'] === 'lswsctrl') {
                    $output = $ssh->exec("/usr/local/lsws/bin/lswsctrl stop 2>&1");
                } else {
                    $output = $ssh->exec("systemctl stop {$service} 2>&1 || true");
                }
            }

            if ($action === 'reload') {
                if ($detected['type'] === 'lswsctrl') {
                    $output = $ssh->exec("/usr/local/lsws/bin/lswsctrl reload 2>&1 || /usr/local/lsws/bin/lswsctrl restart 2>&1");
                } else {
                    $output = $ssh->exec("systemctl reload {$service} 2>&1 || systemctl restart {$service} 2>&1");
                }
            }

            $status = $this->getLiteSpeedStatus($ssh, $detected);
            $ports = $this->getLiteSpeedPorts($ssh);

            $message = "Action: {$action}\nDetected: {$detected['label']}\nService: {$service}\n\nOutput:\n{$output}\n\nStatus:\n{$status}\n\nPorts:\n{$ports}";

            $this->saveLiteSpeedAlert($server, 'LiteSpeed ' . ucfirst($action) . ' executed', $message);

            return back()->with(
                'success',
                'LiteSpeed ' . $action . ' completed. Status: ' . trim($status)
            );

        } catch (\Throwable $e) {
            return back()->with('error', 'LiteSpeed ' . $action . ' failed: ' . $e->getMessage());
        }
    }

    private function getLiteSpeedData(Server $server): array
    {
        $default = [
            'installed' => false,
            'label' => 'Not Installed',
            'service' => null,
            'status' => 'Unknown',
            'version' => 'Unknown',
            'ports' => '',
            'webAdmin' => "https://{$server->host}:7080",
            'message' => null,
        ];

        try {
            $ssh = $this->ssh($server);
            $detected = $this->detectLiteSpeed($ssh);

            if ($detected['type'] === 'not_installed') {
                return $default;
            }

            $version = trim($ssh->exec("
                if [ -x /usr/local/lsws/bin/lshttpd ]; then
                    /usr/local/lsws/bin/lshttpd -v 2>&1 | head -n 1
                elif [ -x /usr/local/lsws/bin/openlitespeed ]; then
                    /usr/local/lsws/bin/openlitespeed -v 2>&1 | head -n 1
                else
                    echo 'Version unavailable'
                fi
            "));

            return [
                'installed' => true,
                'label' => $detected['label'],
                'service' => $detected['service'],
                'status' => trim($this->getLiteSpeedStatus($ssh, $detected)),
                'version' => $version ?: 'Unknown',
                'ports' => $this->getLiteSpeedPorts($ssh),
                'webAdmin' => "https://{$server->host}:7080",
                'message' => null,
            ];

        } catch (\Throwable $e) {
            $default['message'] = $e->getMessage();
            return $default;
        }
    }

    private function detectLiteSpeed(SSH2 $ssh): array
    {
        $detected = trim($ssh->exec("
            if [ -x /usr/local/lsws/bin/lswsctrl ]; then
                echo 'lswsctrl';
            elif systemctl list-unit-files | grep -q '^lsws.service'; then
                echo 'lsws';
            elif systemctl list-unit-files | grep -q '^lshttpd.service'; then
                echo 'lshttpd';
            elif systemctl list-unit-files | grep -q '^openlitespeed.service'; then
                echo 'openlitespeed';
            elif systemctl list-unit-files | grep -q '^litespeed.service'; then
                echo 'litespeed';
            else
                echo 'not_installed';
            fi
        "));

        return match ($detected) {
            'lswsctrl' => [
                'type' => 'lswsctrl',
                'service' => 'lsws',
                'label' => 'LiteSpeed LSWS Control',
            ],
            'lsws' => [
                'type' => 'systemd',
                'service' => 'lsws',
                'label' => 'LiteSpeed Web Server',
            ],
            'lshttpd' => [
                'type' => 'systemd',
                'service' => 'lshttpd',
                'label' => 'LiteSpeed HTTPD',
            ],
            'openlitespeed' => [
                'type' => 'systemd',
                'service' => 'openlitespeed',
                'label' => 'OpenLiteSpeed',
            ],
            'litespeed' => [
                'type' => 'systemd',
                'service' => 'litespeed',
                'label' => 'LiteSpeed',
            ],
            default => [
                'type' => 'not_installed',
                'service' => null,
                'label' => 'Not Installed',
            ],
        };
    }

    private function getLiteSpeedStatus(SSH2 $ssh, array $detected): string
    {
        if ($detected['type'] === 'lswsctrl') {
            return $ssh->exec("/usr/local/lsws/bin/lswsctrl status 2>&1");
        }

        if (!empty($detected['service'])) {
            return $ssh->exec("systemctl is-active {$detected['service']} 2>&1");
        }

        return 'Unknown';
    }

    private function getLiteSpeedPorts(SSH2 $ssh): string
    {
        return trim($ssh->exec("
            ss -tulpn 2>/dev/null | grep -E ':80|:443|:7080|:8088' | head -n 30
        "));
    }

    private function saveLiteSpeedAlert(Server $server, string $title, string $message): void
    {
        if (!class_exists(ServerSecurityAlert::class)) {
            return;
        }

        ServerSecurityAlert::create([
            'server_id' => $server->id,
            'type' => 'litespeed',
            'level' => 'info',
            'title' => $title,
            'message' => $message,
            'detected_at' => now(),
        ]);
    }

    private function ssh(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
        $ssh->setTimeout(30);

        if (!$ssh->login($server->username, $this->getPassword($server))) {
            throw new \Exception('SSH login failed.');
        }

        return $ssh;
    }

    private function getPassword(Server $server): string
    {
        try {
            return decrypt($server->password);
        } catch (\Throwable $e) {
            return $server->password;
        }
    }
}