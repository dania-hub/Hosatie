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
class InternalSupplyRequestController extends BaseApiController
{
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
                // جلب المخزون المتاح لهذا الدواء في المستودع
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('drug_id', $item->drug_id)
                    ->first();
                
                // التأكد من جلب الكمية الصحيحة من قاعدة البيانات
                $availableStock = $inventory ? (int) $inventory->current_quantity : 0;
                
                // جلب جميع الطلبات الأخرى التي حالتها "جديد" (pending) فقط والتي تحتوي نفس الدواء
                // ملاحظة: أي طلب تتغير حالته (approved, rejected, fulfilled) لا يدخل في الحساب
                $otherPendingRequests = DB::table('internal_supply_request_items')
                    ->join('internal_supply_requests', 'internal_supply_request_items.request_id', '=', 'internal_supply_requests.id')
                    ->where('internal_supply_requests.status', 'pending') // فقط الطلبات بحالة "جديد"
                    ->where('internal_supply_request_items.drug_id', $item->drug_id)
                    ->where('internal_supply_requests.id', '!=', $req->id) // استثناء الطلب الحالي
                    ->select('internal_supply_request_items.requested_qty')
                    ->get();
                
                // حساب إجمالي الكمية المطلوبة من جميع الطلبات الأخرى بحالة "جديد" فقط
                $totalOtherRequestsQty = $otherPendingRequests->sum('requested_qty');
                
                // إجمالي الكمية المطلوبة (الطلب الحالي + الطلبات الأخرى بحالة "جديد" فقط)
                // ملاحظة: إذا كان الطلب الحالي ليس في حالة "جديد"، لن يتم حسابه في الكمية المقترحة
                $totalRequestedQty = 0;
                if ($req->status === 'pending') {
                    // فقط إذا كان الطلب الحالي في حالة "جديد"، نضيفه للحساب
                    $totalRequestedQty = $item->requested_qty + $totalOtherRequestsQty;
                } else {
                    // إذا تغيرت حالة الطلب الحالي، نحسب فقط من الطلبات الأخرى بحالة "جديد"
                    $totalRequestedQty = $totalOtherRequestsQty;
                }
                
                // حساب الكمية المقترحة
                // ملاحظة: يتم حساب الكمية المقترحة فقط من الطلبات بحالة "جديد" (pending)
                $suggestedQuantity = 0;
                
                // إذا كان الطلب الحالي ليس في حالة "جديد"، لا نحسب له كمية مقترحة
                if ($req->status !== 'pending') {
                    $suggestedQuantity = 0;
                }
                // الحالة 1: إذا كان المخزون كافي لجميع الطلبات بحالة "جديد" (الطلب الحالي + الطلبات الأخرى)
                else if ($availableStock >= $totalRequestedQty && $totalRequestedQty > 0) {
                    // الكمية المقترحة = الكمية المطلوبة بالكامل للطلب الحالي
                    $suggestedQuantity = $item->requested_qty;
                } 
                // الحالة 2: إذا كان المخزون ناقص ولكن متوفر
                else if ($availableStock > 0 && $totalRequestedQty > 0) {
                    // حساب نسبة الطلب الحالي من إجمالي الطلبات بحالة "جديد"
                    $requestRatio = $item->requested_qty / $totalRequestedQty;
                    
                    // توزيع المخزون المتاح بشكل نسبي حسب نسبة الطلب
                    $suggestedQuantity = floor($availableStock * $requestRatio);
                    
                    // التأكد من أن الكمية المقترحة:
                    // - لا تتجاوز الكمية المطلوبة في الطلب الحالي
                    // - لا تتجاوز المخزون المتاح
                    $suggestedQuantity = min($suggestedQuantity, $item->requested_qty, $availableStock);
                    
                    // التأكد من أن القيمة لا تقل عن 0
                    $suggestedQuantity = max(0, $suggestedQuantity);
                }
                // الحالة 3: إذا كان المخزون = 0 أو لا توجد طلبات بحالة "جديد"
                // $suggestedQuantity سيبقى 0 (القيمة الافتراضية)
                
