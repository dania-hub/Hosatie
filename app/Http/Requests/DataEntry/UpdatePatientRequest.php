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
            'birth_date' => 'required|date|before:today',
            'phone'      => [
                'required',
                'regex:/^(002189|09|\+2189)[1-6]\d{7}$/',
                Rule::unique('users', 'phone')->ignore($id),
            ],
            'email'      => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
        ];
    }
}
