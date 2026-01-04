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
     */
    public function operations(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // Filter logs for this supplier more precisely:
            // 1. All logs created by this supplier user
            // 2. All logs related to external_supply_requests where supplier_id matches or requested_by matches
            // 3. All logs related to inventories where supplier_id matches
            $supplierId = $user->supplier_id;

            // جلب IDs للطلبات المتعلقة بهذا المورد
            $externalRequestIds = ExternalSupplyRequest::where(function($q) use ($user, $supplierId) {
                    $q->where('supplier_id', $supplierId)
                      ->orWhere('requested_by', $user->id);
                })
                ->pluck('id')
                ->toArray();

            // جلب IDs للمخزون المتعلق بهذا المورد
            $inventoryIds = Inventory::where('supplier_id', $supplierId)
                ->pluck('id')
                ->toArray();

            $operations = AuditLog::with('user:id,full_name')
                ->where(function ($q) use ($user, $supplierId, $externalRequestIds, $inventoryIds) {
                    // العمليات التي قام بها المستخدم المورد نفسه
                    $q->where('user_id', $user->id);
                    
                    // العمليات المتعلقة بـ external_supply_requests الخاصة بهذا المورد
                    if (!empty($externalRequestIds)) {
                        $q->orWhere(function($subQ) use ($externalRequestIds) {
                            $subQ->where('table_name', 'external_supply_request')
                                 ->whereIn('record_id', $externalRequestIds);
                        });
                    }
                    
                    // العمليات المتعلقة بـ inventories الخاصة بهذا المورد
                    if (!empty($inventoryIds)) {
                        $q->orWhere(function($subQ) use ($inventoryIds) {
                            $subQ->where('table_name', 'inventories')
                                 ->whereIn('record_id', $inventoryIds);
                        });
                    }
                })
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($log) {
                    $desc = $this->translateOperationType($log->action);
                    
                    // إنشاء وصف أكثر وضوحاً بناءً على نوع العملية والجدول
                    if ($log->table_name === 'external_supply_request' && $log->record_id) {
                        $desc .= ' - رقم الشحنة: EXT-' . $log->record_id;
                    } elseif ($log->table_name === 'inventories' && $log->record_id) {
                        $desc .= ' - رقم السجل: ' . $log->record_id;
                    }

                    // محاولة استخراج معلومات إضافية من new_values
                    if (!empty($log->new_values)) {
                        $decoded = json_decode($log->new_values, true);
                        if (is_array($decoded)) {
                            if (isset($decoded['name'])) {
                                $desc .= ' - ' . $decoded['name'];
                            } elseif (isset($decoded['request_id'])) {
                                $desc .= ' - رقم الطلب: ' . $decoded['request_id'];
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
            'supplier_confirm_external_supply_request' => 'قبول طلب توريد خارجي',
            'supplier_reject_external_supply_request' => 'رفض طلب توريد خارجي',
            'supplier_approve_external_supply_request' => 'موافقة مورد على طلب توريد خارجي',
            'create_external_supply_request' => 'إنشاء طلب توريد خارجي',
            'storekeeper_confirm_external_delivery' => 'تأكيد استلام شحنة خارجية',
            'hospital_admin_reject_external_supply_request' => 'رفض طلب توريد خارجي (مدير مستشفى)',
            'hospital_admin_update_external_supply_request_notes' => 'تحديث ملاحظات طلب توريد خارجي',
        ];

        return $types[$action] ?? $action;
    }
}
