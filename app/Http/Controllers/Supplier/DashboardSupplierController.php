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
            // هذه هي الشحنات التي تم إرسالها للمورد (approved, fulfilled, rejected) + الطلبات التي أنشأها المورد
            $totalShipments = (clone $shipmentsBaseQuery)->count();
            
            // الشحنات قيد الانتظار: الشحنات في حالة pending فقط (في انتظار الموافقة)
            // نحسب فقط الشحنات التي لها supplier_id مباشر لأن pending عادة لا يكون لها supplier_id من خلال hospital
            $pendingShipments = ExternalSupplyRequest::where('supplier_id', $user->supplier_id)
                ->where('status', 'pending')
                ->count();
            
            // طلبات التوريد الخارجية (approved): الشحنات التي حالتها approved وتم إرسالها للمورد
            // هذه هي الشحنات التي تظهر بحالة "جديد" في واجهة المورد
            $approvedShipments = (clone $shipmentsBaseQuery)
                ->where('status', 'approved')
                ->count();
            
            // الشحنات المكتملة (fulfilled): الشحنات التي تم إرسالها من المورد
            $fulfilledShipments = (clone $shipmentsBaseQuery)
                ->where('status', 'fulfilled')
                ->count();
            
            // الشحنات المرفوضة (rejected): الشحنات التي تم رفضها من المورد
            $rejectedShipments = (clone $shipmentsBaseQuery)
                ->where('status', 'rejected')
                ->count();

            // حساب إحصائيات الأدوية
            // إجمالي الأدوية: عدد الأدوية المختلفة في مخزون المورد (حسب drug_id)
            $totalDrugIds = Inventory::where('supplier_id', $supplierId)
                ->whereNull('warehouse_id')
                ->whereNull('pharmacy_id')
                ->distinct()
                ->pluck('drug_id')
                ->toArray();
            
            $totalDrugs = count($totalDrugIds);
            
            // أدوية منخفضة المخزون: الأدوية التي كمية المخزون الحالية أقل من الحد الأدنى
            // نحسب الأدوية التي لديها current_quantity < minimum_level أو current_quantity = 0
            $lowStockDrugIds = Inventory::where('supplier_id', $supplierId)
                ->whereNull('warehouse_id')
                ->whereNull('pharmacy_id')
                ->where(function($query) {
                    $query->whereColumn('current_quantity', '<', 'minimum_level')
                          ->orWhere('current_quantity', '<=', 0);
                })
                ->distinct()
                ->pluck('drug_id')
                ->toArray();
            
            $lowStockDrugs = count($lowStockDrugIds);

            // حساب طلبات التوريد الداخلية (InternalSupplyRequest)
            // يجب أن تساوي عدد الطلبات في Supplier/requests
            // نفس المنطق المستخدم في ShipmentSupplierController::index
            $internalSupplyRequestsQuery = ExternalSupplyRequest::where(function($query) use ($user) {
                    // البحث عن الطلبات التي لها supplier_id مطابق لـ supplier_id الخاص بالمستخدم المورد
                    $query->where('supplier_id', $user->supplier_id)
                          // أو البحث عن الطلبات من المستشفيات المرتبطة بنفس المورد
                          ->orWhereHas('hospital', function($q) use ($user) {
                              $q->where('supplier_id', $user->supplier_id);
                          });
                })
                ->where('status', '!=', 'pending'); // استبعاد الشحنات التي حالتها pending
            
            // تطبيق نفس فلترة الشحنات المرفوضة كما في ShipmentSupplierController
            $internalSupplyRequests = $internalSupplyRequestsQuery->get()
                ->filter(function($shipment) {
                    // استبعاد الشحنات المرفوضة من قبل مدير المستشفى
                    // نعرض فقط الشحنات المرفوضة من قبل المورد نفسه
                    if ($shipment->status !== 'rejected') {
                        return true; // نعرض جميع الشحنات غير المرفوضة
                    }
                    
                    // إذا كانت الحالة rejected، نتحقق من آخر سجل رفض
                    $lastRejectionLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                        ->where('record_id', $shipment->id)
                        ->where(function($q) {
                            $q->where('action', 'supplier_reject_external_supply_request')
                              ->orWhere('action', 'hospital_admin_reject_external_supply_request');
                        })
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    // نعرض فقط إذا كان آخر رفض من المورد
                    return $lastRejectionLog && $lastRejectionLog->action === 'supplier_reject_external_supply_request';
                })
                ->count();

            $stats = [
                'totalShipments' => $totalShipments, // إجمالي الطلبات الخارجية (ExternalSupplyRequest)
                'internalSupplyRequests' => $internalSupplyRequests, // طلبات التوريد الداخلية (InternalSupplyRequest)
                'pendingShipments' => $pendingShipments,
                'approvedShipments' => $approvedShipments,
                'fulfilledShipments' => $fulfilledShipments,
                'rejectedShipments' => $rejectedShipments,
                'totalDrugs' => $totalDrugs,
                'lowStockDrugs' => $lowStockDrugs,
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
            // تشمل: الطلبات التي أنشأها المورد + الطلبات من المستشفيات المرتبطة بالمورد
            $externalRequestIds = ExternalSupplyRequest::where(function($q) use ($user, $supplierId) {
                    $q->where('supplier_id', $supplierId)
                      ->orWhere('requested_by', $user->id)
                      // إضافة: الطلبات من المستشفيات المرتبطة بنفس المورد
                      ->orWhereHas('hospital', function($hq) use ($supplierId) {
                          $hq->where('supplier_id', $supplierId);
                      });
                })
                ->pluck('id')
                ->toArray();

            // قائمة العمليات المسموح بها (فقط عمليات المورد)
            $allowedActions = [
                // طلبات التوريد الخارجية (external)
                'supplier_create_external_supply_request',
                'supplier_confirm_external_supply_request',
                'supplier_reject_external_supply_request',
                'supplier_confirm_external_delivery',
                'supplier_confirm_receipt',
                // طلبات التوريد الداخلية (internal) - Supplier/supply-requests
                'supplier_create_internal_supply_request',  // إنشاء طلب توريد داخلي
                'supplier_confirm_internal_receipt',        // تأكيد استلام طلب توريد داخلي
            ];

            // جلب جميع العمليات التي قام بها المستخدم الحالي أو المتعلقة بطلبات المورد
            // أولاً: العمليات التي قام بها المستخدم الحالي نفسه
            $operations = AuditLog::with('user:id,full_name')
                ->whereIn('action', $allowedActions)
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($log) {
                    $opType = $this->translateOperationType($log->action);

                    // إلحاق رقم الشحنة/الطلب بجانب نوع العملية، مثلاً: إنشاء طلب توريد داخلي (INT-200)
                    if ($log->record_id) {
                        if ($log->table_name === 'internal_supply_request') {
                            $opType .= ' (INT-' . $log->record_id . ')';
                        } elseif ($log->table_name === 'external_supply_request') {
                            $opType .= ' (EXT-' . $log->record_id . ')';
                        }
                    }

                    $desc = $opType;

                    // محاولة استخراج معلومات إضافية من new_values
                    if (!empty($log->new_values)) {
                        $decoded = json_decode($log->new_values, true);
                        if (is_array($decoded)) {
                            if (isset($decoded['hospital_id'])) {
                                $hospital = \App\Models\Hospital::find($decoded['hospital_id']);
                                if ($hospital) {
                                    $desc .= ' - ' . $hospital->name;
                                }
                            } elseif (isset($decoded['hospital_name'])) {
                                $desc .= ' - ' . $decoded['hospital_name'];
                            } elseif (isset($decoded['rejectionReason'])) {
                                $desc .= ' - السبب: ' . $decoded['rejectionReason'];
                            }
                        }
                    }

                    return [
                        'id' => $log->id,
                        'operationType' => $opType,
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
            // عمليات المورد (external)
            'supplier_create_external_supply_request' => 'إنشاء طلب توريد',
            'supplier_confirm_external_supply_request' => 'تأكيد وإرسال شحنة',
            'supplier_reject_external_supply_request' => 'رفض طلب شحنة',
            'supplier_approve_external_supply_request' => 'موافقة على طلب توريد',
            'supplier_confirm_external_delivery' => 'تأكيد استلام الشحنة',
            'supplier_confirm_receipt' => 'تأكيد استلام شحنة',
            // عمليات المورد (internal) - Supplier/supply-requests
            'supplier_create_internal_supply_request' => 'إنشاء طلب توريد داخلي',
            'supplier_confirm_internal_receipt' => 'تأكيد استلام طلب توريد داخلي',
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
