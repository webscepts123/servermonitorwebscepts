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
use App\Http\Controllers\DomainController;
use App\Http\Controllers\TechnologyController;
use App\Http\Controllers\SentinelWebScanController;
use App\Http\Controllers\DeveloperWorkspaceController;
use App\Http\Controllers\DeveloperAuthController;
use App\Http\Controllers\DeveloperCpanelImportController;
use App\Http\Controllers\DeveloperFileEditorController;
use App\Http\Controllers\ProfileSecurityController;


/*
|--------------------------------------------------------------------------
| Developer Codes Subdomain
|--------------------------------------------------------------------------
| Developer portal:
| https://developercodes.webscepts.com/login
| https://developercodes.webscepts.com/workspace
| https://developercodes.webscepts.com/codeditor
|--------------------------------------------------------------------------
*/

Route::domain('developercodes.webscepts.com')
    ->middleware(['web'])
    ->group(function () {

        Route::get('/', function () {
            if (auth()->guard('developer')->check()) {
                return redirect()->route('developer.domain.workspace');
            }

            return redirect()->route('developer.login');
        })->name('developer.home');

        /*
        |--------------------------------------------------------------------------
        | Developer Auth
        |--------------------------------------------------------------------------
        */

        Route::get('/login', [DeveloperAuthController::class, 'showLogin'])
            ->name('developer.login');

        Route::post('/login', [DeveloperAuthController::class, 'login'])
            ->name('developer.login.submit');

        Route::post('/logout', [DeveloperAuthController::class, 'logout'])
            ->name('developer.logout');

        /*
        |--------------------------------------------------------------------------
        | Developer Protected Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware(['developer.auth'])->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Main Workspace Pages
            |--------------------------------------------------------------------------
            */

            Route::get('/workspace', [DeveloperWorkspaceController::class, 'index'])
                ->name('developer.domain.workspace');

            Route::get('/project-files', [DeveloperWorkspaceController::class, 'projectFiles'])
                ->name('developer.domain.project.files');

            Route::get('/commands', [DeveloperWorkspaceController::class, 'commands'])
                ->name('developer.domain.commands');

            /*
            |--------------------------------------------------------------------------
            | Visual Code Editor Routes
            |--------------------------------------------------------------------------
            | This is now Monaco Editor + WHM/cPanel File Manager API.
            | No SSH, no root, no code-server backend needed.
            |
            | Main route:
            | https://developercodes.webscepts.com/codeditor
            |--------------------------------------------------------------------------
            */

            Route::get('/codeditor', [DeveloperFileEditorController::class, 'index'])
                ->name('developer.domain.codeditor');

            Route::get('/codeeditor', [DeveloperFileEditorController::class, 'index'])
                ->name('developer.domain.codeeditor');

            Route::get('/coddeditor', [DeveloperFileEditorController::class, 'index'])
                ->name('developer.domain.coddeditor');

            Route::get('/codeditor/tree', [DeveloperFileEditorController::class, 'tree'])
                ->name('developer.domain.codeditor.tree');

            Route::get('/codeditor/read', [DeveloperFileEditorController::class, 'read'])
                ->name('developer.domain.codeditor.read');

            Route::post('/codeditor/save', [DeveloperFileEditorController::class, 'save'])
                ->name('developer.domain.codeditor.save');

            Route::post('/codeditor/file/create', [DeveloperFileEditorController::class, 'createFile'])
                ->name('developer.domain.codeditor.file.create');

            /*
            |--------------------------------------------------------------------------
            | Developer Tool Pages
            |--------------------------------------------------------------------------
            */

            Route::get('/git-tools', [DeveloperWorkspaceController::class, 'gitTools'])
                ->name('developer.domain.git.tools');

            Route::get('/database', [DeveloperWorkspaceController::class, 'database'])
                ->name('developer.domain.database');

            Route::get('/env-manager', [DeveloperWorkspaceController::class, 'envManager'])
                ->name('developer.domain.env.manager');

            Route::get('/error-logs', [DeveloperWorkspaceController::class, 'errorLogs'])
                ->name('developer.domain.error.logs');

            Route::get('/safe-terminal', [DeveloperWorkspaceController::class, 'safeTerminal'])
                ->name('developer.domain.safe.terminal');

            Route::get('/laravel-tools', [DeveloperWorkspaceController::class, 'laravelTools'])
                ->name('developer.domain.laravel.tools');

            Route::get('/frontend-tools', [DeveloperWorkspaceController::class, 'frontendTools'])
                ->name('developer.domain.frontend.tools');

            Route::get('/python-tools', [DeveloperWorkspaceController::class, 'pythonTools'])
                ->name('developer.domain.python.tools');

            Route::get('/deployment', [DeveloperWorkspaceController::class, 'deployment'])
                ->name('developer.domain.deployment');

            Route::get('/health-check', [DeveloperWorkspaceController::class, 'healthCheck'])
                ->name('developer.domain.health.check');

            Route::get('/security-notes', [DeveloperWorkspaceController::class, 'securityNotes'])
                ->name('developer.domain.security.notes');

            Route::get('/backup-status', [DeveloperWorkspaceController::class, 'backupStatus'])
                ->name('developer.domain.backup.status');

            Route::get('/permissions', [DeveloperWorkspaceController::class, 'permissions'])
                ->name('developer.domain.permissions');

            Route::get('/account-settings', [DeveloperWorkspaceController::class, 'accountSettings'])
                ->name('developer.domain.account.settings');

            /*
            |--------------------------------------------------------------------------
            | Safe Developer Actions
            |--------------------------------------------------------------------------
            */

            Route::post('/git-pull', [DeveloperWorkspaceController::class, 'gitPull'])
                ->name('developer.domain.git.pull');

            Route::post('/clear-cache', [DeveloperWorkspaceController::class, 'clearCache'])
                ->name('developer.domain.clear.cache');

            Route::post('/composer-dump', [DeveloperWorkspaceController::class, 'composerDump'])
                ->name('developer.domain.composer.dump');

            Route::post('/npm-build', [DeveloperWorkspaceController::class, 'npmBuild'])
                ->name('developer.domain.npm.build');

            Route::post('/open-folder', [DeveloperWorkspaceController::class, 'openFolder'])
                ->name('developer.domain.open.folder');

            Route::get('/env-example', [DeveloperWorkspaceController::class, 'downloadEnvExample'])
                ->name('developer.domain.env.example');
        });
    });

