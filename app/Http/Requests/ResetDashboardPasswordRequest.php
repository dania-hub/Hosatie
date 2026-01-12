<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetDashboardPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'email'    => 'required|email|exists:users,email',
            'otp'      => 'required|string|size:4',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages()
    {
        return [
            'email.exists' => 'البريد الإلكتروني المدخل غير مسجل في النظام.',
            'email.required' => 'حقل البريد الإلكتروني مطلوب.',
            'email.email' => 'يرجى إدخال عنوان بريد إلكتروني صالح.',
            'otp.required' => 'حقل رمز التحقق مطلوب.',
            'otp.size' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
            'password.required' => 'حقل كلمة المرور مطلوب.',
            'password.min' => 'كلمة المرور يجب أن لا تقل عن 8 خانات.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
