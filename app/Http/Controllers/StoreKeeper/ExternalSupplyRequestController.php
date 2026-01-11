<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\Warehouse;

use App\Services\StaffNotificationService;

class ExternalSupplyRequestController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}
    // GET /api/storekeeper/supply-requests
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // جلب جميع الطلبات التي أنشأها هذا المستخدم
        // + الطلبات المعتمدة من HospitalAdmin (status = 'approved') - في انتظار Supplier
        // + الطلبات المكتملة من Supplier (status = 'fulfilled') - يمكن تأكيد الاستلام
        // + الطلبات المرفوضة (status = 'rejected') - للعرض فقط
        $requests = ExternalSupplyRequest::with(['supplier', 'items.drug', 'approver'])
            ->where('hospital_id', $user->hospital_id)
            ->where(function($query) use ($user) {
                $query->where('requested_by', $user->id) // طلباته الخاصة
                      ->orWhere(function($q) {
                          // أو الطلبات المعتمدة من HospitalAdmin (في انتظار Supplier)
                          $q->where('status', 'approved')
                            ->whereHas('requester', function($subQ) {
                                $subQ->where('type', 'warehouse_manager');
                            });
                      })
                      ->orWhere(function($q) {
                          // أو الطلبات المكتملة من Supplier (يمكن تأكيد الاستلام)
                          $q->where('status', 'fulfilled')
                            ->whereHas('requester', function($subQ) {
                                $subQ->where('type', 'warehouse_manager');
                            });
                      })
                      ->orWhere(function($q) {
                          // أو الطلبات المرفوضة (للعرض)
                          $q->where('status', 'rejected')
                            ->whereHas('requester', function($subQ) {
                                $subQ->where('type', 'warehouse_manager');
                            });
                      });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $requests->map(function ($req) use ($user) {
            // تحديد حالة العرض حسب الحالة الفعلية
            $displayStatus = $req->status;
            $isDelivered = false;
            
            if ($req->status === 'fulfilled' && $req->requested_by === $user->id) {
                // طلب المستخدم أرسله Supplier
                // نحتاج التمييز بين "أرسلها Supplier" و "استلمها StoreKeeper"
                // الحل: نتحقق من أن updated_at للـ items تم تحديثه بعد أن أرسله Supplier
                // عندما يقبل Supplier، يتم تحديث items.updated_at و req.updated_at
                // عندما يؤكد StoreKeeper الاستلام، يتم تحديث items.updated_at مرة أخرى
                // لذا نتحقق من أن items.updated_at بعد req.updated_at (يعني تم تحديثها عند تأكيد الاستلام)
                
                // أولاً: نتحقق من أن fulfilled_qty موجود (أرسله Supplier)
                $hasFulfilledQty = $req->items->every(function($item) {
                    return $item->fulfilled_qty !== null;
                });
                
                if (!$hasFulfilledQty || $req->items->count() === 0) {
                    // لا يوجد fulfilled_qty، يعني لم يرسله Supplier بعد
                    $displayStatus = 'approved'; 
                } else {
                    // يوجد fulfilled_qty، يعني أرسله Supplier
                    // الآن نتحقق من أن items تم تحديثها بعد أن أرسله Supplier
                    // نستخدم updated_at للطلب كمرجع - إذا تم تحديث items بعد req.updated_at، يعني تم الاستلام
                    // لكن المشكلة أن req.updated_at يتم تحديثه أيضاً عندما يقبل Supplier
                    // لذا نستخدم طريقة أخرى: نتحقق من أن items.updated_at بعد req.created_at + 1 دقيقة
                    // أو نستخدم req.updated_at - 1 دقيقة كمرجع
                    
                    // الحل الأفضل: نتحقق من أن items.updated_at بعد req.updated_at
                    // إذا كان الفرق أكثر من ثانية واحدة، يعني تم تحديثها عند تأكيد الاستلام
                    $requestUpdatedAt = $req->updated_at;
                    $itemsUpdatedAfterDelivery = $req->items->every(function($item) use ($requestUpdatedAt) {
                        // إذا كان updated_at للـ item بعد updated_at للطلب بأكثر من ثانية، يعني تم تحديثه عند تأكيد الاستلام
                        if (!$item->updated_at) {
                            return false;
                        }
                        // نتحقق من أن الفرق أكثر من ثانية واحدة (لأن Supplier و StoreKeeper قد يحدثان في نفس الوقت تقريباً)
                        $diffInSeconds = $item->updated_at->diffInSeconds($requestUpdatedAt);
                        return $item->updated_at->gt($requestUpdatedAt) && $diffInSeconds > 1;
                    });
                    
                    // إذا لم يعمل المنطق السابق، نتحقق ببساطة من أن items.updated_at بعد req.updated_at
                    if (!$itemsUpdatedAfterDelivery) {
                        $itemsUpdatedAfterDelivery = $req->items->every(function($item) use ($requestUpdatedAt) {
                            return $item->updated_at && $item->updated_at->gt($requestUpdatedAt);
                        });
                    }
                    
                    if ($itemsUpdatedAfterDelivery) {
                        // تم تأكيد الاستلام - يمكن عرض "تم الاستلام"
                        $displayStatus = 'delivered'; // "تم الاستلام"
                        $isDelivered = true;
                    } else {
                        // قيد الاستلام - لم يتم تأكيد الاستلام بعد
                        $displayStatus = 'fulfilled'; // "قيد الاستلام"
                    }
                }
            } elseif ($req->status === 'rejected') {
                // مرفوض (من HospitalAdmin أو Supplier)
                $displayStatus = 'rejected'; // "مرفوضة"
            }
            
            // إعداد confirmationDetails إذا تم تأكيد الاستلام
            $confirmationDetails = null;
            if ($isDelivered) {
                // جلب الكميات المرسلة الأصلية من audit_log
                $originalSentQuantities = [];
                $auditLog = AuditLog::where('table_name', 'external_supply_request')
                    ->where('record_id', $req->id)
                    ->where('action', 'storekeeper_confirm_external_delivery')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($auditLog && $auditLog->old_values) {
                    $oldValues = json_decode($auditLog->old_values, true);
                    if (isset($oldValues['items']) && is_array($oldValues['items'])) {
                        foreach ($oldValues['items'] as $auditItem) {
                            if (isset($auditItem['item_id']) && isset($auditItem['sentQuantity'])) {
                                $originalSentQuantities[$auditItem['item_id']] = $auditItem['sentQuantity'];
                            }
                        }
                    }
                }
                
                $confirmationDetails = [
                    'confirmedAt' => $req->updated_at->format('Y-m-d H:i:s'),
                    'receivedItems' => $req->items->map(function($item) use ($originalSentQuantities) {
                        return [
                            'id' => $item->id,
                            'name' => $item->drug->name ?? 'غير محدد',
                            'sentQuantity' => $originalSentQuantities[$item->id] ?? $item->approved_qty ?? 0, // الكمية المرسلة الأصلية من audit_log
                            'receivedQuantity' => $item->fulfilled_qty ?? 0, // الكمية المستلمة الفعلية
                            'unit' => $item->drug->unit ?? 'وحدة'
                        ];
                    })->toArray()
                ];
            }
            
            return [
                'id'                => $req->id,
                'shipmentNumber'    => 'EXT-' . $req->id,
                'requestDate'       => $req->created_at ? $req->created_at->format('Y/m/d') : '',
                'requestDateFull'   => $req->created_at ? $req->created_at->toIso8601String() : null,
                'status'            => $req->status,
                'requestStatus'     => $this->mapStatusToArabic($displayStatus),
                'requestingDepartment' => $req->supplier->name ?? 'مورد غير محدد',
                'department'        => [
                    'name' => $req->supplier->name ?? 'مورد غير محدد',
                ],
                'items'             => $req->items->map(function ($item) {
                    return [
                        'id'                => $item->id,
                        'drugId'            => $item->drug_id,
                        'name'              => $item->drug->name ?? 'دواء غير معروف',
                        'drugName'          => $item->drug->name ?? 'دواء غير معروف',
                        'requested'         => $item->requested_qty,
                        'requested_qty'     => $item->requested_qty,
                        'requestedQty'      => $item->requested_qty,
                        'quantity'          => $item->requested_qty,
                        'approved'          => $item->approved_qty,
                        'approved_qty'      => $item->approved_qty,
                        'approvedQty'       => $item->approved_qty,
                        'fulfilled'         => $item->fulfilled_qty,
                        'fulfilled_qty'     => $item->fulfilled_qty,
                        'fulfilledQty'      => $item->fulfilled_qty,
                        // sentQuantity يجب أن يكون fulfilled_qty (الكمية الفعلية المرسلة من المورد)
                        // وليس approved_qty (الكمية المعتمدة من HospitalAdmin)
                        'sentQuantity'      => $item->fulfilled_qty ?? $item->approved_qty,
                        'unit'              => $item->drug->unit ?? 'وحدة',
                        'dosage'            => $item->drug->strength ?? null,
                        'strength'          => $item->drug->strength ?? null,
                    ];
                }),
                'notes'             => null,
                'createdAt'         => $req->created_at ? $req->created_at->toIso8601String() : null,
                'updatedAt'         => $req->updated_at ? $req->updated_at->toIso8601String() : null,
                'confirmationDetails' => $confirmationDetails,
            ];
        });

        // تحويل Collection إلى array لإمكانية التعديل بالمرجع
        $data = $data->toArray();

        // جلب الملاحظات من audit_log لكل طلب
        foreach ($data as &$requestData) {
            $reqId = $requestData['id'];
            
            // جلب ملاحظة storekeeper (الملاحظة الأصلية عند الإنشاء)
            // البحث في جميع audit_logs لهذا الطلب، وليس فقط create_external_supply_request
            $storekeeperNotes = null;
            
            // أولاً: البحث عن audit_log مع action محدد
            $storekeeperNotesAuditLog = AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $reqId)
                ->where('action', 'create_external_supply_request')
                ->orderBy('created_at', 'asc')
                ->first();
            
            // إذا لم نجد audit_log مع action محدد، نبحث في جميع audit_logs
            if (!$storekeeperNotesAuditLog) {
                $storekeeperNotesAuditLog = AuditLog::where('table_name', 'external_supply_request')
                    ->where('record_id', $reqId)
                    ->where(function($query) {
                        $query->where('action', 'like', '%create%')
                              ->orWhere('action', 'like', '%external%')
                              ->orWhere('action', 'like', '%supply%');
                    })
                    ->orderBy('created_at', 'asc')
                    ->first();
            }
            
            // إذا لم نجد بعد، نبحث في جميع audit_logs لهذا الطلب
            if (!$storekeeperNotesAuditLog) {
                $storekeeperNotesAuditLog = AuditLog::where('table_name', 'external_supply_request')
                    ->where('record_id', $reqId)
                    ->orderBy('created_at', 'asc')
                    ->first();
            }
            
            if ($storekeeperNotesAuditLog && $storekeeperNotesAuditLog->new_values) {
                $newValues = json_decode($storekeeperNotesAuditLog->new_values, true);
                
                // التحقق من أن json_decode نجح وأن notes موجودة وليست فارغة
                if ($newValues !== null && (is_array($newValues) || is_object($newValues))) {
                    if (isset($newValues['notes'])) {
                        // معالجة notes سواء كانت string أو أي نوع آخر
                        $notesValue = $newValues['notes'];
                        if (is_string($notesValue)) {
                            $trimmedNotes = trim($notesValue);
                            if ($trimmedNotes !== '') {
                                $storekeeperNotes = $trimmedNotes;
                            }
                        } elseif (is_scalar($notesValue) && $notesValue !== null && $notesValue !== '') {
                            // في حالة كانت notes رقم أو نوع آخر، نحولها إلى string
                            $storekeeperNotes = trim((string) $notesValue);
                        }
                    }
                }
            }
            
            // جلب ملاحظة supplier (عند القبول/الإرسال)
            $supplierNotes = null;
            $supplierNotesAuditLog = AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $reqId)
                ->where('action', 'supplier_confirm_external_supply_request')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($supplierNotesAuditLog && $supplierNotesAuditLog->new_values) {
                $newValues = json_decode($supplierNotesAuditLog->new_values, true);
                // التحقق من أن json_decode نجح وأن notes موجودة وليست فارغة
                if ($newValues !== null && (is_array($newValues) || is_object($newValues))) {
                    if (isset($newValues['notes']) && is_string($newValues['notes']) && trim($newValues['notes']) !== '') {
                        $supplierNotes = trim($newValues['notes']);
                    }
                }
            }
            
            // جلب ملاحظة تأكيد الاستلام من storekeeper
            $confirmationNotes = null;
            if ($requestData['confirmationDetails']) {
                $confirmationAuditLog = AuditLog::where('table_name', 'external_supply_request')
                    ->where('record_id', $reqId)
                    ->where('action', 'storekeeper_confirm_external_delivery')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($confirmationAuditLog && $confirmationAuditLog->new_values) {
                    $newValues = json_decode($confirmationAuditLog->new_values, true);
                    // التحقق من أن json_decode نجح وأن confirmationNotes موجودة وليست فارغة
                    if ($newValues !== null && (is_array($newValues) || is_object($newValues))) {
                        if (isset($newValues['confirmationNotes']) && is_string($newValues['confirmationNotes']) && trim($newValues['confirmationNotes']) !== '') {
                            $confirmationNotes = trim($newValues['confirmationNotes']);
                            // إضافة الملاحظة إلى confirmationDetails
                            $requestData['confirmationDetails']['confirmationNotes'] = $confirmationNotes;
                        }
                    }
                }
            }
            
            // جلب سبب الرفض من audit_log (إذا كان الطلب مرفوضاً)
            // نبحث دائماً عن rejectionReason بغض النظر عن الحالة الحالية (لضمان عدم فقدان البيانات)
            $rejectionReason = null;
            $rejectedAt = null;
            
            // البحث عن audit log للرفض
            $rejectionAuditLog = AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $reqId)
                ->where(function($query) {
                    $query->where('action', 'supplier_reject_external_supply_request')
                          ->orWhere('action', 'hospital_admin_reject_external_supply_request')
                          ->orWhere('action', 'like', '%reject%');
                })
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($rejectionAuditLog) {
                $rejectedAt = $rejectionAuditLog->created_at ? $rejectionAuditLog->created_at->toIso8601String() : null;
                
                // محاولة استخراج سبب الرفض من new_values
                if ($rejectionAuditLog->new_values) {
                    $newValues = json_decode($rejectionAuditLog->new_values, true);
                    // التحقق من أن json_decode نجح (لا يرجع null بسبب خطأ في JSON)
                    if ($newValues !== null && (is_array($newValues) || is_object($newValues))) {
                        if (isset($newValues['rejectionReason']) && is_string($newValues['rejectionReason']) && !empty(trim($newValues['rejectionReason']))) {
                            $rejectionReason = trim($newValues['rejectionReason']);
                        } elseif (isset($newValues['reason']) && is_string($newValues['reason']) && !empty(trim($newValues['reason']))) {
                            $rejectionReason = trim($newValues['reason']);
                        }
                    }
                }
                // محاولة استخراج من old_values أيضاً (للتوافق مع بعض الحالات)
                if (!$rejectionReason && $rejectionAuditLog->old_values) {
                    $oldValues = json_decode($rejectionAuditLog->old_values, true);
                    // التحقق من أن json_decode نجح (لا يرجع null بسبب خطأ في JSON)
                    if ($oldValues !== null && (is_array($oldValues) || is_object($oldValues))) {
                        if (isset($oldValues['rejectionReason']) && is_string($oldValues['rejectionReason']) && !empty(trim($oldValues['rejectionReason']))) {
                            $rejectionReason = trim($oldValues['rejectionReason']);
                        } elseif (isset($oldValues['reason']) && is_string($oldValues['reason']) && !empty(trim($oldValues['reason']))) {
                            $rejectionReason = trim($oldValues['reason']);
                        }
                    }
                }
                
                // Log للتحقق من سبب الرفض (يمكن حذفه لاحقاً)
                if (!$rejectionReason) {
                    \Log::warning('Rejection reason not found in audit log', [
                        'request_id' => $reqId,
                        'audit_log_id' => $rejectionAuditLog->id,
                        'action' => $rejectionAuditLog->action,
                        'new_values' => $rejectionAuditLog->new_values,
                        'old_values' => $rejectionAuditLog->old_values
                    ]);
                }
            }
            
            // التأكد من أن البيانات يتم إضافتها بشكل صحيح
            $requestData['storekeeperNotes'] = $storekeeperNotes ?: null;
            $requestData['supplierNotes'] = $supplierNotes ?: null;
            $requestData['rejectionReason'] = $rejectionReason ?: null;
            $requestData['rejectedAt'] = $rejectedAt ?: null;
            // إضافة notes من storekeeperNotes للتوافق مع الكود القديم
            // إذا كانت storekeeperNotes موجودة، نستخدمها، وإلا نستخدم notes الأصلية من البيانات
            $requestData['notes'] = $storekeeperNotes ?: ($requestData['notes'] ?? '');
            
        }

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
            'items.*.drug_id'         => 'required|exists:drugs,id',
            'items.*.requested_qty'   => 'required|integer|min:1',
            'supplier_id'             => 'nullable|exists:suppliers,id',
            'notes'                   => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            // التحقق من وجود الأدوية وصحتها قبل إنشاء الطلب
            foreach ($validated['items'] as $item) {
                $drug = \App\Models\Drug::find($item['drug_id']);
                if (!$drug) {
                    throw new \Exception("الدواء برقم {$item['drug_id']} غير موجود في قاعدة البيانات");
                }

                if ($drug->status === \App\Models\Drug::STATUS_ARCHIVED) {
                    throw new \Exception("لا يمكن طلب الدواء '{$drug->name}' لأنه مؤرشف وغير مدعوم.");
                }

                if ($drug->status === \App\Models\Drug::STATUS_PHASING_OUT) {
                    // التحقق من مخزون المورد إذا كان الإيقاف تدريجياً
                    $supplierId = $validated['supplier_id'] ?? null;
                    if ($supplierId) {
                        $supplierInventory = \App\Models\Inventory::where('drug_id', $drug->id)
                            ->where('supplier_id', $supplierId)
                            ->first();
                        
                        if (!$supplierInventory || $supplierInventory->current_quantity <= 0) {
                            throw new \Exception("لا يمكن طلب الدواء '{$drug->name}' (قيد الإيقاف التدريجي) لنفاذ مخزون المورد.");
                        }
                    }
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

            // دمج الأدوية المكررة قبل الحفظ (حماية إضافية)
            $mergedItems = [];
            foreach ($validated['items'] as $item) {
                $drugId = $item['drug_id'];
                if (isset($mergedItems[$drugId])) {
                    // إذا كان الدواء موجوداً مسبقاً، نضيف الكمية
                    $mergedItems[$drugId]['requested_qty'] += $item['requested_qty'];
                } else {
                    // إذا لم يكن موجوداً، نضيفه كعنصر جديد
                    $mergedItems[$drugId] = [
                        'drug_id' => $drugId,
                        'requested_qty' => $item['requested_qty']
                    ];
                }
            }

            // حفظ العناصر المدمجة
            foreach ($mergedItems as $item) {
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

            try {
                $this->notifications->notifyAdminNewExternalRequest($externalRequest);
            } catch (\Exception $e) {
                \Log::error('Failed to notify admin about new external request', ['error' => $e->getMessage()]);
            }

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
                        'notes'       => $validated['notes'] ?? null,
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
     * تأكيد استلام الشحنة
     * POST /api/storekeeper/supply-requests/{id}/confirm-delivery
     * عند تأكيد الاستلام، يتم تحديث المخزون في المستودع
     */
    public function confirmDelivery(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'warehouse_manager') {
                return response()->json(['message' => 'غير مصرح'], 403);
            }

            // التحقق من البيانات المرسلة
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer|exists:external_supply_request_items,id', // ✅ تم تصحيح اسم الجدول
                'items.*.receivedQuantity' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ]);

            // جلب الطلب
            $externalRequest = ExternalSupplyRequest::with('items.drug')
                ->where('hospital_id', $user->hospital_id)
                ->where('requested_by', $user->id)
                ->findOrFail($id);

            // يجب أن تكون الحالة 'fulfilled' (أرسلها Supplier) أو 'approved' (موافقة Admin)
            if (!in_array($externalRequest->status, ['fulfilled', 'approved'])) {
                return response()->json([
                    'message' => 'لا يمكن تأكيد الاستلام. يجب أن تكون الشحنة في حالة "قيد الاستلام".',
                    'current_status' => $externalRequest->status
                ], 400);
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
                $warehouse = Warehouse::where('hospital_id', $user->hospital_id)->first();
                if ($warehouse) {
                    $warehouseId = $warehouse->id;
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'لا يوجد مستودع مرتبط بالمستخدم أو المستشفى'
                    ], 400);
                }
            }

            // حفظ الكميات المرسلة الأصلية قبل تحديثها
            $originalSentQuantities = [];
            
            // تحديث الكميات المستلمة لكل عنصر وإضافة للمخزون
            foreach ($validated['items'] as $itemData) {
                $item = $externalRequest->items->firstWhere('id', $itemData['id']);
                if (!$item) {
                    continue;
                }

                // حفظ الكمية المرسلة الأصلية قبل تحديثها
                $originalSentQty = $item->fulfilled_qty ?? $item->approved_qty ?? 0;
                $originalSentQuantities[$item->id] = $originalSentQty;

                $receivedQty = (float)($itemData['receivedQuantity'] ?? 0);
                
                // تحديث fulfilled_qty بالكمية المستلمة الفعلية
                $item->fulfilled_qty = $receivedQty;
                $item->touch(); 
                $item->save();

                if ($receivedQty <= 0) {
                    continue;
                }

                // البحث عن مخزون هذا الدواء في المستودع
                $inventory = Inventory::firstOrNew([
                    'drug_id' => $item->drug_id,
                    'warehouse_id' => $warehouseId,
                ]);

                // إذا كان سجل جديد، نتأكد من عدم ارتباطه بصيدلية
                if (!$inventory->exists) {
                    $inventory->pharmacy_id = null;
                    $inventory->current_quantity = 0;
                }

                // إضافة الكمية المستلمة للمخزون
                $inventory->current_quantity = ($inventory->current_quantity ?? 0) + $receivedQty;
                $inventory->save();

                // التنبيه في حالة انخفاض المخزون عن الحد الأدنى (حتى بعد التوريد)
                try {
                    $this->notifications->checkAndNotifyLowStock($inventory);
                } catch (\Exception $e) {
                    \Log::error('Warehouse stock replenishment alert notification failed', ['error' => $e->getMessage()]);
                }

                // تحديث fulfilled_qty بالكمية المستلمة الفعلية (إذا كانت مختلفة عن المرسلة)
                // ملاحظة: fulfilled_qty تم تعيينه من قبل Supplier، لكن يمكن تحديثه بالكمية الفعلية المستلمة
                // في هذه الحالة، نستخدم الكمية المستلمة الفعلية
                // مهم: نحن نحدث fulfilled_qty هنا، وهذا سيحدث updated_at للـ item
                // لذا عندما نتحقق من updated_at في index()، سنجد أنه تم تحديثه بعد req.updated_at
                $item->fulfilled_qty = $receivedQty;
                // تحديث updated_at يدوياً للتأكد من أنه بعد req.updated_at
                $item->touch(); // هذا سيحدث updated_at إلى الوقت الحالي
                $item->save();
            }

            // تحديث الحالة - يمكن إضافة حالة جديدة أو نتركها 'fulfilled'
            // حالياً، نتركها 'fulfilled' لأنها تعني أن Supplier أرسلها و StoreKeeper استلمها
            // يمكن إضافة عمود جديد في الجدول لتتبع حالة الاستلام إذا لزم الأمر

            // إرسال إشعار في حالة وجود نقص
            $shortageItems = [];
            foreach ($validated['items'] as $itemData) {
                $item = $externalRequest->items->firstWhere('id', $itemData['id']);
                if (!$item) continue;

                $sentQty = $originalSentQuantities[$item->id] ?? 0;
                $receivedQty = (float)($itemData['receivedQuantity'] ?? 0);

                if ($receivedQty < $sentQty) {
                    $shortageItems[] = [
                        'name' => $item->drug->name ?? 'دواء',
                        'sent' => $sentQty,
                        'received' => $receivedQty
                    ];
                }
            }

            if (!empty($shortageItems)) {
                try {
                    $this->notifications->notifyExternalShipmentShortage($externalRequest, $shortageItems);
                } catch (\Exception $e) {
                    \Log::error('Failed to send shortage notifications', ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            // تسجيل العملية مع حفظ الكميات المرسلة الأصلية
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $user->hospital_id,
                    'action' => 'storekeeper_confirm_external_delivery',
                    'table_name' => 'external_supply_request',
                    'record_id' => $externalRequest->id,
                    'old_values' => json_encode([
                        'status' => 'fulfilled',
                        'items' => collect($externalRequest->items)->map(function($item) use ($originalSentQuantities) {
                            return [
                                'item_id' => $item->id,
                                'sentQuantity' => $originalSentQuantities[$item->id] ?? $item->fulfilled_qty ?? $item->approved_qty ?? 0
                            ];
                        })->toArray()
                    ]),
                    'new_values' => json_encode([
                        'status' => 'fulfilled',
                        'confirmed_delivery' => true,
                        'items' => $validated['items'],
                        'confirmationNotes' => $validated['notes'] ?? null,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to log delivery confirmation', ['error' => $e->getMessage()]);
            }

            // إعادة جلب البيانات المحدثة
            $externalRequest->refresh();
            $externalRequest->load('items.drug');
            
            // إعداد بيانات confirmation
            $confirmationData = [
                'confirmedAt' => now()->format('Y-m-d H:i:s'),
                'receivedItems' => $externalRequest->items->map(function($item) use ($validated, $originalSentQuantities) {
                    $itemData = collect($validated['items'])->firstWhere('id', $item->id);
                    return [
                        'id' => $item->id,
                        'name' => $item->drug->name ?? 'غير محدد',
                        'sentQuantity' => $originalSentQuantities[$item->id] ?? $item->fulfilled_qty ?? $item->approved_qty ?? 0, // الكمية المرسلة الأصلية
                        'receivedQuantity' => $itemData['receivedQuantity'] ?? $item->fulfilled_qty ?? 0, // الكمية المستلمة الفعلية
                        'unit' => $item->drug->unit ?? 'وحدة'
                    ];
                })->toArray()
            ];
            
            return response()->json([
                'message' => 'تم تأكيد استلام الشحنة بنجاح',
                'data' => [
                    'id' => $externalRequest->id,
                    'status' => $externalRequest->status,
                    'confirmationDetails' => $confirmationData
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Confirm Delivery Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'فشل في تأكيد الاستلام',
                'error' => config('app.debug') ? $e->getMessage() : 'حدث خطأ غير متوقع',
            ], 500);
        }
    }

    /**
     * ترجمة حالة الطلب إلى العربية
     */
    private function mapStatusToArabic(string $status): string
    {
        return match ($status) {
            'pending'           => 'قيد الانتظار', // في انتظار موافقة HospitalAdmin
            'approved'          => 'قيد الاستلام', // معتمدة من HospitalAdmin، في انتظار StoreKeeper
            'partially_approved'=> 'قيد الاستلام', // حالة خاصة للعرض
            'fulfilled'         => 'قيد الاستلام', // أرسلها Supplier، يمكن تأكيد الاستلام
            'delivered'         => 'تم الاستلام', // تم تأكيد الاستلام من StoreKeeper
            'rejected'          => 'مرفوضة', // مرفوضة من HospitalAdmin أو Supplier
            'cancelled'         => 'ملغاة',
            default             => $status,
        };
    }
}
