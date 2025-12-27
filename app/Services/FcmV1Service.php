<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmV1Service
{
    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        Log::info('🚨 === FcmV1Service::sendToToken START ===', [
            'token' => substr($token, 0, 20) . '...',
            'title' => $title,
            'data_count' => count($data),
            'data_keys' => array_keys($data),
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $projectId = (string) config('services.fcm.project_id');
        $serviceAccountJsonPath = (string) config('services.fcm.service_account_json');

        if ($projectId === '' || $serviceAccountJsonPath === '') {
            Log::error('❌ FCM config missing');
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => 'Missing FCM_PROJECT_ID or FCM_SERVICE_ACCOUNT_JSON',
            ];
        }

        if (!is_file($serviceAccountJsonPath)) {
            Log::error('❌ Service account file not found', ['path' => $serviceAccountJsonPath]);
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => 'Service account json file not found',
            ];
        }

        $serviceAccount = json_decode((string) file_get_contents($serviceAccountJsonPath), true);

        if (!is_array($serviceAccount)) {
            Log::error('❌ Invalid service account JSON');
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => 'Invalid service account json',
            ];
        }

        $accessToken = $this->getAccessToken($serviceAccount);

        if ($accessToken === null) {
            Log::error('❌ Failed to get access token');
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'error' => 'Failed to obtain access token',
            ];
        }

        // ✅ FIX: استخدم متغيراً جديداً بدلاً من إعادة تعريف $data
        $stringifiedData = $this->stringifyData($data);
        
        Log::info('🚨 Stringified data', [
            'original_data' => $data,
            'stringified_data' => $stringifiedData
        ]);

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringifiedData, // ✅ استخدم المتغير الجديد
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'hossati_channel',
                    ],
                ],
            ],
        ];

        // ✅ FIX: تحقق من البيانات الأصلية، ليس البيانات المعالجة
        if (!array_key_exists('click_action', $data)) {
            $payload['message']['data']['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
            Log::info('🚨 Added click_action to data');
        }

        Log::info('🚨 Final payload', [
            'payload_keys' => array_keys($payload['message']),
            'has_notification' => isset($payload['message']['notification']),
            'has_data' => isset($payload['message']['data']),
            'data_count' => count($payload['message']['data'])
        ]);

        Log::info('🚨 Sending to FCM API...');
        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->post('https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send', $payload);

        $result = [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json(),
            'raw' => $response->body(),
        ];
        
        Log::info('🚨 === FcmV1Service::sendToToken END ===', [
            'ok' => $result['ok'],
            'status' => $result['status'],
            'response_keys' => $result['ok'] ? array_keys($result['body'] ?? []) : []
        ]);
        
        return $result;
    }

    private function getAccessToken(array $serviceAccount): ?string
    {
        return Cache::remember('fcm_v1_access_token', 3300, function () use ($serviceAccount) {
            Log::info('🚨 Getting FCM access token from cache or generating new');
            
            $clientEmail = $serviceAccount['client_email'] ?? null;
            $privateKey = $serviceAccount['private_key'] ?? null;
            $tokenUri = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            if (!is_string($clientEmail) || !is_string($privateKey)) {
                Log::error('❌ Invalid service account credentials');
                return null;
            }

            $now = time();

            $jwtHeader = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ]));

            $jwtClaims = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $jwtUnsigned = $jwtHeader . '.' . $jwtClaims;

            $signature = '';
            $signed = openssl_sign($jwtUnsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            if ($signed !== true) {
                Log::error('❌ Failed to sign JWT');
                return null;
            }

            $jwt = $jwtUnsigned . '.' . $this->base64UrlEncode($signature);

            Log::info('🚨 Requesting access token from Google OAuth');
            $tokenResponse = Http::asForm()
                ->timeout(60)
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (!$tokenResponse->successful()) {
                Log::error('❌ Token request failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body()
                ]);
                return null;
            }

            $json = $tokenResponse->json();

            if (!is_array($json) || !isset($json['access_token']) || !is_string($json['access_token'])) {
                Log::error('❌ Invalid token response', ['json' => $json]);
                return null;
            }

            Log::info('✅ Access token obtained successfully');
            return $json['access_token'];
        });
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function stringifyData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[(string) $key] = is_string($value) ? $value : json_encode($value);
        }

        return $result;
    }
}