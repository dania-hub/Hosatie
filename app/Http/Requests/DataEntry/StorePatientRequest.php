<?php

namespace App\Http\Requests\DataEntry;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'full_name'   => 'required|string|min:3|max:255',
            'national_id' => 'required|digits:12|unique:users,national_id',
            'birth_date'  => 'required|date|before:today',
            // هاتف: 002189 أو 09 أو +2189 ثم رقم من 1 إلى 6 ثم 7 أرقام
            'phone'       => [
                 'required',
                'regex:/^(002189|09|\+2189)[1-6]\d{7}$/',
                'unique:users,phone',
            ],
            'email'       => 'nullable|email|unique:users,email',
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
            
            // رسائل الرقم الوطني
            'national_id.required' => 'الرقم الوطني مطلوب',
            'national_id.digits'   => 'الرقم الوطني يجب أن يكون 12 رقماً',
            'national_id.unique'   => 'الرقم الوطني مسجل مسبقاً',
            
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
