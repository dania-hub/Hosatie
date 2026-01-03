<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogStoreKeeperController extends BaseApiController
{
    // GET /api/storekeeper/operations
    public function index(Request $request)
    {
        $user = $request->user();

        // فقط مدير المخزن
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // يمكن تخصيص المنطق: هنا نعرض العمليات التي نفذها هذا المستخدم فقط
        $logs = AuditLog::where('user_id', $user->id)
            ->where('hospital_id', $user->hospital_id)
            ->orderBy('created_at', 'desc')
            ->get();

        // تحويلها إلى الفورمات الذي تتوقعه الواجهة
        $data = $logs->map(function ($log) {
            $translatedAction = $this->translateAction($log->action);
            $shipmentNumber = $this->getShipmentNumber($log->table_name, $log->record_id);
            
            // دمج نوع العملية مع رقم الشحنة إن وجد
            $operationType = $shipmentNumber 
                ? $translatedAction . ' - ' . $shipmentNumber
                : $translatedAction;
            
            return [
                'fileNumber'    => $log->id,          // معرف العملية
                'operationType' => $operationType,    // نوع العملية مع رقم الشحنة
                'operationDate' => $log->created_at   // تاريخ العملية بصيغة YYYY/MM/DD
                    ? $log->created_at->format('Y/m/d')
                    : '',
            ];
        });

        return response()->json($data);
    }

    /**
     * ترجمة نوع العملية إلى العربية
     */
    private function translateAction($action)
    {
        $translations = [
            // عمليات طلبات التوريد الداخلية
            'storekeeper_confirm_internal_request' => 'تأكيد طلب توريد داخلي',
            'storekeeper_reject_internal_request' => 'رفض طلب توريد داخلي',
            'pharmacist_confirm_internal_receipt' => 'تأكيد استلام شحنة داخلية',
            'pharmacist_create_supply_request' => 'إنشاء طلب توريد داخلي',
            'department_create_supply_request' => 'إنشاء طلب توريد داخلي',
            
            // عمليات طلبات التوريد الخارجية
            'create_external_supply_request' => 'إنشاء طلب توريد خارجي',
            'storekeeper_confirm_external_delivery' => 'تأكيد استلام شحنة خارجية',
            'storekeeper_reject_external_request' => 'رفض طلب توريد خارجي',
            'supplier_confirm_external_supply_request' => 'قبول طلب توريد خارجي',
            'supplier_reject_external_supply_request' => 'رفض طلب توريد خارجي',
            'hospital_admin_reject_external_supply_request' => 'رفض طلب توريد خارجي (مدير مستشفى)',
            
            // عمليات عامة
            'إنشاء طلب توريد' => 'إنشاء طلب توريد',
            'استلام شحنة' => 'استلام شحنة',
            'إضافة دواء' => 'إضافة دواء',
            'تعديل دواء' => 'تعديل دواء',
            'حذف دواء' => 'حذف دواء',
            'create' => 'إنشاء',
            'update' => 'تعديل',
            'delete' => 'حذف',
        ];

        return $translations[$action] ?? $action;
    }

    /**
     * استخراج رقم الشحنة من اسم الجدول ومعرف السجل
     */
    private function getShipmentNumber($tableName, $recordId)
    {
        if (!$tableName || !$recordId) {
            return null;
        }

        // تحديد رقم الشحنة حسب نوع الجدول
        if ($tableName === 'internal_supply_request') {
            return 'INT-' . $recordId;
        } elseif ($tableName === 'external_supply_request') {
            return 'EXT-' . $recordId;
        }

        return null;
    }
}
