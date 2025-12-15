<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplyRequestRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'hospital_id' => 'required|exists:hospital,id',
            'items' => 'required|array|min:1',
            'items.*.drug_id' => 'required|exists:drug,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ];
    }

    public function messages()
    {
        return [
            'hospital_id.required' => 'المستشفى مطلوب',
            'hospital_id.exists' => 'المستشفى المحدد غير موجود',

            'items.required' => 'يجب إضافة دواء واحد على الأقل',
            'items.array' => 'صيغة الأدوية غير صحيحة',
            'items.min' => 'يجب إضافة دواء واحد على الأقل',

            'items.*.drug_id.required' => 'معرف الدواء مطلوب',
            'items.*.drug_id.exists' => 'الدواء المحدد غير موجود',

            'items.*.quantity.required' => 'الكمية مطلوبة',
            'items.*.quantity.integer' => 'الكمية يجب أن تكون رقم صحيح',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',

            'notes.string' => 'الملاحظات يجب أن تكون نص',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',

            'priority.in' => 'الأولوية يجب أن تكون: low, normal, high, urgent',
        ];
    }
}
