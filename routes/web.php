<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CpanelAccountController;
use App\Http\Controllers\WordPressManagerController;



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

Route::middleware('auth')->group(function () {

    Route::get('/', [ServerController::class, 'index'])->name('dashboard');

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

        // 👉 MAIN WORDPRESS MANAGER PAGE
        Route::get('/servers/{server}/wordpress/{account}', [WordPressManagerController::class, 'show'])
        ->name('servers.wordpress.show');

    // 👉 UPDATE WORDPRESS CORE
    Route::post('/servers/{server}/wordpress/{account}/core-update', [WordPressManagerController::class, 'updateCore'])
        ->name('servers.wordpress.coreUpdate');

    // 👉 UPDATE ALL PLUGINS
    Route::post('/servers/{server}/wordpress/{account}/plugins-update', [WordPressManagerController::class, 'updateAllPlugins'])
        ->name('servers.wordpress.plugins.updateAll');

    // 👉 ACTIVATE PLUGIN
    Route::post('/servers/{server}/wordpress/{account}/plugin-activate', [WordPressManagerController::class, 'activatePlugin'])
        ->name('servers.wordpress.plugin.activate');

    // 👉 DEACTIVATE PLUGIN
    Route::post('/servers/{server}/wordpress/{account}/plugin-deactivate', [WordPressManagerController::class, 'deactivatePlugin'])
        ->name('servers.wordpress.plugin.deactivate');

    // 👉 UPDATE SINGLE PLUGIN
    Route::post('/servers/{server}/wordpress/{account}/plugin-update', [WordPressManagerController::class, 'updatePlugin'])
        ->name('servers.wordpress.plugin.update');

    // 👉 ACTIVATE THEME
    Route::post('/servers/{server}/wordpress/{account}/theme-activate', [WordPressManagerController::class, 'activateTheme'])
        ->name('servers.wordpress.theme.activate');

    /*
    |--------------------------------------------------------------------------
    | Backup Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/backups', [BackupController::class, 'index'])
        ->name('backups.index');

    Route::post('/backups/settings', [BackupController::class, 'saveSettings'])
        ->name('backups.settings');

    Route::get('/backups/run-google-drive', [BackupController::class, 'runGoogleDriveBackup'])
        ->name('backups.google');

    Route::post('/backups/transfer-server', [BackupController::class, 'transferToBackupServer'])
        ->name('backups.transfer');

        Route::get('/backups/auto-disk-backup', [BackupController::class, 'autoDiskBackup'])
    ->name('backups.auto');

    Route::get('/backups/logs', [BackupController::class, 'logs'])
    ->name('backups.logs');

    Route::post('/backups/pull', [BackupController::class, 'pullBackupToMonitor'])
    ->name('backups.pull');

    Route::post('/backups/google-drive', [BackupController::class, 'uploadToGoogleDrive'])
    ->name('backups.google');

Route::post('/backups/full-sync', [BackupController::class, 'fullSync'])
    ->name('backups.fullSync');

Route::post('/backups/transfer-server', [BackupController::class, 'transferToBackupServer'])
    ->name('backups.transfer');

Route::post('/backups/settings', [BackupController::class, 'saveSettings'])
    ->name('backups.settings');

    /*
|--------------------------------------------------------------------------
| Monitoring Routes
|--------------------------------------------------------------------------
*/

Route::prefix('monitoring')->group(function () {

    Route::get('/uptime', [App\Http\Controllers\MonitoringController::class, 'uptime'])
        ->name('monitoring.uptime');

    Route::get('/ports', [App\Http\Controllers\MonitoringController::class, 'ports'])
        ->name('monitoring.ports');

    Route::get('/services', [App\Http\Controllers\MonitoringController::class, 'services'])
        ->name('monitoring.services');

    Route::get('/resources', [App\Http\Controllers\MonitoringController::class, 'resources'])
        ->name('monitoring.resources');
});

Route::prefix('servers/{server}/cpanel-accounts')->name('servers.cpanel.')->group(function () {
    Route::get('/', [CpanelAccountController::class, 'index'])->name('index');
    Route::get('/create', [CpanelAccountController::class, 'create'])->name('create');
    Route::post('/store', [CpanelAccountController::class, 'store'])->name('store');

    Route::get('/{user}/edit', [CpanelAccountController::class, 'edit'])->name('edit');
    Route::post('/{user}/password', [CpanelAccountController::class, 'updatePassword'])->name('password');
    Route::post('/{user}/package', [CpanelAccountController::class, 'updatePackage'])->name('package');
    Route::post('/{user}/ip', [CpanelAccountController::class, 'updateIp'])->name('ip');
});

/*
|--------------------------------------------------------------------------
| SECURITY ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('security')->name('security.')->group(function () {
    Route::get('/alerts', [App\Http\Controllers\SecurityController::class, 'alerts'])->name('alerts');
    Route::get('/firewall', [App\Http\Controllers\SecurityController::class, 'firewall'])->name('firewall');
    Route::get('/abuse', [App\Http\Controllers\SecurityController::class, 'abuse'])->name('abuse');
    Route::get('/email', [App\Http\Controllers\SecurityController::class, 'email'])->name('email');
    Route::get('/ssh', [App\Http\Controllers\SecurityController::class, 'ssh'])->name('ssh');
});

/*
|--------------------------------------------------------------------------
| TOOLS ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('tools')->name('tools.')->group(function () {

    // LIST PAGE (NEW)
    Route::get('/terminal', [App\Http\Controllers\ToolsController::class, 'terminalList'])
        ->name('terminal');

    // ACTUAL TERMINAL
    Route::get('/terminal/{server}', [App\Http\Controllers\ServerController::class, 'terminal'])
        ->name('terminal.connect');

    Route::post('/run/{server}', [App\Http\Controllers\ServerController::class, 'runCommand'])
        ->name('run');

    Route::get('/checks', [App\Http\Controllers\ToolsController::class, 'checks'])->name('checks');
    Route::get('/logs', [App\Http\Controllers\ToolsController::class, 'logs'])->name('logs');
});

/*
|--------------------------------------------------------------------------
| CLOUDDNS DOMAINS
|--------------------------------------------------------------------------
*/
Route::prefix('domains')->name('domains.')->group(function () {
    Route::get('/', [App\Http\Controllers\DomainController::class, 'index'])->name('index');
});

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});