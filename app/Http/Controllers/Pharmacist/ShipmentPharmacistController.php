<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\AuditLog;
use App\Models\Drug;
use App\Services\StaffNotificationService;
use App\Services\PatientNotificationService;

class ShipmentPharmacistController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $staffNotifications,
        private PatientNotificationService $patientNotifications
    ) {}
    /**
     * جلب الملاحظات من audit_log
     */
    private function getNotesFromAuditLog($requestId)
    {
        $notes = [
            'storekeeperNotes' => null,  // ملاحظة عند إنشاء الطلب من pharmacist/department
            'storekeeperNotesSource' => null, // مصدر الملاحظة: 'pharmacist' أو 'department'
            'supplierNotes' => null,     // ملاحظة عند إرسال الشحنة من storekeeper
            'confirmationNotes' => null,  // ملاحظة عند تأكيد الاستلام من pharmacist/department
            'confirmationNotesSource' => null, // مصدر الملاحظة: 'pharmacist' أو 'department'
            'rejectionReason' => null,    // سبب الرفض من storekeeper
            'rejectedAt' => null         // تاريخ الرفض من storekeeper
        ];

        // جلب جميع سجلات audit_log لهذا الطلب
        $auditLogs = AuditLog::where('table_name', 'internal_supply_request')
            ->where('record_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($auditLogs as $log) {
            $newValues = json_decode($log->new_values, true);
            if (!$newValues) continue;

            // ملاحظة عند إنشاء الطلب
            if (in_array($log->action, ['إنشاء طلب توريد', 'pharmacist_create_supply_request', 'department_create_supply_request']) 
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['storekeeperNotes'] = $newValues['notes'];
                if ($log->action === 'pharmacist_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'department';
                }
            }

            // ملاحظة عند إرسال الشحنة من storekeeper
            if ($log->action === 'storekeeper_confirm_internal_request' 
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['supplierNotes'] = $newValues['notes'];
            }

            // ملاحظة عند تأكيد الاستلام
            if (in_array($log->action, ['pharmacist_confirm_internal_receipt', 'department_confirm_internal_receipt'])
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['confirmationNotes'] = $newValues['notes'];
                if ($log->action === 'pharmacist_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'department';
                }
            }

            // سبب الرفض من storekeeper
            if (in_array($log->action, ['رفض طلب توريد داخلي', 'storekeeper_reject_internal_request', 'reject'])
                && isset($newValues['rejectionReason']) && !empty($newValues['rejectionReason'])) {
                $notes['rejectionReason'] = $newValues['rejectionReason'];
                // جلب تاريخ الرفض من audit_log
                if (isset($newValues['rejectedAt'])) {
                    $notes['rejectedAt'] = $newValues['rejectedAt'];
                } else {
                    // إذا لم يكن موجوداً في new_values، نستخدم created_at من audit_log
                    $notes['rejectedAt'] = $log->created_at->toIso8601String();
                }
            }
        }

        return $notes;
    }

    /**
     * GET /api/pharmacist/shipments
     * عرض الشحنات التي طلبها أو استلمها المستخدم الحالي فقط.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // بناء الاستعلام: الشحنات التابعة لمستشفى المستخدم
        // نعرض كل الطلبات الخاصة بالمستشفى التي قام بها "صيدلي" فقط
        $query = InternalSupplyRequest::with('items.drug')
            ->whereHas('pharmacy', function ($q) use ($user) {
                // نفترض أن المستخدم لديه hospital_id 
                // وأن الصيدلية تابعة لنفس المستشفى
                $q->where('hospital_id', $user->hospital_id);
            })
            ->whereHas('requester', function ($q) {
                $q->where('type', 'pharmacist');
            })
            ->orderBy('created_at', 'desc');

        $shipments = $query->get()
            ->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'shipmentNumber' => 'SHP-' . $shipment->id,
                    'requestDate' => Carbon::parse($shipment->created_at)->format('Y/m/d'),
                    'status' => $this->translateStatus($shipment->status),
                    'received' => $shipment->status === 'fulfilled', // Corrected status check
                    'items' => $shipment->items->map(function($item) {
                        return [
                            'name' => $item->drug->name ?? 'Unknown',
                            'quantity' => $item->requested_qty
                        ];
                    }),
                    'confirmationDetails' => $shipment->status === 'fulfilled' ? [
                        'confirmedAt' => $shipment->updated_at->format('Y/m/d H:i')
                    ] : null
                ];
            });

        return $this->sendSuccess($shipments, 'تم جلب الشحنات بنجاح.');
    }

    /**
     * GET /api/pharmacist/shipments/{id}
     * عرض تفاصيل شحنة واحدة.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $shipment = InternalSupplyRequest::with(['items.drug', 'pharmacy'])
            ->where('id', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('الشحنة غير موجودة.', [], 404);
        }

        // التحقق من أن الشحنة تخص مستشفى المستخدم (نفس الـ hospital_id)
        $isInSameHospital = $shipment->pharmacy && $shipment->pharmacy->hospital_id == $user->hospital_id;
        
        if (!$isInSameHospital) {
            return $this->sendError('هذه الشحنة لا تخص المستشفى الذي تعمل فيه.', [], 403);
        }

        // جلب الملاحظات من audit_log
        $notes = $this->getNotesFromAuditLog($shipment->id);

        $data = [
            'id' => $shipment->id,
            'shipmentNumber' => 'SHP-' . $shipment->id,
            'requestDate' => Carbon::parse($shipment->created_at)->format('Y/m/d'),
            'status' => $this->translateStatus($shipment->status),
            'received' => $shipment->status === 'fulfilled',
            'storekeeperNotes' => $notes['storekeeperNotes'],
            'storekeeperNotesSource' => $notes['storekeeperNotesSource'],
            'supplierNotes' => $notes['supplierNotes'],
            'confirmationNotes' => $notes['confirmationNotes'],
            'confirmationNotesSource' => $notes['confirmationNotesSource'],
            'rejectionReason' => $notes['rejectionReason'], // جلب سبب الرفض من audit_log
            'rejectedAt' => $notes['rejectedAt'], // جلب تاريخ الرفض من audit_log
            'items' => $shipment->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'drugId' => $item->drug_id,
                    'name' => $item->drug->name ?? 'Unknown',
                    'genericName' => $item->drug->generic_name ?? null,
                    'strength' => $item->drug->strength ?? null,
                    'dosage' => $item->drug->strength ?? null, // للتوافق مع الواجهة
                    'quantity' => $item->requested_qty, // الكمية المطلوبة
                    'requestedQty' => $item->requested_qty, // الكمية المطلوبة (اسم بديل)
                    'requested_qty' => $item->requested_qty, // للتوافق مع الواجهة
                    'approvedQty' => $item->approved_qty ?? null, // الكمية المعتمدة
                    'approved_qty' => $item->approved_qty ?? null, // للتوافق مع الواجهة
                    'fulfilledQty' => $item->fulfilled_qty ?? null, // الكمية المستلمة
                    'fulfilled_qty' => $item->fulfilled_qty ?? null, // للتوافق مع الواجهة
                    'receivedQuantity' => $item->fulfilled_qty ?? null, // الكمية المستلمة فقط - لا نستخدم approved_qty كبديل
                    'unit' => $item->drug->unit ?? 'علبة',
                    'units_per_box' => $item->drug->units_per_box ?? 1,
                    'unitsPerBox'   => $item->drug->units_per_box ?? 1,
                    'form' => $item->drug->form ?? null,
                    'type' => $item->drug->form ?? null, // للتوافق مع الواجهة
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date,
                ];
            }),
            'notes' => $shipment->notes,
            'confirmationDetails' => $shipment->status === 'fulfilled' ? [
                'confirmedAt' => $shipment->updated_at->format('Y/m/d H:i')
            ] : null
        ];

        return $this->sendSuccess($data, 'تم جلب تفاصيل الشحنة بنجاح.');
    }

    /**
     * POST /api/pharmacist/shipments/{id}/confirm
     * تأكيد الاستلام: ينقل الأدوية إلى مخزون الصيدلية.
     */
   public function confirm(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1) جلب الطلب الداخلي مع العناصر والصيدلية لضمان الحصول على hospital_id
            $shipment = InternalSupplyRequest::with(['items', 'pharmacy'])->findOrFail($id);

            if ($shipment->status === 'fulfilled') {
                return $this->sendError('تم استلام هذه الشحنة مسبقاً.');
            }

            // 2) التحقق من أن الشحنة تم قبولها (approved) قبل السماح بتأكيد الاستلام
            if ($shipment->status !== 'approved') {
                $statusText = $this->translateStatus($shipment->status);
                return $this->sendError("لا يمكن تأكيد الاستلام. يجب قبول الشحنة أولاً. الحالة الحالية: {$statusText}");
            }

            // 3) تحقق أن الشحنة تخص صيدلية هذا المستخدم (أمان)
            $user = $request->user();
            if ($user->pharmacy_id && $shipment->pharmacy_id !== $user->pharmacy_id) {
                // يمكن تفعيل هذا الشرط لو أردت
                // return $this->sendError('هذه الشحنة لا تخص صيدليتك.');
            }

            // 3) تغيير الحالة إلى fulfilled (استلام نهائي)
            $shipment->status = 'fulfilled';
            $shipment->save();

            // 4) إضافة الأدوية لمخزون الصيدلية
            $targetPharmacyId = $shipment->pharmacy_id ?: ($user->pharmacy_id ?? null);
            if (!$targetPharmacyId) {
                DB::rollBack();
                return $this->sendError('لا يوجد صيدلية مرتبطة بهذا الطلب أو بالمستخدم.');
            }

            // الحصول على الكميات المستلمة من الطلب
            $receivedItems = $request->input('receivedItems', []);
            $receivedItemsMap = [];
            foreach ($receivedItems as $receivedItem) {
                $itemId = $receivedItem['id'] ?? null;
                $receivedQty = $receivedItem['receivedQuantity'] ?? null;
                if ($itemId !== null && $receivedQty !== null) {
                    // تحويل ID إلى integer للتأكد من المطابقة الصحيحة
                    $receivedItemsMap[(int)$itemId] = (float)$receivedQty;
                }
            }

            // تحقق من وجود نقص قبل المتابعة لإلزامية الملاحظات
            $hasShortageBefore = false;
            foreach ($shipment->items as $item) {
                $sentQty = $item->approved_qty ?? $item->requested_qty ?? 0;
                $receivedQty = isset($receivedItemsMap[(int)$item->id]) ? $receivedItemsMap[(int)$item->id] : $sentQty;
                if ($receivedQty < $sentQty) {
                    $hasShortageBefore = true;
                    break;
                }
            }

            if ($hasShortageBefore && empty(trim($request->input('notes', '')))) {
                DB::rollBack();
                return $this->sendError('يجب إدخال ملاحظات لتوضيح سبب النقص في الكمية المستلمة.');
            }

            foreach ($shipment->items as $item) {
                // الكمية التي ستضاف للصيدلية:
                // أولوية: الكمية المستلمة من الطلب > approved_qty > requested_qty
                $qtyToAdd = null;
                
                // التحقق من الكمية المستلمة المرسلة من الواجهة
                // البحث باستخدام ID كـ integer
                if (isset($receivedItemsMap[(int)$item->id])) {
                    $qtyToAdd = $receivedItemsMap[(int)$item->id];
                }
                // إذا لم توجد كمية مستلمة في الطلب، نستخدم approved_qty
                else {
                    $qtyToAdd = $item->approved_qty ?? $item->requested_qty ?? 0;
                }

                if ($qtyToAdd <= 0) {
                    continue;
                }

                // البحث عن مخزون هذا الدواء في هذه الصيدلية - مع مراعاة الدُفعة وتاريخ الصلاحية
                $inventory = Inventory::firstOrNew([
                    'drug_id'    => $item->drug_id,
                    'pharmacy_id'=> $targetPharmacyId,
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date,
                ]);

                // سجل جديد؟ نتأكد ألا يكون مرتبطاً بمستودع
                if (!$inventory->exists) {
                    $inventory->warehouse_id = null;
                    $inventory->department_id = null;
                    $inventory->current_quantity = 0;
                    $inventory->minimum_level = 20; // قيمة افتراضية
                }

                $oldQuantity = (float)($inventory->current_quantity ?? 0);
                $inventory->current_quantity = $oldQuantity + $qtyToAdd;
                $inventory->save();

                // التنبيه في حالة انخفاض المخزون عن الحد الأدنى (حتى بعد التوريد)
                try {
                    $this->staffNotifications->checkAndNotifyLowStock($inventory);
                } catch (\Exception $e) {
                    \Log::error('Pharmacy stock replenishment alert notification failed', ['error' => $e->getMessage()]);
                }

                // ✅ منطق إخطار المرضى عند توفر دواء كان غير متوفر
                // إذا كان الرصيد السابق 0 والرصيد الجديد > 0
                if ($oldQuantity <= 0 && $inventory->current_quantity > 0) {
                    try {
                        $drug = Drug::find($item->drug_id);
                        if ($drug) {
                            $hospitalId = $shipment->pharmacy->hospital_id ?? 0;
                            $this->patientNotifications->notifyDrugAvailability($drug, (int)$hospitalId);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Patient availability notification failed', ['error' => $e->getMessage()]);
                    }
                }

                // تحديث fulfilled_qty in item بالكمية المستلمة الفعلية:
                // ملاحظة: 
                // - requested_qty: الكمية المطلوبة الأصلية (لا يتم تعديلها)
                // - approved_qty: الكمية المرسلة من storekeeper (لا يتم تعديلها)
                // - fulfilled_qty: الكمية المستلمة الفعلية من pharmacist (يتم تحديثها هنا)
                $item->fulfilled_qty = $qtyToAdd;
                $item->save();
            }

            // تحقق من وجود نقص أو تلف في أي صنف
            $hasShortage = false;
            foreach ($shipment->items as $item) {
                $sentQty = $item->approved_qty ?? $item->requested_qty ?? 0;
                $receivedQty = $item->fulfilled_qty ?? 0;
                if ($receivedQty < $sentQty) {
                    $hasShortage = true;
                    break;
                }
            }

            if ($hasShortage) {
                try {
                    $pharmacyName = $shipment->pharmacy->name ?? 'الصيدلية';
                    $this->staffNotifications->notifyAdminShipmentDamage($shipment, $pharmacyName);
                } catch (\Exception $e) {
                    \Log::error('Failed to notify admin about pharmacist shortage: ' . $e->getMessage());
                }
            }

            DB::commit();

            // إعادة تحميل البيانات المحدثة من قاعدة البيانات
            $shipment = InternalSupplyRequest::with('items.drug')->findOrFail($id);

            // جلب الملاحظات من الطلب
            $notes = $request->input('notes', '');

            AuditLog::create([
                'user_id'    => $user->id,
                'hospital_id'=> $user->hospital_id,
                'action'     => 'pharmacist_confirm_internal_receipt',
                'table_name' => 'internal_supply_request',
                'record_id'  => $shipment->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'status'      => $shipment->status,
                    'pharmacy_id' => $shipment->pharmacy_id,
                    'items'       => $shipment->items->map(fn($item) => [
                        'item_id'       => $item->id,
                        'drug_id'       => $item->drug_id,
                        'fulfilled_qty' => $item->fulfilled_qty ?? $item->approved_qty ?? $item->requested_qty,
                    ]),
                    'notes' => $notes, // ملاحظة pharmacist عند تأكيد الاستلام
                ]),
                'ip_address' => $request->ip(),
            ]);
            
            return $this->sendSuccess([
                'id' => $id,
                'status' => 'تم الإستلام',
                'confirmationDetails' => [
                    'confirmedAt' => now()->format('Y/m/d H:i')
                ],
                'items' => $shipment->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'drugId' => $item->drug_id,
                        'name' => $item->drug->name ?? 'Unknown',
                        'quantity' => $item->requested_qty,
                        'requestedQty' => $item->requested_qty,
                        'approvedQty' => $item->approved_qty ?? null,
                        'fulfilledQty' => $item->fulfilled_qty ?? null,
                        'receivedQuantity' => $item->fulfilled_qty ?? null, // الكمية المستلمة فقط - لا نستخدم approved_qty كبديل
                        'unit' => $item->drug->unit ?? 'علبة',
                        'units_per_box' => $item->drug->units_per_box ?? 1,
                        'unitsPerBox'   => $item->drug->units_per_box ?? 1,
                    ];
                })
            ], 'تم تأكيد استلام الشحنة وإضافتها لمخزون الصيدلية.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في تأكيد الاستلام: ' . $e->getMessage());
        }}
    // Helper function
    private function translateStatus($status)
    {
        return match($status) {
            'pending' => 'قيد الانتظار',
            'approved' => 'قيد الاستلام', // أو 'تمت الموافقة'
            'shipped' => 'تم الشحن',
            'fulfilled' => 'تم الإستلام', // Correct DB value translation
            'rejected' => 'مرفوضة',
            default => $status
        };
    }
}