/*
|--------------------------------------------------------------------------
| Main Admin Login Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.submit');

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
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

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');




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
    | Servers
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
    | LiteSpeed Manager
    |--------------------------------------------------------------------------
    */

    Route::prefix('servers/{server}/litespeed')
        ->name('servers.litespeed.')
        ->middleware(['throttle:20,1'])
        ->group(function () {

            Route::get('/', [LiteSpeedController::class, 'index'])
                ->name('index');

            Route::post('/activate', [LiteSpeedController::class, 'activate'])
                ->name('activate');

            Route::post('/restart', [LiteSpeedController::class, 'restart'])
                ->name('restart');

            Route::post('/stop', [LiteSpeedController::class, 'stop'])
                ->name('stop');

            Route::post('/reload', [LiteSpeedController::class, 'reload'])
                ->name('reload');

            Route::post('/config-test', [LiteSpeedController::class, 'configTest'])
                ->name('configTest');

            Route::post('/logs', [LiteSpeedController::class, 'logs'])
                ->name('logs');
        });

    /*
    |--------------------------------------------------------------------------
    | cPanel / WHM Account Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('servers/{server}/cpanel-accounts')
        ->name('servers.cpanel.')
        ->middleware(['throttle:30,1'])
        ->group(function () {

            Route::get('/', [CpanelAccountController::class, 'index'])
                ->name('index');

            Route::get('/create', [CpanelAccountController::class, 'create'])
                ->name('create');

            Route::post('/store', [CpanelAccountController::class, 'store'])
                ->name('store');

            Route::get('/{user}/edit', [CpanelAccountController::class, 'edit'])
                ->name('edit');

                  Route::post('/{user}/alerts', [CpanelAccountController::class, 'updateAlertContacts'])
            ->name('alerts.update');

            Route::post('/{user}/password', [CpanelAccountController::class, 'updatePassword'])
                ->name('password');

            Route::post('/{user}/package', [CpanelAccountController::class, 'updatePackage'])
                ->name('package');

            Route::post('/{user}/ip', [CpanelAccountController::class, 'updateIp'])
                ->name('ip');

            Route::post('/{user}/sms', [CpanelAccountController::class, 'sendAccountSms'])
                ->name('sms');

            Route::post('/{user}/email', [CpanelAccountController::class, 'sendAccountEmail'])
                ->name('email');

            Route::get('/{user}/login', [CpanelAccountController::class, 'autoLogin'])
                ->name('login');

            Route::get('/{user}/login/email', [CpanelAccountController::class, 'autoLoginEmail'])
                ->name('login.email');

            Route::get('/{user}/login/files', [CpanelAccountController::class, 'autoLoginFiles'])
                ->name('login.files');

            Route::get('/{user}/login/wordpress', [CpanelAccountController::class, 'autoLoginWordPress'])
                ->name('login.wordpress');
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

            Route::get('/', [WordPressManagerController::class, 'show'])
                ->name('show');

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
    | Backups
    |--------------------------------------------------------------------------
    */

    Route::prefix('backups')
        ->name('backups.')
        ->group(function () {

            Route::get('/', [BackupController::class, 'index'])
                ->name('index');

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
    | SMS Alert Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('sms')
        ->name('sms.')
        ->middleware(['throttle:20,1'])
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
    | Technology Web Scanner
    |--------------------------------------------------------------------------
    */

    Route::prefix('technology/web-scanner')
        ->name('technology.webscanner.')
        ->middleware(['throttle:20,1'])
        ->group(function () {

            Route::get('/', [SentinelWebScanController::class, 'index'])
                ->name('index');

            Route::post('/scan', [SentinelWebScanController::class, 'scan'])
                ->name('scan');

            Route::get('/{scan}', [SentinelWebScanController::class, 'show'])
                ->name('show');

            Route::post('/{scan}/rescan', [SentinelWebScanController::class, 'rescan'])
                ->name('rescan');

            Route::delete('/{scan}', [SentinelWebScanController::class, 'destroy'])
                ->name('destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Monitoring Pages
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
    | Security Pages + Actions
    |--------------------------------------------------------------------------
    */

    Route::prefix('security')
        ->name('security.')
        ->group(function () {

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

            Route::post('/block-ip', [App\Http\Controllers\SecurityController::class, 'blockIp'])
                ->name('block.ip');

            Route::post('/unblock-ip', [App\Http\Controllers\SecurityController::class, 'unblockIp'])
                ->name('unblock.ip');

            Route::post('/restart-firewall', [App\Http\Controllers\SecurityController::class, 'restartFirewall'])
                ->name('firewall.restart');

            Route::post('/ssh/kill-session', [App\Http\Controllers\SecurityController::class, 'killSession'])
                ->name('ssh.kill');

            Route::post('/ssh/block-ip', [App\Http\Controllers\SecurityController::class, 'sshBlockIp'])
                ->name('ssh.block');

            Route::post('/email/clear-queue', [App\Http\Controllers\SecurityController::class, 'clearQueue'])
                ->name('email.clear');

            Route::post('/email/block-sender', [App\Http\Controllers\SecurityController::class, 'blockSender'])
                ->name('email.block');

            Route::post('/scan-malware', [App\Http\Controllers\SecurityController::class, 'scanMalware'])
                ->name('malware.scan');

            Route::post('/kill-process', [App\Http\Controllers\SecurityController::class, 'killProcess'])
                ->name('process.kill');

            Route::get('/logs/live', [App\Http\Controllers\SecurityController::class, 'liveLogs'])
                ->name('logs.live');

            Route::post('/ai-detect', [App\Http\Controllers\SecurityController::class, 'aiDetect'])
                ->name('ai.detect');
        });

    /*
    |--------------------------------------------------------------------------
    | Tools
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
    | Cloud DNS / Domains
    |--------------------------------------------------------------------------
    */

    Route::prefix('domains')
        ->name('domains.')
        ->group(function () {

            Route::get('/', [DomainController::class, 'index'])
                ->name('index');

            Route::post('/servers/{server}/link', [DomainController::class, 'linkServer'])
                ->name('servers.link');

            Route::post('/servers/{server}/unlink', [DomainController::class, 'unlinkServer'])
                ->name('servers.unlink');

            Route::post('/servers/{server}/domains/{domain}/primary', [DomainController::class, 'makePrimary'])
                ->name('servers.domains.primary');

            Route::post('/zone/create', [DomainController::class, 'createZone'])
                ->name('zone.create');

            Route::get('/records/{domain}', [DomainController::class, 'records'])
                ->name('records');

            Route::post('/records/add', [DomainController::class, 'addRecord'])
                ->name('records.add');

            Route::post('/records/delete', [DomainController::class, 'deleteRecord'])
                ->name('records.delete');
        });

    /*
    |--------------------------------------------------------------------------
    | Webscepts SentinelCore Technology
    |--------------------------------------------------------------------------
    */

    Route::prefix('technology')
        ->name('technology.')
        ->middleware(['throttle:30,1'])
        ->group(function () {

            Route::get('/', [TechnologyController::class, 'index'])
                ->name('index');

            Route::post('/encrypt-text', [TechnologyController::class, 'encryptText'])
                ->name('encrypt.text');

            Route::post('/decrypt-text', [TechnologyController::class, 'decryptText'])
                ->name('decrypt.text');

            Route::post('/encrypt-file', [TechnologyController::class, 'encryptFile'])
                ->name('encrypt.file');

            Route::post('/rotate-server-passwords', [TechnologyController::class, 'rotateServerPasswords'])
                ->name('rotate.passwords');
        });

    /*
    |--------------------------------------------------------------------------
    | Admin Developer Management Routes
    |--------------------------------------------------------------------------
    | Admin panel:
    | https://systemmonitor.webscepts.com/developers/cpanel-import
    |--------------------------------------------------------------------------
    */

    Route::prefix('developers')
        ->name('developers.')
        ->middleware(['throttle:30,1'])
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Admin Developer Workspace Tools
            |--------------------------------------------------------------------------
            */

            Route::get('/workspace', [DeveloperWorkspaceController::class, 'index'])
                ->name('workspace');

            Route::post('/git-pull', [DeveloperWorkspaceController::class, 'gitPull'])
                ->name('git.pull');

            Route::post('/clear-cache', [DeveloperWorkspaceController::class, 'clearCache'])
                ->name('clear.cache');

            Route::post('/composer-dump', [DeveloperWorkspaceController::class, 'composerDump'])
                ->name('composer.dump');

            Route::post('/npm-build', [DeveloperWorkspaceController::class, 'npmBuild'])
                ->name('npm.build');

            Route::post('/open-folder', [DeveloperWorkspaceController::class, 'openFolder'])
                ->name('open.folder');

            Route::get('/env-example', [DeveloperWorkspaceController::class, 'downloadEnvExample'])
                ->name('env.example');

            /*
            |--------------------------------------------------------------------------
            | cPanel Developer Import
            |--------------------------------------------------------------------------
            */

            Route::get('/cpanel-import', [DeveloperCpanelImportController::class, 'index'])
                ->name('cpanel.import');

            Route::post('/cpanel-sync', [DeveloperCpanelImportController::class, 'sync'])
                ->name('cpanel.sync');

            Route::post('/cpanel-login-import', [DeveloperCpanelImportController::class, 'importSingleCpanelLogin'])
                ->name('cpanel.login.import');

            Route::post('/cpanel-bulk-import', [DeveloperCpanelImportController::class, 'bulkImport'])
                ->name('cpanel.bulk.import');

            /*
            |--------------------------------------------------------------------------
            | Old Code Server Setup Routes
            |--------------------------------------------------------------------------
            | Kept so existing buttons/forms do not break.
            | The new visual editor does not need SSH/root/code-server.
            |--------------------------------------------------------------------------
            */

            Route::post('/code-editor/setup-existing', [DeveloperCpanelImportController::class, 'setupAllExistingCodeEditors'])
                ->name('code-editor.setup-existing');

            Route::post('/{developer}/code-editor/setup', [DeveloperCpanelImportController::class, 'setupCodeEditor'])
                ->name('code-editor.setup');

            /*
            |--------------------------------------------------------------------------
            | Existing Developer Settings
            |--------------------------------------------------------------------------
            */

            Route::put('/{developer}/settings', [DeveloperCpanelImportController::class, 'updateSettings'])
                ->name('settings.update');

            Route::post('/{developer}/reset-password', [DeveloperCpanelImportController::class, 'resetPassword'])
                ->name('reset-password');

            Route::post('/{developer}/toggle', [DeveloperCpanelImportController::class, 'toggle'])
                ->name('toggle');

            Route::post('/{developer}/portal-access', [DeveloperCpanelImportController::class, 'portalAccess'])
                ->name('portal-access');

            Route::post('/{developer}/portal-enable', [DeveloperCpanelImportController::class, 'enablePortal'])
                ->name('portal-enable');

            Route::post('/{developer}/portal-disable', [DeveloperCpanelImportController::class, 'disablePortal'])
                ->name('portal-disable');

            Route::delete('/{developer}', [DeveloperCpanelImportController::class, 'destroy'])
                ->name('destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Admin Logout
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

        /*
    |--------------------------------------------------------------------------
    | Profile Security Direct Route Fix
    |--------------------------------------------------------------------------
    | Keep this outside developer subdomain group.
    |--------------------------------------------------------------------------
    */

    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/profile/security', [\App\Http\Controllers\ProfileSecurityController::class, 'index'])
            ->name('profile.security');

        Route::get('/profile/passkeys', [\App\Http\Controllers\PasskeyPageController::class, 'index'])
            ->name('profile.passkeys');
    });
});