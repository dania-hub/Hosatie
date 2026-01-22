<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory;
use App\Models\AuditLog;
use App\Models\Warehouse;
use App\Models\Department;
use App\Services\StaffNotificationService;

class InternalSupplyRequestController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}
    /**
     * جلب الملاحظات من audit_log
     */
    private function getNotesFromAuditLog($requestId)
    {
        $notes = [
            'storekeeperNotes' => null,  // ملاحظة عند إنشاء الطلب من pharmacist/department
            'storekeeperNotesSource' => null, // مصدر الملاحظة: 'pharmacist' أو 'department'
            'supplierNotes' => null,     // ملاحظة عند إرسال الشحنة من storekeeper
            'confirmationNotes' => null,  // ملاحظة عند تأكيد الاستلام من pharmacist/department
            'confirmationNotesSource' => null, // مصدر الملاحظة: 'pharmacist' أو 'department'
            'rejectionReason' => null    // سبب الرفض من storekeeper
        ];

        // جلب جميع سجلات audit_log لهذا الطلب
        $auditLogs = AuditLog::where('table_name', 'internal_supply_request')
            ->where('record_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($auditLogs as $log) {
            $newValues = json_decode($log->new_values, true);
            if (!$newValues) continue;

            // ملاحظة عند إنشاء الطلب
            if (in_array($log->action, ['إنشاء طلب توريد', 'pharmacist_create_supply_request', 'department_create_supply_request']) 
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['storekeeperNotes'] = $newValues['notes'];
                if ($log->action === 'pharmacist_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'department';
                }
            }

            // ملاحظة عند إرسال الشحنة من storekeeper
            if ($log->action === 'storekeeper_confirm_internal_request' 
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['supplierNotes'] = $newValues['notes'];
            }

            // ملاحظة عند تأكيد الاستلام
            if (in_array($log->action, ['pharmacist_confirm_internal_receipt', 'department_confirm_internal_receipt'])
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['confirmationNotes'] = $newValues['notes'];
                if ($log->action === 'pharmacist_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'department';
                }
            }

            // سبب الرفض من storekeeper
            if (in_array($log->action, ['رفض طلب توريد داخلي', 'storekeeper_reject_internal_request', 'reject'])
                && isset($newValues['rejectionReason']) && !empty($newValues['rejectionReason'])) {
                $notes['rejectionReason'] = $newValues['rejectionReason'];
            }
        }

        return $notes;
    }

    // GET /api/storekeeper/shipments
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التأكد من وجود hospital_id للمستخدم
        if (!$user->hospital_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 403);
        }

        // عرض الطلبات التي تم إرسالها من الصيدليات التابعة لنفس المستشفى
        $requests = InternalSupplyRequest::with(['pharmacy', 'requester.department'])
            ->whereHas('pharmacy', function($query) use ($user) {
                $query->where('hospital_id', $user->hospital_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $requests->map(function ($req) {
            // جلب الملاحظات من audit_log
            $notes = $this->getNotesFromAuditLog($req->id);
            
            // تحديد اسم الجهة الطالبة حسب نوع المستخدم
            $requestingDepartmentName = 'غير محدد';
            
            // إعادة تحميل العلاقات للتأكد من أنها محملة
            $req->loadMissing(['requester.department', 'pharmacy']);
            
            // أولاً: محاولة جلب اسم القسم/الصيدلية من audit_log (القسم/الصيدلية وقت إنشاء الطلب)
            $creationLog = AuditLog::where('table_name', 'internal_supply_request')
                ->where('record_id', $req->id)
                ->whereIn('action', ['department_create_supply_request', 'pharmacist_create_supply_request', 'إنشاء طلب توريد'])
                ->orderBy('created_at', 'asc')
                ->first();
            
            if ($creationLog && $creationLog->new_values) {
                $newValues = json_decode($creationLog->new_values, true);
                // إذا كان الطلب من department، استخدم department_name
                if (isset($newValues['department_name']) && !empty($newValues['department_name'])) {
                    $requestingDepartmentName = $newValues['department_name'];
                }
                // إذا كان الطلب من pharmacist، استخدم pharmacy_name
                elseif (isset($newValues['pharmacy_name']) && !empty($newValues['pharmacy_name'])) {
                    $requestingDepartmentName = $newValues['pharmacy_name'];
                }
            }
            
            // إذا لم نجد اسم القسم في audit_log، نستخدم الطريقة القديمة (للتوافق مع الطلبات القديمة)
            if ($requestingDepartmentName === 'غير محدد' && $req->requester) {
                // إذا كان الطلب من department_head أو department_admin، نعرض اسم القسم
                if (in_array($req->requester->type, ['department_head', 'department_admin'])) {
                    // أولاً: البحث عن القسم الذي يكون head_user_id = requester->id (الأولوية للـ department_head)
                    $department = Department::where('head_user_id', $req->requester->id)->first();
                    if ($department) {
                        $requestingDepartmentName = $department->name;
                    } 
                    // ثانياً: محاولة جلب القسم من العلاقة
                    elseif ($req->requester->department) {
                        $requestingDepartmentName = $req->requester->department->name;
                    } 
                    // ثالثاً: محاولة جلب القسم من department_id مباشرة
                    elseif ($req->requester->department_id) {
                        $department = Department::find($req->requester->department_id);
                        if ($department) {
                            $requestingDepartmentName = $department->name;
                        }
                    }
                } 
                // إذا كان الطلب من pharmacist، نعرض اسم الصيدلية
                elseif ($req->requester->type === 'pharmacist' && $req->pharmacy) {
                    $requestingDepartmentName = $req->pharmacy->name;
                }
            }
            
            // fallback: إذا لم يتم تحديد الاسم بعد وكان هناك pharmacy، نستخدمه
            if ($requestingDepartmentName === 'غير محدد' && $req->pharmacy) {
                $requestingDepartmentName = $req->pharmacy->name;
            }
            
            return [
                'id'                => $req->id,
                'shipmentNumber'    => 'INT-' . $req->id, // رقم شحنة افتراضي
                'requestDate'       => $req->created_at,
                'status'            => $req->status,
                'requestStatus'     => $this->mapStatusToArabic($req->status),
                'requestingDepartment' => $requestingDepartmentName,
                'department'        => [
                    'name' => $requestingDepartmentName,
                ],
                'items'             => [], // ستُجلب بالتفصيل في show
                'notes'             => null, // تم إزالة عمود notes من الجدول
                'createdAt'         => $req->created_at,
                'updatedAt'         => $req->updated_at,
                'rejectionReason'   => $notes['rejectionReason'], // جلب سبب الرفض من audit_log
                'confirmedBy'       => null,
                'confirmedAt'       => null,
            ];
        });

        return response()->json($data);
    }

    private function mapStatusToArabic(string $status): string
    {
        return match ($status) {
            'pending'   => 'جديد',
            'approved'  => 'قيد الاستلام',
            'fulfilled' => 'تم الإستلام',
            'rejected'  => 'مرفوضة',
            'cancelled' => 'ملغاة',
            default     => $status,
        };
    }
        // GET /api/storekeeper/shipments/{id}
    public function show(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التأكد من وجود hospital_id للمستخدم
        if (!$user->hospital_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 403);
        }

        try {
            // التأكد من أن الطلب ينتمي لنفس المستشفى
            $req = InternalSupplyRequest::with(['pharmacy', 'requester.department', 'items.drug'])
                ->whereHas('pharmacy', function($query) use ($user) {
                    $query->where('hospital_id', $user->hospital_id);
                })
                ->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'الطلب غير موجود أو لا ينتمي لنفس المستشفى'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error loading shipment details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $id,
                'user_id' => $user->id,
                'hospital_id' => $user->hospital_id
            ]);
            return response()->json([
                'message' => 'فشل في تحميل تفاصيل الشحنة',
                'error' => config('app.debug') ? $e->getMessage() : 'حدث خطأ غير متوقع'
            ], 500);
        }

        // جلب warehouse_id الصحيح من المستخدم أو من المستشفى
        $warehouseId = null;
        if ($user->warehouse_id) {
            $warehouseId = $user->warehouse_id;
        } elseif ($user->hospital_id) {
            // جلب warehouse من المستشفى
            $warehouse = Warehouse::where('hospital_id', $user->hospital_id)->first();
            if ($warehouse) {
                $warehouseId = $warehouse->id;
            }
        }
        
        // إذا لم يتم العثور على warehouse، استخدام الأول المتاح (fallback)
        if (!$warehouseId) {
            $warehouse = Warehouse::first();
            $warehouseId = $warehouse ? $warehouse->id : 1;
        }

        // جلب الملاحظات من audit_log
        $notes = $this->getNotesFromAuditLog($req->id);

        // إعادة تحميل العلاقات للتأكد من أنها محملة
        $req->loadMissing(['requester.department', 'pharmacy']);

        // تحديد اسم الجهة الطالبة حسب نوع المستخدم
        $requestingDepartmentName = 'غير محدد';
        
        // أولاً: محاولة جلب اسم القسم/الصيدلية من audit_log (القسم/الصيدلية وقت إنشاء الطلب)
        $creationLog = AuditLog::where('table_name', 'internal_supply_request')
            ->where('record_id', $req->id)
            ->whereIn('action', ['department_create_supply_request', 'pharmacist_create_supply_request', 'إنشاء طلب توريد'])
            ->orderBy('created_at', 'asc')
            ->first();
        
        if ($creationLog && $creationLog->new_values) {
            $newValues = json_decode($creationLog->new_values, true);
            // إذا كان الطلب من department، استخدم department_name
            if (isset($newValues['department_name']) && !empty($newValues['department_name'])) {
                $requestingDepartmentName = $newValues['department_name'];
            }
            // إذا كان الطلب من pharmacist، استخدم pharmacy_name
            elseif (isset($newValues['pharmacy_name']) && !empty($newValues['pharmacy_name'])) {
                $requestingDepartmentName = $newValues['pharmacy_name'];
            }
        }
        
        // إذا لم نجد اسم القسم في audit_log، نستخدم الطريقة القديمة (للتوافق مع الطلبات القديمة)
        if ($requestingDepartmentName === 'غير محدد' && $req->requester) {
            // إذا كان الطلب من department_head أو department_admin، نعرض اسم القسم
            if (in_array($req->requester->type, ['department_head', 'department_admin'])) {
                // أولاً: البحث عن القسم الذي يكون head_user_id = requester->id (الأولوية للـ department_head)
                $department = Department::where('head_user_id', $req->requester->id)->first();
                if ($department) {
                    $requestingDepartmentName = $department->name;
                } 
                // ثانياً: محاولة جلب القسم من العلاقة
                elseif ($req->requester->department) {
                    $requestingDepartmentName = $req->requester->department->name;
                } 
                // ثالثاً: محاولة جلب القسم من department_id مباشرة
                elseif ($req->requester->department_id) {
                    $department = Department::find($req->requester->department_id);
                    if ($department) {
                        $requestingDepartmentName = $department->name;
                    }
                }
            } 
            // إذا كان الطلب من pharmacist، نعرض اسم الصيدلية
            elseif ($req->requester->type === 'pharmacist' && $req->pharmacy) {
                $requestingDepartmentName = $req->pharmacy->name;
            }
        }
        
        // fallback: إذا لم يتم تحديد الاسم بعد وكان هناك pharmacy، نستخدمه
        if ($requestingDepartmentName === 'غير محدد' && $req->pharmacy) {
            $requestingDepartmentName = $req->pharmacy->name;
        }

        return response()->json([
            'id'             => $req->id,
            'shipmentNumber' => 'INT-' . $req->id,
            'department'     => $requestingDepartmentName,
            'date'           => $req->created_at,
            'status'         => $this->mapStatusToArabic($req->status),
            'notes'          => null, // تم إزالة عمود notes من الجدول
            'storekeeperNotes' => $notes['storekeeperNotes'],
            'storekeeperNotesSource' => $notes['storekeeperNotesSource'],
            'supplierNotes' => $notes['supplierNotes'],
            'confirmationNotes' => $notes['confirmationNotes'],
            'confirmationNotesSource' => $notes['confirmationNotesSource'],
            'rejectionReason' => $notes['rejectionReason'], // جلب سبب الرفض من audit_log
            'items'          => $req->items->map(function ($item) use ($warehouseId, $req) {
                // حساب إجمالي المخزون المتاح لهذا الدواء في المستودع (من جميع الدفعات)
                $availableStock = Inventory::where('warehouse_id', $warehouseId)
                    ->where('drug_id', $item->drug_id)
                    ->sum('current_quantity');
                
                // جلب جميع الطلبات التي بحالة "جديد" (pending) والتي تحتوي نفس الدواء
                $allPendingRequests = DB::table('internal_supply_request_items')
                    ->join('internal_supply_requests', 'internal_supply_request_items.request_id', '=', 'internal_supply_requests.id')
                    ->where('internal_supply_requests.status', 'pending')
                    ->where('internal_supply_request_items.drug_id', $item->drug_id)
                    ->select(
                        'internal_supply_requests.id as request_id', 
                        'internal_supply_request_items.requested_qty'
                    )
                    ->get();
                
                // إجمالي الكمية المطلوبة من جميع الطلبات
                $totalRequestedQty = $allPendingRequests->sum('requested_qty');
                
                // إجمالي الطلبات الأخرى (لأغراض العرض)
                // نحسبها باستبعاد الطلب الحالي من المجموع الكلي (إذا كان موجوداً)
                $totalOtherRequestsQty = $allPendingRequests->where('request_id', '!=', $req->id)->sum('requested_qty');
                
                // حساب الكمية المقترحة باستخدام خوارزمية التوزيع العادل (Max-Min Fairness)
                $suggestedQuantity = 0;
                
                if ($req->status === 'pending') {
                    // إذا المخزون كافي للجميع، نعطي الجميع طلبهم
                    if ($availableStock >= $totalRequestedQty) {
                        $suggestedQuantity = $item->requested_qty;
                    } else {
                        // المخزون غير كافي - توزيع عادل (Max-Min Fairness)
                        // 1. ترتيب الطلبات تصاعدياً حسب الكمية المطلوبة
                        $sortedRequests = $allPendingRequests->sortBy('requested_qty')->values();
                        
                        $remainingStock = $availableStock;
                        $remainingRequestsCount = $sortedRequests->count();
                        $allocations = []; // request_id => allocated_qty
                        
                        foreach ($sortedRequests as $requestItem) {
                            if ($remainingRequestsCount == 0) break;
                            
                            // الحصة العادلة لما تبقى (Stock / N)
                            $fairShare = floor($remainingStock / $remainingRequestsCount);
                            
                            // نمنح الطلب: الأقل بين (حاجته) و (الحصة العادلة)
                            $allocated = min($requestItem->requested_qty, $fairShare);
                            
                            $allocations[$requestItem->request_id] = $allocated;
                            
                            $remainingStock -= $allocated;
                            $remainingRequestsCount--;
                        }
                        
                        // 2. توزيع المتبقي (الكسور أو الفائض) على من يحتاج المزيد
                        if ($remainingStock > 0) {
                            // نعيد التوزيع على الطلبات الكبيرة أولاً
                            foreach ($sortedRequests->sortByDesc('requested_qty') as $requestItem) {
                                if ($remainingStock <= 0) break;
                                
                                $currentAllocated = $allocations[$requestItem->request_id] ?? 0;
                                $stillNeeded = $requestItem->requested_qty - $currentAllocated;
                                
                                if ($stillNeeded > 0) {
                                    $extra = min($remainingStock, $stillNeeded);
                                    $allocations[$requestItem->request_id] += $extra;
                                    $remainingStock -= $extra;
                                }
                            }
                        }
                        
                        $suggestedQuantity = $allocations[$req->id] ?? 0;
                    }
                } else {
                    // إذا لم يكن الطلب جديداً، فلا نحسب كمية مقترحة
                    $suggestedQuantity = 0; 
                }
                
                return [
                    'id'             => $item->id,
                    'drug_id'        => $item->drug_id,
                    'drug_name'      => $item->drug->name ?? '',
                    'name'           => $item->drug->name ?? '', // للتوافق مع المكون
                    'requested_qty'  => $item->requested_qty,
                    'quantity'       => $item->requested_qty, // للتوافق مع المكون
                    'approved_qty'   => $item->approved_qty ?? 0,
                    'fulfilled_qty'  => $item->fulfilled_qty ?? 0,
                    'sentQuantity'   => ($item->approved_qty ?? $suggestedQuantity), // للتوافق مع المكون - استخدام approved_qty إذا كان موجوداً، وإلا suggestedQuantity
                    'receivedQuantity' => $item->fulfilled_qty ?? 0, // للتوافق مع المكون
                    'availableQuantity' => $availableStock, // المخزون المتاح
                    'suggestedQuantity' => $suggestedQuantity, // الكمية المقترحة من الـ API
                    'stock'          => $availableStock, // للتوافق مع المكون
                    'totalOtherRequestsQty' => $totalOtherRequestsQty, // إجمالي طلبات الأدوية الأخرى
                    'strength'       => $item->drug->strength ?? '',
                    'dosage'         => $item->drug->strength ?? '', // للتوافق مع المكون
                    'form'           => $item->drug->form ?? '',
                    'type'           => $item->drug->form ?? '', // للتوافق مع المكون
                    'unit'           => $item->drug->unit ?? 'قرص',
                    'units_per_box'  => $item->drug->units_per_box ?? 1,
                    'unitsPerBox'    => $item->drug->units_per_box ?? 1,
                    'batch_number'   => $item->batch_number,
                    'expiry_date'    => $item->expiry_date,
                ];
            }),
            'confirmationDetails' => null,
        ]);
    }
    // POST /api/storekeeper/shipments/{id}/reject
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التأكد من وجود hospital_id للمستخدم
        if (!$user->hospital_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 403);
        }

        $validated = $request->validate([
            'rejectionReason' => 'required|string|max:1000',
        ]);

        // التأكد من أن الطلب ينتمي لنفس المستشفى
        $req = InternalSupplyRequest::whereHas('pharmacy', function($query) use ($user) {
                $query->where('hospital_id', $user->hospital_id);
            })
            ->findOrFail($id);

        // منع رفض الطلبات في حالة "قيد الاستلام" أو الحالات المغلقة
        if ($req->status !== 'pending') {
            return response()->json([
                'message' => 'لا يمكن رفض طلب في حالة "قيد الاستلام" أو الحالات المغلقة',
            ], 409);
        }

        $req->status = 'rejected';
        // ملاحظة: تم إزالة عمود notes من الجدول، لذا نحفظ سبب الرفض في audit_log فقط
        $req->save();
        
        // تسجيل سبب الرفض في audit_log
        try {
            $rejectedAt = now()->toIso8601String();
            AuditLog::create([
                'user_id'    => $user->id,
                'hospital_id'=> $user->hospital_id,
                'action'     => 'رفض طلب توريد داخلي',
                'table_name' => 'internal_supply_request',
                'record_id'  => $req->id,
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode([
                    'status' => 'rejected',
                    'rejectionReason' => $validated['rejectionReason'],
                    'rejectedAt' => $rejectedAt
                ]),
                'ip_address' => $request->ip(),
            ]);

            // إنشاء إشعار للمستخدم الطالب (requester)
            \Log::info('Attempting to create rejection notification', [
                'requested_by' => $req->requested_by,
                'handeled_by'  => $req->handeled_by,
                'request_id'   => $req->id
            ]);

            if ($req->requester) {
                 $this->notifications->notifyRequesterShipmentRejected($req->requester, $req, $validated['rejectionReason']);
            } else {
                 $requester = \App\Models\User::find($req->requested_by);
                 if ($requester) {
                      $this->notifications->notifyRequesterShipmentRejected($requester, $req, $validated['rejectionReason']);
                 }
            }

        } catch (\Exception $e) {
            \Log::error('Failed to log rejection reason or send notification', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'تم رفض الطلب الداخلي بنجاح']);
    }

    // POST /api/storekeeper/shipments/{id}/confirm
    public function confirm(Request $request, $id)
    {
        \Log::info('=== Starting shipment confirmation ===', ['id' => $id]);
        
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        \Log::info('Validating request data');
        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.id'            => 'required|exists:internal_supply_request_items,id',
            'items.*.sentQuantity'  => 'required|numeric|min:0', // Changed to numeric to support floats if needed
            'items.*.batch_number'  => 'nullable|string|max:100',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        // التأكد من وجود hospital_id للمستخدم
        if (!$user->hospital_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 403);
        }

        \Log::info('Loading request with items', ['id' => $id]);
        // التأكد من أن الطلب ينتمي لنفس المستشفى
        $req = InternalSupplyRequest::with('items.drug')
            ->whereHas('pharmacy', function($query) use ($user) {
                $query->where('hospital_id', $user->hospital_id);
            })
            ->findOrFail($id);

        // منع التأكيد إذا كان الطلب في حالة "قيد الاستلام" أو الحالات المغلقة
        if (in_array($req->status, ['rejected', 'cancelled', 'fulfilled', 'approved'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب في حالة "قيد الاستلام" أو الحالات المغلقة'], 409);
        }

        $pharmacyId = $req->pharmacy_id;
        
        // جلب warehouse_id الصحيح من المستخدم أو من المستشفى
        $warehouseId = null;
        if ($user->warehouse_id) {
            $warehouseId = $user->warehouse_id;
        } elseif ($user->hospital_id) {
            // جلب warehouse من المستشفى
            $warehouse = Warehouse::where('hospital_id', $user->hospital_id)->first();
            if ($warehouse) {
                $warehouseId = $warehouse->id;
            }
        }
        
        // إذا لم يتم العثور على warehouse، استخدام الأول المتاح (fallback)
        if (!$warehouseId) {
            $warehouse = Warehouse::first();
            $warehouseId = $warehouse ? $warehouse->id : 1;
        }

        \Log::info('Starting database transaction');
        DB::beginTransaction();

        try {
            \Log::info('Processing items', ['count' => count($validated['items'])]);
            
            foreach ($validated['items'] as $index => $itemData) {
                $item = $req->items->firstWhere('id', $itemData['id']);
                if (!$item) {
                    continue;
                }

                $qtyNeeded = (float) $itemData['sentQuantity'];
                $batchNumber = $itemData['batch_number'] ?? null;
                
                if ($qtyNeeded > 0) {
                    $drugId = $item->drug_id;
                    $inventoryQuery = Inventory::where('warehouse_id', $warehouseId)
                        ->where('drug_id', $drugId)
                        ->where('current_quantity', '>', 0);
                    
                    // إذا تم تحديد دفعة معينة يدوياً
                    if ($batchNumber) {
                        $inventoryQuery->where('batch_number', $batchNumber);
                        $inventories = $inventoryQuery->get(); // قد يكون هناك سجل واحد أو أكثر (نادر ولكن ممكن)
                    } else {
                        // FEFO (First Expired First Out)
                        // نجلب جميع الدفعات المتوفرة مرتبة حسب تاريخ الانتهاء
                        $inventories = $inventoryQuery->orderBy('expiry_date', 'asc')
                                                      ->orderBy('created_at', 'asc')
                                                      ->get();
                    }

                    if ($inventories->isEmpty()) {
                        DB::rollBack();
                        $drugName = $item->drug ? $item->drug->name : "ID: {$drugId}";
                        return response()->json([
                            'message' => "لا يوجد مخزون للمستودع لهذا الدواء: {$drugName}" . ($batchNumber ? " (الدُفعة: $batchNumber)" : "")
                        ], 404);
                    }

                    // التحقق من كفاية المخزون الإجمالي (فقط إذا لم نكن نستخدم دفعة محددة، أو يمكن التحقق للدُفعة المحددة أيضاً)
                    $totalAvailable = $inventories->sum('current_quantity');
                    if ($totalAvailable < $qtyNeeded) {
                        DB::rollBack();
                        $drugName = $item->drug ? $item->drug->name : "ID: {$drugId}";
                        return response()->json([
                            'message' => "الكمية غير متوفرة في المخزن للدواء: {$drugName}" . ($batchNumber ? " (الدُفعة: $batchNumber)" : "") . " (المتاح الكلي: {$totalAvailable}, المطلوب: {$qtyNeeded})",
                        ], 409);
                    }

                    // توزيع الكمية المطلوبة على الدفعات (Splitting Logic)
                    $batchesUsed = []; // لتخزين الدفعات التي تم السحب منها: [inventory_id, deducted_qty, batch_number, expiry_date]
                    $remainingToDeduct = $qtyNeeded;

                    foreach ($inventories as $inv) {
                        if ($remainingToDeduct <= 0) break;

                        $deducted = min($inv->current_quantity, $remainingToDeduct);
                        
                        // خصم الكمية من المخزون
                        $inv->current_quantity -= $deducted;
                        $inv->save();
                        
                        // التنبيه في حالة انخفاض المخزون
                        try {
                            $this->notifications->checkAndNotifyLowStock($inv);
                        } catch (\Exception $e) {
                            // ignore log here to reduce spam
                        }

                        $remainingToDeduct -= $deducted;
                        
                        $batchesUsed[] = [
                            'batch_number' => $inv->batch_number,
                            'expiry_date' => $inv->expiry_date,
                            'quantity' => $deducted
                        ];
                    }
                    
                    // الآن نقوم بتحديث الـ Items في الطلب
                    // إذا تم استخدام أكثر من دفعة، نقوم بتقسيم الـ item
                    if (count($batchesUsed) > 0) {
                        // استخدام الدفعة الأولى لتحديث السجل الحالي
                        $firstBatch = array_shift($batchesUsed);
                        
                        $item->approved_qty = $firstBatch['quantity'];
                        $item->fulfilled_qty = 0; // لم يستلم بعد
                        $item->batch_number = $firstBatch['batch_number'];
                        $item->expiry_date = $firstBatch['expiry_date'];
                        $item->save();
                        
                        // إنشاء سجلات جديدة لباقي الدفعات
                        foreach ($batchesUsed as $batch) {
                            $newItem = $item->replicate();
                            $newItem->request_id = $req->id; // Ensure link to same request
                            $newItem->requested_qty = 0; // هذا عنصر منقسم، ليس طلباً جديداً (لتجنب تضخيم الإجماليات)
                            $newItem->approved_qty = $batch['quantity'];
                            $newItem->fulfilled_qty = 0;
                            $newItem->batch_number = $batch['batch_number'];
                            $newItem->expiry_date = $batch['expiry_date'];
                            $newItem->save();
                        }
                    } else {
                        // حالة نظرية: الكمية = 0 أو خطأ ما، نحدث السجل الحالي بصفر (تم التحقق من > 0 سابقاً)
                        $item->approved_qty = 0;
                        $item->save();
                    }

                    // التحقق من الأرشفة التلقائية
                    try {
                        if ($item->drug) {
                            $item->drug->checkAndArchiveIfNoStock($user->hospital_id);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Auto-archiving check failed', ['error' => $e->getMessage()]);
                    }
                } else {
                    // إذا كانت الكمية المرسلة 0
                    $item->approved_qty = 0;
                    $item->fulfilled_qty = 0;
                    $item->save();
                }
            }

            \Log::info('Updating request status');
            
            // تحديث حالة الطلب
            $req->status = 'approved';
            $req->handeled_by = $user->id; 
            $req->handeled_at = now();
            $req->save();

            \Log::info('Committing transaction');
            DB::commit();
            \Log::info('Transaction committed successfully');

            // تسجيل العملية
            try {
                AuditLog::create([
                    'user_id'    => $user->id,
                    'hospital_id'=> $user->hospital_id,
                    'action'     => 'storekeeper_confirm_internal_request',
                    'table_name' => 'internal_supply_request',
                    'record_id'  => $req->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'status' => $req->status,
                        'items_count' => count($validated['items']),
                        'notes'  => $validated['notes'] ?? null, 
                    ]),
                    'ip_address' => $request->ip(),
                ]);

                // إشعار
                if ($req->requester) {
                     $this->notifications->notifyRequesterShipmentApproved($req->requester, $req);
                } elseif ($req->requested_by) {
                     $requester = \App\Models\User::find($req->requested_by);
                     if ($requester) {
                          $this->notifications->notifyRequesterShipmentApproved($requester, $req);
                     }
                }

            } catch (\Exception $auditError) {
                \Log::error('Failed to create audit log/notification', ['error' => $auditError->getMessage()]);
            }

            return response()->json(['message' => 'تم تأكيد تجهيز الطلب الداخلي وخصم الكميات من مخزون المستودع بنجاح']);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error confirming shipment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'فشل في تأكيد تجهيز الطلب الداخلي',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}

 