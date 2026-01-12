<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotDashboardPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['email' => 'required|email|exists:users,email'];
    }

    public function messages()
    {
        return [
            'email.exists' => 'البريد الإلكتروني المدخل غير مسجل في النظام.',
            'email.required' => 'حقل البريد الإلكتروني مطلوب.',
            'email.email' => 'يرجى إدخال عنوان بريد إلكتروني صالح.',
        ];
    }
}
