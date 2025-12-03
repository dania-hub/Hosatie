<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResalaSmsService
{
    protected $baseUrl;
    protected $token;
    protected $appName;

    public function __construct()
    {
        $this->baseUrl = env('RESALA_BASE_URL');
        $this->token = env('RESALA_TOKEN');
        $this->appName = env('RESALA_APP_NAME', 'Hosatie');
    }

    /**
     * Send OTP (PIN)
     * 
     * @param string $phone Phone number (e.g., 0912939198)
     * @return array
     */
    public function sendOtp($phone)
    {
        try {
            // Prepare URL
            $url = "{$this->baseUrl}/pins";

            // Send Request with SSL Verification Disabled (withoutVerifying)
            $response = Http::withoutVerifying() // Fix for local connection issues
                ->withToken($this->token)
                ->acceptJson()
                ->post($url, [
                    'phone' => $phone,       
                    'service_name' => $this->appName,
                    'len' => 6,              
                    // 'test' => 'test' // Uncomment to avoid real sending
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Resala SMS Success: ", $data);
                return ['success' => true, 'data' => $data];
            }

            // Log specific API error
            Log::error("Resala SMS API Error: " . $response->body());
            return ['success' => false, 'error' => $response->json()];

        } catch (\Exception $e) {
            // Log connection/system error
            Log::error("Resala Connection Exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        }
    }
}
