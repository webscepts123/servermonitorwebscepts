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
        }

        /*
        |--------------------------------------------------------------------------
        | Resource averages
        |--------------------------------------------------------------------------
        */
        $avgCpu = $this->averageServerMetric($servers, ['cpu_usage', 'cpu']);
        $avgRam = $this->averageServerMetric($servers, ['ram_usage', 'ram']);
        $avgDisk = $this->averageServerMetric($servers, ['disk_usage', 'disk']);

        $criticalDiskServers = $servers->filter(function ($server) {
            $disk = $this->metricValue($server, ['disk_usage', 'disk']);

            return $disk !== null && $disk >= 90;
        })->count();

        $highCpuServers = $servers->filter(function ($server) {
            $cpu = $this->metricValue($server, ['cpu_usage', 'cpu']);

            return $cpu !== null && $cpu >= 85;
        })->count();

        $highRamServers = $servers->filter(function ($server) {
            $ram = $this->metricValue($server, ['ram_usage', 'ram']);

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
        }

        if (Schema::hasTable('sentinel_web_scans')) {
            $sentinelStats['web_scans'] = DB::table('sentinel_web_scans')->count();
            $sentinelStats['critical_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'critical')->count();
            $sentinelStats['high_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'high')->count();
            $sentinelStats['medium_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'medium')->count();
            $sentinelStats['low_web_scans'] = DB::table('sentinel_web_scans')->where('risk_level', 'low')->count();
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

        return null;
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