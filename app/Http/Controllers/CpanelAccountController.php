<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use App\Models\Server;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpseclib3\Net\SSH2;

class CpanelAccountController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST cPanel ACCOUNTS
    |--------------------------------------------------------------------------
    */
    public function index(Server $server)
    {
        $accounts = [];
        $error = null;

        try {
            $response = $this->whmRequest($server, 'listaccts');
            $accounts = $response['data']['acct'] ?? [];

            foreach ($accounts as $key => $account) {
                $username = $account['user'] ?? $account['username'] ?? null;

                if ($username) {
                    $accounts[$key]['alert_contacts'] = $this->getSavedAlertContacts($server, $username);
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('cpanel.accounts.index', compact('server', 'accounts', 'error'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ACCOUNT PAGE
    |--------------------------------------------------------------------------
    */
    public function create(Server $server)
    {
        $error = null;
        $packages = [];
        $ips = [];
        $frameworks = $this->frameworkOptions();

        try {
            $whm = $this->whmRequest($server, 'listpkgs');

            if (!empty($whm['package'])) {
                $packages = $whm['package'];
            } elseif (!empty($whm['data']['pkg'])) {
                $packages = $whm['data']['pkg'];
            } elseif (!empty($whm['data']['packages'])) {
                $packages = $whm['data']['packages'];
            }
        } catch (\Throwable $e) {
            $error = 'Unable to load packages: ' . $e->getMessage();
        }

        try {
            $ipResponse = $this->whmRequest($server, 'listips');

            if (!empty($ipResponse['data']['ip'])) {
                $ips = $ipResponse['data']['ip'];
            } elseif (!empty($ipResponse['ip'])) {
                $ips = $ipResponse['ip'];
            } elseif (!empty($ipResponse['data']['ips'])) {
                $ips = $ipResponse['data']['ips'];
            }
        } catch (\Throwable $e) {
            $ips = [];
        }

        return view('cpanel.accounts.create', compact(
            'server',
            'packages',
            'ips',
            'frameworks',
            'error'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE cPanel ACCOUNT
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, Server $server)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'username' => 'required|string|max:16',
            'password' => 'required|string|min:8',
            'email' => 'nullable|email|max:255',

            /*
            |--------------------------------------------------------------------------
            | Blade uses package. Keep plan too for old forms.
            |--------------------------------------------------------------------------
            */
            'package' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'ip' => 'nullable|string|max:255',

            /*
            |--------------------------------------------------------------------------
            | Monitoring Alert Contacts
            |--------------------------------------------------------------------------
            */
            'admin_phone' => 'nullable|string|max:30',
            'admin_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'customer_email' => 'nullable|email|max:255',
            'alert_phones' => 'nullable|string|max:1000',
            'alert_emails' => 'nullable|string|max:1000',
            'monitor_website' => 'nullable',
            'monitor_cpanel' => 'nullable',
            'monitor_frameworks' => 'nullable',
            'send_recovery_alert' => 'nullable',

            /*
            |--------------------------------------------------------------------------
            | Developer Codes / Visual Editor
            |--------------------------------------------------------------------------
            */
            'create_developer_login' => 'nullable',
            'developer_portal_access' => 'nullable',
            'framework' => 'nullable|string|max:100',
            'project_root' => 'nullable|string|max:255',

            'can_view_files' => 'nullable',
            'can_edit_files' => 'nullable',
            'can_delete_files' => 'nullable',
            'can_git_pull' => 'nullable',
            'can_clear_cache' => 'nullable',
            'can_composer' => 'nullable',
            'can_npm' => 'nullable',
            'can_run_build' => 'nullable',
            'can_run_python' => 'nullable',
            'can_restart_app' => 'nullable',
            'can_mysql' => 'nullable',
            'can_postgresql' => 'nullable',

            'db_type' => 'nullable|string|max:50',
            'db_host' => 'nullable|string|max:255',
            'db_port' => 'nullable|string|max:20',
            'db_username' => 'nullable|string|max:255',
            'db_password' => 'nullable|string|max:255',
            'db_name' => 'nullable|string|max:255',
        ]);

        try {
            $package = $data['package'] ?? $data['plan'] ?? null;

            $params = [
                'domain' => $data['domain'],
                'username' => $data['username'],
                'password' => $data['password'],
                'contactemail' => $data['email'] ?? '',
            ];

            if (!empty($package)) {
                $params['plan'] = $package;
            }

            if (!empty($data['ip'])) {
                $params['ip'] = $data['ip'];
            }

            $this->whmRequest($server, 'createacct', $params);

            /*
            |--------------------------------------------------------------------------
            | Save monitoring alert contacts
            |--------------------------------------------------------------------------
            */
            $this->saveAlertContacts($server, $data['username'], [
                'domain' => $data['domain'],
                'email' => $data['email'] ?? null,
                'admin_phone' => $data['admin_phone'] ?? null,
                'admin_email' => $data['admin_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'alert_phones' => $data['alert_phones'] ?? null,
                'alert_emails' => $data['alert_emails'] ?? null,
                'monitor_website' => $request->boolean('monitor_website', true),
                'monitor_cpanel' => $request->boolean('monitor_cpanel', true),
                'monitor_frameworks' => $request->boolean('monitor_frameworks', true),
                'send_recovery_alert' => $request->boolean('send_recovery_alert', true),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Save real cPanel password for Visual Code Editor File Manager API
            |--------------------------------------------------------------------------
            | This is the important part for /codeditor.
            |--------------------------------------------------------------------------
            */
            if ($request->boolean('create_developer_login', true)) {
                $developer = $this->createOrUpdateDeveloperFromCpanelAccount(
                    $server,
                    $request,
                    $data,
                    $package
                );

                return redirect()
                    ->route('servers.cpanel.index', $server)
                    ->with('success', 'cPanel account created, alert contacts saved, and Developer Codes login saved successfully.')
                    ->with('created_logins', [
                        [
                            'name' => $developer->name ?? $data['username'],
                            'login' => $data['username'],
                            'email' => $data['email'] ?? '',
                            'domain' => $data['domain'],
                            'framework' => $data['framework'] ?? 'custom',
                            'project_root' => $developer->project_root ?? '/home/' . $data['username'] . '/public_html',
                            'portal_access' => $request->boolean('developer_portal_access', true) ? 'Enabled' : 'Disabled',
                            'password' => $data['password'],
                            'url' => 'https://developercodes.webscepts.com/login',
                            'codeditor' => 'https://developercodes.webscepts.com/codeditor',
                            'code_editor_url' => 'Monaco cPanel File Manager API',
                        ],
                    ]);
            }

            return redirect()
                ->route('servers.cpanel.index', $server)
                ->with('success', 'cPanel account created and alert contacts saved successfully.');

        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Create account failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGE ACCOUNT PAGE
    |--------------------------------------------------------------------------
    */
    public function edit(Server $server, string $user)
    {
        $account = [];
        $packages = [];
        $ips = [];
        $error = null;

        try {
            $account = $this->getAccount($server, $user);
            $packages = $this->getPackages($server);
            $ips = $this->getIps($server);

            $savedAlerts = $this->getSavedAlertContacts($server, $user);

            $account = array_merge($account, [
                'admin_phone' => $savedAlerts['admin_phone'] ?? null,
                'admin_email' => $savedAlerts['admin_email'] ?? null,
                'customer_phone' => $savedAlerts['customer_phone'] ?? null,
                'customer_email' => $savedAlerts['customer_email'] ?? null,
                'alert_phones' => $savedAlerts['alert_phones'] ?? null,
                'alert_emails' => $savedAlerts['alert_emails'] ?? null,
                'monitor_website' => $savedAlerts['monitor_website'] ?? 1,
                'monitor_cpanel' => $savedAlerts['monitor_cpanel'] ?? 1,
                'monitor_frameworks' => $savedAlerts['monitor_frameworks'] ?? 1,
                'send_recovery_alert' => $savedAlerts['send_recovery_alert'] ?? 1,
            ]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $remoteData = $this->getRemoteAccountData($server, $user, $account);

        return view('cpanel.accounts.edit', array_merge(
            compact('server', 'user', 'account', 'packages', 'ips', 'error'),
            $remoteData
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ALERT CONTACTS
    |--------------------------------------------------------------------------
    */
    public function updateAlertContacts(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'admin_phone' => 'nullable|string|max:30',
            'admin_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'customer_email' => 'nullable|email|max:255',
            'alert_phones' => 'nullable|string|max:1000',
            'alert_emails' => 'nullable|string|max:1000',
            'monitor_website' => 'nullable',
            'monitor_cpanel' => 'nullable',
            'monitor_frameworks' => 'nullable',
            'send_recovery_alert' => 'nullable',
        ]);

        try {
            $account = [];

            try {
                $account = $this->getAccount($server, $user);
            } catch (\Throwable $e) {
                $account = [];
            }

            $domain = $account['domain'] ?? $account['main_domain'] ?? null;
            $email = $account['email'] ?? $account['contactemail'] ?? $account['contact_email'] ?? null;

            $this->saveAlertContacts($server, $user, [
                'domain' => $domain,
                'email' => $email,
                'admin_phone' => $data['admin_phone'] ?? null,
                'admin_email' => $data['admin_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'alert_phones' => $data['alert_phones'] ?? null,
                'alert_emails' => $data['alert_emails'] ?? null,
                'monitor_website' => $request->boolean('monitor_website'),
                'monitor_cpanel' => $request->boolean('monitor_cpanel'),
                'monitor_frameworks' => $request->boolean('monitor_frameworks'),
                'send_recovery_alert' => $request->boolean('send_recovery_alert'),
            ]);

            return back()->with('success', 'Alert contacts updated successfully.');

        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Alert contacts update failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE cPanel ACCOUNT PASSWORD
    |--------------------------------------------------------------------------
    */
    public function updatePassword(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        try {
            $this->whmRequest($server, 'passwd', [
                'user' => $user,
                'password' => $data['password'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Also update Developer Codes saved cPanel password
            |--------------------------------------------------------------------------
            */
            $developer = DeveloperUser::where('cpanel_username', $user)->first();

            if ($developer) {
                $developer->update($this->filterDeveloperColumns([
                    'temporary_password' => Crypt::encryptString($data['password']),
                    'cpanel_password' => Crypt::encryptString($data['password']),
                    'ssh_username' => $user,
                ]));
            }

            return back()->with('success', 'Password updated successfully and Developer Codes cPanel password saved.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Password update failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PACKAGE
    |--------------------------------------------------------------------------
    */
    public function updatePackage(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'package' => 'required|string|max:255',
        ]);

        try {
            $this->whmRequest($server, 'changepackage', [
                'user' => $user,
                'pkg' => $data['package'],
            ]);

            return back()->with('success', 'Package changed successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Package change failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ACCOUNT IP
    |--------------------------------------------------------------------------
    */
    public function updateIp(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'ip' => 'required|string|max:255',
        ]);

        try {
            $this->whmRequest($server, 'setsiteip', [
                'user' => $user,
                'ip' => $data['ip'],
            ]);

            return back()->with('success', 'IP changed successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', 'IP change failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MANUAL SMS
    |--------------------------------------------------------------------------
    */
    public function sendAccountSms(
        Request $request,
        Server $server,
        string $user,
        SmsService $smsService
    ) {
        $data = $request->validate([
            'phone' => 'required|string|max:30',
            'message' => 'required|string|max:500',
        ]);

        try {
            $sent = $smsService->send($data['phone'], $data['message']);

            return back()->with(
                $sent ? 'success' : 'error',
                $sent ? 'SMS sent successfully.' : 'SMS failed. Check storage/logs/laravel.log.'
            );

        } catch (\Throwable $e) {
            return back()->with('error', 'SMS failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MANUAL EMAIL
    |--------------------------------------------------------------------------
    */
    public function sendAccountEmail(Request $request, Server $server, string $user)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        try {
            Mail::raw($data['message'], function ($mail) use ($data) {
                $mail->to($data['email'])
                    ->subject($data['subject']);
            });

            return back()->with('success', 'Email sent successfully.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Email failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO cPanel HOME
    |--------------------------------------------------------------------------
    */
    public function autoLogin(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            return back()->with('error', 'Auto login failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO EMAIL ACCOUNTS PAGE
    |--------------------------------------------------------------------------
    */
    public function autoLoginEmail(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld', 'Email_Accounts');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            try {
                $url = $this->createUserSession($server, $user, 'cpaneld');
                return redirect()->away($url);
            } catch (\Throwable $e2) {
                return back()->with('error', 'Email auto login failed: ' . $e2->getMessage());
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO FILE MANAGER
    |--------------------------------------------------------------------------
    */
    public function autoLoginFiles(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld', 'FileManager');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            try {
                $url = $this->createUserSession($server, $user, 'cpaneld');
                return redirect()->away($url);
            } catch (\Throwable $e2) {
                return back()->with('error', 'File Manager auto login failed: ' . $e2->getMessage());
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN TO WORDPRESS MANAGER
    |--------------------------------------------------------------------------
    */
    public function autoLoginWordPress(Server $server, string $user)
    {
        try {
            $url = $this->createUserSession($server, $user, 'cpaneld', 'WordPress_Manager');

            return redirect()->away($url);

        } catch (\Throwable $e) {
            try {
                $url = $this->createUserSession($server, $user, 'cpaneld');
                return redirect()->away($url);
            } catch (\Throwable $e2) {
                return back()->with('error', 'WordPress auto login failed: ' . $e2->getMessage());
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE / UPDATE DEVELOPER CODES LOGIN
    |--------------------------------------------------------------------------
    */
    private function createOrUpdateDeveloperFromCpanelAccount(
        Server $server,
        Request $request,
        array $data,
        ?string $package = null
    ): DeveloperUser {
        $username = trim($data['username']);
        $domain = trim($data['domain']);
        $email = trim($data['email'] ?? '') ?: $username . '@developer.local';
        $password = $data['password'];

        $framework = trim($data['framework'] ?? 'custom') ?: 'custom';
        $frameworkConfig = $this->frameworkDefaults($framework, $username, $domain);

        $projectRoot = trim($data['project_root'] ?? '')
            ?: $frameworkConfig['project_root']
            ?: '/home/' . $username . '/public_html';

        $dbType = strtolower(trim($data['db_type'] ?? 'mysql'));

        if (!in_array($dbType, ['mysql', 'postgresql', 'pgsql', 'postgres'], true)) {
            $dbType = 'mysql';
        }

        if (in_array($dbType, ['pgsql', 'postgres'], true)) {
            $dbType = 'postgresql';
        }

        $portalAccess = $request->boolean('developer_portal_access', true);

        $payload = [
            'server_id' => $server->id,

            'name' => $username,
            'email' => $email,
            'contact_email' => $email,
            'cpanel_username' => $username,
            'cpanel_domain' => $domain,

            /*
            |--------------------------------------------------------------------------
            | Developer portal login password
            |--------------------------------------------------------------------------
            | Keep same as cPanel password because user wants cPanel/API access available.
            |--------------------------------------------------------------------------
            */
            'password' => bcrypt($password),
            'temporary_password' => Crypt::encryptString($password),
            'cpanel_password' => Crypt::encryptString($password),
            'password_must_change' => false,

            'role' => 'developer',
            'ssh_username' => $username,
            'allowed_project_path' => $projectRoot,

            'project_type' => $frameworkConfig['project_type'],
            'framework' => $framework,
            'project_root' => $projectRoot,
            'build_command' => $frameworkConfig['build_command'],
            'deploy_command' => $frameworkConfig['deploy_command'],
            'start_command' => $frameworkConfig['start_command'],

            /*
            |--------------------------------------------------------------------------
            | Monaco Visual Editor does not need code-server URL
            |--------------------------------------------------------------------------
            */
            'code_editor_url' => 'https://developercodes.webscepts.com/codeditor',
            'vscode_url' => 'https://developercodes.webscepts.com/codeditor',

            'package' => $package,

            'can_view_files' => $request->boolean('can_view_files', true),
            'can_edit_files' => $request->boolean('can_edit_files', true),
            'can_delete_files' => $request->boolean('can_delete_files', false),

            'can_git_pull' => $request->boolean('can_git_pull'),
            'can_clear_cache' => $request->boolean('can_clear_cache', true),
            'can_composer' => $request->boolean('can_composer'),
            'can_npm' => $request->boolean('can_npm'),
            'can_run_build' => $request->boolean('can_run_build'),
            'can_run_python' => $request->boolean('can_run_python'),
            'can_restart_app' => $request->boolean('can_restart_app'),

            'can_mysql' => $request->boolean('can_mysql') || $dbType === 'mysql',
            'can_postgresql' => $request->boolean('can_postgresql') || $dbType === 'postgresql',

            'db_type' => $dbType,
            'db_host' => $data['db_host'] ?? 'localhost',
            'db_port' => $data['db_port'] ?? $this->defaultDbPort($dbType),
            'db_username' => $data['db_username'] ?? $username,
            'db_password' => !empty($data['db_password']) ? Crypt::encryptString($data['db_password']) : null,
            'db_name' => $data['db_name'] ?? '',

            /*
            |--------------------------------------------------------------------------
            | Alert contact fields if DeveloperUser table has these columns
            |--------------------------------------------------------------------------
            */
            'admin_phone' => $data['admin_phone'] ?? null,
            'admin_email' => $data['admin_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'alert_phones' => $data['alert_phones'] ?? null,
            'alert_emails' => $data['alert_emails'] ?? null,
            'monitor_website' => $request->boolean('monitor_website', true),
            'monitor_cpanel' => $request->boolean('monitor_cpanel', true),
            'monitor_frameworks' => $request->boolean('monitor_frameworks', true),
            'send_recovery_alert' => $request->boolean('send_recovery_alert', true),
        ];

        $payload = array_merge($payload, $this->portalAccessPayload($portalAccess));

        return DeveloperUser::updateOrCreate(
            [
                'cpanel_username' => $username,
            ],
            $this->filterDeveloperColumns($payload)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ALERT CONTACT STORAGE
    |--------------------------------------------------------------------------
    */
    private function saveAlertContacts(Server $server, string $user, array $contacts): void
    {
        $user = trim($user);

        if (!$user) {
            return;
        }

        $contacts = [
            'server_id' => $server->id,
            'server_name' => $server->name ?? null,
            'server_host' => $server->host ?? $server->hostname ?? $server->ip_address ?? null,
            'cpanel_username' => $user,
            'domain' => $contacts['domain'] ?? null,
            'email' => $contacts['email'] ?? null,
            'admin_phone' => $contacts['admin_phone'] ?? null,
            'admin_email' => $contacts['admin_email'] ?? null,
            'customer_phone' => $contacts['customer_phone'] ?? null,
            'customer_email' => $contacts['customer_email'] ?? null,
            'alert_phones' => $contacts['alert_phones'] ?? null,
            'alert_emails' => $contacts['alert_emails'] ?? null,
            'monitor_website' => (bool) ($contacts['monitor_website'] ?? true),
            'monitor_cpanel' => (bool) ($contacts['monitor_cpanel'] ?? true),
            'monitor_frameworks' => (bool) ($contacts['monitor_frameworks'] ?? true),
            'send_recovery_alert' => (bool) ($contacts['send_recovery_alert'] ?? true),
            'updated_at' => now()->toDateTimeString(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Save to session for immediate page reload
        |--------------------------------------------------------------------------
        */
        session()->put("cpanel_alert_contacts.{$server->id}.{$user}", $contacts);

        /*
        |--------------------------------------------------------------------------
        | Save to JSON file for persistence and scheduled monitor usage
        |--------------------------------------------------------------------------
        */
        $allContacts = $this->readAlertContactsFile();

        if (!isset($allContacts[$server->id])) {
            $allContacts[$server->id] = [];
        }

        $allContacts[$server->id][$user] = $contacts;

        Storage::disk('local')->put(
            'cpanel-alert-contacts.json',
            json_encode($allContacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        /*
        |--------------------------------------------------------------------------
        | Also update DeveloperUser if the account exists and columns are available
        |--------------------------------------------------------------------------
        */
        $developer = DeveloperUser::where('cpanel_username', $user)->first();

        if ($developer) {
            $developer->update($this->filterDeveloperColumns([
                'admin_phone' => $contacts['admin_phone'],
                'admin_email' => $contacts['admin_email'],
                'customer_phone' => $contacts['customer_phone'],
                'customer_email' => $contacts['customer_email'],
                'alert_phones' => $contacts['alert_phones'],
                'alert_emails' => $contacts['alert_emails'],
                'monitor_website' => $contacts['monitor_website'],
                'monitor_cpanel' => $contacts['monitor_cpanel'],
                'monitor_frameworks' => $contacts['monitor_frameworks'],
                'send_recovery_alert' => $contacts['send_recovery_alert'],
            ]));
        }
    }

    private function getSavedAlertContacts(Server $server, string $user): array
    {
        $sessionContacts = session("cpanel_alert_contacts.{$server->id}.{$user}", []);

        if (!empty($sessionContacts)) {
            return $sessionContacts;
        }

        $allContacts = $this->readAlertContactsFile();

        if (!empty($allContacts[$server->id][$user])) {
            return $allContacts[$server->id][$user];
        }

        $developer = DeveloperUser::where('cpanel_username', $user)->first();

        if ($developer) {
            return [
                'server_id' => $server->id,
                'server_name' => $server->name ?? null,
                'server_host' => $server->host ?? $server->hostname ?? $server->ip_address ?? null,
                'cpanel_username' => $user,
                'domain' => $developer->cpanel_domain ?? null,
                'email' => $developer->email ?? null,
                'admin_phone' => $developer->admin_phone ?? null,
                'admin_email' => $developer->admin_email ?? null,
                'customer_phone' => $developer->customer_phone ?? null,
                'customer_email' => $developer->customer_email ?? null,
                'alert_phones' => $developer->alert_phones ?? null,
                'alert_emails' => $developer->alert_emails ?? null,
                'monitor_website' => $developer->monitor_website ?? true,
                'monitor_cpanel' => $developer->monitor_cpanel ?? true,
                'monitor_frameworks' => $developer->monitor_frameworks ?? true,
                'send_recovery_alert' => $developer->send_recovery_alert ?? true,
            ];
        }

        return [
            'server_id' => $server->id,
            'server_name' => $server->name ?? null,
            'server_host' => $server->host ?? $server->hostname ?? $server->ip_address ?? null,
            'cpanel_username' => $user,
            'admin_phone' => $server->admin_phone ?? null,
            'admin_email' => $server->admin_email ?? null,
            'customer_phone' => $server->customer_phone ?? null,
            'customer_email' => $server->customer_email ?? null,
            'alert_phones' => null,
            'alert_emails' => null,
            'monitor_website' => true,
            'monitor_cpanel' => true,
            'monitor_frameworks' => true,
            'send_recovery_alert' => true,
        ];
    }

    private function readAlertContactsFile(): array
    {
        try {
            if (!Storage::disk('local')->exists('cpanel-alert-contacts.json')) {
                return [];
            }

            $json = Storage::disk('local')->get('cpanel-alert-contacts.json');
            $data = json_decode($json, true);

            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE ACCOUNT
    |--------------------------------------------------------------------------
    */
    private function getAccount(Server $server, string $user): array
    {
        try {
            $response = $this->whmRequest($server, 'accountsummary', [
                'user' => $user,
            ]);

            $acct = $response['data']['acct'][0] ?? null;

            if ($acct) {
                return $acct;
            }
        } catch (\Throwable $e) {
            // fallback to listaccts below
        }

        $list = $this->whmRequest($server, 'listaccts');
        $accounts = $list['data']['acct'] ?? [];

        foreach ($accounts as $account) {
            if (($account['user'] ?? null) === $user) {
                return $account;
            }
        }

        throw new \Exception('Account not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET PACKAGES
    |--------------------------------------------------------------------------
    */
    private function getPackages(Server $server): array
    {
        try {
            $response = $this->whmRequest($server, 'listpkgs');

            if (!empty($response['data']['pkg'])) {
                return $response['data']['pkg'];
            }

            if (!empty($response['package'])) {
                return $response['package'];
            }

            if (!empty($response['data']['packages'])) {
                return $response['data']['packages'];
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET IPs
    |--------------------------------------------------------------------------
    */
    private function getIps(Server $server): array
    {
        try {
            $response = $this->whmRequest($server, 'listips');

            if (!empty($response['data']['ip'])) {
                return $response['data']['ip'];
            }

            if (!empty($response['ip'])) {
                return $response['ip'];
            }

            if (!empty($response['data']['ips'])) {
                return $response['data']['ips'];
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REAL REMOTE ACCOUNT DATA THROUGH SSH
    |--------------------------------------------------------------------------
    */
    private function getRemoteAccountData(Server $server, string $user, array $account): array
    {
        $data = [
            'realDiskUsage' => null,
            'realDiskLimit' => null,
            'realHomePath' => "/home/{$user}",
            'realPublicHtml' => "/home/{$user}/public_html",
            'remoteServices' => [],
            'wordpressData' => [
                'detected' => false,
                'wp_cli_available' => false,
                'version' => null,
                'plugins_total' => 0,
                'plugins_active' => 0,
                'plugins_update' => 0,
                'themes_total' => 0,
                'themes_active' => 0,
                'themes_update' => 0,
                'status_message' => 'Not checked',
                'plugins' => [],
                'themes' => [],
            ],
            'emailSecurityData' => [
                'spf' => 'Unknown',
                'dkim' => 'Unknown',
                'dmarc' => 'Unknown',
            ],
        ];

        try {
            $ssh = $this->ssh($server);

            $homePath = trim($ssh->exec("eval echo ~{$user} 2>/dev/null"));

            if ($homePath && !str_contains(strtolower($homePath), 'not found')) {
                $data['realHomePath'] = $homePath;
                $data['realPublicHtml'] = $homePath . '/public_html';
            }

            $homeArg = escapeshellarg($data['realHomePath']);

            $data['realDiskUsage'] = trim(
                $ssh->exec("du -sh {$homeArg} 2>/dev/null | awk '{print $1}'")
            );

            $data['realDiskLimit'] =
                $account['disklimit']
                ?? $account['disklimit_human']
                ?? $account['diskquota']
                ?? null;

            $data['remoteServices'] = [
                'apache/httpd' => trim($ssh->exec("systemctl is-active httpd 2>/dev/null || systemctl is-active apache2 2>/dev/null || echo unknown")),
                'nginx' => trim($ssh->exec("systemctl is-active nginx 2>/dev/null || echo unknown")),
                'mysql/mariadb' => trim($ssh->exec("systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null || echo unknown")),
                'exim' => trim($ssh->exec("systemctl is-active exim 2>/dev/null || echo unknown")),
                'cpanel' => trim($ssh->exec("systemctl is-active cpanel 2>/dev/null || echo unknown")),
                'ssh' => trim($ssh->exec("systemctl is-active sshd 2>/dev/null || systemctl is-active ssh 2>/dev/null || echo unknown")),
            ];

            $data['wordpressData'] = $this->getWordPressData($ssh, $data['realPublicHtml']);

            $domain = $account['domain'] ?? null;
            $data['emailSecurityData'] = $this->getEmailSecurityData($ssh, $domain);

        } catch (\Throwable $e) {
            $data['wordpressData']['status_message'] = 'SSH check failed: ' . $e->getMessage();
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | WORDPRESS REAL DATA
    |--------------------------------------------------------------------------
    */
    private function getWordPressData(SSH2 $ssh, string $path): array
    {
        $wp = [
            'detected' => false,
            'wp_cli_available' => false,
            'version' => null,
            'plugins_total' => 0,
            'plugins_active' => 0,
            'plugins_update' => 0,
            'themes_total' => 0,
            'themes_active' => 0,
            'themes_update' => 0,
            'status_message' => 'WordPress not detected',
            'plugins' => [],
            'themes' => [],
        ];

        $pathArg = escapeshellarg($path);

        $hasConfig = trim(
            $ssh->exec("[ -f {$pathArg}/wp-config.php ] && echo yes || echo no")
        );

        $wp['detected'] = $hasConfig === 'yes';

        if (!$wp['detected']) {
            $wp['status_message'] = 'wp-config.php not found in public_html';
            return $wp;
        }

        $wpCli = trim($ssh->exec("command -v wp 2>/dev/null || echo no"));
        $wp['wp_cli_available'] = $wpCli !== 'no' && $wpCli !== '';

        if (!$wp['wp_cli_available']) {
            $versionFile = trim(
                $ssh->exec("grep \"\\\$wp_version\" {$pathArg}/wp-includes/version.php 2>/dev/null | head -n 1 | sed \"s/.*= '//\" | sed \"s/';//\"")
            );

            $wp['version'] = $versionFile ?: null;
            $wp['status_message'] = 'WordPress detected, but WP-CLI is not installed on server';

            return $wp;
        }

        $version = trim(
            $ssh->exec("wp core version --path={$pathArg} --allow-root 2>/dev/null")
        );

        $pluginsJson = trim(
            $ssh->exec("wp plugin list --format=json --path={$pathArg} --allow-root 2>/dev/null")
        );

        $themesJson = trim(
            $ssh->exec("wp theme list --format=json --path={$pathArg} --allow-root 2>/dev/null")
        );

        $plugins = json_decode($pluginsJson, true) ?: [];
        $themes = json_decode($themesJson, true) ?: [];

        $wp['version'] = $version ?: null;
        $wp['plugins'] = $plugins;
        $wp['themes'] = $themes;

        $wp['plugins_total'] = count($plugins);
        $wp['plugins_active'] = collect($plugins)->where('status', 'active')->count();
        $wp['plugins_update'] = collect($plugins)->where('update', 'available')->count();

        $wp['themes_total'] = count($themes);
        $wp['themes_active'] = collect($themes)->where('status', 'active')->count();
        $wp['themes_update'] = collect($themes)->where('update', 'available')->count();

        $wp['status_message'] = 'WordPress detected';

        return $wp;
    }

    /*
    |--------------------------------------------------------------------------
    | EMAIL DNS SECURITY DATA
    |--------------------------------------------------------------------------
    */
    private function getEmailSecurityData(SSH2 $ssh, ?string $domain): array
    {
        if (!$domain || $domain === 'Unknown domain') {
            return [
                'spf' => 'Unknown',
                'dkim' => 'Unknown',
                'dmarc' => 'Unknown',
            ];
        }

        $domainArg = escapeshellarg($domain);

        $spf = trim(
            $ssh->exec("dig TXT {$domainArg} +short 2>/dev/null | grep -i 'v=spf1' | head -n 1")
        );

        $dmarc = trim(
            $ssh->exec("dig TXT _dmarc.{$domainArg} +short 2>/dev/null | head -n 1")
        );

        $dkim = trim(
            $ssh->exec("dig TXT default._domainkey.{$domainArg} +short 2>/dev/null | head -n 1")
        );

        return [
            'spf' => $spf ? 'Configured' : 'Missing',
            'dkim' => $dkim ? 'Configured / Possible' : 'Missing / Unknown',
            'dmarc' => $dmarc ? 'Configured' : 'Missing',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE TEMPORARY cPanel USER SESSION
    |--------------------------------------------------------------------------
    */
    private function createUserSession(
        Server $server,
        string $cpanelUser,
        string $service = 'cpaneld',
        ?string $app = null
    ): string {
        $params = [
            'user' => $cpanelUser,
            'service' => $service,
        ];

        if ($app) {
            $params['app'] = $app;
        }

        $response = $this->whmRequest($server, 'create_user_session', $params);

        $url = $response['data']['url'] ?? null;

        if (!$url && !empty($response['data']['session'])) {
            $url = "https://{$server->host}:2083/login/?session=" . urlencode($response['data']['session']);
        }

        if (!$url) {
            throw new \Exception('cPanel session URL not returned.');
        }

        return $url;
    }

    /*
    |--------------------------------------------------------------------------
    | WHM REQUEST WITHOUT API TOKEN
    |--------------------------------------------------------------------------
    */
    private function whmRequest(Server $server, string $function, array $params = []): array
    {
        $host = $server->host;
        $url = "https://{$host}:2087/json-api/{$function}";

        $params = array_merge([
            'api.version' => 1,
        ], $params);

        $username = $server->username ?: 'root';
        $password = $this->getPassword($server);

        if (!$username || !$password) {
            throw new \Exception('Server username/password missing.');
        }

        $response = Http::withBasicAuth($username, $password)
            ->withoutVerifying()
            ->timeout(30)
            ->get($url, $params);

        if (!$response->successful()) {
            throw new \Exception(
                'WHM login/API failed: HTTP ' . $response->status() . ' - ' . $response->body()
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new \Exception('Invalid WHM response.');
        }

        $metadata = $json['metadata'] ?? [];

        if (isset($metadata['result']) && (int) $metadata['result'] === 0) {
            $reason = $metadata['reason'] ?? 'Unknown WHM API error.';
            throw new \Exception($reason);
        }

        return $json;
    }

    /*
    |--------------------------------------------------------------------------
    | SSH LOGIN
    |--------------------------------------------------------------------------
    */
    private function ssh(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->ssh_port ?? 22);
        $ssh->setTimeout(25);

        $password = $this->getPassword($server);

        if (!$ssh->login($server->username, $password)) {
            throw new \Exception('SSH login failed.');
        }

        return $ssh;
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD HELPER
    |--------------------------------------------------------------------------
    */
    private function getPassword(Server $server): string
    {
        try {
            return decrypt($server->password);
        } catch (\Throwable $e) {
            return $server->password;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DEVELOPER HELPERS
    |--------------------------------------------------------------------------
    */
    private function portalAccessPayload(bool $enabled): array
    {
        return [
            'is_active' => $enabled,
            'developer_portal_access' => $enabled,
            'portal_access_enabled' => $enabled,
            'developer_portal_enabled' => $enabled,
        ];
    }

    private function frameworkOptions(): array
    {
        return [
            'custom' => 'Custom / Other',
            'html' => 'Static HTML / CSS / JS',
            'php' => 'PHP',
            'wordpress' => 'WordPress',
            'laravel' => 'Laravel',
            'react' => 'React.js',
            'vue' => 'Vue.js',
            'angular' => 'Angular',
            'node' => 'Node.js / Express',
            'nextjs' => 'Next.js',
            'nuxt' => 'Nuxt.js',
            'svelte' => 'Svelte',
            'python' => 'Python',
            'flask' => 'Flask',
            'django' => 'Django',
            'fastapi' => 'FastAPI',
            'java' => 'Java',
            'springboot' => 'Spring Boot',
            'dotnet' => '.NET',
            'ruby' => 'Ruby / Rails',
            'go' => 'Go',
        ];
    }

    private function frameworkDefaults(string $framework, ?string $user = null, ?string $domain = null): array
    {
        $framework = strtolower(trim($framework ?: 'custom'));

        $home = $user ? '/home/' . $user : base_path();
        $publicHtml = $home . '/public_html';

        return match ($framework) {
            'laravel' => [
                'project_type' => 'php',
                'project_root' => $publicHtml,
                'build_command' => 'composer install --no-dev --optimize-autoloader && php artisan optimize:clear',
                'deploy_command' => 'php artisan migrate --force && php artisan optimize',
                'start_command' => '',
            ],

            'wordpress' => [
                'project_type' => 'cms',
                'project_root' => $publicHtml,
                'build_command' => '',
                'deploy_command' => '',
                'start_command' => '',
            ],

            'php' => [
                'project_type' => 'php',
                'project_root' => $publicHtml,
                'build_command' => 'composer install --no-dev',
                'deploy_command' => '',
                'start_command' => '',
            ],

            'react', 'vue', 'angular', 'nextjs', 'nuxt', 'svelte' => [
                'project_type' => 'frontend',
                'project_root' => $publicHtml,
                'build_command' => 'npm install && npm run build',
                'deploy_command' => 'npm run build',
                'start_command' => 'npm run dev',
            ],

            'node' => [
                'project_type' => 'node',
                'project_root' => $publicHtml,
                'build_command' => 'npm install',
                'deploy_command' => 'npm install --production',
                'start_command' => 'npm start',
            ],

            'python', 'flask', 'django', 'fastapi' => [
                'project_type' => 'python',
                'project_root' => $publicHtml,
                'build_command' => 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
                'deploy_command' => './venv/bin/pip install -r requirements.txt',
                'start_command' => 'python3 app.py',
            ],

            'java', 'springboot' => [
                'project_type' => 'java',
                'project_root' => $publicHtml,
                'build_command' => './mvnw clean package -DskipTests',
                'deploy_command' => './mvnw clean package -DskipTests',
                'start_command' => 'java -jar target/*.jar',
            ],

            'dotnet' => [
                'project_type' => 'dotnet',
                'project_root' => $publicHtml,
                'build_command' => 'dotnet restore && dotnet build',
                'deploy_command' => 'dotnet publish -c Release',
                'start_command' => 'dotnet run',
            ],

            'ruby' => [
                'project_type' => 'ruby',
                'project_root' => $publicHtml,
                'build_command' => 'bundle install',
                'deploy_command' => 'bundle install --deployment',
                'start_command' => 'bundle exec rails server',
            ],

            'go' => [
                'project_type' => 'go',
                'project_root' => $publicHtml,
                'build_command' => 'go mod download && go build',
                'deploy_command' => 'go build',
                'start_command' => './app',
            ],

            'html' => [
                'project_type' => 'static',
                'project_root' => $publicHtml,
                'build_command' => '',
                'deploy_command' => '',
                'start_command' => '',
            ],

            default => [
                'project_type' => 'custom',
                'project_root' => $publicHtml,
                'build_command' => '',
                'deploy_command' => '',
                'start_command' => '',
            ],
        };
    }

    private function defaultDbPort(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return in_array($dbType, ['postgresql', 'pgsql', 'postgres'], true) ? '5432' : '3306';
    }

    private function filterDeveloperColumns(array $payload): array
    {
        $table = (new DeveloperUser())->getTable();

        return collect($payload)
            ->filter(function ($value, $column) use ($table) {
                return Schema::hasColumn($table, $column);
            })
            ->toArray();
    }
}