<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class RejectShipmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages()
    {
        return [
            'reason.required' => 'سبب الرفض مطلوب',
            'reason.string' => 'سبب الرفض يجب أن يكون نص',
            'reason.max' => 'سبب الرفض يجب ألا يتجاوز 500 حرف',
        ];
    }
}
