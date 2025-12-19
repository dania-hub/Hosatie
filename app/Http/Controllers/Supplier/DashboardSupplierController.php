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

            $stats = [
                'totalShipments' => ExternalSupplyRequest::where('supplier_id', $supplierId)->count(),
                'pendingShipments' => ExternalSupplyRequest::where('supplier_id', $supplierId)
                    ->where('status', 'pending')->count(),
                'approvedShipments' => ExternalSupplyRequest::where('supplier_id', $supplierId)
                    ->where('status', 'approved')->count(),
                'fulfilledShipments' => ExternalSupplyRequest::where('supplier_id', $supplierId)
                    ->where('status', 'fulfilled')->count(),
                'rejectedShipments' => ExternalSupplyRequest::where('supplier_id', $supplierId)
                    ->where('status', 'rejected')->count(),
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
            // - logs created by this user
            // - or logs where `new_values`/`old_values` JSON includes the current supplier_id
            // - or logs related to tables mentioning supplier
            $supplierId = $user->supplier_id;

            $operations = AuditLog::with('user:id,full_name')
                ->where(function ($q) use ($user, $supplierId) {
                    $q->where('user_id', $user->id)
                        ->orWhere('table_name', 'like', '%supplier%')
                        ->orWhere('new_values', 'like', '%"supplier_id":' . ($supplierId ?? 'NULL') . '%')
                        ->orWhere('old_values', 'like', '%"supplier_id":' . ($supplierId ?? 'NULL') . '%');
                })
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($log) {
                    $desc = $log->table_name ? ($log->action . ' on ' . $log->table_name) : $log->action;

                    // If new_values contains useful JSON, try to extract a readable summary
                    if (!empty($log->new_values)) {
                        $decoded = json_decode($log->new_values, true);
                        if (is_array($decoded)) {
                            if (isset($decoded['name'])) {
                                $desc = $log->action . ' ' . $decoded['name'];
                            } elseif (isset($decoded['id'])) {
                                $desc = $log->action . ' #' . $decoded['id'];
                            }
                        } elseif (is_string($log->new_values) && empty($desc)) {
                            $desc = substr($log->new_values, 0, 200);
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
        ];

        return $types[$action] ?? $action;
    }
}
