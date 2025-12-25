<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeDashboardPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed|different:current_password',
            'new_password_confirmation' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            // حقل كلمة المرور الحالية
            'current_password.required' => 'كلمة المرور الحالية مطلوبة',
            'current_password.string'   => 'كلمة المرور الحالية يجب أن تكون نصاً',

            // حقل كلمة المرور الجديدة
            'new_password.required'     => 'كلمة المرور الجديدة مطلوبة',
            'new_password.string'       => 'كلمة المرور الجديدة يجب أن تكون نصاً',
            'new_password.min'          => 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل',
            'new_password.confirmed'    => 'كلمات المرور غير متطابقة',
            'new_password.different'    => 'كلمة المرور الجديدة يجب أن تكون مختلفة عن الحالية',

            // حقل تأكيد كلمة المرور الجديدة
            'new_password_confirmation.required' => 'تأكيد كلمة المرور الجديدة مطلوب',
            'new_password_confirmation.string'   => 'تأكيد كلمة المرور الجديدة يجب أن يكون نصاً',
            'new_password_confirmation.min'      => 'تأكيد كلمة المرور الجديدة يجب أن يكون 8 أحرف على الأقل',
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password'          => 'كلمة المرور الحالية',
            'new_password'              => 'كلمة المرور الجديدة',
            'new_password_confirmation' => 'تأكيد كلمة المرور الجديدة',
        ];
    }
}