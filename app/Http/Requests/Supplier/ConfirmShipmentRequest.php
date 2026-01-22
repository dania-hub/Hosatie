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
            'items.*.id' => 'required|integer|exists:external_supply_request_items,id',
            'items.*.approved_qty' => 'nullable|numeric|min:0', // الكمية المعتمدة من Supplier
            'items.*.fulfilled_qty' => 'nullable|numeric|min:0', // الكمية الفعلية المرسلة من Supplier
            'items.*.sentQuantity' => 'nullable|numeric|min:0', // للتوافق (يستخدم كـ fulfilled_qty)
            'items.*.receivedQuantity' => 'nullable|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:100', // رقم الدفعة
            'items.*.batchNumber' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date', // تاريخ الصلاحية
            'items.*.expiryDate' => 'nullable|date',
            'items.*.drugId' => 'nullable|integer',
            'items.*.drug_id' => 'nullable|integer',
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
