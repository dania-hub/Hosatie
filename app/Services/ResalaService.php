<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash; // <-- الخطوة 1: التأكد من وجود هذا الاستيراد

class ResalaService
{
    protected $baseUrl = 'https://dev.resala.ly/api/v1';
    protected $token;

    public function __construct( )
    {
        $this->token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczovL2Rldi5yZXNhbGEubHkiLCJzdWIiOiJjODAyODFjNS1lM2MxLTRlZDItYWVlMC04MDQ2NjQ4ZmVjNjAiLCJpYXQiOjE3NjU2NDgxODksImp0aSI6IjUyOTllM2MyLTkwMTQtNDM5Yy1hNjFhLTUwN2FhZjRkYTFmNCJ9.RjGbA9rF6XgTwskA9wcSSUAc6T559u93od6-9qaZT_I';
    }

    // =================================================================
    // === لا يوجد أي تغيير في هذه الدالة (sendOtp) ===
    // =================================================================
    public function sendOtp($phone, $appName = 'حصتي')
    {
        try {
            Log::info('=== ResalaService::sendOtp ===');
            Log::info('Phone received: ' . $phone);
            Log::info('App name: ' . $appName);
            
            if (empty($phone)) {
                Log::error('Missing phone parameter');
                return ['success' => false];
            }
            
            $formattedPhone = $this->formatPhoneForResala($phone);
            Log::info('Phone formatted for Resala API: ' . $formattedPhone);
            
            Log::info('Sending to Resala API...');
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/pins?service_name=' . urlencode($appName) . '&len=6', [
                'phone' => $formattedPhone,
            ]);
            
            $responseData = $response->json();
            
            Log::info('Resala API Response Status: ' . $response->status());
            Log::info('Resala API Response Body:', $responseData);
            
