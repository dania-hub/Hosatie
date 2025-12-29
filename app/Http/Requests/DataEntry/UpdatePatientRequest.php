<?php

namespace App\Http\Requests\DataEntry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id'); 

        return [
            'full_name'  => 'required|string|max:255|min:3',
            'birth_date' => 'required|date|before:today',
            'phone'      => [
                'required',
                'regex:/^(002189|09|\+2189)[1-6]\d{7}$/',
                Rule::unique('users', 'phone')->ignore($id),
            ],
            'email'      => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
        ];
    }

    public function messages()
    {
        return [
            // رسائل الاسم الرباعي
            'full_name.required' => 'الاسم الرباعي مطلوب',
            'full_name.string'   => 'الاسم الرباعي يجب أن يكون نصاً',
            'full_name.min'      => 'الاسم الرباعي يجب أن يكون على الأقل 3 أحرف',
            'full_name.max'      => 'الاسم الرباعي يجب ألا يتجاوز 255 حرفاً',
            
            // رسائل تاريخ الميلاد
            'birth_date.required' => 'تاريخ الميلاد مطلوب',
            'birth_date.date'     => 'تاريخ الميلاد غير صحيح',
            'birth_date.before'   => 'تاريخ الميلاد يجب أن يكون في الماضي',
            
            // رسائل الهاتف
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.regex'    => 'رقم الهاتف غير صحيح (يجب أن يبدأ بـ 09 أو 002189 أو +2189)',
            'phone.unique'   => 'رقم الهاتف مسجل مسبقاً',
            
            // رسائل البريد الإلكتروني
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email'    => 'البريد الإلكتروني غير صحيح',
            'email.unique'   => 'البريد الإلكتروني مسجل مسبقاً',
        ];
    }
}