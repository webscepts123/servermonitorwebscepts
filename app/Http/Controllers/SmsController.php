<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SmsController extends Controller
{
    protected SmsService $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
    }

    /**
     * Send custom SMS from form/API.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:30',
            'message' => 'required|string|max:500',
        ]);

        $sent = $this->sendSmsSafe($data['phone'], $data['message']);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'SMS sent successfully' : 'SMS failed',
        ]);
    }

    /**
     * Manual DOWN alert for server.
     */
    public function sendDownAlert($serverId)
    {
        $server = Server::findOrFail($serverId);

        $message = $this->cleanSmsMessage(
            "🚨 DOWN ALERT\n" .
            "Server: {$server->name}\n" .
            "Host: {$server->host}\n" .
            "Status: OFFLINE"
        );

        $this->sendServerSms($server, $message);

        $this->sendEmailSafe(
            $this->serverAlertEmails($server),
            "DOWN ALERT - {$server->name}",
            $message
        );

        return back()->with('success', 'Down alert SMS/email sent');
    }

    /**
     * Manual RECOVERY alert for server.
     */
    public function sendRecoveryAlert($serverId)
    {
        $server = Server::findOrFail($serverId);

        $message = $this->cleanSmsMessage(
            "✅ RECOVERED\n" .
            "Server: {$server->name}\n" .
            "Host: {$server->host}\n" .
            "Status: ONLINE"
        );

        $this->sendServerSms($server, $message);

        $this->sendEmailSafe(
            $this->serverAlertEmails($server),
            "RECOVERED - {$server->name}",
            $message
        );

        return back()->with('success', 'Recovery alert SMS/email sent');
    }

    /**
     * Scheduled DOWN/ISSUE alert for cPanel account / website / framework issue.
     *
     * Expected data:
     * [
     *   'name' => '',
     *   'domain' => '',
     *   'host' => '',
     *   'reason' => '',
     *   'phones' => [],
     *   'emails' => [],
     *   'platforms' => [],
     *   'critical_issues' => [],
     *   'warning_issues' => [],
     * ]
     */
    public function sendAccountDownAlert(array $data): bool
    {
        $name = $data['name'] ?? 'Account';
        $domain = $data['domain'] ?? 'N/A';
        $host = $data['host'] ?? 'N/A';
        $reason = $data['reason'] ?? 'Service unavailable';

        $platforms = $this->arrayToText($data['platforms'] ?? []);
        $criticalIssues = $this->arrayToText($data['critical_issues'] ?? []);
        $warningIssues = $this->arrayToText($data['warning_issues'] ?? []);

        $smsMessage = $this->cleanSmsMessage(
            "🚨 WEBSITE ISSUE\n" .
            "Account: {$name}\n" .
            "Domain: {$domain}\n" .
            "Host: {$host}\n" .
            ($platforms ? "Platform: {$platforms}\n" : '') .
            "Reason: {$reason}"
        );

        $emailMessage =
            "Website / Account Issue Detected\n\n" .
            "Account: {$name}\n" .
            "Domain: {$domain}\n" .
            "Host: {$host}\n" .
            "Platform/CMS/Framework: " . ($platforms ?: 'Unknown') . "\n\n" .
            "Reason:\n{$reason}\n\n" .
            "Critical Issues:\n" . ($criticalIssues ?: 'None') . "\n\n" .
            "Warning Issues:\n" . ($warningIssues ?: 'None') . "\n\n" .
            "Checked At: " . now()->format('Y-m-d H:i:s') . "\n";

        $smsSent = $this->sendToRecipients($data['phones'] ?? [], $smsMessage);

        $this->sendEmailSafe(
            $data['emails'] ?? [],
            "WEBSITE ISSUE - {$domain}",
            $emailMessage
        );

        Log::warning('Website/account down alert sent.', [
            'name' => $name,
            'domain' => $domain,
            'host' => $host,
            'sms_sent' => $smsSent,
            'emails' => $data['emails'] ?? [],
            'phones' => $data['phones'] ?? [],
            'platforms' => $data['platforms'] ?? [],
            'reason' => $reason,
        ]);

        return $smsSent;
    }

    /**
     * Scheduled RECOVERY alert for cPanel account / website / framework issue.
     *
     * Expected data:
     * [
     *   'name' => '',
     *   'domain' => '',
     *   'host' => '',
     *   'reason' => '',
     *   'phones' => [],
     *   'emails' => [],
     *   'platforms' => [],
     * ]
     */
    public function sendAccountRecoveryAlert(array $data): bool
    {
        $name = $data['name'] ?? 'Account';
        $domain = $data['domain'] ?? 'N/A';
        $host = $data['host'] ?? 'N/A';
        $reason = $data['reason'] ?? 'All monitored checks are back online';
        $platforms = $this->arrayToText($data['platforms'] ?? []);

        $smsMessage = $this->cleanSmsMessage(
            "✅ RECOVERED\n" .
            "Account: {$name}\n" .
            "Domain: {$domain}\n" .
            "Host: {$host}\n" .
            ($platforms ? "Platform: {$platforms}\n" : '') .
            "Status: Back online"
        );

        $emailMessage =
            "Website / Account Recovered\n\n" .
            "Account: {$name}\n" .
            "Domain: {$domain}\n" .
            "Host: {$host}\n" .
            "Platform/CMS/Framework: " . ($platforms ?: 'Unknown') . "\n\n" .
            "Status:\n{$reason}\n\n" .
            "Recovered At: " . now()->format('Y-m-d H:i:s') . "\n";

        $smsSent = $this->sendToRecipients($data['phones'] ?? [], $smsMessage);

        $this->sendEmailSafe(
            $data['emails'] ?? [],
            "RECOVERED - {$domain}",
            $emailMessage
        );

        Log::info('Website/account recovery alert sent.', [
            'name' => $name,
            'domain' => $domain,
            'host' => $host,
            'sms_sent' => $smsSent,
            'emails' => $data['emails'] ?? [],
            'phones' => $data['phones'] ?? [],
            'platforms' => $data['platforms'] ?? [],
        ]);

        return $smsSent;
    }

    /**
     * Scheduled WARNING alert.
     * Use this only if you later want non-critical warning emails/SMS.
     */
    public function sendAccountWarningAlert(array $data): bool
    {
        $name = $data['name'] ?? 'Account';
        $domain = $data['domain'] ?? 'N/A';
        $host = $data['host'] ?? 'N/A';
        $warning = $data['warning'] ?? $data['reason'] ?? 'Warning detected';
        $platforms = $this->arrayToText($data['platforms'] ?? []);

        $smsMessage = $this->cleanSmsMessage(
            "⚠️ WEBSITE WARNING\n" .
            "Account: {$name}\n" .
            "Domain: {$domain}\n" .
            ($platforms ? "Platform: {$platforms}\n" : '') .
            "Warning: {$warning}"
        );

        $emailMessage =
            "Website / Account Warning\n\n" .
            "Account: {$name}\n" .
            "Domain: {$domain}\n" .
            "Host: {$host}\n" .
            "Platform/CMS/Framework: " . ($platforms ?: 'Unknown') . "\n\n" .
            "Warning:\n{$warning}\n\n" .
            "Checked At: " . now()->format('Y-m-d H:i:s') . "\n";

        $smsSent = false;

        if (!empty($data['send_sms_for_warning'])) {
            $smsSent = $this->sendToRecipients($data['phones'] ?? [], $smsMessage);
        }

        $this->sendEmailSafe(
            $data['emails'] ?? [],
            "WEBSITE WARNING - {$domain}",
            $emailMessage
        );

        Log::info('Website/account warning alert sent.', [
            'name' => $name,
            'domain' => $domain,
            'host' => $host,
            'sms_sent' => $smsSent,
            'warning' => $warning,
        ]);

        return $smsSent;
    }

    /**
     * Send SMS to server phone fields.
     */
    private function sendServerSms(Server $server, string $message): bool
    {
        $phones = [
            $server->admin_phone ?? null,
            $server->customer_phone ?? null,
            $server->phone ?? null,
            $server->mobile ?? null,
            $server->alert_phone ?? null,
            $server->sms_phone ?? null,
        ];

        return $this->sendToRecipients($phones, $message);
    }

    /**
     * Get server alert emails.
     */
    private function serverAlertEmails(Server $server): array
    {
        $emails = [
            $server->email ?? null,
            $server->admin_email ?? null,
            $server->customer_email ?? null,
            $server->alert_email ?? null,
        ];

        $envEmails = explode(',', (string) env('MONITOR_ALERT_EMAILS', ''));

        foreach ($envEmails as $email) {
            if (trim($email)) {
                $emails[] = trim($email);
            }
        }

        return $this->cleanEmailList($emails);
    }

    /**
     * Send one SMS message to many recipients.
     */
    private function sendToRecipients($phones, string $message): bool
    {
        if (!is_array($phones)) {
            $phones = [$phones];
        }

        $phones = $this->cleanPhoneList($phones);

        if (empty($phones)) {
            Log::warning('No SMS recipients found.', [
                'message' => Str::limit($message, 120),
            ]);

            return false;
        }

        $sent = false;

        foreach ($phones as $phone) {
            if ($this->sendSmsSafe($phone, $message)) {
                $sent = true;
            }
        }

        return $sent;
    }

    /**
     * Safe SMS send wrapper.
     */
    private function sendSmsSafe(?string $phone, string $message): bool
    {
        $phone = trim((string) $phone);

        if (!$phone) {
            return false;
        }

        try {
            $message = $this->cleanSmsMessage($message);

            $result = (bool) $this->sms->send($phone, $message);

            Log::info('SMS send attempted.', [
                'phone' => $phone,
                'success' => $result,
                'message' => Str::limit($message, 120),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('SMS sending failed.', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Safe email sender.
     */
    private function sendEmailSafe($emails, string $subject, string $message): bool
    {
        $emails = $this->cleanEmailList($emails);

        if (empty($emails)) {
            Log::warning('No email recipients found.', [
                'subject' => $subject,
            ]);

            return false;
        }

        $sent = false;

        foreach ($emails as $email) {
            try {
                Mail::raw($message, function ($mail) use ($email, $subject) {
                    $mail->to($email)->subject($subject);
                });

                $sent = true;

                Log::info('Email alert sent.', [
                    'email' => $email,
                    'subject' => $subject,
                ]);
            } catch (\Throwable $e) {
                Log::error('Email alert sending failed.', [
                    'email' => $email,
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Clean SMS text.
     */
    private function cleanSmsMessage(string $message): string
    {
        $message = strip_tags($message);
        $message = preg_replace('/[ \t]+/', ' ', $message);
        $message = preg_replace('/\n{3,}/', "\n\n", $message);
        $message = trim($message);

        return Str::limit($message, 500, '');
    }

    /**
     * Clean phone list.
     */
    private function cleanPhoneList($phones): array
    {
        if (!is_array($phones)) {
            $phones = [$phones];
        }

        $envPhones = explode(',', (string) env('MONITOR_ALERT_PHONES', ''));

        foreach ($envPhones as $phone) {
            if (trim($phone)) {
                $phones[] = trim($phone);
            }
        }

        $clean = [];

        foreach ($phones as $phone) {
            $phone = trim((string) $phone);

            if (!$phone) {
                continue;
            }

            $phone = str_replace([' ', '-', '(', ')'], '', $phone);

            if (!in_array($phone, $clean, true)) {
                $clean[] = $phone;
            }
        }

        return $clean;
    }

    /**
     * Clean email list.
     */
    private function cleanEmailList($emails): array
    {
        if (!is_array($emails)) {
            $emails = [$emails];
        }

        $clean = [];

        foreach ($emails as $email) {
            $email = trim((string) $email);

            if (!$email) {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (!in_array($email, $clean, true)) {
                $clean[] = $email;
            }
        }

        return $clean;
    }

    /**
     * Convert array/text to short readable text.
     */
    private function arrayToText($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_unique($value)));
        }

        return trim((string) $value);
    }
}