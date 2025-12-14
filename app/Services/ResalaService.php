<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ResalaService
{
    protected $baseUrl = 'https://dev.resala.ly/api/v1';
    protected $token;

    public function __construct()
    {
        // التوكن الخاص بك من Resala
        $this->token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczovL2Rldi5yZXNhbGEubHkiLCJzdWIiOiJjODAyODFjNS1lM2MxLTRlZDItYWVlMC04MDQ2NjQ4ZmVjNjAiLCJpYXQiOjE3NjU2NDgxODksImp0aSI6IjUyOTllM2MyLTkwMTQtNDM5Yy1hNjFhLTUwN2FhZjRkYTFmNCJ9.RjGbA9rF6XgTwskA9wcSSUAc6T559u93od6-9qaZT_I';
    }

public function sendOtp($phone, $appName = 'حصتي')
{
    try {
        Log::info('=== ResalaService::sendOtp ===');
        Log::info('Phone received: ' . $phone);
        Log::info('App name: ' . $appName);
        
        // 1. التحقق من صحة البيانات
        if (empty($phone)) {
            Log::error('Missing phone parameter');
            return ['success' => false];
        }
        
        // 2. تنسيق الرقم للإرسال
        $formattedPhone = $this->formatPhoneForResala($phone);
        Log::info('Phone formatted for Resala API: ' . $formattedPhone);
        
        // 3. إرسال عبر Resala API (بدون 'code'، دع Resala يولد)
        Log::info('Sending to Resala API...');
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/pins?service_name=' . urlencode($appName) . '&len=6', [  // أضف &test=test إذا كنت في وضع اختبار
            'phone' => $formattedPhone,
        ]);
        
        $responseData = $response->json();
        
        Log::info('Resala API Response Status: ' . $response->status());
        Log::info('Resala API Response Body:', $responseData);
        
        // 4. التحقق من نجاح الإرسال
        if ($response->successful() && isset($responseData['pin'])) {
            $realOtp = $responseData['pin'];
            Log::info('✅ Resala generated OTP: ' . $realOtp);
            
            // 5. حفظ OTP في قاعدة البيانات
            $savedToDb = $this->saveOtpToDatabase($phone, $realOtp);
            
            if ($savedToDb) {
                Log::info('✅ OTP saved to database successfully');
                
                // 6. حفظ OTP في Laravel Cache
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
    /**
     * تنسيق الرقم للـ Resala API
     */
    protected function formatPhoneForResala($phone)
    {
        // تنظيف الرقم من أي رموز غير رقمية
        $cleanedPhone = preg_replace('/[^0-9]/', '', $phone);
        
        Log::info('Cleaned phone: ' . $cleanedPhone);
        
        // إذا كان الرقم يبدأ بـ 09 ويتكون من 10 أرقام
        if (strlen($cleanedPhone) === 10 && strpos($cleanedPhone, '09') === 0) {
            $formatted = '218' . substr($cleanedPhone, 1);
            Log::info('Formatted phone for Resala: ' . $formatted);
            return $formatted;
        }
        
        // إذا كان الرقم يبدأ بـ 218 بالفعل
        if (strlen($cleanedPhone) === 12 && strpos($cleanedPhone, '218') === 0) {
            Log::info('Phone already in Resala format: ' . $cleanedPhone);
            return $cleanedPhone;
        }
        
        // إذا كان الرقم مختلفاً، ارجعه كما هو
        Log::warning('Unexpected phone format, returning as-is: ' . $cleanedPhone);
        return $cleanedPhone;
    }

    /**
     * حفظ OTP في جدول قاعدة البيانات (otp_verifications)
     */
    private function saveOtpToDatabase($phone, $otp)
    {
        try {
            Log::info('=== Saving OTP to database ===');
            Log::info('Phone for DB: ' . $phone);
            Log::info('OTP for DB: ' . $otp);
            
            // تنظيف الرقم للتخزين في قاعدة البيانات
            $dbPhone = $this->formatPhoneForDatabase($phone);
            Log::info('Phone formatted for DB: ' . $dbPhone);
            
            // حذف أي OTP قديم لنفس الرقم (جميع التنسيقات)
            DB::table('otp_verifications')
                ->where(function($query) use ($dbPhone, $phone) {
                    $query->where('phone', $dbPhone)
                          ->orWhere('phone', $phone)
                          ->orWhere('phone', '218' . substr(preg_replace('/[^0-9]/', '', $phone), 1))
                          ->orWhere('phone', ltrim(preg_replace('/[^0-9]/', '', $phone), '0'));
                })
                ->delete();
            
            Log::info('Deleted old OTP records for phone: ' . $dbPhone);
            
            // حفظ OTP الجديد
            DB::table('otp_verifications')->insert([
                'phone' => $dbPhone,
                'otp' => (string) $otp,
                'created_at' => now(),
                'expires_at' => now()->addMinutes(15),
                'updated_at' => now(),
            ]);
            
            Log::info('✅ New OTP saved to database:');
            Log::info('   Table: otp_verifications');
            Log::info('   Phone: ' . $dbPhone);
            Log::info('   OTP: ' . $otp);
            Log::info('   Expires at: ' . now()->addMinutes(15)->format('Y-m-d H:i:s'));
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to save OTP to database: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * تنسيق الرقم للتخزين في قاعدة البيانات
     */
    private function formatPhoneForDatabase($phone)
    {
        // تنظيف الرقم
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // إذا كان يبدأ بـ 218، حوله إلى 09
        if (strlen($cleaned) === 12 && strpos($cleaned, '218') === 0) {
            return '0' . substr($cleaned, 3);
        }
        
        // إذا كان يبدأ بـ 09 ويتكون من 10 أرقام، ارجعه كما هو
        if (strlen($cleaned) === 10 && strpos($cleaned, '09') === 0) {
            return $cleaned;
        }
        
        // إذا كان عدد الأرقام 9، أضف 0 في البداية
        if (strlen($cleaned) === 9) {
            return '0' . $cleaned;
        }
        
        // في أي حالة أخرى، ارجع الرقم كما هو
        return $cleaned;
    }

    /**
     * حفظ OTP في Laravel Cache (بجميع التنسيقات)
     */
    private function saveOtpToLaravelCache($phone, $otp)
    {
        try {
            Log::info('=== Saving OTP to Laravel Cache ===');
            
            // إنشاء جميع التنسيقات الممكنة للرقم
            $formats = $this->generateAllPhoneFormats($phone);
            
            Log::info('All cache formats for phone ' . $phone . ':', $formats);
            
            // حفظ OTP في جميع التنسيقات
            foreach ($formats as $format) {
                $key = 'otp_mobile_' . $format;
                Cache::put($key, $otp, 900); // 15 دقيقة
                
                // التحقق من الحفظ
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

    /**
     * توليد جميع التنسيقات الممكنة للرقم
     */
    private function generateAllPhoneFormats($phone)
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        $formats = [];
        
        // 1. الرقم كما هو
        $formats[] = $cleaned;
        
        // 2. إذا كان يبدأ بـ 09 (10 أرقام)
        if (strlen($cleaned) === 10 && strpos($cleaned, '09') === 0) {
            // بدون الصفر الأول
            $formats[] = ltrim($cleaned, '0');
            
            // بتنسيق 218
            $formats[] = '218' . substr($cleaned, 1);
            
            // بتنسيق +218
            $formats[] = '+218' . substr($cleaned, 1);
            
            // مع صفرين في البداية
            $formats[] = '0' . ltrim($cleaned, '218');
        }
        
        // 3. إذا كان يبدأ بـ 218 (12 رقم)
        if (strlen($cleaned) === 12 && strpos($cleaned, '218') === 0) {
            // بتنسيق 09
            $formats[] = '0' . substr($cleaned, 3);
            
            // بدون 218
            $formats[] = substr($cleaned, 3);
        }
        
        // إزالة التكرارات
        $formats = array_unique($formats);
        
        return $formats;
    }

    /**
     * التحقق من OTP في قاعدة البيانات
     * (يمكن استخدامها في ForgotPasswordController)
     */
    public function verifyOtpFromDatabase($phone, $otp)
    {
        try {
            Log::info('=== ResalaService::verifyOtpFromDatabase ===');
            Log::info('Verifying OTP for phone: ' . $phone);
            Log::info('OTP to verify: ' . $otp);
            
            // تنظيف الرقم
            $cleanedPhone = $this->formatPhoneForDatabase($phone);
            Log::info('Cleaned phone for verification: ' . $cleanedPhone);
            
            // البحث في قاعدة البيانات
            $otpRecord = DB::table('otp_verifications')
                ->where('phone', $cleanedPhone)
                ->where('expires_at', '>', now())
                ->first();
            
            // إذا لم نجده، جرب البحث بتنسيقات أخرى
            if (!$otpRecord) {
                $formats = $this->generateAllPhoneFormats($phone);
                
                foreach ($formats as $format) {
                    $dbFormat = $this->formatPhoneForDatabase($format);
                    $otpRecord = DB::table('otp_verifications')
                        ->where('phone', $dbFormat)
                        ->where('expires_at', '>', now())
                        ->first();
                    
                    if ($otpRecord) {
                        Log::info('Found OTP record with alternative format: ' . $dbFormat);
                        break;
                    }
                }
            }
            
            if ($otpRecord) {
                Log::info('Found OTP record:');
                Log::info('  Phone in DB: ' . $otpRecord->phone);
                Log::info('  OTP in DB: ' . $otpRecord->otp);
                Log::info('  Expires at: ' . $otpRecord->expires_at);
                Log::info('  Current time: ' . now()->format('Y-m-d H:i:s'));
                
                if ($otpRecord->otp == $otp) {
                    Log::info('✅ OTP verification SUCCESSFUL');
                    return true;
                } else {
                    Log::warning('OTP mismatch: DB=' . $otpRecord->otp . ', Submitted=' . $otp);
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

    /**
     * حذف OTP من قاعدة البيانات بعد الاستخدام
     */
    public function deleteOtpFromDatabase($phone)
    {
        try {
            Log::info('Deleting OTP for phone: ' . $phone);
            
            $formats = $this->generateAllPhoneFormats($phone);
            
            foreach ($formats as $format) {
                $dbFormat = $this->formatPhoneForDatabase($format);
                $deleted = DB::table('otp_verifications')
                    ->where('phone', $dbFormat)
                    ->delete();
                
                if ($deleted) {
                    Log::info('Deleted OTP for phone format: ' . $dbFormat);
                }
            }
            
            // حذف من Cache أيضاً
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

    /**
     * إرسال رسالة نصية مخصصة
     */
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

    /**
     * استرجاع سجل الرسائل من Resala
     */
    public function getMessageHistory($phone = null)
    {
        $url = $this->baseUrl . '/sent-view?filters=source:pin';
        
        if ($phone) {
            $formattedPhone = $this->formatPhoneForResala($phone);
            $url .= '&q=' . $formattedPhone;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->get($url);

            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Get message history error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * دالة مساعدة: الحصول على آخر OTP مرسل لرقم معين
     */
    public function getLastSentOtp($phone)
    {
        try {
            $cleanedPhone = $this->formatPhoneForDatabase($phone);
            
            $otpRecord = DB::table('otp_verifications')
                ->where('phone', $cleanedPhone)
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($otpRecord) {
                return [
                    'phone' => $otpRecord->phone,
                    'otp' => $otpRecord->otp,
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