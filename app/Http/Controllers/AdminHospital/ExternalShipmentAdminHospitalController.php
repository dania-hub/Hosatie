<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\Hospital;
use App\Models\Inventory;
use App\Models\Pharmacy;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalShipmentAdminHospitalController extends BaseApiController
{
    // 1) قائمة الشحنات - فقط الطلبات من StoreKeeper التي تحتاج موافقة
    public function index(Request $request)
    {
        try {
            $hospitalId = $request->user()->hospital_id;

            if (!$hospitalId) {
                return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 400);
            }

            // جلب جميع الطلبات من StoreKeeper (جميع الحالات)
            $requests = ExternalSupplyRequest::with(['supplier', 'requester'])
                ->where('hospital_id', $hospitalId)
                ->whereHas('requester', function($q) {
                    // فقط الطلبات من StoreKeeper (requested_by هو warehouse_manager)
                    $q->where('type', 'warehouse_manager');
                })
                ->latest()
                ->get()
                ->map(function ($r) {
                    return [
                        'id'                  => $r->id,
                        'shipmentNumber'      => 'EXT-' . $r->id,
                        'requestDate'         => optional($r->created_at)->toIso8601String(),
                        'createdAt'           => optional($r->created_at)->toIso8601String(),
                        'status'              => $this->mapStatusToArabic($r->status),
                        'requestingDepartment'=> $r->requester?->full_name ?? 'مسؤول المخزن',
                        'department'          => $r->requester?->full_name ?? 'مسؤول المخزن',
                    ];
                });

            return response()->json($requests);
        } catch (\Exception $e) {
            \Log::error('Error in ExternalShipmentAdminHospitalController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'حدث خطأ في جلب البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // 2) تفاصيل شحنة + البنود
    public function show(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $r = ExternalSupplyRequest::with(['supplier', 'items.drug'])
            ->where('hospital_id', $hospitalId)
            ->findOrFail($id);

        // جلب جميع الصيدليات والمستودعات في المستشفى
        $pharmacyIds = Pharmacy::where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $warehouseIds = Warehouse::where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $items = $r->items->map(function ($item) use ($pharmacyIds, $warehouseIds) {
            // حساب الكمية المتوفرة من جميع الصيدليات والمستودعات
            $availableQuantity = 0;
            
            if (!empty($pharmacyIds) || !empty($warehouseIds)) {
                $inventoryQuery = Inventory::where('drug_id', $item->drug_id);
                
                if (!empty($pharmacyIds) && !empty($warehouseIds)) {
                    $inventoryQuery->where(function($query) use ($pharmacyIds, $warehouseIds) {
                        $query->whereIn('pharmacy_id', $pharmacyIds)
                              ->orWhereIn('warehouse_id', $warehouseIds);
                    });
                } elseif (!empty($pharmacyIds)) {
                    $inventoryQuery->whereIn('pharmacy_id', $pharmacyIds);
                } elseif (!empty($warehouseIds)) {
                    $inventoryQuery->whereIn('warehouse_id', $warehouseIds);
                }
                
                $inventories = $inventoryQuery->get();
                $availableQuantity = $inventories->sum('current_quantity');
            }

            return [
                'id'                => $item->id,
                'drugId'            => $item->drug_id,
                'name'              => $item->drug?->name ?? 'دواء غير معروف',
                'drugName'          => $item->drug?->name ?? 'دواء غير معروف',
                'requestedQuantity' => $item->requested_qty,
                'requested'         => $item->requested_qty,
                'requested_qty'     => $item->requested_qty,
                'approved'          => $item->approved_qty,
                'approved_qty'      => $item->approved_qty,
                'fulfilled'         => $item->fulfilled_qty,
                'fulfilled_qty'     => $item->fulfilled_qty,
                'sent'              => $item->fulfilled_qty,
                'availableQuantity' => $availableQuantity,
                'available_quantity'=> $availableQuantity,
                'unit'              => $item->drug?->unit ?? 'وحدة',
                'dosage'            => $item->drug?->strength ?? null,
                'strength'          => $item->drug?->strength ?? null,
            ];
        });

        // جلب سبب الرفض والملاحظات من audit_log
        $rejectionReason = null;
        $rejectedAt = null;
        $notes = null;
        
        if ($r->status === 'rejected') {
            $rejectionAuditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $r->id)
                ->where(function($query) {
                    $query->where('action', 'like', '%reject%')
                          ->orWhere('action', 'like', '%رفض%');
                })
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($rejectionAuditLog) {
                $rejectedAt = $rejectionAuditLog->created_at->toIso8601String();
                // محاولة استخراج سبب الرفض من new_values أو old_values
                if ($rejectionAuditLog->new_values) {
                    $newValues = json_decode($rejectionAuditLog->new_values, true);
                    if (isset($newValues['rejectionReason'])) {
                        $rejectionReason = $newValues['rejectionReason'];
                    } elseif (isset($newValues['reason'])) {
                        $rejectionReason = $newValues['reason'];
                    }
                }
                if (!$rejectionReason && $rejectionAuditLog->old_values) {
                    $oldValues = json_decode($rejectionAuditLog->old_values, true);
                    if (isset($oldValues['rejectionReason'])) {
                        $rejectionReason = $oldValues['rejectionReason'];
                    } elseif (isset($oldValues['reason'])) {
                        $rejectionReason = $oldValues['reason'];
                    }
                }
            }
        }
        
        // جلب ملاحظة storekeeper (الملاحظة الأصلية عند الإنشاء)
        $storekeeperNotes = null;
        $storekeeperNotesAuditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
            ->where('record_id', $r->id)
            ->where('action', 'create_external_supply_request')
            ->orderBy('created_at', 'asc')
            ->first();
        
        if ($storekeeperNotesAuditLog && $storekeeperNotesAuditLog->new_values) {
            $newValues = json_decode($storekeeperNotesAuditLog->new_values, true);
            if (isset($newValues['notes']) && !empty($newValues['notes'])) {
                $storekeeperNotes = $newValues['notes'];
            }
        }
        
        // جلب ملاحظة supplier (عند القبول/الإرسال)
        $supplierNotes = null;
        $supplierNotesAuditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
            ->where('record_id', $r->id)
            ->where('action', 'supplier_confirm_external_supply_request')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($supplierNotesAuditLog && $supplierNotesAuditLog->new_values) {
            $newValues = json_decode($supplierNotesAuditLog->new_values, true);
            if (isset($newValues['notes']) && !empty($newValues['notes'])) {
                $supplierNotes = $newValues['notes'];
            }
        }
        
        // للتوافق مع الكود القديم، نستخدم ملاحظة supplier إذا كانت موجودة، وإلا ملاحظة storekeeper
        $notes = $supplierNotes ?? $storekeeperNotes;

        // إعداد confirmationDetails إذا تم تأكيد الاستلام
        $confirmationDetails = null;
        $isDelivered = false;
        
        // التحقق من أن الطلب تم استلامه (fulfilled + تم تأكيد الاستلام من storekeeper)
        if ($r->status === 'fulfilled') {
            $requestUpdatedAt = $r->updated_at;
            // التحقق من أن items تم تحديثها بعد تحديث الطلب (يعني تم تأكيد الاستلام)
            $itemsUpdatedAfterDelivery = $r->items->some(function($item) use ($requestUpdatedAt) {
                if (!$item->updated_at) return false;
                $diffInSeconds = $item->updated_at->diffInSeconds($requestUpdatedAt);
                return $item->updated_at->gt($requestUpdatedAt) && $diffInSeconds > 1;
            });
            
            if (!$itemsUpdatedAfterDelivery) {
                $itemsUpdatedAfterDelivery = $r->items->every(function($item) use ($requestUpdatedAt) {
                    return $item->updated_at && $item->updated_at->gt($requestUpdatedAt);
                });
            }
            
            $isDelivered = $itemsUpdatedAfterDelivery;
        }
        
        if ($isDelivered) {
            // جلب الكميات المرسلة والمستلمة من audit_log
            $originalSentQuantities = [];
            $actualReceivedQuantities = [];
            $confirmationNotes = null;
            $auditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $r->id)
                ->where('action', 'storekeeper_confirm_external_delivery')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($auditLog) {
                // جلب الكميات المرسلة الأصلية من old_values
                if ($auditLog->old_values) {
                    $oldValues = json_decode($auditLog->old_values, true);
                    if (isset($oldValues['items']) && is_array($oldValues['items'])) {
                        foreach ($oldValues['items'] as $auditItem) {
                            if (isset($auditItem['item_id']) && isset($auditItem['sentQuantity'])) {
                                $originalSentQuantities[$auditItem['item_id']] = $auditItem['sentQuantity'];
                            }
                        }
                    }
                }
                
                // جلب الكميات المستلمة الفعلية من new_values
                if ($auditLog->new_values) {
                    $newValues = json_decode($auditLog->new_values, true);
                    if (isset($newValues['items']) && is_array($newValues['items'])) {
                        foreach ($newValues['items'] as $auditItem) {
                            if (isset($auditItem['id']) && isset($auditItem['receivedQuantity'])) {
                                $actualReceivedQuantities[$auditItem['id']] = $auditItem['receivedQuantity'];
                            }
                        }
                    }
                    // جلب ملاحظة تأكيد الاستلام
                    if (isset($newValues['confirmationNotes']) && !empty($newValues['confirmationNotes'])) {
                        $confirmationNotes = $newValues['confirmationNotes'];
                    }
                }
            }
            
            // جلب تاريخ الاستلام من audit_log
            $deliveryDate = null;
            if ($auditLog && $auditLog->created_at) {
                $deliveryDate = $auditLog->created_at->toIso8601String();
            } else {
                // إذا لم نجد audit_log، نستخدم updated_at للطلب
                $deliveryDate = $r->updated_at->toIso8601String();
            }
            
            $confirmationDetails = [
                'confirmedAt' => $deliveryDate,
                'confirmationNotes' => $confirmationNotes ?? null,
                'receivedItems' => $r->items->map(function($item) use ($originalSentQuantities, $actualReceivedQuantities) {
                    // الكمية المرسلة من Supplier: fulfilled_qty
                    $sentQty = $originalSentQuantities[$item->id] ?? null;
                    if ($sentQty === null) {
                        $sentQty = $item->fulfilled_qty ?? $item->approved_qty ?? 0;
                    }
                    
                    // الكمية المستلمة الفعلية من audit_log
                    $receivedQty = $actualReceivedQuantities[$item->id] ?? null;
                    if ($receivedQty === null) {
                        $receivedQty = $item->fulfilled_qty ?? 0;
                    }
                    
                    return [
                        'id' => $item->id,
                        'name' => $item->drug->name ?? 'غير محدد',
                        'sentQuantity' => $sentQty,
                        'receivedQuantity' => $receivedQty,
                        'unit' => $item->drug->unit ?? 'وحدة'
                    ];
                })->toArray()
            ];
        }

        return response()->json([
            'id'                  => $r->id,
            'shipmentNumber'      => 'EXT-' . $r->id,
            'requestingDepartment'=> $r->supplier?->name ?? 'المستشفى',
            'department'          => $r->supplier?->name ?? 'المستشفى',
            'status'              => $this->mapStatusToArabic($r->status),
            'requestDate'         => optional($r->created_at)->toIso8601String(),
            'createdAt'           => optional($r->created_at)->toIso8601String(),
            'rejectionReason'     => $rejectionReason,
            'rejectedAt'          => $rejectedAt,
            'notes'               => $notes,
            'storekeeperNotes'    => $storekeeperNotes,
            'supplierNotes'       => $supplierNotes,
            'confirmationDetails' => $confirmationDetails,
            'items'               => $items,
        ]);
    }

    // 3) تأكيد الشحنة (واجهة المدير: /shipments/{id}/confirm)
    // القبول المبدئي: يغير الحالة إلى "approved" ويضع approved_qty = requested_qty
    // بعد القبول، سيظهر الطلب للـ Supplier للموافقة النهائية
    public function confirm(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;
        $adminUser = $request->user();

        $data = $request->validate([
            'items'        => 'required|array',
            'items.*.id'   => 'required|integer|exists:external_supply_request_items,id',
            'items.*.sent' => 'nullable|numeric|min:0', // جعل sent اختياري للقبول المبدئي
            'notes'        => 'nullable|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::with('items')->where('hospital_id', $hospitalId)->findOrFail($id);

        // التحقق من أن الطلب من StoreKeeper
        $requester = User::find($r->requested_by);
        if (!$requester || $requester->type !== 'warehouse_manager') {
            return response()->json(['message' => 'هذا الطلب ليس من مسؤول المخزن'], 400);
        }

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        // جلب المستشفى للحصول على supplier_id
        $hospital = Hospital::find($hospitalId);
        if (!$hospital || !$hospital->supplier_id) {
            return response()->json(['message' => 'المستشفى غير مرتبط بمورد. يرجى التحقق من إعدادات المستشفى.'], 400);
        }

        $oldStatus = $r->status;
        
        DB::transaction(function () use ($r, $data, $adminUser, $hospital, $oldStatus, $request) {
            // ملاحظة: العلاقة الصحيحة للكميات:
            // - requested_qty: الكمية المطلوبة من StoreKeeper
            // - approved_qty: الكمية المعتمدة من Supplier (سيتم تحديدها لاحقاً من Supplier)
            // - fulfilled_qty: الكمية الفعلية المرسلة من Supplier (سيتم تحديدها لاحقاً من Supplier)
            // HospitalAdmin يوافق على الطلب فقط (يغير الحالة)، ولا يغير الكميات
            // الكميات ستُحدد من قبل Supplier عند الموافقة والإرسال
            
            // تغيير الحالة إلى "approved" (موافقة من HospitalAdmin)
            // تعيين supplier_id من المستشفى لإرسال الطلب للمورد
            $r->status = 'approved';
            $r->supplier_id = $hospital->supplier_id; // تعيين المورد المرتبط بنفس المستشفى
            $r->handeled_by = $adminUser->id; // تسجيل من وافق على الطلب (HospitalAdmin)
            $r->handeled_at = now(); // تسجيل وقت الموافقة
            $r->save();
            
            // حفظ عملية القبول في audit_log
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $adminUser->id,
                    'hospital_id' => $adminUser->hospital_id,
                    'action' => 'hospital_admin_confirm_external_supply_request',
                    'table_name' => 'external_supply_request',
                    'record_id' => $r->id,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode([
                        'status' => 'approved',
                        'approved_by' => $adminUser->id
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to log approval', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الطلب بنجاح. سيتم إرساله للمورد للموافقة النهائية.',
        ]);
    }

    // 4) رفض الشحنة (واجهة المدير: /shipments/{id}/reject)
    // عند الرفض، لا يذهب الطلب للـ Supplier
    public function reject(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;
        $adminUser = $request->user();

        $data = $request->validate([
            'rejectionReason' => 'required|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        // التحقق من أن الطلب من StoreKeeper
        $requester = User::find($r->requested_by);
        if (!$requester || $requester->type !== 'warehouse_manager') {
            return response()->json(['message' => 'هذا الطلب ليس من مسؤول المخزن'], 400);
        }

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        // حفظ سبب الرفض قبل تحديث الحالة
        $rejectionReason = $data['rejectionReason'] ?? '';
        
        // عند الرفض، لا يذهب الطلب للـ Supplier
        $oldStatus = $r->status;
        $r->status = 'rejected';
        $r->save();
        
        // حفظ سبب الرفض في audit_log
        try {
            $auditLog = \App\Models\AuditLog::create([
                'user_id' => $adminUser->id,
                'hospital_id' => $hospitalId,
                'action' => 'hospital_admin_reject_external_supply_request',
                'table_name' => 'external_supply_request',
                'record_id' => $r->id,
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode([
                    'status' => 'rejected',
                    'rejectionReason' => $rejectionReason,
                    'reason' => $rejectionReason, // للتوافق
                    'rejected_by' => $adminUser->id
                ]),
                'ip_address' => $request->ip(),
            ]);
            \Log::info('Audit log created for rejection by HospitalAdmin', [
                'audit_log_id' => $auditLog->id,
                'rejectionReason' => $rejectionReason
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to log rejection', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب بنجاح. لن يتم إرساله للمورد.',
        ]);
    }

    // 5) تغيير حالة عامة (واجهة القسم: PUT /shipments/{id}/status)
    public function updateStatus(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $data = $request->validate([
            'status' => 'required|in:pending,approved,fulfilled,rejected',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        $r->status = $data['status'];
        $r->save();

        return response()->json([
            'success' => true,
            'status'  => $this->mapStatusToArabic($r->status),
        ]);
    }

    // 6) تأكيد استلام من جهة القسم (POST /shipments/{id}/confirm-delivery)
    public function confirmDelivery(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $data = $request->validate([
            'confirmationNotes' => 'nullable|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        $r->status = 'fulfilled';
        $notes = $data['confirmationNotes'] ?? null;
        $r->notes = $notes ?? $r->notes;
        $r->save();
        
        // حفظ الملاحظات في audit_log إذا كانت موجودة
        if ($notes) {
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $adminUser->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'hospital_admin_update_external_supply_request_notes',
                    'table_name' => 'external_supply_request',
                    'record_id' => $r->id,
                    'old_values' => json_encode(['notes' => $r->getOriginal('notes')]),
                    'new_values' => json_encode(['notes' => $notes]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to log notes', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد الاستلام بنجاح',
            'status'  => $this->mapStatusToArabic($r->status),
        ]);
    }

    // تحويل status من DB إلى عربي
    private function mapStatusToArabic(string $status): string
    {
        return match ($status) {
            'pending'   => 'جديد',
            'approved'  => 'معتمدة مبدئياً', // معتمدة من HospitalAdmin، في انتظار Supplier
            'fulfilled' => 'تم الإرسال', // أرسلها Supplier، في انتظار StoreKeeper
            'rejected'  => 'مرفوضة',
            default     => $status,
        };
    }
}
