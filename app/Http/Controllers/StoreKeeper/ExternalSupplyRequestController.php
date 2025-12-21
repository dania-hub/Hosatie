<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\AuditLog;

class ExternalSupplyRequestController extends BaseApiController
{
    // GET /api/storekeeper/supply-requests
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // جلب جميع الطلبات التي أنشأها هذا المستخدم
        $requests = ExternalSupplyRequest::with(['supplier', 'items.drug'])
            ->where('requested_by', $user->id)
            ->where('hospital_id', $user->hospital_id)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $requests->map(function ($req) {
            return [
                'id'                => $req->id,
                'shipmentNumber'    => 'EXT-' . $req->id,
                'requestDate'       => $req->created_at ? $req->created_at->format('Y/m/d') : '',
                'requestDateFull'   => $req->created_at ? $req->created_at->toIso8601String() : null,
                'status'            => $req->status,
                'requestStatus'     => $this->mapStatusToArabic($req->status),
                'requestingDepartment' => $req->supplier->name ?? 'مورد غير محدد',
                'department'        => [
                    'name' => $req->supplier->name ?? 'مورد غير محدد',
                ],
                'items'             => $req->items->map(function ($item) {
                    return [
                        'id'        => $item->id,
                        'drugId'    => $item->drug_id,
                        'drugName'  => $item->drug->name ?? 'دواء غير معروف',
                        'requested' => $item->requested_qty,
                        'approved'  => $item->approved_qty,
                        'fulfilled' => $item->fulfilled_qty,
                    ];
                }),
                'notes'             => null,
                'createdAt'         => $req->created_at ? $req->created_at->toIso8601String() : null,
                'updatedAt'         => $req->updated_at ? $req->updated_at->toIso8601String() : null,
            ];
        });

        return response()->json($data);
    }

    // POST /api/storekeeper/supply-requests
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التحقق من وجود hospital_id
        if (!$user->hospital_id) {
            return response()->json([
                'message' => 'فشل في إنشاء طلب التوريد الخارجي',
                'error'   => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

        $validated = $request->validate([
            'items'                   => 'required|array|min:1',
            'items.*.drug_id'         => 'required|exists:drug,id',
            'items.*.requested_qty'   => 'required|integer|min:1',
            'supplier_id'             => 'nullable|exists:supplier,id',
        ]);

        DB::beginTransaction();

        try {
            // التحقق من وجود الأدوية قبل إنشاء الطلب
            foreach ($validated['items'] as $item) {
                $drugExists = \App\Models\Drug::where('id', $item['drug_id'])->exists();
                if (!$drugExists) {
                    throw new \Exception("الدواء برقم {$item['drug_id']} غير موجود في قاعدة البيانات");
                }
            }

            // إنشاء الطلب الرئيسي
            $externalRequest = ExternalSupplyRequest::create([
                'hospital_id' => $user->hospital_id,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'requested_by'=> $user->id,
                'status'      => 'pending',
            ]);

            if (!$externalRequest || !$externalRequest->id) {
                throw new \Exception("فشل في إنشاء سجل الطلب في قاعدة البيانات");
            }

            // عناصر الطلب
            foreach ($validated['items'] as $item) {
                $itemCreated = ExternalSupplyRequestItem::create([
                    'request_id'    => $externalRequest->id,
                    'drug_id'       => $item['drug_id'],
                    'requested_qty' => $item['requested_qty'],
                    'approved_qty'  => null,
                    'fulfilled_qty' => null,
                ]);
                
                if (!$itemCreated || !$itemCreated->id) {
                    throw new \Exception("فشل في إنشاء عنصر الطلب للدواء برقم {$item['drug_id']}");
                }
            }

            DB::commit();

            // 🟢 تسجيل العملية في audit_log
            try {
                AuditLog::create([
                    'user_id'    => $user->id,
                    'hospital_id' => $user->hospital_id,
                    'action'     => 'create_external_supply_request',
                    'table_name' => 'external_supply_request',
                    'record_id'  => $externalRequest->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'supplier_id' => $validated['supplier_id'] ?? null,
                        'items'       => $validated['items'],
                        'status'      => 'pending',
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                // في حالة فشل الـ logging، نستمر (لا نريد أن نفشل العملية بسبب الـ logging)
                \Log::warning('Failed to log external supply request creation', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'message' => 'تم إنشاء طلب التوريد الخارجي بنجاح',
                'data'    => [
                    'requestNumber' => 'EXT-' . $externalRequest->id,
                    'id' => $externalRequest->id,
                    'status' => $externalRequest->status,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل في التحقق من البيانات',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('External Supply Request Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'user_id' => $user->id ?? null,
                'hospital_id' => $user->hospital_id ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $errorMessage = config('app.debug') 
                ? $e->getMessage() . ' (File: ' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : 'حدث خطأ أثناء حفظ الطلب';

            return response()->json([
                'message' => 'فشل في إنشاء طلب التوريد الخارجي',
                'error'   => $errorMessage,
            ], 500);
        }
    }

    /**
     * ترجمة حالة الطلب إلى العربية
     */
    private function mapStatusToArabic(string $status): string
    {
        return match ($status) {
            'pending'   => 'قيد الانتظار',
            'approved'  => 'قيد الاستلام',
            'fulfilled' => 'تم الإستلام',
            'rejected'  => 'مرفوضة',
            'cancelled' => 'ملغاة',
            default     => $status,
        };
    }
}
