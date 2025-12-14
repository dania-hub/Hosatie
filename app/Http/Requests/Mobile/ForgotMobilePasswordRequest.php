<?php
namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ForgotMobilePasswordRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; 
    }
    
    public function rules(): array 
    {
        return [
            'phone' => 'required|string|regex:/^09[1-4]\d{7}$/',
        ];
    }
    
    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.regex' => 'رقم الهاتف غير صالح. يجب أن يبدأ بـ 091 أو 092 أو 093 أو 094 ويتكون من 10 أرقام.',
            'phone.exists' => 'رقم الهاتف غير مسجل في النظام.',
        ];
    }
}