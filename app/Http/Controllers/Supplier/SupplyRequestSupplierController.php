<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\Hospital;
use App\Models\AuditLog;
use App\Http\Requests\Supplier\CreateSupplyRequestRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplyRequestSupplierController extends BaseApiController
{
    /**
     * عرض قائمة طلبات التوريد التي أنشأها المورد
     * GET /api/supplier/supply-requests
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // جلب الطلبات التي أنشأها المورد (الطلبات التي قام المورد بطلب توريد لها)
            $requests = ExternalSupplyRequest::with([
                'hospital:id,name,city',
                'requester:id,full_name',
                'approver:id,full_name',
                'items.drug:id,name'
            ])
                ->where('requested_by', $user->id)
                ->whereIn('status', ['pending', 'approved', 'fulfilled', 'rejected'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'hospitalName' => $request->hospital->name ?? 'غير محدد',
                        'hospitalCode' => $request->hospital->code ?? '',
                        'status' => $this->translateStatus($request->status),
                        'statusOriginal' => $request->status,
                        'itemsCount' => $request->items->count(),
                        'createdAt' => $request->created_at->format('Y/m/d'),
                        'approvedBy' => $request->approver?->full_name ?? 'مدير المستشفى',
                    ];
                });

            return $this->sendSuccess($requests, 'تم جلب طلبات التوريد بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Supply Requests Index Error');
        }
    }

    /**
     * عرض تفاصيل طلب توريد محدد
     * GET /api/supplier/supply-requests/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplyRequest = ExternalSupplyRequest::with([
                'hospital:id,name,city,address,phone',
                'requester:id,full_name,email,phone',
                'items.drug:id,name,category,form,strength,unit',
            ])
                ->where('requested_by', $user->id)
                ->findOrFail($id);

            $data = [
                'id' => $supplyRequest->id,
                'hospital' => [
                    'id' => $supplyRequest->hospital->id,
                    'name' => $supplyRequest->hospital->name,
                    'city' => $supplyRequest->hospital->city,
                    'address' => $supplyRequest->hospital->address,
                    'phone' => $supplyRequest->hospital->phone,
                ],
                'requestedBy' => [
                    'name' => $supplyRequest->requester->full_name ?? 'غير محدد',
                    'email' => $supplyRequest->requester->email ?? '',
                    'phone' => $supplyRequest->requester->phone ?? '',
                ],
                'department' => $supplyRequest->hospital->name ?? 'غير محدد',
                'status' => $this->translateStatus($supplyRequest->status),
                'statusOriginal' => $supplyRequest->status,
                'items' => $supplyRequest->items->map(function ($item) {
                    $drug = $item->drug;
                    // التأكد من استخراج الكمية بشكل صحيح
                    // الحقل في قاعدة البيانات هو requested_qty
                    $requestedQty = (int) ($item->requested_qty ?? 0);
                    $approvedQty = $item->approved_qty !== null ? (int) $item->approved_qty : null;
                    
                    return [
                        'id' => $item->id,
                        'drugId' => $item->drug_id,
                        'name' => $drug->name ?? 'غير محدد',
                        'drugName' => $drug->name ?? 'غير محدد',
                        'category' => $drug
                            ? (is_object($drug->category)
                                ? ($drug->category->name ?? $drug->category)
                                : ($drug->category ?? 'غير محدد'))
                            : 'غير محدد',
                        'requestedQuantity' => (int) $requestedQty,
                        'requested_qty' => (int) $requestedQty,
                        'requestedQty' => (int) $requestedQty,
                        'approvedQuantity' => $approvedQty !== null ? (int) $approvedQty : null,
                        'approved_qty' => $approvedQty !== null ? (int) $approvedQty : null,
                        'approvedQty' => $approvedQty !== null ? (int) $approvedQty : null,
                        'quantity' => (int) $requestedQty,
                        'strength' => $drug->strength ?? null,
                        'dosage' => $drug->strength ?? null,
                        'form' => $drug->form ?? null,
                        'type' => $drug->form ?? 'Tablet',
                        'unit' => $drug->unit ?? 'وحدة',
                    ];
                }),
                'createdAt' => $supplyRequest->created_at->format('Y/m/d H:i'),
                'notes' => $supplyRequest->notes ?? '',
            ];

            return $this->sendSuccess($data, 'تم جلب تفاصيل الطلب بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Supply Request Show Error');
        }
    }

    /**
     * إنشاء طلب توريد جديد
     * POST /api/supplier/supply-requests
     */
    public function store(CreateSupplyRequestRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // التحقق من وجود المستشفى
            $hospital = Hospital::findOrFail($request->input('hospital_id'));

            // إنشاء طلب التوريد
            $supplyRequest = ExternalSupplyRequest::create([
                'hospital_id' => $hospital->id,
                'supplier_id' => $user->supplier_id,
                'requested_by' => $user->id,
                'status' => 'pending',
                'notes' => $request->input('notes'),
                'priority' => $request->input('priority', 'normal'),
            ]);

            // إضافة الأدوية المطلوبة
            $items = $request->input('items', []);

            // تجميع البنود حسب `drug_id` وجمع الكميات لتجنب إدخالات مكررة
            $grouped = [];
            foreach ($items as $item) {
                $drugId = $item['drug_id'] ?? null;
                $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                if (!$drugId || $qty <= 0) {
                    continue;
                }
                if (!isset($grouped[$drugId])) {
                    $grouped[$drugId] = 0;
                }
                $grouped[$drugId] += $qty;
            }

            foreach ($grouped as $drugId => $totalQty) {
                ExternalSupplyRequestItem::create([
                    'request_id' => $supplyRequest->id,
                    'drug_id' => $drugId,
                    'requested_qty' => $totalQty,
                ]);
            }

            // تسجيل العملية في AuditLog
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospital->id,
                    'action' => 'supplier_create_external_supply_request',
                    'table_name' => 'external_supply_request',
                    'record_id' => $supplyRequest->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'request_id' => $supplyRequest->id,
                        'hospital_id' => $hospital->id,
                        'hospital_name' => $hospital->name,
                        'supplier_id' => $user->supplier_id,
                        'status' => 'pending',
                        'notes' => $request->input('notes'),
                        'items_count' => count($grouped),
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to create audit log for supplier supply request', [
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $supplyRequest->id,
                'status' => $this->translateStatus($supplyRequest->status),
            ], 'تم إنشاء طلب التوريد بنجاح', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Supplier Create Supply Request Error');
        }
    }

    /**
     * جلب قائمة المستشفيات المتاحة
     * GET /api/supplier/hospitals
     */
    public function hospitals(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // جلب المستشفيات التابعة لهذا المورد أو جميع المستشفيات النشطة
            $hospitals = Hospital::where('status', 'active')
                ->where(function ($query) use ($user) {
                    $query->where('supplier_id', $user->supplier_id)
                        ->orWhereNull('supplier_id');
                })
                ->orderBy('name')
                ->get()
                ->map(function ($hospital) {
                    return [
                        'id' => $hospital->id,
                        'name' => $hospital->name,
                        'code' => $hospital->code,
                        'city' => $hospital->city,
                        'type' => $hospital->type,
                    ];
                });

            return $this->sendSuccess($hospitals, 'تم جلب قائمة المستشفيات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Hospitals Error');
        }
    }

    /**
     * ترجمة حالة الطلب
     */
    private function translateStatus($status)
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'approved' => 'جديد',
            'fulfilled' => 'تم التنفيذ',
            'rejected' => 'مرفوض',
        ];

        return $statuses[$status] ?? $status;
    }
}
