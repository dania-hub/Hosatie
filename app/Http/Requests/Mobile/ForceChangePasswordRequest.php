<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ForceChangePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled via middleware (auth:sanctum)
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'new_password' => 'required|string|min:8|confirmed',
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'new_password.required'  => 'يرجى إدخال كلمة المرور الجديدة.',
            'new_password.min'       => 'يجب أن تكون كلمة المرور الجديدة 8 أحرف على الأقل.',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
