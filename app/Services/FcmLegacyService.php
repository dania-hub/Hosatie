<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FcmLegacyService
{
    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        $serverKey = (string) config('services.fcm.server_key');

        if ($serverKey === '') {
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => 'Missing FCM_SERVER_KEY',
            ];
        }

        $payload = [
            'to' => $token,
            'priority' => 'high',
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $data,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://fcm.googleapis.com/fcm/send', $payload);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json(),
            'raw' => $response->body(),
        ];
    }
}