            if ($response->successful() && isset($responseData['pin'])) {
                $realOtp = $responseData['pin'];
                Log::info('✅ Resala generated OTP: ' . $realOtp);
                
                $savedToDb = $this->saveOtpToDatabase($phone, $realOtp);
                
                if ($savedToDb) {
                    Log::info('✅ OTP saved to database successfully');
                    
                    $savedToCache = $this->saveOtpToLaravelCache($phone, $realOtp);
                    
                    if ($savedToCache) {
                        Log::info('✅ OTP saved to Laravel Cache successfully');
                    }
                    
                    return ['success' => true, 'otp' => $realOtp];
                } else {
                    Log::error('❌ Failed to save OTP to database');
                    return ['success' => false];
                }
            } else {
                Log::error('❌ Resala API call failed');
                Log::error('Error response:', $responseData);
                return ['success' => false];
            }
            
        } catch (\Exception $e) {
            Log::error('ResalaService Exception: ' . $e->getMessage());
            Log::error('Exception trace: ' . $e->getTraceAsString());
            return ['success' => false];
        }
    }

    // =================================================================
    // === لا يوجد أي تغيير في هذه الدالة (formatPhoneForResala) ===
    // =================================================================
    protected function formatPhoneForResala($phone)
    {
        $cleanedPhone = preg_replace('/[^0-9]/', '', $phone);
        Log::info('Cleaned phone: ' . $cleanedPhone);
        if (strlen($cleanedPhone) === 10 && strpos($cleanedPhone, '09') === 0) {
            $formatted = '218' . substr($cleanedPhone, 1);
            Log::info('Formatted phone for Resala: ' . $formatted);
            return $formatted;
        }
        if (strlen($cleanedPhone) === 12 && strpos($cleanedPhone, '218') === 0) {
            Log::info('Phone already in Resala format: ' . $cleanedPhone);
            return $cleanedPhone;
        }
        Log::warning('Unexpected phone format, returning as-is: ' . $cleanedPhone);
        return $cleanedPhone;
    }

    // =================================================================
    // === التعديل الأول هنا: تشفير الـ OTP قبل الحفظ ===
    // =================================================================
    private function saveOtpToDatabase($phone, $otp)
    {
        try {
            Log::info('=== Saving OTP to database ===');
            Log::info('Phone for DB: ' . $phone);
            Log::info('OTP for DB: ' . $otp);
            
            $dbPhone = $this->formatPhoneForDatabase($phone);
            Log::info('Phone formatted for DB: ' . $dbPhone);
            
            DB::table('otp_verifications')
                ->where(function($query) use ($dbPhone, $phone) {
                    $query->where('phone', $dbPhone)
                          ->orWhere('phone', $phone)
                          ->orWhere('phone', '218' . substr(preg_replace('/[^0-9]/', '', $phone), 1))
                          ->orWhere('phone', ltrim(preg_replace('/[^0-9]/', '', $phone), '0'));
                })
                ->delete();
            
            Log::info('Deleted old OTP records for phone: ' . $dbPhone);
            
            // الخطوة 2: تشفير الـ OTP
            $hashedOtp = Hash::make((string) $otp);

            DB::table('otp_verifications')->insert([
                'phone' => $dbPhone,
                'otp' => $hashedOtp, // <-- حفظ القيمة المشفرة
                'created_at' => now(),
                'expires_at' => now()->addMinutes(15),
                'updated_at' => now(),
            ]);
            
            Log::info('✅ New HASHED OTP saved to database:');
            Log::info('   Phone: ' . $dbPhone);
            Log::info('   Hashed OTP: ' . $hashedOtp);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to save OTP to database: ' . $e->getMessage());
            return false;
        }
    }

    // =================================================================
    // === لا يوجد أي تغيير في هذه الدوال (formatPhoneForDatabase, saveOtpToLaravelCache, generateAllPhoneFormats) ===
    // =================================================================
    private function formatPhoneForDatabase($phone)
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) === 12 && strpos($cleaned, '218') === 0) {
            return '0' . substr($cleaned, 3);
        }
        if (strlen($cleaned) === 10 && strpos($cleaned, '09') === 0) {
            return $cleaned;
        }
        if (strlen($cleaned) === 9) {
            return '0' . $cleaned;
        }
        return $cleaned;
    }

    private function saveOtpToLaravelCache($phone, $otp)
    {
        try {
            Log::info('=== Saving OTP to Laravel Cache ===');
            $formats = $this->generateAllPhoneFormats($phone);
            Log::info('All cache formats for phone ' . $phone . ':', $formats);
            foreach ($formats as $format) {
                $key = 'otp_mobile_' . $format;
                Cache::put($key, $otp, 900);
                $cachedValue = Cache::get($key);
                Log::info('Cache key: ' . $key . ' -> Value: ' . ($cachedValue ?: 'NOT FOUND'));
            }
            Log::info('✅ OTP saved to Laravel Cache in ' . count($formats) . ' formats');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save OTP to Laravel Cache: ' . $e->getMessage());
            return false;
        }
    }

    private function generateAllPhoneFormats($phone)
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $formats = [];
        $formats[] = $cleaned;
        if (strlen($cleaned) === 10 && strpos($cleaned, '09') === 0) {
            $formats[] = ltrim($cleaned, '0');
            $formats[] = '218' . substr($cleaned, 1);
            $formats[] = '+218' . substr($cleaned, 1);
            $formats[] = '0' . ltrim($cleaned, '218');
        }
        if (strlen($cleaned) === 12 && strpos($cleaned, '218') === 0) {
            $formats[] = '0' . substr($cleaned, 3);
            $formats[] = substr($cleaned, 3);
        }
        return array_unique($formats);
    }

    // =================================================================
    // === التعديل الثاني هنا: التحقق من الـ OTP المشفر ===
    // =================================================================
    public function verifyOtpFromDatabase($phone, $otp)
    {
        try {
            Log::info('=== ResalaService::verifyOtpFromDatabase ===');
            Log::info('Verifying OTP for phone: ' . $phone);
            Log::info('OTP to verify: ' . $otp);
            
            $cleanedPhone = $this->formatPhoneForDatabase($phone);
            Log::info('Cleaned phone for verification: ' . $cleanedPhone);
            
            $otpRecord = DB::table('otp_verifications')
                ->where('phone', $cleanedPhone)
                ->where('expires_at', '>', now())
                ->first();
            
            if (!$otpRecord) {
                $formats = $this->generateAllPhoneFormats($phone);
                foreach ($formats as $format) {
                    $dbFormat = $this->formatPhoneForDatabase($format);
                    $otpRecord = DB::table('otp_verifications')->where('phone', $dbFormat)->where('expires_at', '>', now())->first();
                    if ($otpRecord) {
                        Log::info('Found OTP record with alternative format: ' . $dbFormat);
                        break;
                    }
                }
            }
            
            if ($otpRecord) {
                Log::info('Found OTP record:');
                Log::info('  Hashed OTP in DB: ' . $otpRecord->otp);
                
                // الخطوة 3: التحقق من الـ OTP المشفر
                if (Hash::check((string) $otp, $otpRecord->otp)) {
                    Log::info('✅ OTP verification SUCCESSFUL (Hash matched)');
                    return true;
                } else {
                    Log::warning('OTP mismatch: Hash check failed.');
                }
            } else {
                Log::warning('No valid OTP record found for phone: ' . $cleanedPhone);
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('OTP verification error: ' . $e->getMessage());
            return false;
        }
    }

    // ... بقية الدوال تبقى كما هي تمامًا ...
    public function deleteOtpFromDatabase($phone)
    {
        try {
            Log::info('Deleting OTP for phone: ' . $phone);
            $formats = $this->generateAllPhoneFormats($phone);
            foreach ($formats as $format) {
                $dbFormat = $this->formatPhoneForDatabase($format);
                $deleted = DB::table('otp_verifications')->where('phone', $dbFormat)->delete();
                if ($deleted) {
                    Log::info('Deleted OTP for phone format: ' . $dbFormat);
                }
            }
            foreach ($formats as $format) {
                $key = 'otp_mobile_' . $format;
                Cache::forget($key);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Delete OTP error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendCustomMessage($phone, $message)
    {
        try {
            Log::info('Sending custom message to: ' . $phone);
            $formattedPhone = $this->formatPhoneForResala($phone);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/message', [
                'phone' => $formattedPhone,
                'content' => $message,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Resala Custom Message Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getMessageHistory($phone = null)
    {
        $url = $this->baseUrl . '/sent-view?filters=source:pin';
        if ($phone) {
            $formattedPhone = $this->formatPhoneForResala($phone);
            $url .= '&q=' . $formattedPhone;
        }
        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->token])->get($url);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Get message history error: ' . $e->getMessage());
            return null;
        }
    }

    public function getLastSentOtp($phone)
    {
        try {
            $cleanedPhone = $this->formatPhoneForDatabase($phone);
            $otpRecord = DB::table('otp_verifications')->where('phone', $cleanedPhone)->orderBy('created_at', 'desc')->first();
            if ($otpRecord) {
                return [
                    'phone' => $otpRecord->phone,
                    'otp' => 'ENCRYPTED', // <-- تم التعديل هنا ليعكس الواقع
                    'created_at' => $otpRecord->created_at,
                    'expires_at' => $otpRecord->expires_at,
                    'is_valid' => $otpRecord->expires_at > now()
                ];
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Get last OTP error: ' . $e->getMessage());
            return null;
        }
    }
}
