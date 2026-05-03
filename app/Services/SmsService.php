<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(?string $phone, string $message): bool
    {
        if (!$phone || !config('services.sms.token')) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.sms.token'),
                'Accept' => 'application/json',
            ])->post(config('services.sms.url'), [
                'recipient' => $phone,
                'sender_id' => config('services.sms.sender_id'),
                'message' => $message,
            ]);

            if (!$response->successful()) {
                Log::error('Paid2Marketing SMS failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('Paid2Marketing SMS exception: ' . $e->getMessage());
            return false;
        }
    }
}