<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\Inventory;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class DashboardSupplierController extends BaseApiController
{
    /**
     * إحصائيات لوحة التحكم
     * GET /api/supplier/dashboard/stats
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplierId = $user->supplier_id;

            // استخدام نفس منطق البحث المستخدم في ShipmentSupplierController بالضبط
            // البحث عن الشحنات المرتبطة بهذا المورد
            $shipmentsBaseQuery = ExternalSupplyRequest::where(function($query) use ($user) {
                // البحث عن الطلبات التي لها supplier_id مطابق لـ supplier_id الخاص بالمستخدم المورد
                $query->where('supplier_id', $user->supplier_id)
                      // أو البحث عن الطلبات من المستشفيات المرتبطة بنفس المورد
                      ->orWhereHas('hospital', function($q) use ($user) {
                          $q->where('supplier_id', $user->supplier_id);
                      });
            });

            // حساب الإحصائيات بدقة
            // إجمالي الشحنات: جميع الشحنات المرتبطة بهذا المورد (pending, approved, fulfilled, rejected)
            $totalShipments = (clone $shipmentsBaseQuery)->count();
            
            // الشحنات قيد الانتظار: الشحنات في حالة pending فقط (في انتظار الموافقة)
            // نحسب فقط الشحنات التي لها supplier_id مباشر لأن pending عادة لا يكون لها supplier_id من خلال hospital
            $pendingShipments = ExternalSupplyRequest::where('supplier_id', $user->supplier_id)
                ->where('status', 'pending')
                ->count();
            
            // تطبيق الفلتر للحالات الأخرى: approved, fulfilled, rejected
            $shipmentsQuery = (clone $shipmentsBaseQuery)
                ->whereIn('status', ['approved', 'fulfilled', 'rejected']);
            
            // طلبات التوريد الخارجية: إجمالي طلبات التوريد الخارجية التي أنشأها المورد (بجميع الحالات)
            // هذه هي الطلبات التي requested_by = user.id
            $approvedShipments = ExternalSupplyRequest::where('requested_by', $user->id)
                ->count();
            
            // الشحنات المكتملة: الشحنات التي تم إرسالها من المورد (fulfilled)
            $fulfilledShipments = (clone $shipmentsQuery)->where('status', 'fulfilled')->count();
            
            // الشحنات المرفوضة: الشحنات التي تم رفضها من المورد (rejected)
            $rejectedShipments = (clone $shipmentsQuery)->where('status', 'rejected')->count();

            $stats = [
                'totalShipments' => $totalShipments,
                'pendingShipments' => $pendingShipments,
                'approvedShipments' => $approvedShipments,
                'fulfilledShipments' => $fulfilledShipments,
                'rejectedShipments' => $rejectedShipments,
                'totalDrugs' => Inventory::where('supplier_id', $supplierId)->count(),
                'lowStockDrugs' => Inventory::where('supplier_id', $supplierId)
                    ->whereColumn('current_quantity', '<', 'minimum_level')->count(),
            ];

            return $this->sendSuccess($stats, 'تم جلب الإحصائيات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Stats Error');
        }
    }

    /**
     * سجل العمليات
     * GET /api/supplier/operations
     * يعرض فقط العمليات التي يقوم بها المورد نفسه:
     * 1. Supplier/supply-requests: الطلبات التي أنشأها المورد (supplier_create_external_supply_request)
     * 2. Supplier/shipments: تأكيد/رفض الشحنات (supplier_confirm/supplier_reject)
     */
    public function operations(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplierId = $user->supplier_id;

            // جلب IDs للطلبات المتعلقة بهذا المورد
            // فقط الطلبات من external_supply_requests
            $externalRequestIds = ExternalSupplyRequest::where(function($q) use ($user, $supplierId) {
                    $q->where('supplier_id', $supplierId)
                      ->orWhere('requested_by', $user->id);
                })
                ->pluck('id')
                ->toArray();

            // قائمة العمليات المسموح بها (فقط عمليات المورد)
            $allowedActions = [
                'supplier_create_external_supply_request',  // إنشاء طلب توريد من المورد
                'supplier_confirm_external_supply_request', // تأكيد وإرسال شحنة
                'supplier_reject_external_supply_request',  // رفض طلب شحنة
           'supplier_confirm_external_delivery'
            ];

            $operations = AuditLog::with('user:id,full_name')
                ->where(function ($q) use ($externalRequestIds, $allowedActions) {
                    // العمليات المتعلقة بـ external_supply_requests الخاصة بهذا المورد فقط
                    if (!empty($externalRequestIds)) {
                        $q->where('table_name', 'external_supply_request')
                          ->whereIn('record_id', $externalRequestIds)
                          ->whereIn('action', $allowedActions); // فلترة العمليات المسموح بها فقط
                    }
                })
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($log) {
                    $desc = $this->translateOperationType($log->action);
                    
                    // إنشاء وصف أكثر وضوحاً بناءً على نوع العملية
                    if ($log->table_name === 'external_supply_request' && $log->record_id) {
                        $desc .= ' - رقم الطلب: EXT-' . $log->record_id;
                    }

                    // محاولة استخراج معلومات إضافية من new_values
                    if (!empty($log->new_values)) {
                        $decoded = json_decode($log->new_values, true);
                        if (is_array($decoded)) {
                            if (isset($decoded['hospital_id'])) {
                                // جلب اسم المستشفى
                                $hospital = \App\Models\Hospital::find($decoded['hospital_id']);
                                if ($hospital) {
                                    $desc .= ' - ' . $hospital->name;
                                }
                            } elseif (isset($decoded['hospital_name'])) {
                                // استخدام اسم المستشفى من new_values مباشرة
                                $desc .= ' - ' . $decoded['hospital_name'];
                            } elseif (isset($decoded['rejectionReason'])) {
                                $desc .= ' - السبب: ' . $decoded['rejectionReason'];
                            }
                        }
                    }

                    return [
                        'id' => $log->id,
                        'operationType' => $this->translateOperationType($log->action),
                        'description' => $desc,
                        'userName' => $log->user->full_name ?? 'النظام',
                        'operationDate' => $log->created_at->format('Y/m/d'),
                        'operationTime' => $log->created_at->format('H:i'),
                    ];
                });

            return $this->sendSuccess($operations, 'تم جلب سجل العمليات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Operations Error');
        }
    }

    /**
     * ترجمة نوع العملية
     */
    private function translateOperationType($action)
    {
        $types = [
            'create' => 'إضافة',
            'update' => 'تعديل',
            'delete' => 'حذف',
            'confirm' => 'تأكيد',
            'reject' => 'رفض',
            // عمليات المورد
            'supplier_create_external_supply_request' => 'إنشاء طلب توريد  ',
            'supplier_confirm_external_supply_request' => 'تأكيد وإرسال شحنة',
            'supplier_reject_external_supply_request' => 'رفض طلب شحنة',
            'supplier_approve_external_supply_request' => 'موافقة على طلب توريد',
            'supplier_confirm_external_delivery' => 'تأكيد استلام الشحنة',
            // عمليات StoreKeeper
            'create_external_supply_request' => 'إنشاء طلب توريد من المخزن',
            'storekeeper_confirm_external_delivery' => 'تأكيد استلام الشحنة',
            // عمليات مدير المستشفى
            'hospital_admin_confirm_external_supply_request' => 'موافقة مدير المستشفى',
            'hospital_admin_approve_external_supply_request' => 'موافقة مدير المستشفى',
            'hospital_admin_reject_external_supply_request' => 'رفض مدير المستشفى',
            'hospital_admin_update_external_supply_request_notes' => 'تحديث ملاحظات',
        ];

        return $types[$action] ?? $action;
    }
}
