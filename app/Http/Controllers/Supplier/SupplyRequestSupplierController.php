<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\Hospital;
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

            $requests = ExternalSupplyRequest::with([
                'hospital:id,name,code,city',
                'requester:id,full_name',
                'items.drug:id,name,code'
            ])
                ->where('supplier_id', $user->supplier_id)
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
                'hospital:id,name,code,city,address,phone',
                'requester:id,full_name,email,phone',
                'items.drug:id,name,code,category',
            ])
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            $data = [
                'id' => $supplyRequest->id,
                'hospital' => [
                    'id' => $supplyRequest->hospital->id,
                    'name' => $supplyRequest->hospital->name,
                    'code' => $supplyRequest->hospital->code,
                    'city' => $supplyRequest->hospital->city,
                    'address' => $supplyRequest->hospital->address,
                    'phone' => $supplyRequest->hospital->phone,
                ],
                'requestedBy' => [
                    'name' => $supplyRequest->requester->full_name ?? 'غير محدد',
                    'email' => $supplyRequest->requester->email ?? '',
                    'phone' => $supplyRequest->requester->phone ?? '',
                ],
                'status' => $this->translateStatus($supplyRequest->status),
                'statusOriginal' => $supplyRequest->status,
                'items' => $supplyRequest->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'drugId' => $item->drug_id,
                        'drugName' => $item->drug->name ?? 'غير محدد',
                        'drugCode' => $item->drug->code ?? '',
                        'category' => $item->drug
                            ? (is_object($item->drug->category)
                                ? ($item->drug->category->name ?? $item->drug->category)
                                : ($item->drug->category ?? 'غير محدد'))
                            : 'غير محدد',
                        'requestedQuantity' => $item->requested_quantity,
                        'approvedQuantity' => $item->approved_quantity,
                    ];
                }),
                'createdAt' => $supplyRequest->created_at->format('Y/m/d H:i'),
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
            foreach ($items as $item) {
                ExternalSupplyRequestItem::create([
                    'external_supply_request_id' => $supplyRequest->id,
                    'drug_id' => $item['drug_id'],
                    'requested_quantity' => $item['quantity'],
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
            'approved' => 'تم الموافقة',
            'fulfilled' => 'تم التنفيذ',
            'rejected' => 'مرفوض',
        ];

        return $statuses[$status] ?? $status;
    }
}
