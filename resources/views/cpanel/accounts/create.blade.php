@extends('layouts.app')

@section('page-title', 'Create cPanel Account')

@section('content')

@php
    use Illuminate\Support\Facades\Route;

    $packages = $packages ?? [];
    $ips = $ips ?? [];
    $error = $error ?? null;

    $serverName = $server->name ?? 'Server';
    $serverHost = $server->host ?? $server->hostname ?? $server->ip_address ?? $server->ip ?? '';

    $adminPhone = old('admin_phone', $server->admin_phone ?? '');
    $adminEmail = old('admin_email', $server->admin_email ?? '');
    $customerPhone = old('customer_phone', $server->customer_phone ?? '');
    $customerEmail = old('customer_email', old('email', $server->customer_email ?? ''));
    $alertPhones = old('alert_phones', '');
    $alertEmails = old('alert_emails', '');

    $monitorWebsite = old('monitor_website', 1);
    $monitorCpanel = old('monitor_cpanel', 1);
    $monitorFrameworks = old('monitor_frameworks', 1);
    $sendRecoveryAlert = old('send_recovery_alert', 1);

    $storeRoute = Route::has('servers.cpanel.store')
        ? route('servers.cpanel.store', $server)
        : url('/servers/' . $server->id . '/cpanel-accounts/store');

    $backRoute = Route::has('servers.cpanel.index')
        ? route('servers.cpanel.index', $server)
        : url('/servers/' . $server->id . '/cpanel-accounts');
@endphp

