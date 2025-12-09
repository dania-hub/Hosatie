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
            'email'       => 'required|email|unique:users,email',
        ];
    }

    public function messages()
    {
        return [
            'national_id.digits' => 'الرقم الوطني يجب أن يكون 12 خانة.',
            'phone.regex'        => 'رقم الهاتف غير صحيح (يجب أن يبدأ بـ 09 أو 002189 أو +2189).',
            'email.unique'       => 'البريد الإلكتروني مسجل مسبقاً.',
            'birth_date.before'  => 'تاريخ الميلاد يجب أن يكون في الماضي.',
        ];
    }
}
