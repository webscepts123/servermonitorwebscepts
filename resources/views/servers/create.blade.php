@extends('layouts.app')

@section('page-title', 'Add Server')

@section('content')

<div class="space-y-6">

    {{-- Header --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-purple-500/20 blur-3xl"></div>

        <div class="relative p-6 lg:p-8 text-white">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-black tracking-tight">
                        Add New Server
                    </h1>
                    <p class="text-slate-300 mt-2">
                        Configure SSH monitoring, cPanel/Plesk access, backups, SMS alerts and email alerts.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                            <i class="fa-solid fa-server mr-1"></i> Server Monitoring
                        </span>

                        <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                            <i class="fa-solid fa-message mr-1"></i> SMS Alerts
                        </span>

                        <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                            <i class="fa-solid fa-cloud-arrow-up mr-1"></i> Backup Ready
                        </span>
                    </div>
                </div>

                <a href="{{ route('servers.index') }}"
                   class="w-full lg:w-auto text-center px-5 py-3 rounded-2xl bg-white/10 border border-white/20 text-white hover:bg-white/20 font-bold">
                    <i class="fa-solid fa-arrow-left mr-2"></i>Back to Servers
                </a>
            </div>
        </div>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-2xl p-5">
            <strong>Please fix these errors:</strong>
            <ul class="list-disc ml-5 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ route('servers.store') }}" id="serverForm">
        @csrf

        {{-- Tabs --}}
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-3 sticky top-4 z-20 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <button type="button" class="tab-btn active-tab" data-tab="basic">
                    <i class="fa-solid fa-server mr-2"></i>Basic
                </button>

                <button type="button" class="tab-btn" data-tab="alerts">
                    <i class="fa-solid fa-bell mr-2"></i>Alerts
                </button>

                <button type="button" class="tab-btn" data-tab="backup">
                    <i class="fa-solid fa-cloud-arrow-up mr-2"></i>Backup
                </button>

                <button type="button" class="tab-btn" data-tab="security">
                    <i class="fa-solid fa-shield-halved mr-2"></i>Security
                </button>
            </div>
        </div>

        {{-- Basic Tab --}}
        <div class="tab-panel" id="tab-basic">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                <div class="xl:col-span-2 bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <div class="flex items-center justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-xl font-black text-slate-800">
                                Basic Server Info
                            </h3>
                            <p class="text-sm text-slate-500">
                                Main SSH and panel access information.
                            </p>
                        </div>

                        <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                            <i class="fa-solid fa-server text-xl"></i>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="form-label">Server Name</label>
                            <input type="text"
                                   name="name"
                                   value="{{ old('name') }}"
                                   class="form-input-modern"
                                   placeholder="Example: Main Production Server"
                                   required>
                        </div>

                        <div>
                            <label class="form-label">Host / IP</label>
                            <input type="text"
                                   name="host"
                                   value="{{ old('host') }}"
                                   class="form-input-modern"
                                   placeholder="192.168.1.10 or server.example.com"
                                   required>
                        </div>

                        <div>
                            <label class="form-label">Website URL</label>
                            <input type="url"
                                   name="website_url"
                                   value="{{ old('website_url') }}"
                                   class="form-input-modern"
                                   placeholder="https://example.com">
                        </div>

                        <div>
                            <label class="form-label">Panel Type</label>
                            <select name="panel_type" class="form-input-modern">
                                <option value="">Auto Detect / Not Set</option>
                                <option value="cpanel" {{ old('panel_type') === 'cpanel' ? 'selected' : '' }}>cPanel / WHM</option>
                                <option value="plesk" {{ old('panel_type') === 'plesk' ? 'selected' : '' }}>Plesk</option>
                                <option value="none" {{ old('panel_type') === 'none' ? 'selected' : '' }}>No Panel</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">SSH Port</label>
                            <input type="number"
                                   name="ssh_port"
                                   value="{{ old('ssh_port', 22) }}"
                                   class="form-input-modern"
                                   min="1"
                                   max="65535"
                                   required>
                        </div>

                        <div>
                            <label class="form-label">Username</label>
                            <input type="text"
                                   name="username"
                                   value="{{ old('username') }}"
                                   class="form-input-modern"
                                   placeholder="root or reseller username"
                                   required>
                        </div>

                        <div class="md:col-span-2">
                            <label class="form-label">Password</label>
                            <div class="relative">
                                <input type="password"
                                       name="password"
                                       id="passwordInput"
                                       class="form-input-modern pr-12"
                                       placeholder="Enter server password"
                                       required>

                                <button type="button"
                                        onclick="togglePassword()"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-900">
                                    <i class="fa-solid fa-eye" id="passwordIcon"></i>
                                </button>
                            </div>

                            <div class="mt-3">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-slate-500">Password strength</span>
                                    <span id="strengthText" class="font-bold text-slate-500">Waiting</span>
                                </div>
                                <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                    <div id="strengthBar" class="h-2 bg-slate-400 rounded-full transition-all" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="toggle-card">
                            <input type="checkbox" name="is_active" value="1" class="toggle-input" {{ old('is_active', true) ? 'checked' : '' }}>
                            <div>
                                <p class="font-black text-slate-800">Enable Monitoring</p>
                                <p class="text-xs text-slate-500">Allow automatic checks.</p>
                            </div>
                        </label>

                        <label class="toggle-card">
                            <input type="checkbox" name="auto_transfer" value="1" class="toggle-input" {{ old('auto_transfer') ? 'checked' : '' }}>
                            <div>
                                <p class="font-black text-slate-800">Auto Transfer</p>
                                <p class="text-xs text-slate-500">Move backups automatically.</p>
                            </div>
                        </label>

                        <label class="toggle-card">
                            <input type="checkbox" name="google_drive_sync" value="1" class="toggle-input" {{ old('google_drive_sync') ? 'checked' : '' }}>
                            <div>
                                <p class="font-black text-slate-800">Google Drive Sync</p>
                                <p class="text-xs text-slate-500">Sync backups to Drive.</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Preview --}}
                <div class="bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <h3 class="text-xl font-black text-slate-800 mb-4">
                        Live Preview
                    </h3>

                    <div class="rounded-3xl bg-slate-950 p-5 text-white">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-blue-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-server text-blue-300"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Server</p>
                                <h4 class="font-black" id="previewName">New Server</h4>
                            </div>
                        </div>

                        <div class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-400">Host</span>
                                <span class="font-bold break-all" id="previewHost">Not set</span>
                            </div>

                            <div class="flex justify-between gap-3">
                                <span class="text-slate-400">SSH</span>
                                <span class="font-bold" id="previewSsh">:22</span>
                            </div>

                            <div class="flex justify-between gap-3">
                                <span class="text-slate-400">Panel</span>
                                <span class="font-bold" id="previewPanel">Auto Detect</span>
                            </div>

                            <div class="flex justify-between gap-3">
                                <span class="text-slate-400">Website</span>
                                <span class="font-bold break-all" id="previewWebsite">N/A</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border p-4">
                        <h4 class="font-black text-slate-800">Helpful defaults</h4>
                        <p class="text-sm text-slate-500 mt-1">
                            For WHM/cPanel auto-login, use root or reseller credentials with WHM permission.
                        </p>
                    </div>
                </div>

            </div>
        </div>

        {{-- Alerts Tab --}}
        <div class="tab-panel hidden" id="tab-alerts">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <div class="bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <h3 class="text-xl font-black text-slate-800 mb-1">
                        Admin Alerts
                    </h3>
                    <p class="text-sm text-slate-500 mb-6">
                        Notifications sent to your internal team.
                    </p>

                    <div class="space-y-5">
                        <div>
                            <label class="form-label">Admin Email</label>
                            <input type="email"
                                   name="admin_email"
                                   value="{{ old('admin_email') }}"
                                   class="form-input-modern"
                                   placeholder="admin@example.com">
                        </div>

                        <div>
                            <label class="form-label">Admin Phone</label>
                            <input type="text"
                                   name="admin_phone"
                                   value="{{ old('admin_phone') }}"
                                   class="form-input-modern"
                                   placeholder="947XXXXXXXX">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="toggle-card">
                                <input type="checkbox" name="email_alerts_enabled" value="1" class="toggle-input" {{ old('email_alerts_enabled', true) ? 'checked' : '' }}>
                                <div>
                                    <p class="font-black text-slate-800">Email Alerts</p>
                                    <p class="text-xs text-slate-500">Send downtime emails.</p>
                                </div>
                            </label>

                            <label class="toggle-card">
                                <input type="checkbox" name="sms_alerts_enabled" value="1" class="toggle-input" {{ old('sms_alerts_enabled') ? 'checked' : '' }}>
                                <div>
                                    <p class="font-black text-slate-800">SMS Alerts</p>
                                    <p class="text-xs text-slate-500">Send downtime SMS.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <h3 class="text-xl font-black text-slate-800 mb-1">
                        Customer Alerts
                    </h3>
                    <p class="text-sm text-slate-500 mb-6">
                        Notifications sent to your customer when server or website is down.
                    </p>

                    <div class="space-y-5">
                        <div>
                            <label class="form-label">Customer Name</label>
                            <input type="text"
                                   name="customer_name"
                                   value="{{ old('customer_name') }}"
                                   class="form-input-modern"
                                   placeholder="Customer name">
                        </div>

                        <div>
                            <label class="form-label">Customer Email</label>
                            <input type="email"
                                   name="customer_email"
                                   value="{{ old('customer_email') }}"
                                   class="form-input-modern"
                                   placeholder="customer@example.com">
                        </div>

                        <div>
                            <label class="form-label">Customer Phone</label>
                            <input type="text"
                                   name="customer_phone"
                                   value="{{ old('customer_phone') }}"
                                   class="form-input-modern"
                                   placeholder="947XXXXXXXX">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Backup Tab --}}
        <div class="tab-panel hidden" id="tab-backup">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <div class="bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <h3 class="text-xl font-black text-slate-800 mb-1">
                        Backup Settings
                    </h3>
                    <p class="text-sm text-slate-500 mb-6">
                        Configure local, remote and Google Drive backup locations.
                    </p>

                    <div class="space-y-5">
                        <div>
                            <label class="form-label">Backup Path</label>
                            <input type="text"
                                   name="backup_path"
                                   value="{{ old('backup_path') }}"
                                   class="form-input-modern"
                                   placeholder="/home/backups">
                        </div>

                        <div>
                            <label class="form-label">Local Backup Path</label>
                            <input type="text"
                                   name="local_backup_path"
                                   value="{{ old('local_backup_path') }}"
                                   class="form-input-modern"
                                   placeholder="/var/backups/webscept">
                        </div>

                        <div>
                            <label class="form-label">Google Drive Remote</label>
                            <input type="text"
                                   name="google_drive_remote"
                                   value="{{ old('google_drive_remote') }}"
                                   class="form-input-modern"
                                   placeholder="gdrive:server-backups">
                        </div>

                        <div>
                            <label class="form-label">Backup Server</label>
                            <select name="backup_server_id" class="form-input-modern">
                                <option value="">No backup server</option>
                                @foreach($backupServers ?? [] as $backupServer)
                                    <option value="{{ $backupServer->id }}" {{ old('backup_server_id') == $backupServer->id ? 'selected' : '' }}>
                                        {{ $backupServer->name }} - {{ $backupServer->host }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <h3 class="text-xl font-black text-slate-800 mb-1">
                        Disk Rules
                    </h3>
                    <p class="text-sm text-slate-500 mb-6">
                        Trigger warnings and transfer actions based on disk usage.
                    </p>

                    <div class="space-y-6">
                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="form-label mb-0">Disk Warning %</label>
                                <span class="text-sm font-black text-orange-600" id="warningValue">{{ old('disk_warning_percent', 80) }}%</span>
                            </div>
                            <input type="range"
                                   name="disk_warning_percent"
                                   id="warningRange"
                                   min="1"
                                   max="100"
                                   value="{{ old('disk_warning_percent', 80) }}"
                                   class="w-full">
                        </div>

                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="form-label mb-0">Disk Transfer %</label>
                                <span class="text-sm font-black text-red-600" id="transferValue">{{ old('disk_transfer_percent', 90) }}%</span>
                            </div>
                            <input type="range"
                                   name="disk_transfer_percent"
                                   id="transferRange"
                                   min="1"
                                   max="100"
                                   value="{{ old('disk_transfer_percent', 90) }}"
                                   class="w-full">
                        </div>

                        <div class="rounded-2xl bg-slate-50 border p-5">
                            <h4 class="font-black text-slate-800">Rule Preview</h4>
                            <p class="text-sm text-slate-500 mt-2">
                                System will warn at <strong id="warningPreview">{{ old('disk_warning_percent', 80) }}%</strong>
                                and transfer/backup at <strong id="transferPreview">{{ old('disk_transfer_percent', 90) }}%</strong>.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Security Tab --}}
        <div class="tab-panel hidden" id="tab-security">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                <div class="xl:col-span-2 bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <h3 class="text-xl font-black text-slate-800 mb-1">
                        Security Checklist
                    </h3>
                    <p class="text-sm text-slate-500 mb-6">
                        Use these recommendations before saving the server.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="security-card">
                            <i class="fa-solid fa-key text-blue-600"></i>
                            <div>
                                <h4>Use SSH keys if possible</h4>
                                <p>Passwords work, but keys are safer for production.</p>
                            </div>
                        </div>

                        <div class="security-card">
                            <i class="fa-solid fa-shield-halved text-green-600"></i>
                            <div>
                                <h4>Enable firewall</h4>
                                <p>Keep only required ports open: 22, 80, 443, 2087.</p>
                            </div>
                        </div>

                        <div class="security-card">
                            <i class="fa-solid fa-lock text-purple-600"></i>
                            <div>
                                <h4>Restrict root access</h4>
                                <p>Use root only when WHM auto-login is required.</p>
                            </div>
                        </div>

                        <div class="security-card">
                            <i class="fa-solid fa-cloud-arrow-up text-orange-600"></i>
                            <div>
                                <h4>Enable backups</h4>
                                <p>Protect customer files with daily backup strategy.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow border border-slate-100">
                    <h3 class="text-xl font-black text-slate-800 mb-4">
                        Save Summary
                    </h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-slate-500">Monitoring</span>
                            <span class="font-black text-green-600">Ready</span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-500">SMS</span>
                            <span class="font-black" id="summarySms">Disabled</span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-500">Email</span>
                            <span class="font-black" id="summaryEmail">Enabled</span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-slate-500">Backups</span>
                            <span class="font-black" id="summaryBackup">Optional</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Bottom Actions --}}
        <div class="mt-6 bg-white rounded-3xl shadow border border-slate-100 p-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h3 class="font-black text-slate-800">Ready to save?</h3>
                    <p class="text-sm text-slate-500">
                        Server password will be encrypted before saving.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('servers.index') }}"
                       class="text-center px-6 py-3 rounded-2xl bg-slate-200 text-slate-800 hover:bg-slate-300 font-bold">
                        Cancel
                    </a>

                    <button type="submit"
                            class="px-8 py-3 rounded-2xl bg-blue-600 text-white hover:bg-blue-700 font-black shadow-lg">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>Save Server
                    </button>
                </div>
            </div>
        </div>

    </form>