                return [
                    'id'             => $item->id,
                    'drug_id'        => $item->drug_id,
                    'drug_name'      => $item->drug->name ?? '',
                    'name'           => $item->drug->name ?? '', // للتوافق مع المكون
                    'requested_qty'  => $item->requested_qty,
                    'quantity'       => $item->requested_qty, // للتوافق مع المكون
                    'approved_qty'   => $item->approved_qty,
                    'fulfilled_qty'  => $item->fulfilled_qty,
                    'sentQuantity'   => $item->approved_qty ?? $suggestedQuantity, // للتوافق مع المكون - استخدام approved_qty إذا كان موجوداً، وإلا suggestedQuantity
                    'receivedQuantity' => $item->fulfilled_qty, // للتوافق مع المكون
                    'availableQuantity' => $availableStock, // المخزون المتاح
                    'suggestedQuantity' => $suggestedQuantity, // الكمية المقترحة من الـ API
                    'stock'          => $availableStock, // للتوافق مع المكون
                    'suggestedQuantity' => $suggestedQuantity, // الكمية المقترحة
                    'totalOtherRequestsQty' => $totalOtherRequestsQty, // إجمالي طلبات الأدوية الأخرى
                    'strength'       => $item->drug->strength ?? '',
                    'dosage'         => $item->drug->strength ?? '', // للتوافق مع المكون
                    'form'           => $item->drug->form ?? '',
                    'type'           => $item->drug->form ?? '', // للتوافق مع المكون
                    'unit'           => $item->drug->unit ?? 'وحدة',
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

            $usersToNotify = array_unique(array_filter([$req->requested_by, $req->handeled_by]));

            if (empty($usersToNotify)) {
                \Log::warning('No requested_by or approved_by found for request', ['request_id' => $req->id]);
            }

            foreach ($usersToNotify as $userId) {
                // ملاحظة: الحقل type في قاعدة البيانات هو enum يقبل فقط ['عادي', 'مستعجل']
                $notif = \App\Models\Notification::create([
                    'user_id' => $userId,
                    'type'    => 'مستعجل',
                    'title'   => 'تم رفض طلب التوريد',
                    'message' => "تم رفض طلب التوريد رقم {$req->id}. السبب: {$validated['rejectionReason']}",
                    'is_read' => false,
                ]);
                \Log::info('Rejection notification created', ['notification_id' => $notif->id, 'user_id' => $userId]);
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
            'items.*.sentQuantity'  => 'required|integer|min:0',
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
            
            // التحقق من جميع الأدوية قبل البدء في المعالجة
            $inventoryChecks = [];
            foreach ($validated['items'] as $index => $itemData) {
                \Log::info("Processing item {$index}", ['item_id' => $itemData['id'], 'qty' => $itemData['sentQuantity']]);
                
                $item = $req->items->firstWhere('id', $itemData['id']);
                if (!$item) {
                    \Log::warning("Item not found", ['item_id' => $itemData['id']]);
                    continue;
                }

                $drugId = $item->drug_id;
                $qty    = (int) $itemData['sentQuantity'];
                if ($qty <= 0) {
                    \Log::info("Skipping item with zero quantity", ['item_id' => $itemData['id']]);
                    continue;
                }

                \Log::info("Checking inventory", ['drug_id' => $drugId, 'warehouse_id' => $warehouseId]);
                
                // التحقق من المخزون بدون lock (أسرع)
                $warehouseInventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('drug_id', $drugId)
                    ->first();

                if (!$warehouseInventory) {
                    \Log::error("Inventory not found", ['drug_id' => $drugId, 'warehouse_id' => $warehouseId]);
                    DB::rollBack();
                    return response()->json([
                        'message' => "لا يوجد مخزون للمستودع لهذا الدواء (ID: {$drugId})"
                    ], 404);
                }

                if ($warehouseInventory->current_quantity < $qty) {
                    \Log::error("Insufficient inventory", [
                        'drug_id' => $drugId,
                        'available' => $warehouseInventory->current_quantity,
                        'required' => $qty
                    ]);
                    DB::rollBack();
                    $drugName = $item->drug ? $item->drug->name : "ID: {$drugId}";
                    return response()->json([
                        'message' => "الكمية غير متوفرة في المخزن للدواء: {$drugName} (المتاح: {$warehouseInventory->current_quantity}, المطلوب: {$qty})",
                    ], 409);
                }

                $inventoryChecks[] = [
                    'inventory' => $warehouseInventory,
                    'item' => $item,
                    'qty' => $qty
                ];
            }

            \Log::info('Updating inventory and items', ['count' => count($inventoryChecks)]);
            
            // الآن نقوم بالخصم والتحديث
            foreach ($inventoryChecks as $checkIndex => $check) {
                \Log::info("Updating inventory {$checkIndex}", [
                    'drug_id' => $check['item']->drug_id,
                    'qty' => $check['qty']
                ]);
                
                // خصم من المستودع
                $check['inventory']->current_quantity -= $check['qty'];
                $check['inventory']->save();

                // تثبيت الكميات في عناصر الطلب الداخلي
                // ملاحظة: requested_qty لا يتم تعديله (يحتوي على الكمية المطلوبة الأصلية من pharmacist/department)
                // approved_qty: الكمية التي يتم إرسالها من storekeeper
                // fulfilled_qty: الكمية التي استلمتها pharmacist/department (سيتم تحديثها لاحقاً عند الاستلام)
                $check['item']->approved_qty  = $check['qty']; // الكمية المرسلة من storekeeper
                $check['item']->fulfilled_qty = 0; // لم تستلم الصيدلية بعد، سيتم تحديثها عند الاستلام
                $check['item']->save();
            }

            \Log::info('Updating request status');
            
            // تحديث حالة الطلب
            $req->status = 'approved';
            $req->handeled_by = $user->id; // Assign the storekeeper who approved it
            $req->handeled_at = now();
            // ملاحظة: تم إزالة عمود notes من الجدول، لذا نحفظ الملاحظات في audit_log فقط
            $req->save();

            \Log::info('Committing transaction');
            DB::commit();
            \Log::info('Transaction committed successfully');

            // تسجيل عملية تجهيز الطلب الداخلي وخصم الكميات (خارج الـ transaction)
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
                        'items'  => collect($validated['items'])->map(fn($i) => [
                            'item_id'       => $i['id'],
                            'sentQuantity'  => $i['sentQuantity'],
                        ])->toArray(),
                        'notes'  => $validated['notes'] ?? null, // ملاحظة storekeeper عند الإرسال
                    ]),
                    'ip_address' => $request->ip(),
                ]);

                // إنشاء إشعار للمستخدم الطالب (requester)
                \Log::info('Attempting to create success notification', [
                    'requested_by' => $req->requested_by,
                    'handeled_by'  => $user->id,
                    'request_id'   => $req->id
                ]);

                $usersToNotify = array_unique(array_filter([$req->requested_by, $user->id]));

                if (empty($usersToNotify)) {
                    \Log::warning('No requested_by or approved_by found for request', ['request_id' => $req->id]);
                }

                foreach ($usersToNotify as $userId) {
                    // ملاحظة: الحقل type في قاعدة البيانات هو enum يقبل فقط ['عادي', 'مستعجل']
                    $notif = \App\Models\Notification::create([
                        'user_id' => $userId,
                        'type'    => 'عادي',
                        'title'   => 'تم قبول طلب التوريد',
                        'message' => "تم قبول طلب التوريد رقم {$req->id}وتم إرسال الشحنة. ",
                        'is_read' => false,
                    ]);
                    \Log::info('Success notification created', ['notification_id' => $notif->id, 'user_id' => $userId]);
                }

            } catch (\Exception $auditError) {
                // لا نفشل العملية إذا فشل الـ logging
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

 