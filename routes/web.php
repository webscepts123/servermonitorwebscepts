<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CpanelAccountController;
use App\Http\Controllers\WordPressManagerController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\PanelAccountPageController;
use App\Http\Controllers\LiteSpeedController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard.index');

    /*
    |--------------------------------------------------------------------------
    | Panel Account Landing Pages
    |--------------------------------------------------------------------------
    */

    Route::get('/panel/cpanel', [PanelAccountPageController::class, 'cpanel'])
        ->name('panel.cpanel');

    Route::get('/panel/plesk', [PanelAccountPageController::class, 'plesk'])
        ->name('panel.plesk');

    Route::get('/panel/wordpress', [PanelAccountPageController::class, 'wordpress'])
        ->name('panel.wordpress');

    /*
    |--------------------------------------------------------------------------
    | Server Routes
    |--------------------------------------------------------------------------
    */

    Route::resource('servers', ServerController::class);

    Route::post('/servers/{server}/check-now', [ServerController::class, 'checkNow'])
        ->name('servers.checkNow');

    Route::post('/servers/{server}/security-scan', [ServerController::class, 'securityScan'])
        ->name('servers.securityScan');

    Route::get('/servers/{server}/terminal', [ServerController::class, 'terminal'])
        ->name('servers.terminal');

    Route::post('/servers/{server}/terminal/run', [ServerController::class, 'runCommand'])
        ->name('servers.terminal.run');

    /*
    |--------------------------------------------------------------------------
    | LiteSpeed Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('servers/{server}/litespeed')
        ->name('servers.litespeed.')
        ->middleware(['throttle:20,1'])
        ->group(function () {
            Route::get('/', [LiteSpeedController::class, 'index'])->name('index');

            Route::post('/activate', [LiteSpeedController::class, 'activate'])->name('activate');
            Route::post('/restart', [LiteSpeedController::class, 'restart'])->name('restart');
            Route::post('/stop', [LiteSpeedController::class, 'stop'])->name('stop');
            Route::post('/reload', [LiteSpeedController::class, 'reload'])->name('reload');

            Route::post('/config-test', [LiteSpeedController::class, 'configTest'])->name('configTest');
            Route::post('/logs', [LiteSpeedController::class, 'logs'])->name('logs');
        });

    /*
    |--------------------------------------------------------------------------
    | cPanel Account Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('servers/{server}/cpanel-accounts')
        ->name('servers.cpanel.')
        ->middleware(['throttle:20,1'])
        ->group(function () {
            Route::get('/', [CpanelAccountController::class, 'index'])->name('index');
            Route::get('/create', [CpanelAccountController::class, 'create'])->name('create');
            Route::post('/store', [CpanelAccountController::class, 'store'])->name('store');

            Route::get('/{user}/edit', [CpanelAccountController::class, 'edit'])->name('edit');
            Route::post('/{user}/password', [CpanelAccountController::class, 'updatePassword'])->name('password');
            Route::post('/{user}/package', [CpanelAccountController::class, 'updatePackage'])->name('package');
            Route::post('/{user}/ip', [CpanelAccountController::class, 'updateIp'])->name('ip');

            Route::post('/{user}/sms', [CpanelAccountController::class, 'sendAccountSms'])->name('sms');
            Route::post('/{user}/email', [CpanelAccountController::class, 'sendAccountEmail'])->name('email');

            /*
            |--------------------------------------------------------------------------
            | cPanel Auto Login / SSO
            |--------------------------------------------------------------------------
            */

            Route::get('/{user}/login', [CpanelAccountController::class, 'autoLogin'])->name('login');
            Route::get('/{user}/login/email', [CpanelAccountController::class, 'autoLoginEmail'])->name('login.email');
            Route::get('/{user}/login/files', [CpanelAccountController::class, 'autoLoginFiles'])->name('login.files');
            Route::get('/{user}/login/wordpress', [CpanelAccountController::class, 'autoLoginWordPress'])->name('login.wordpress');
        });

    /*
    |--------------------------------------------------------------------------
    | WordPress Manager Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('servers/{server}/wordpress/{account}')
        ->name('servers.wordpress.')
        ->middleware(['throttle:20,1'])
        ->group(function () {
            Route::get('/', [WordPressManagerController::class, 'show'])->name('show');

            Route::post('/core-update', [WordPressManagerController::class, 'updateCore'])
                ->name('coreUpdate');

            Route::post('/plugins-update', [WordPressManagerController::class, 'updateAllPlugins'])
                ->name('plugins.updateAll');

            Route::post('/plugin-activate', [WordPressManagerController::class, 'activatePlugin'])
                ->name('plugin.activate');

            Route::post('/plugin-deactivate', [WordPressManagerController::class, 'deactivatePlugin'])
                ->name('plugin.deactivate');

            Route::post('/plugin-update', [WordPressManagerController::class, 'updatePlugin'])
                ->name('plugin.update');

            Route::post('/theme-activate', [WordPressManagerController::class, 'activateTheme'])
                ->name('theme.activate');
        });

    /*
    |--------------------------------------------------------------------------
    | Backup Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('backups')
        ->name('backups.')
        ->group(function () {
            Route::get('/', [BackupController::class, 'index'])->name('index');

            Route::post('/settings', [BackupController::class, 'saveSettings'])
                ->name('settings');

            Route::get('/google-drive', [BackupController::class, 'runGoogleDriveBackup'])
                ->name('google');

            Route::post('/google-drive/upload', [BackupController::class, 'uploadToGoogleDrive'])
                ->name('google.upload');

            Route::post('/transfer-server', [BackupController::class, 'transferToBackupServer'])
                ->name('transfer');

            Route::get('/auto-disk-backup', [BackupController::class, 'autoDiskBackup'])
                ->name('auto');

            Route::get('/logs', [BackupController::class, 'logs'])
                ->name('logs');

            Route::post('/pull', [BackupController::class, 'pullBackupToMonitor'])
                ->name('pull');

            Route::post('/full-sync', [BackupController::class, 'fullSync'])
                ->name('fullSync');
        });

    /*
    |--------------------------------------------------------------------------
    | SMS Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('sms')
        ->name('sms.')
        ->middleware(['throttle:10,1'])
        ->group(function () {
            Route::post('/send', [SmsController::class, 'send'])
                ->name('send');

            Route::post('/server/{server}/down', [SmsController::class, 'sendDownAlert'])
                ->name('down');

            Route::post('/server/{server}/recovery', [SmsController::class, 'sendRecoveryAlert'])
                ->name('recovery');
        });

    /*
    |--------------------------------------------------------------------------
    | Monitoring Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('monitoring')
        ->name('monitoring.')
        ->group(function () {
            Route::get('/uptime', [App\Http\Controllers\MonitoringController::class, 'uptime'])
                ->name('uptime');

            Route::get('/ports', [App\Http\Controllers\MonitoringController::class, 'ports'])
                ->name('ports');

            Route::get('/services', [App\Http\Controllers\MonitoringController::class, 'services'])
                ->name('services');

            Route::get('/resources', [App\Http\Controllers\MonitoringController::class, 'resources'])
                ->name('resources');
        });

    /*
    |--------------------------------------------------------------------------
    | Security Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('security')
        ->name('security.')
        ->group(function () {
            /*
            |--------------------------------------------------------------------------
            | Pages
            |--------------------------------------------------------------------------
            */

            Route::get('/alerts', [App\Http\Controllers\SecurityController::class, 'alerts'])
                ->name('alerts');

            Route::get('/firewall', [App\Http\Controllers\SecurityController::class, 'firewall'])
                ->name('firewall');

            Route::get('/abuse', [App\Http\Controllers\SecurityController::class, 'abuse'])
                ->name('abuse');

            Route::get('/email', [App\Http\Controllers\SecurityController::class, 'email'])
                ->name('email');

            Route::get('/ssh', [App\Http\Controllers\SecurityController::class, 'ssh'])
                ->name('ssh');

            /*
            |--------------------------------------------------------------------------
            | Firewall Actions
            |--------------------------------------------------------------------------
            */

            Route::post('/block-ip', [App\Http\Controllers\SecurityController::class, 'blockIp'])
                ->name('block.ip');

            Route::post('/unblock-ip', [App\Http\Controllers\SecurityController::class, 'unblockIp'])
                ->name('unblock.ip');

            Route::post('/restart-firewall', [App\Http\Controllers\SecurityController::class, 'restartFirewall'])
                ->name('firewall.restart');

            /*
            |--------------------------------------------------------------------------
            | SSH Security
            |--------------------------------------------------------------------------
            */

            Route::post('/ssh/kill-session', [App\Http\Controllers\SecurityController::class, 'killSession'])
                ->name('ssh.kill');

            Route::post('/ssh/block-ip', [App\Http\Controllers\SecurityController::class, 'sshBlockIp'])
                ->name('ssh.block');

            /*
            |--------------------------------------------------------------------------
            | Email Security
            |--------------------------------------------------------------------------
            */

            Route::post('/email/clear-queue', [App\Http\Controllers\SecurityController::class, 'clearQueue'])
                ->name('email.clear');

            Route::post('/email/block-sender', [App\Http\Controllers\SecurityController::class, 'blockSender'])
                ->name('email.block');

            /*
            |--------------------------------------------------------------------------
            | Abuse / Malware
            |--------------------------------------------------------------------------
            */

            Route::post('/scan-malware', [App\Http\Controllers\SecurityController::class, 'scanMalware'])
                ->name('malware.scan');

            Route::post('/kill-process', [App\Http\Controllers\SecurityController::class, 'killProcess'])
                ->name('process.kill');

            /*
            |--------------------------------------------------------------------------
            | Real-time / AI / Logs
            |--------------------------------------------------------------------------
            */

            Route::get('/logs/live', [App\Http\Controllers\SecurityController::class, 'liveLogs'])
                ->name('logs.live');

            Route::post('/ai-detect', [App\Http\Controllers\SecurityController::class, 'aiDetect'])
                ->name('ai.detect');
        });

    /*
    |--------------------------------------------------------------------------
    | Tools Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('tools')
        ->name('tools.')
        ->group(function () {
            Route::get('/terminal', [App\Http\Controllers\ToolsController::class, 'terminalList'])
                ->name('terminal');

            Route::get('/terminal/{server}', [ServerController::class, 'terminal'])
                ->name('terminal.connect');

            Route::post('/run/{server}', [ServerController::class, 'runCommand'])
                ->name('run');

            Route::get('/checks', [App\Http\Controllers\ToolsController::class, 'checks'])
                ->name('checks');

            Route::get('/logs', [App\Http\Controllers\ToolsController::class, 'logs'])
                ->name('logs');
        });

    /*
    |--------------------------------------------------------------------------
    | CloudDNS / Domains
    |--------------------------------------------------------------------------
    */

    Route::prefix('domains')
        ->name('domains.')
        ->group(function () {
            Route::get('/', [App\Http\Controllers\DomainController::class, 'index'])
                ->name('index');
        });

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');
});