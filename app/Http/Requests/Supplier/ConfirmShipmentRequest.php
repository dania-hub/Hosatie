<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmShipmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:external_supply_request_item,id',
            'items.*.approved_qty' => 'nullable|numeric|min:0', // الكمية المعتمدة من Supplier
            'items.*.fulfilled_qty' => 'nullable|numeric|min:0', // الكمية الفعلية المرسلة من Supplier
            'items.*.sentQuantity' => 'nullable|numeric|min:0', // للتوافق (يستخدم كـ fulfilled_qty)
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages()
    {
        return [
            'notes.string' => 'الملاحظات يجب أن تكون نص',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 500 حرف',
        ];
    }
}
