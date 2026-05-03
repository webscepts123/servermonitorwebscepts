<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SmsService;

class SmsController extends Controller
{
    protected $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
    }

    /**
     * Send custom SMS
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:500',
        ]);

        $sent = $this->sms->send($data['phone'], $data['message']);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'SMS sent successfully' : 'SMS failed',
        ]);
    }

    /**
     * Send DOWN alert SMS
     */
    public function sendDownAlert($serverId)
    {
        $server = \App\Models\Server::findOrFail($serverId);

        $message = "🚨 DOWN ALERT: {$server->name} is OFFLINE. Host: {$server->host}";

        $this->sms->send($server->admin_phone, $message);
        $this->sms->send($server->customer_phone, $message);

        return back()->with('success', 'Down alert SMS sent');
    }

    /**
     * Send RECOVERY alert SMS
     */
    public function sendRecoveryAlert($serverId)
    {
        $server = \App\Models\Server::findOrFail($serverId);

        $message = "✅ RECOVERED: {$server->name} is back ONLINE.";

        $this->sms->send($server->admin_phone, $message);
        $this->sms->send($server->customer_phone, $message);

        return back()->with('success', 'Recovery SMS sent');
    }
}