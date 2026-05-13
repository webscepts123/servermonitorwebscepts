<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Base server data
        |--------------------------------------------------------------------------
        */
        $servers = Server::latest()->get();

        /*
        |--------------------------------------------------------------------------
        | Latest check for each server
        |--------------------------------------------------------------------------
        */
        $latestChecksByServer = collect();

        if (class_exists(ServerCheck::class) && Schema::hasTable('server_checks')) {
            $latestChecksByServer = ServerCheck::query()
                ->select('server_checks.*')
                ->join(DB::raw('(SELECT server_id, MAX(id) as latest_id FROM server_checks GROUP BY server_id) latest_checks'), function ($join) {
                    $join->on('server_checks.id', '=', 'latest_checks.latest_id');
                })
                ->get()
                ->keyBy('server_id');
        }

        /*
        |--------------------------------------------------------------------------
        | Attach latest check data to server collection
        |--------------------------------------------------------------------------
        */
        $servers = $servers->map(function ($server) use ($latestChecksByServer) {
            $latestCheck = $latestChecksByServer->get($server->id);

            $server->latest_check = $latestCheck;

            $server->cpu_usage = $this->firstMetricValue([
                $latestCheck->cpu_usage ?? null,
                $latestCheck->cpu ?? null,
                $latestCheck->cpu_percent ?? null,
                $latestCheck->cpu_average ?? null,
                $server->cpu_usage ?? null,
                $server->cpu ?? null,
            ]);

            $server->ram_usage = $this->firstMetricValue([
                $latestCheck->ram_usage ?? null,
                $latestCheck->ram ?? null,
                $latestCheck->memory_usage ?? null,
                $latestCheck->memory_percent ?? null,
                $server->ram_usage ?? null,
                $server->ram ?? null,
            ]);

            $server->disk_usage = $this->firstMetricValue([
                $latestCheck->disk_usage ?? null,
                $latestCheck->disk ?? null,
                $latestCheck->disk_percent ?? null,
                $latestCheck->storage_usage ?? null,
                $server->disk_usage ?? null,
                $server->disk ?? null,
            ]);

            $server->response_time = $this->firstMetricValue([
                $latestCheck->response_time ?? null,
                $latestCheck->speed ?? null,
                $latestCheck->latency ?? null,
                $server->response_time ?? null,
            ]);

            if ($latestCheck && isset($latestCheck->status) && !empty($latestCheck->status)) {
                $server->status = $latestCheck->status;
            }

            if ($latestCheck && isset($latestCheck->created_at)) {
                $server->last_checked_at = $latestCheck->created_at;
            }

            return $server;
        });

        $totalServers = $servers->count();

        $onlineServers = $servers->filter(function ($server) {
            return strtolower((string) $server->status) === 'online';
        })->count();

        $offlineServers = $servers->filter(function ($server) {
            return strtolower((string) $server->status) === 'offline';
        })->count();

        $activeServers = $servers->filter(function ($server) {
            return !empty($server->is_active);
        })->count();

        $inactiveServers = max($totalServers - $activeServers, 0);

        /*
        |--------------------------------------------------------------------------
        | Latest server checks
        |--------------------------------------------------------------------------
        */
        $latestChecks = collect();

        if (class_exists(ServerCheck::class) && Schema::hasTable('server_checks')) {
            $latestChecks = ServerCheck::with('server')
                ->latest()
                ->limit(10)
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | Response time data
        |--------------------------------------------------------------------------
        */
        $avgResponseTime = null;
        $fastServers = collect();
        $slowServers = collect();

        if (Schema::hasTable('server_checks') && Schema::hasColumn('server_checks', 'response_time')) {
            $avgResponseTime = ServerCheck::whereNotNull('response_time')
                ->avg('response_time');

            $fastServers = ServerCheck::select('server_id', DB::raw('AVG(response_time) as avg_speed'))
                ->whereNotNull('response_time')
                ->groupBy('server_id')
                ->orderBy('avg_speed', 'asc')
                ->with('server')
                ->limit(5)
                ->get();

            $slowServers = ServerCheck::select('server_id', DB::raw('AVG(response_time) as avg_speed'))
                ->whereNotNull('response_time')
                ->groupBy('server_id')
                ->orderBy('avg_speed', 'desc')
                ->with('server')
                ->limit(5)
                ->get();
        } else {
            $avgResponseTime = $servers->whereNotNull('response_time')->avg('response_time');
        }

        /*
        |--------------------------------------------------------------------------
        | Resource averages
        |--------------------------------------------------------------------------
        */
        $avgCpu = $this->averageServerMetric($servers, ['cpu_usage', 'cpu', 'cpu_percent', 'cpu_average']);
        $avgRam = $this->averageServerMetric($servers, ['ram_usage', 'ram', 'memory_usage', 'memory_percent']);
        $avgDisk = $this->averageServerMetric($servers, ['disk_usage', 'disk', 'disk_percent', 'storage_usage']);

        $criticalDiskServers = $servers->filter(function ($server) {
            $disk = $this->metricValue($server, ['disk_usage', 'disk', 'disk_percent', 'storage_usage']);

            return $disk !== null && $disk >= 90;
        })->count();

        $highCpuServers = $servers->filter(function ($server) {
            $cpu = $this->metricValue($server, ['cpu_usage', 'cpu', 'cpu_percent', 'cpu_average']);

            return $cpu !== null && $cpu >= 85;
        })->count();

        $highRamServers = $servers->filter(function ($server) {
            $ram = $this->metricValue($server, ['ram_usage', 'ram', 'memory_usage', 'memory_percent']);

            return $ram !== null && $ram >= 85;
        })->count();

        /*
        |--------------------------------------------------------------------------
        | SentinelCore technology / security stats
        |--------------------------------------------------------------------------
        */
        $sentinelStats = [
            'encrypted_credentials' => $servers->filter(fn ($server) => !empty($server->password))->count(),
            'email_alerts' => $servers->filter(fn ($server) => !empty($server->email_alerts_enabled))->count(),
            'sms_alerts' => $servers->filter(fn ($server) => !empty($server->sms_alerts_enabled))->count(),
            'google_drive' => $servers->filter(fn ($server) => !empty($server->google_drive_sync))->count(),
            'backup_failover' => $servers->filter(fn ($server) => !empty($server->failover_enabled))->count(),
            'dns_failover' => $servers->filter(fn ($server) => !empty($server->dns_failover_enabled))->count(),
            'linked_domains' => 0,
            'web_scans' => 0,
            'critical_web_scans' => 0,
            'high_web_scans' => 0,
            'medium_web_scans' => 0,
            'low_web_scans' => 0,
            'security_alerts' => 0,
            'danger_alerts' => 0,
            'warning_alerts' => 0,
        ];

        if (Schema::hasTable('server_domains')) {
            $sentinelStats['linked_domains'] = DB::table('server_domains')->count();
        } else {
            $sentinelStats['linked_domains'] = $servers->filter(function ($server) {
                return !empty($server->website_url)
                    || !empty($server->domain)
                    || !empty($server->linked_domain)
                    || !empty($server->url);
            })->count();
        }

        if (Schema::hasTable('sentinel_web_scans')) {
            $sentinelStats['web_scans'] = DB::table('sentinel_web_scans')->count();

            if (Schema::hasColumn('sentinel_web_scans', 'risk_level')) {
                $sentinelStats['critical_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'critical')->count();
                $sentinelStats['high_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'high')->count();
                $sentinelStats['medium_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'medium')->count();
                $sentinelStats['low_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'low')->count();
            }
        }

        if (Schema::hasTable('web_scans')) {
            $sentinelStats['web_scans'] = max($sentinelStats['web_scans'], DB::table('web_scans')->count());

            if (Schema::hasColumn('web_scans', 'risk_level')) {
                $sentinelStats['critical_web_scans'] += DB::table('web_scans')->where('risk_level', 'critical')->count();
                $sentinelStats['high_web_scans'] += DB::table('web_scans')->where('risk_level', 'high')->count();
                $sentinelStats['medium_web_scans'] += DB::table('web_scans')->where('risk_level', 'medium')->count();
                $sentinelStats['low_web_scans'] += DB::table('web_scans')->where('risk_level', 'low')->count();
            }
        }

        if (Schema::hasTable('server_security_alerts')) {
            $sentinelStats['security_alerts'] = DB::table('server_security_alerts')->count();

            if (Schema::hasColumn('server_security_alerts', 'level')) {
                $sentinelStats['danger_alerts'] = DB::table('server_security_alerts')
                    ->where('level', 'danger')
                    ->count();

                $sentinelStats['warning_alerts'] = DB::table('server_security_alerts')
                    ->where('level', 'warning')
                    ->count();
            }
        }

        if (Schema::hasTable('security_alerts')) {
            $sentinelStats['security_alerts'] = max(
                $sentinelStats['security_alerts'],
                DB::table('security_alerts')->count()
            );

            if (Schema::hasColumn('security_alerts', 'level')) {
                $sentinelStats['danger_alerts'] += DB::table('security_alerts')
                    ->where('level', 'danger')
                    ->count();

                $sentinelStats['warning_alerts'] += DB::table('security_alerts')
                    ->where('level', 'warning')
                    ->count();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SentinelCore score
        |--------------------------------------------------------------------------
        */
        $securityScore = 0;

        if ($totalServers > 0) {
            $securityScore += round(($sentinelStats['encrypted_credentials'] / max($totalServers, 1)) * 20);
            $securityScore += round(($sentinelStats['email_alerts'] / max($totalServers, 1)) * 10);
            $securityScore += round(($sentinelStats['sms_alerts'] / max($totalServers, 1)) * 10);
            $securityScore += round(($sentinelStats['google_drive'] / max($totalServers, 1)) * 10);
            $securityScore += round(($sentinelStats['backup_failover'] / max($totalServers, 1)) * 20);
            $securityScore += round(($sentinelStats['dns_failover'] / max($totalServers, 1)) * 20);
            $securityScore += $sentinelStats['web_scans'] > 0 ? 10 : 0;
        }

        $securityScore = min($securityScore, 100);

        $securityLevel = match (true) {
            $securityScore >= 85 => 'Enterprise',
            $securityScore >= 65 => 'Protected',
            $securityScore >= 40 => 'Basic',
            default => 'Needs Setup',
        };

        /*
        |--------------------------------------------------------------------------
        | Latest SentinelCore scans
        |--------------------------------------------------------------------------
        */
        $latestWebScans = collect();

        if (Schema::hasTable('sentinel_web_scans')) {
            $latestWebScans = DB::table('sentinel_web_scans')
                ->latest()
                ->limit(5)
                ->get();
        } elseif (Schema::hasTable('web_scans')) {
            $latestWebScans = DB::table('web_scans')
                ->latest()
                ->limit(5)
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | Latest security alerts
        |--------------------------------------------------------------------------
        */
        $latestSecurityAlerts = collect();

        if (Schema::hasTable('server_security_alerts')) {
            $latestSecurityAlerts = DB::table('server_security_alerts')
                ->latest()
                ->limit(8)
                ->get();
        } elseif (Schema::hasTable('security_alerts')) {
            $latestSecurityAlerts = DB::table('security_alerts')
                ->latest()
                ->limit(8)
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | Enterprise modules
        |--------------------------------------------------------------------------
        */
        $enterpriseModules = [
            [
                'title' => 'Server Speed',
                'value' => $avgResponseTime ? round($avgResponseTime, 2) . ' ms' : 'N/A',
                'icon' => 'fa-gauge-high',
                'color' => 'blue',
                'description' => 'Average response time from latest server checks.',
                'route' => null,
            ],
            [
                'title' => 'Cache Status',
                'value' => 'Optimized',
                'icon' => 'fa-bolt',
                'color' => 'green',
                'description' => 'Optimized for LiteSpeed, WordPress, Laravel and PHP apps.',
                'route' => null,
            ],
            [
                'title' => 'Security Level',
                'value' => $securityLevel,
                'icon' => 'fa-shield-halved',
                'color' => 'purple',
                'description' => 'Calculated from SentinelCore protections.',
                'route' => route_exists('technology.index') ? route('technology.index') : null,
            ],
            [
                'title' => 'Auto Backup',
                'value' => $sentinelStats['backup_failover'] > 0 ? 'Enabled' : 'Setup Needed',
                'icon' => 'fa-cloud-arrow-up',
                'color' => 'orange',
                'description' => 'Backup failover and Google Drive sync status.',
                'route' => route_exists('backups.index') ? route('backups.index') : null,
            ],
            [
                'title' => 'Web Scanner',
                'value' => $sentinelStats['web_scans'] . ' Scans',
                'icon' => 'fa-magnifying-glass-chart',
                'color' => 'red',
                'description' => 'WordPress, Laravel, Angular, Node.js, PHP and database risk scans.',
                'route' => route_exists('technology.webscanner.index') ? route('technology.webscanner.index') : null,
            ],
            [
                'title' => 'DNS Failover',
                'value' => $sentinelStats['dns_failover'] . ' Active',
                'icon' => 'fa-globe',
                'color' => 'cyan',
                'description' => 'ClouDNS linked domain failover protection.',
                'route' => route_exists('domains.index') ? route('domains.index') : null,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | SentinelCore modules for dashboard blade
        |--------------------------------------------------------------------------
        */
        $sentinelModules = [
            [
                'title' => 'SentinelCore',
                'value' => $securityLevel,
                'icon' => 'fa-shield-virus',
                'color' => 'bg-red-100 text-red-700',
                'route' => route_exists('technology.index') ? route('technology.index') : null,
            ],
            [
                'title' => 'Web Scanner',
                'value' => 'Smart Python Engine',
                'icon' => 'fa-magnifying-glass-chart',
                'color' => 'bg-blue-100 text-blue-700',
                'route' => route_exists('technology.webscanner.index') ? route('technology.webscanner.index') : null,
            ],
            [
                'title' => 'Encryption Vault',
                'value' => 'File Shield',
                'icon' => 'fa-file-shield',
                'color' => 'bg-purple-100 text-purple-700',
                'route' => route_exists('technology.index') ? route('technology.index') : null,
            ],
            [
                'title' => 'DNS Failover',
                'value' => $sentinelStats['dns_failover'] . ' Active',
                'icon' => 'fa-globe',
                'color' => 'bg-green-100 text-green-700',
                'route' => route_exists('domains.index') ? route('domains.index') : null,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Framework protection cards
        |--------------------------------------------------------------------------
        */
        $frameworkSecurity = [
            [
                'name' => 'WordPress',
                'icon' => 'fa-brands fa-wordpress',
                'status' => 'Protected',
                'checks' => ['wp-config', 'plugins', 'themes', 'XML-RPC', 'REST API'],
            ],
            [
                'name' => 'Laravel',
                'icon' => 'fa-brands fa-laravel',
                'status' => 'Protected',
                'checks' => ['.env', 'APP_DEBUG', 'storage logs', 'composer files'],
            ],
            [
                'name' => 'Angular',
                'icon' => 'fa-brands fa-angular',
                'status' => 'Scanning Ready',
                'checks' => ['source maps', 'environment files', 'API keys'],
            ],
            [
                'name' => 'Node.js',
                'icon' => 'fa-brands fa-node-js',
                'status' => 'Scanning Ready',
                'checks' => ['package.json', '.env', 'stack traces', 'API risks'],
            ],
            [
                'name' => 'PHP',
                'icon' => 'fa-brands fa-php',
                'status' => 'Protected',
                'checks' => ['phpinfo', 'config.php', 'warnings', 'display_errors'],
            ],
            [
                'name' => 'Database',
                'icon' => 'fa-solid fa-database',
                'status' => 'Protected',
                'checks' => ['MySQL', 'PostgreSQL', 'SQL dumps', 'SQLSTATE leaks'],
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Last checked time
        |--------------------------------------------------------------------------
        */
        $lastCheckedAt = null;

        if ($servers->count()) {
            $lastCheckedAt = $servers
                ->pluck('last_checked_at')
                ->filter()
                ->sortDesc()
                ->first();
        }

        if (!$lastCheckedAt && $latestChecks->count()) {
            $lastCheckedAt = $latestChecks->pluck('created_at')->filter()->sortDesc()->first();
        }

        return view('dashboard.index', compact(
            'servers',
            'totalServers',
            'onlineServers',
            'offlineServers',
            'activeServers',
            'inactiveServers',
            'latestChecks',
            'avgResponseTime',
            'fastServers',
            'slowServers',
            'enterpriseModules',
            'sentinelModules',
            'sentinelStats',
            'securityScore',
            'securityLevel',
            'latestWebScans',
            'latestSecurityAlerts',
            'frameworkSecurity',
            'avgCpu',
            'avgRam',
            'avgDisk',
            'criticalDiskServers',
            'highCpuServers',
            'highRamServers',
            'lastCheckedAt'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Average server metric
    |--------------------------------------------------------------------------
    */
    private function averageServerMetric($servers, array $columns): float
    {
        $values = $servers->map(function ($server) use ($columns) {
            return $this->metricValue($server, $columns);
        })->filter(function ($value) {
            return $value !== null;
        });

        if ($values->count() === 0) {
            return 0;
        }

        return round($values->avg(), 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Get metric from possible column names
    |--------------------------------------------------------------------------
    */
    private function metricValue($server, array $columns): ?float
    {
        foreach ($columns as $column) {
            if (isset($server->{$column}) && $server->{$column} !== null && $server->{$column} !== '') {
                return $this->toNumber($server->{$column});
            }
        }

        $latestCheck = $server->latest_check ?? null;

        if ($latestCheck) {
            foreach ($columns as $column) {
                if (isset($latestCheck->{$column}) && $latestCheck->{$column} !== null && $latestCheck->{$column} !== '') {
                    return $this->toNumber($latestCheck->{$column});
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Get first valid metric
    |--------------------------------------------------------------------------
    */
    private function firstMetricValue(array $values): float
    {
        foreach ($values as $value) {
            $number = $this->toNumber($value);

            if ($number !== null) {
                return $number;
            }
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Convert value to number
    |--------------------------------------------------------------------------
    */
    private function toNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        if ($clean === '') {
            return null;
        }

        return (float) $clean;
    }
}

/*
|--------------------------------------------------------------------------
| Route helper fallback
|--------------------------------------------------------------------------
*/
if (!function_exists('route_exists')) {
    function route_exists(string $name): bool
    {
        try {
            return app('router')->has($name);
        } catch (\Throwable $e) {
            return false;
        }
    }
}