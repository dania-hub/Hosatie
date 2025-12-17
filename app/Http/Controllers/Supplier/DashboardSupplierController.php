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

            $operations = AuditLog::with('user:id,full_name')
                ->where('user_id', $user->id)
                ->orWhere('description', 'like', '%supplier%')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'operationType' => $this->translateOperationType($log->action),
                        'description' => $log->description,
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