<div class="max-w-6xl mx-auto">

    <style>
        .ws-create-card {
            background: #ffffff;
            border-radius: 26px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .ws-create-header {
            padding: 28px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 48%, #ecfdf5 100%);
            border-bottom: 1px solid #e5e7eb;
        }

        .ws-create-title {
            font-size: 30px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.03em;
        }

        .ws-create-subtitle {
            margin-top: 8px;
            color: #64748b;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.5;
        }

        .ws-create-body {
            padding: 28px;
        }

        .ws-section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .ws-section-icon {
            width: 38px;
            height: 38px;
            border-radius: 14px;
            background: #2563eb;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .ws-section-title {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
        }

        .ws-section-text {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            margin-top: 2px;
        }

        .ws-label {
            display: block;
            font-size: 13px;
            font-weight: 900;
            color: #334155;
            margin-bottom: 8px;
        }

        .ws-input,
        .ws-select,
        .ws-textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 15px;
            padding: 13px 14px;
            color: #0f172a;
            font-size: 14px;
            font-weight: 600;
            outline: none;
            background: #ffffff;
            transition: all .2s ease;
        }

        .ws-textarea {
            min-height: 96px;
            resize: vertical;
        }

        .ws-input:focus,
        .ws-select:focus,
        .ws-textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .ws-input-icon-wrap {
            position: relative;
        }

        .ws-input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
        }

        .ws-input-icon-wrap .ws-input {
            padding-left: 43px;
        }

        .ws-help {
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.4;
        }

        .ws-error {
            color: #dc2626;
            font-size: 12px;
            margin-top: 6px;
            font-weight: 800;
        }

        .ws-alert-box {
            border-radius: 18px;
            padding: 16px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .ws-alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .ws-alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .ws-monitor-panel {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 22px;
        }

        .ws-switch-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #ffffff;
        }

        .ws-switch-title {
            font-weight: 900;
            color: #0f172a;
            font-size: 14px;
        }

        .ws-switch-desc {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            margin-top: 3px;
            line-height: 1.35;
        }

        .ws-toggle {
            position: relative;
            width: 52px;
            height: 30px;
            flex: 0 0 auto;
        }

        .ws-toggle input {
            display: none;
        }

        .ws-slider {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: #cbd5e1;
            cursor: pointer;
            transition: .2s;
        }

        .ws-slider:before {
            content: "";
            position: absolute;
            width: 24px;
            height: 24px;
            left: 3px;
            top: 3px;
            background: #ffffff;
            border-radius: 999px;
            box-shadow: 0 5px 12px rgba(15,23,42,.2);
            transition: .2s;
        }

        .ws-toggle input:checked + .ws-slider {
            background: #2563eb;
        }

        .ws-toggle input:checked + .ws-slider:before {
            transform: translateX(22px);
        }

        .ws-preview-card {
            background: #0f172a;
            color: #ffffff;
            border-radius: 22px;
            padding: 22px;
            height: 100%;
        }

        .ws-preview-title {
            font-size: 20px;
            font-weight: 900;
            margin-bottom: 16px;
        }

        .ws-preview-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 11px 0;
            border-bottom: 1px solid rgba(255,255,255,.1);
            font-size: 14px;
        }

        .ws-preview-row:last-child {
            border-bottom: none;
        }

        .ws-preview-label {
            color: #94a3b8;
            font-weight: 800;
        }

        .ws-preview-value {
            color: #ffffff;
            font-weight: 900;
            text-align: right;
            word-break: break-word;
        }

        .ws-sample-alert {
            margin-top: 18px;
            border-radius: 18px;
            padding: 15px;
            font-size: 13px;
            line-height: 1.55;
        }

        .ws-sample-down {
            background: rgba(239, 68, 68, .16);
            border: 1px solid rgba(248, 113, 113, .3);
        }

        .ws-sample-up {
            background: rgba(34, 197, 94, .16);
            border: 1px solid rgba(74, 222, 128, .3);
        }

        .ws-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .ws-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 50px;
            padding: 13px 24px;
            border-radius: 15px;
            background: #2563eb;
            color: #ffffff;
            font-weight: 900;
            border: none;
            text-decoration: none;
            transition: all .2s ease;
            cursor: pointer;
        }

        .ws-btn-primary:hover {
            background: #1d4ed8;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .ws-btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            padding: 13px 24px;
            border-radius: 15px;
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 900;
            text-decoration: none;
            transition: all .2s ease;
        }

        .ws-btn-secondary:hover {
            background: #cbd5e1;
            color: #0f172a;
        }

        .ws-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        @media (max-width: 768px) {
            .ws-create-header,
            .ws-create-body {
                padding: 20px;
            }

            .ws-create-title {
                font-size: 24px;
            }

            .ws-actions {
                flex-direction: column;
            }

            .ws-btn-primary,
            .ws-btn-secondary {
                width: 100%;
            }

            .ws-preview-row {
                flex-direction: column;
                gap: 4px;
            }

            .ws-preview-value {
                text-align: left;
            }
        }
    </style>

    <div class="ws-create-card">

        <div class="ws-create-header">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="ws-create-title">Create cPanel Account</h2>
                    <p class="ws-create-subtitle">
                        {{ $serverName }}{{ $serverHost ? ' - ' . $serverHost : '' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="ws-badge">
                        <i class="fa-solid fa-clock"></i>
                        Monitoring 30 min 24/7
                    </span>
                    <span class="ws-badge">
                        <i class="fa-solid fa-bell"></i>
                        SMS + Email Alerts
                    </span>
                </div>
            </div>
        </div>

        <div class="ws-create-body">

            @if($error)
                <div class="ws-alert-box ws-alert-error">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    {{ $error }}
                </div>
            @endif

            @if(session('success'))
                <div class="ws-alert-box ws-alert-success">
                    <i class="fa-solid fa-circle-check mr-2"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="ws-alert-box ws-alert-error">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i>
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="ws-alert-box ws-alert-error">
                    <div class="font-black mb-2">Please fix these errors:</div>
                    <ul class="list-disc ml-5 text-sm">
                        @foreach($errors->all() as $validationError)
                            <li>{{ $validationError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $storeRoute }}" id="cpanelCreateForm">
                @csrf

                {{-- Account Details --}}
                <div class="mb-8">
                    <div class="ws-section-heading">
                        <span class="ws-section-icon">1</span>
                        <div>
                            <h3 class="ws-section-title">Account Details</h3>
                            <p class="ws-section-text">Create the WHM/cPanel account with domain, username, password, package and IP.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        <div>
                            <label class="ws-label">Domain</label>
                            <div class="ws-input-icon-wrap">
                                <span class="ws-input-icon">
                                    <i class="fa-solid fa-globe"></i>
                                </span>
                                <input type="text"
                                       name="domain"
                                       id="domainInput"
                                       value="{{ old('domain') }}"
                                       placeholder="example.com"
                                       class="ws-input alert-live-field">
                            </div>
                            @error('domain') <p class="ws-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ws-label">Username</label>
                            <div class="ws-input-icon-wrap">
                                <span class="ws-input-icon">
                                    <i class="fa-solid fa-user"></i>
                                </span>
                                <input type="text"
                                       name="username"
                                       value="{{ old('username') }}"
                                       placeholder="example"
                                       class="ws-input">
                            </div>
                            @error('username') <p class="ws-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ws-label">Password</label>
                            <div class="flex gap-2">
                                <div class="ws-input-icon-wrap flex-1">
                                    <span class="ws-input-icon">
                                        <i class="fa-solid fa-lock"></i>
                                    </span>
                                    <input type="password"
                                           name="password"
                                           id="passwordInput"
                                           class="ws-input"
                                           placeholder="Strong cPanel password">
                                </div>

                                <button type="button"
                                        onclick="togglePassword()"
                                        class="px-5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-900 font-black">
                                    Show
                                </button>
                            </div>
                            @error('password') <p class="ws-error">{{ $message }}</p> @enderror
                            <p class="ws-help">Use a strong password. It can also be saved for Developer Codes / Visual Code Editor.</p>
                        </div>

                        <div>
                            <label class="ws-label">Contact Email</label>
                            <div class="ws-input-icon-wrap">
                                <span class="ws-input-icon">
                                    <i class="fa-solid fa-envelope"></i>
                                </span>
                                <input type="email"
                                       name="email"
                                       id="contactEmailInput"
                                       value="{{ old('email') }}"
                                       placeholder="client@example.com"
                                       class="ws-input alert-live-field">
                            </div>
                            @error('email') <p class="ws-error">{{ $message }}</p> @enderror
                            <p class="ws-help">This is the cPanel account contact email.</p>
                        </div>

                        <div>
                            <label class="ws-label">Package</label>
                            <select name="package" class="ws-select">
                                <option value="">Select Package</option>
                                @foreach($packages as $package)
                                    @php
                                        $pkgName = $package['name'] ?? $package['pkg'] ?? null;
                                    @endphp

                                    @if($pkgName)
                                        <option value="{{ $pkgName }}" {{ old('package') == $pkgName ? 'selected' : '' }}>
                                            {{ $pkgName }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('package') <p class="ws-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="ws-label">IP Address</label>
                            <select name="ip" class="ws-select">
                                <option value="">Auto / Shared IP</option>
                                @foreach($ips as $ip)
                                    @php
                                        $ipValue = $ip['ip'] ?? $ip['address'] ?? null;
                                    @endphp

                                    @if($ipValue)
                                        <option value="{{ $ipValue }}" {{ old('ip') == $ipValue ? 'selected' : '' }}>
                                            {{ $ipValue }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('ip') <p class="ws-error">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                {{-- Monitoring Alert Contacts --}}
                <div class="mb-8">
                    <div class="ws-section-heading">
                        <span class="ws-section-icon">2</span>
                        <div>
                            <h3 class="ws-section-title">Monitoring Alert Contacts</h3>
                            <p class="ws-section-text">
                                Save mobile numbers and email addresses for website down, cPanel down, uptime issue, CMS/framework issue and recovery alerts.
                            </p>
                        </div>
                    </div>

                    <div class="ws-monitor-panel">
                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                            <div class="xl:col-span-2 space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                                    <div>
                                        <label class="ws-label">Admin Mobile Number</label>
                                        <div class="ws-input-icon-wrap">
                                            <span class="ws-input-icon">
                                                <i class="fa-solid fa-mobile-screen-button"></i>
                                            </span>
                                            <input type="text"
                                                   name="admin_phone"
                                                   id="alertAdminPhone"
                                                   value="{{ $adminPhone }}"
                                                   class="ws-input alert-live-field"
                                                   placeholder="947XXXXXXXX">
                                        </div>
                                        @error('admin_phone') <p class="ws-error">{{ $message }}</p> @enderror
                                        <p class="ws-help">Webscepts/admin mobile number for SMS alerts.</p>
                                    </div>

                                    <div>
                                        <label class="ws-label">Admin Email Address</label>
                                        <div class="ws-input-icon-wrap">
                                            <span class="ws-input-icon">
                                                <i class="fa-solid fa-envelope-open-text"></i>
                                            </span>
                                            <input type="email"
                                                   name="admin_email"
                                                   id="alertAdminEmail"
                                                   value="{{ $adminEmail }}"
                                                   class="ws-input alert-live-field"
                                                   placeholder="admin@webscepts.com">
                                        </div>
                                        @error('admin_email') <p class="ws-error">{{ $message }}</p> @enderror
                                        <p class="ws-help">Webscepts/admin email address for email alerts.</p>
                                    </div>

                                    <div>
                                        <label class="ws-label">Customer Mobile Number</label>
                                        <div class="ws-input-icon-wrap">
                                            <span class="ws-input-icon">
                                                <i class="fa-solid fa-phone"></i>
                                            </span>
                                            <input type="text"
                                                   name="customer_phone"
                                                   id="alertCustomerPhone"
                                                   value="{{ $customerPhone }}"
                                                   class="ws-input alert-live-field"
                                                   placeholder="947XXXXXXXX">
                                        </div>
                                        @error('customer_phone') <p class="ws-error">{{ $message }}</p> @enderror
                                        <p class="ws-help">Client mobile number for website down and recovery SMS.</p>
                                    </div>

                                    <div>
                                        <label class="ws-label">Customer Email Address</label>
                                        <div class="ws-input-icon-wrap">
                                            <span class="ws-input-icon">
                                                <i class="fa-solid fa-at"></i>
                                            </span>
                                            <input type="email"
                                                   name="customer_email"
                                                   id="alertCustomerEmail"
                                                   value="{{ $customerEmail }}"
                                                   class="ws-input alert-live-field"
                                                   placeholder="client@example.com">
                                        </div>
                                        @error('customer_email') <p class="ws-error">{{ $message }}</p> @enderror
                                        <p class="ws-help">Client email address for website down and recovery email alerts.</p>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="ws-label">Extra Alert Mobile Numbers</label>
                                        <textarea name="alert_phones"
                                                  id="alertExtraPhones"
                                                  rows="3"
                                                  class="ws-textarea alert-live-field"
                                                  placeholder="947XXXXXXXX, 947YYYYYYYY">{{ $alertPhones }}</textarea>
                                        @error('alert_phones') <p class="ws-error">{{ $message }}</p> @enderror
                                        <p class="ws-help">Optional. Add multiple mobile numbers separated by commas.</p>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="ws-label">Extra Alert Email Addresses</label>
                                        <textarea name="alert_emails"
                                                  id="alertExtraEmails"
                                                  rows="3"
                                                  class="ws-textarea alert-live-field"
                                                  placeholder="support@example.com, owner@example.com">{{ $alertEmails }}</textarea>
                                        @error('alert_emails') <p class="ws-error">{{ $message }}</p> @enderror
                                        <p class="ws-help">Optional. Add multiple email addresses separated by commas.</p>
                                    </div>

                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                                    <label class="ws-switch-card">
                                        <div>
                                            <div class="ws-switch-title">Website Uptime</div>
                                            <div class="ws-switch-desc">Website down and recovery alerts.</div>
                                        </div>

                                        <span class="ws-toggle">
                                            <input type="checkbox"
                                                   name="monitor_website"
                                                   value="1"
                                                   {{ $monitorWebsite ? 'checked' : '' }}>
                                            <span class="ws-slider"></span>
                                        </span>
                                    </label>

                                    <label class="ws-switch-card">
                                        <div>
                                            <div class="ws-switch-title">cPanel / WHM</div>
                                            <div class="ws-switch-desc">Check cPanel 2083 and WHM 2087.</div>
                                        </div>

                                        <span class="ws-toggle">
                                            <input type="checkbox"
                                                   name="monitor_cpanel"
                                                   value="1"
                                                   {{ $monitorCpanel ? 'checked' : '' }}>
                                            <span class="ws-slider"></span>
                                        </span>
                                    </label>

                                    <label class="ws-switch-card">
                                        <div>
                                            <div class="ws-switch-title">CMS / Framework</div>
                                            <div class="ws-switch-desc">Laravel, PHP, Magento, Drupal, WordPress.</div>
                                        </div>

                                        <span class="ws-toggle">
                                            <input type="checkbox"
                                                   name="monitor_frameworks"
                                                   value="1"
                                                   {{ $monitorFrameworks ? 'checked' : '' }}>
                                            <span class="ws-slider"></span>
                                        </span>
                                    </label>

                                    <label class="ws-switch-card">
                                        <div>
                                            <div class="ws-switch-title">Recovery Alert</div>
                                            <div class="ws-switch-desc">Notify when website/service comes back.</div>
                                        </div>

                                        <span class="ws-toggle">
                                            <input type="checkbox"
                                                   name="send_recovery_alert"
                                                   value="1"
                                                   {{ $sendRecoveryAlert ? 'checked' : '' }}>
                                            <span class="ws-slider"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <div class="ws-preview-card">
                                    <h3 class="ws-preview-title">
                                        <i class="fa-solid fa-satellite-dish mr-2 text-blue-300"></i>
                                        Live Alert Preview
                                    </h3>

                                    <div class="ws-preview-row">
                                        <span class="ws-preview-label">Domain</span>
                                        <span class="ws-preview-value" id="previewAlertDomain">example.com</span>
                                    </div>

                                    <div class="ws-preview-row">
                                        <span class="ws-preview-label">SMS To</span>
                                        <span class="ws-preview-value" id="previewAlertPhones">No mobile numbers</span>
                                    </div>

                                    <div class="ws-preview-row">
                                        <span class="ws-preview-label">Email To</span>
                                        <span class="ws-preview-value" id="previewAlertEmails">No email addresses</span>
                                    </div>

                                    <div class="ws-preview-row">
                                        <span class="ws-preview-label">Schedule</span>
                                        <span class="ws-preview-value text-green-300">Every 30 min</span>
                                    </div>

                                    <div class="ws-sample-alert ws-sample-down">
                                        <strong>Sample Down Alert:</strong><br>
                                        WEBSITE ISSUE: <span id="sampleDownDomain">example.com</span> is down / cPanel down / framework error detected.
                                    </div>

                                    <div class="ws-sample-alert ws-sample-up">
                                        <strong>Sample Recovery Alert:</strong><br>
                                        RECOVERED: <span id="sampleUpDomain">example.com</span> is back online.
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="ws-actions">
                    <button type="submit" class="ws-btn-primary">
                        <i class="fa-solid fa-circle-plus"></i>
                        Create Account & Save Alerts
                    </button>

                    <a href="{{ $backRoute }}" class="ws-btn-secondary">
                        Cancel
                    </a>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById('passwordInput');

        if (!input) {
            return;
        }

        input.type = input.type === 'password' ? 'text' : 'password';
    }

    function splitAlertValues(value) {
        if (!value) {
            return [];
        }

        return value
            .split(',')
            .map(function (item) {
                return item.trim();
            })
            .filter(function (item) {
                return item.length > 0;
            });
    }

    function uniqueAlertValues(values) {
        return values.filter(function (value, index, self) {
            return self.indexOf(value) === index;
        });
    }

    function updateAlertPreview() {
        const domainInput = document.getElementById('domainInput');
        const contactEmailInput = document.getElementById('contactEmailInput');
        const customerEmailInput = document.getElementById('alertCustomerEmail');

        const domain = domainInput && domainInput.value.trim()
            ? domainInput.value.trim()
            : 'example.com';

        if (contactEmailInput && customerEmailInput && !customerEmailInput.value.trim()) {
            customerEmailInput.value = contactEmailInput.value;
        }

        const phoneFields = [
            document.getElementById('alertAdminPhone'),
            document.getElementById('alertCustomerPhone'),
            document.getElementById('alertExtraPhones')
        ];

        const emailFields = [
            document.getElementById('alertAdminEmail'),
            document.getElementById('alertCustomerEmail'),
            document.getElementById('alertExtraEmails')
        ];

        let phones = [];
        let emails = [];

        phoneFields.forEach(function (field) {
            if (field) {
                phones = phones.concat(splitAlertValues(field.value));
            }
        });

        emailFields.forEach(function (field) {
            if (field) {
                emails = emails.concat(splitAlertValues(field.value));
            }
        });

        phones = uniqueAlertValues(phones);
        emails = uniqueAlertValues(emails);

        const previewDomain = document.getElementById('previewAlertDomain');
        const previewPhones = document.getElementById('previewAlertPhones');
        const previewEmails = document.getElementById('previewAlertEmails');
        const sampleDownDomain = document.getElementById('sampleDownDomain');
        const sampleUpDomain = document.getElementById('sampleUpDomain');

        if (previewDomain) {
            previewDomain.innerText = domain;
        }

        if (sampleDownDomain) {
            sampleDownDomain.innerText = domain;
        }

        if (sampleUpDomain) {
            sampleUpDomain.innerText = domain;
        }

        if (previewPhones) {
            previewPhones.innerText = phones.length ? phones.join(', ') : 'No mobile numbers';
        }

        if (previewEmails) {
            previewEmails.innerText = emails.length ? emails.join(', ') : 'No email addresses';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert-live-field').forEach(function (field) {
            field.addEventListener('input', updateAlertPreview);
            field.addEventListener('change', updateAlertPreview);
        });

        updateAlertPreview();
    });
</script>

@endsection