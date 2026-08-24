<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZaloOaService
{
    public function sendText(string $userId, string $text): bool
    {
        $token = config('services.zalo.oa_token');

        if (! $token) {
            Log::warning('Zalo OA token not configured, skipping send.');

            return false;
        }

        try {
            $response = Http::withHeaders(['access_token' => $token])
                ->timeout(10)
                ->post('https://openapi.zalo.me/v3.0/oa/message/cs', [
                    'recipient' => ['user_id' => $userId],
                    'message' => ['text' => $text],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Zalo OA send error: '.$e->getMessage());

            return false;
        }
    }
}
