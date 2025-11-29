<?php

namespace App\Http\Requests\DataEntry;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Permissions handled in Controller/Middleware
    }

    public function rules()
    {
        return [
             'national_id' => 'required|digits:12|unique:users,national_id', // <--- unique is key
        'phone'       => 'required|unique:users,phone',
        'email'       => 'required|email|unique:users,email',
            'full_name'   => 'required|string|min:3|max:255',
            'birth_date'  => 'required|date|before:today',
            // Regex matches 09, 002189, +2189 followed by correct digits
            'phone'       => ['required', 'regex:/^(002189|09|\+2189)[1-6]\d{7}$/', 'unique:users,phone'],
            'email'       => 'required|email|unique:users,email',
        ];
    }

    public function messages()
    {
        return [
            'national_id.digits' => 'الرقم الوطني يجب أن يكون 12 خانة.',
            'phone.regex'        => 'رقم الهاتف غير صحيح (يجب أن يبدأ بـ 09..).',
            'email.unique'       => 'البريد الإلكتروني مسجل مسبقاً.',
        ];
    }
}