</div>

<style>
    .tab-btn {
        padding: 12px 14px;
        border-radius: 16px;
        font-weight: 900;
        color: #475569;
        background: #f1f5f9;
        transition: all .2s ease;
        font-size: 14px;
    }

    .tab-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .active-tab {
        background: #0f172a !important;
        color: white !important;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .18);
    }

    .form-label {
        display: block;
        margin-bottom: 6px;
        font-weight: 800;
        color: #334155;
        font-size: 14px;
    }

    .form-input-modern {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 16px;
        padding: 12px 16px;
        outline: none;
        transition: all .2s ease;
        background: white;
    }

    .form-input-modern:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
    }

    .toggle-card {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        cursor: pointer;
        background: #fff;
        transition: all .2s ease;
    }

    .toggle-card:hover {
        box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        transform: translateY(-1px);
    }

    .toggle-input {
        margin-top: 4px;
        width: 18px;
        height: 18px;
        accent-color: #2563eb;
    }

    .security-card {
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 18px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        background: #fff;
    }

    .security-card h4 {
        font-weight: 900;
        color: #0f172a;
    }

    .security-card p {
        font-size: 13px;
        color: #64748b;
        margin-top: 4px;
    }
</style>

<script>
    const tabs = document.querySelectorAll('.tab-btn');
    const panels = document.querySelectorAll('.tab-panel');

    tabs.forEach(button => {
        button.addEventListener('click', () => {
            const tab = button.dataset.tab;

            tabs.forEach(item => item.classList.remove('active-tab'));
            panels.forEach(panel => panel.classList.add('hidden'));

            button.classList.add('active-tab');
            document.getElementById('tab-' + tab).classList.remove('hidden');

            localStorage.setItem('serverCreateTab', tab);
        });
    });

    const savedTab = localStorage.getItem('serverCreateTab');
    if (savedTab && document.querySelector(`[data-tab="${savedTab}"]`)) {
        document.querySelector(`[data-tab="${savedTab}"]`).click();
    }

    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const icon = document.getElementById('passwordIcon');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    const passwordInput = document.getElementById('passwordInput');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            const value = this.value;
            let score = 0;

            if (value.length >= 8) score += 25;
            if (/[A-Z]/.test(value)) score += 25;
            if (/[0-9]/.test(value)) score += 25;
            if (/[^A-Za-z0-9]/.test(value)) score += 25;

            strengthBar.style.width = score + '%';

            strengthBar.classList.remove('bg-slate-400', 'bg-red-500', 'bg-yellow-500', 'bg-green-600');

            if (score <= 25) {
                strengthBar.classList.add('bg-red-500');
                strengthText.innerText = 'Weak';
                strengthText.className = 'font-bold text-red-600';
            } else if (score <= 75) {
                strengthBar.classList.add('bg-yellow-500');
                strengthText.innerText = 'Medium';
                strengthText.className = 'font-bold text-yellow-600';
            } else {
                strengthBar.classList.add('bg-green-600');
                strengthText.innerText = 'Strong';
                strengthText.className = 'font-bold text-green-600';
            }
        });
    }

    const fields = {
        name: document.querySelector('[name="name"]'),
        host: document.querySelector('[name="host"]'),
        website: document.querySelector('[name="website_url"]'),
        ssh: document.querySelector('[name="ssh_port"]'),
        panel: document.querySelector('[name="panel_type"]'),
        sms: document.querySelector('[name="sms_alerts_enabled"]'),
        email: document.querySelector('[name="email_alerts_enabled"]'),
        backup: document.querySelector('[name="backup_path"]')
    };

    function updatePreview() {
        document.getElementById('previewName').innerText = fields.name?.value || 'New Server';
        document.getElementById('previewHost').innerText = fields.host?.value || 'Not set';
        document.getElementById('previewSsh').innerText = ':' + (fields.ssh?.value || '22');

        let panelValue = fields.panel?.value || 'Auto Detect';
        document.getElementById('previewPanel').innerText = panelValue === 'cpanel'
            ? 'cPanel / WHM'
            : panelValue === 'plesk'
                ? 'Plesk'
                : panelValue === 'none'
                    ? 'No Panel'
                    : 'Auto Detect';

        document.getElementById('previewWebsite').innerText = fields.website?.value || 'N/A';

        document.getElementById('summarySms').innerText = fields.sms?.checked ? 'Enabled' : 'Disabled';
        document.getElementById('summarySms').className = fields.sms?.checked ? 'font-black text-green-600' : 'font-black text-slate-500';

        document.getElementById('summaryEmail').innerText = fields.email?.checked ? 'Enabled' : 'Disabled';
        document.getElementById('summaryEmail').className = fields.email?.checked ? 'font-black text-green-600' : 'font-black text-slate-500';

        document.getElementById('summaryBackup').innerText = fields.backup?.value ? 'Configured' : 'Optional';
        document.getElementById('summaryBackup').className = fields.backup?.value ? 'font-black text-green-600' : 'font-black text-slate-500';
    }

    Object.values(fields).forEach(field => {
        if (field) {
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        }
    });

    updatePreview();

    const warningRange = document.getElementById('warningRange');
    const transferRange = document.getElementById('transferRange');

    function updateRanges() {
        document.getElementById('warningValue').innerText = warningRange.value + '%';
        document.getElementById('transferValue').innerText = transferRange.value + '%';
        document.getElementById('warningPreview').innerText = warningRange.value + '%';
        document.getElementById('transferPreview').innerText = transferRange.value + '%';
    }

    warningRange?.addEventListener('input', updateRanges);
    transferRange?.addEventListener('input', updateRanges);
</script>

@endsection