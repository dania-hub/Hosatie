<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'full_name' => 'required|string|max:255',
            'email'     => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone'     => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // حقل الاسم الكامل
            'full_name.required' => 'الاسم الرباعي مطلوب',
            'full_name.string'   => 'الاسم الرباعي يجب أن يكون نصاً',
            'full_name.max'      => 'الاسم الرباعي يجب ألا يزيد عن 255 حرف',

            // حقل البريد الإلكتروني
            'email.required'     => 'البريد الإلكتروني مطلوب',
            'email.email'        => 'البريد الإلكتروني غير صالح',
            'email.max'          => 'البريد الإلكتروني يجب ألا يزيد عن 255 حرف',
            'email.unique'       => 'هذا البريد الإلكتروني مسجل بالفعل',

            // حقل رقم الهاتف
            'phone.required'     => 'رقم الهاتف مطلوب',
            'phone.string'       => 'رقم الهاتف يجب أن يكون نصاً',
            'phone.max'          => 'رقم الهاتف يجب ألا يزيد عن 20 رقم',
            'phone.unique'       => 'رقم الهاتف هذا مسجل بالفعل',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'الاسم الرباعي',
            'email'     => 'البريد الإلكتروني',
            'phone'     => 'رقم الهاتف',
        ];
    }
}