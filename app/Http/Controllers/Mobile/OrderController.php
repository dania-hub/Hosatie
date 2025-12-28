<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\PatientTransferRequest;
use App\Models\Hospital;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseApiController
{
    // 4.1 عرض قائمة الطلبات (شكاوى + نقل)
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        Log::info('Fetching orders for user ID: ' . $userId);

        // 1. جلب الشكاوى - ترجمة الحالة
        $complaints = Complaint::where('patient_id', $userId)
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id'          => (string) $item->id,
                    'orderNumber' => 'C-' . $item->id,
                    'type'        => 'شكوى',
                    'type_value'  => 'complaint',
                    'status'      => $this->translateComplaintStatus($item->status), // ترجمة الحالة
                    'date'        => $item->created_at->format('Y-m-d'), // التاريخ فقط بدون وقت
                    'created_at'  => $item->created_at->format('Y-m-d H:i:s'),
                    'content'     => $item->message ?? '',
                    'real_id'     => $item->id,
                    'order_type'  => 'complaint',
                    'status_raw'  => $item->status
                ];
            });

        Log::info('Found ' . $complaints->count() . ' complaints');

        // 2. جلب طلبات النقل - ترجمة الحالة
        $transfers = PatientTransferRequest::where('patient_id', $userId)
            ->with('toHospital')
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id'          => (string) $item->id,
                    'orderNumber' => 'T-' . $item->id,
                    'type'        => 'نقل',
                    'type_value'  => 'transfer',
                    'status'      => $this->translateTransferStatus($item->status), // ترجمة الحالة
                    'date'        => $item->created_at->format('Y-m-d'), // التاريخ فقط بدون وقت
                    'created_at'  => $item->created_at->format('Y-m-d H:i:s'),
                    'content'     => $item->reason ?? 'طلب نقل',
                    'real_id'     => $item->id,
                    'order_type'  => 'transfer',
                    'hospital_name' => $item->toHospital->name ?? 'مستشفى',
                    'status_raw'   => $item->status
                ];
            });

        Log::info('Found ' . $transfers->count() . ' transfer requests');

        // 3. دمج القائمتين وترتيبهم بالتاريخ
        $allOrders = $complaints->concat($transfers)
            ->sortByDesc('created_at')
            ->values()
            ->all();

        Log::info('Total orders to return: ' . count($allOrders));

        return response()->json([
            'success' => true, 
            'data' => $allOrders,
            'count' => count($allOrders)
        ]);
    }

    // ترجمة حالة الشكاوى
    private function translateComplaintStatus($status)
    {
        // إذا كانت الحالة بالفعل بالعربية، نعيدها كما هي
        if (in_array($status, ['جديد', 'قيد المراجعة', 'تمت المراجعة', 'مستلم', 'مفتوح', 'مغلق', 'تحت الدراسة', 'مكتمل'])) {
            return $status;
        }
        
        $translations = [
            'new' => 'جديد',
            'pending' => 'قيد المراجعة',
            'reviewed' => 'تمت المراجعة',
            'received' => 'مستلم',
            'open' => 'مفتوح',
            'closed' => 'مغلق',
            'under_study' => 'تحت الدراسة',
            'completed' => 'مكتمل',
            'in_progress' => 'قيد المعالجة',
            'resolved' => 'تم الحل',
        ];
        
        return $translations[strtolower($status)] ?? $status;
    }

    // ترجمة حالة طلبات النقل
    private function translateTransferStatus($status)
    {
        $translations = [
            'pending' => 'قيد المراجعة',
            'approved' => 'مقبول',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            'in_review' => 'قيد المراجعة',
            'under_consideration' => 'قيد الدراسة',
            'processed' => 'تمت المعالجة',
        ];
        
        return $translations[strtolower($status)] ?? $status;
    }

    // 4.2 إنشاء طلب جديد (توجيه حسب النوع)
    public function store(Request $request)
    {
        Log::info('Order Store Request Received:', $request->all());
        
        $user = $request->user();
        Log::info('User making request:', [
            'id' => $user->id,
            'name' => $user->full_name,
            'hospital_id' => $user->hospital_id
        ]);

        // التحقق من صحة البيانات
        $validated = $request->validate([
            'type'    => 'required|in:complaint,transfer',
            'content' => 'required_if:type,complaint|nullable|string|min:5',
            'transfer_to_hospital_id' => 'required_if:type,transfer|exists:hospitals,id',
            'reason'  => 'nullable|string',
            'transfer_reason' => 'required_if:type,transfer|string', // تم إزالة القيم الثابتة لأن العمود أزيل
        ]);

        Log::info('Validation passed:', $validated);

        if ($request->type === 'complaint') {
            return $this->createComplaint($user, $request);
        } elseif ($request->type === 'transfer') {
            return $this->createTransferRequest($user, $request);
        }

        return response()->json([
            'success' => false, 
            'message' => 'نوع الطلب غير معروف.'
        ], 400);
    }

    // إنشاء شكوى
    private function createComplaint($user, $request)
    {
        try {
            // استخدام حالة باللغة الإنجليزية لتكون موحدة
            $possibleStatuses = [
                'pending',           // الإنجليزية
                'new',
                'open',
                'in_review',
            ];
            
            $inserted = false;
            $lastId = null;
            $usedStatus = null;
            
            foreach ($possibleStatuses as $status) {
                try {
                    $sql = "INSERT INTO complaints (patient_id, message, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
                    
                    DB::insert($sql, [
                        $user->id,
                        $request->input('content'),
                        $status // استخدام الإنجليزية
                    ]);
                    
                    $lastId = DB::getPdo()->lastInsertId();
                    $inserted = true;
                    $usedStatus = $status;
                    Log::info('Complaint created with English status: ' . $status);
                    break;
                    
                } catch (\Exception $statusError) {
                    Log::warning('Failed with status ' . $status . ': ' . $statusError->getMessage());
                    continue;
                }
            }
            
            if ($inserted) {
                return response()->json([
                    'success' => true, 
                    'message' => 'تم إرسال الشكوى بنجاح.',
                    'data' => [
                        'id' => $lastId,
                        'type' => 'complaint',
                        'status' => $this->translateComplaintStatus($usedStatus) // ترجمة للعرض
                    ]
                ], 201);
            } else {
                try {
                    $sql = "INSERT INTO complaints (patient_id, message, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                    
                    DB::insert($sql, [
                        $user->id,
                        $request->input('content')
                    ]);
                    
                    $lastId = DB::getPdo()->lastInsertId();
                    Log::info('Complaint created without status field (using default)');
                    
                    return response()->json([
                        'success' => true, 
                        'message' => 'تم إرسال الشكوى بنجاح.',
                        'data' => [
                            'id' => $lastId,
                            'type' => 'complaint',
                            'status' => 'قيد المراجعة' // قيمة افتراضية
                        ]
                    ], 201);
                    
                } catch (\Exception $e3) {
                    Log::error('Failed to create complaint even without status: ' . $e3->getMessage());
                    return response()->json([
                        'success' => false, 
                        'message' => 'حدث خطأ في إنشاء الشكوى. الرجاء الاتصال بالدعم.'
                    ], 500);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error creating complaint: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'حدث خطأ في إنشاء الشكوى: ' . $e->getMessage()
            ], 500);
        }
    }

    // إنشاء طلب نقل
    private function createTransferRequest($user, $request)
    {
        // تأكد من وجود المستشفى الحالي للمريض
        if (!$user->hospital_id) {
            Log::warning('User has no hospital_id', ['user_id' => $user->id]);
            return response()->json([
                'success' => false, 
                'message' => 'المريض غير مسجل في أي مستشفى حالياً للنقل منه.'
            ], 400);
        }

        // التحقق من أن المستشفى المراد النقل إليه مختلف عن المستشفى الحالي
        if ($user->hospital_id == $request->transfer_to_hospital_id) {
            Log::warning('User trying to transfer to same hospital', [
                'current_hospital' => $user->hospital_id,
                'requested_hospital' => $request->transfer_to_hospital_id
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'لا يمكن النقل إلى نفس المستشفى الحالي.'
            ], 400);
        }

        try {
            // تحديد محتوى الطلب: تخزين سبب النقل في حقل reason
            $requestContent = '';
            
            if (!empty($request->input('reason'))) {
                // إذا كان هناك سبب مخصص (مثل "غير ذلك" مع نص مخصص)
                $requestContent = $request->input('reason');
            } elseif (!empty($request->input('transfer_reason'))) {
                // إذا كان هناك سبب النقل المختار من القائمة
                $requestContent = $request->input('transfer_reason');
            } else {
                // القيمة الافتراضية
                $requestContent = 'طلب نقل';
            }

            // إنشاء طلب نقل
            $transfer = PatientTransferRequest::create([
                'patient_id'       => $user->id,
                'from_hospital_id' => $user->hospital_id,
                'to_hospital_id'   => $request->transfer_to_hospital_id,
                'requested_by'     => $user->id,
                'reason'           => $requestContent, // تخزين سبب النقل هنا
                'status'           => 'pending',
            ]);

            Log::info('Transfer request created successfully:', [
                'id' => $transfer->id,
                'patient_id' => $transfer->patient_id,
                'from_hospital' => $transfer->from_hospital_id,
                'to_hospital' => $transfer->to_hospital_id,
                'status' => $transfer->status,
                'reason' => $transfer->reason,
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'تم إرسال طلب النقل بنجاح.',
                'data' => [
                    'id' => $transfer->id,
                    'type' => 'transfer',
                    'status' => $this->translateTransferStatus($transfer->status),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating transfer request: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'حدث خطأ في إنشاء طلب النقل: ' . $e->getMessage()
            ], 500);
        }
    }

    // 4.3 جلب قائمة المستشفيات
    public function hospitals(Request $request)
    {
        $user = $request->user();
        $currentHospitalId = $user->hospital_id;

        Log::info('Fetching hospitals for user:', [
            'user_id' => $user->id,
            'current_hospital' => $currentHospitalId
        ]);

        $query = Hospital::where('status', 'active')->select('id', 'name');

        // إذا كان المريض مسجل في مستشفى، نستثنيه
        if ($currentHospitalId) {
            $query->where('id', '!=', $currentHospitalId);
        }

        $hospitals = $query->get();

        Log::info('Found ' . $hospitals->count() . ' hospitals');

        return response()->json([
            'success' => true, 
            'data' => $hospitals
        ]);
    }

    // 4.4 جلب قيم سبب النقل المتاحة
    public function transferReasons(Request $request)
    {
        $reasons = [
            ['id' => 1, 'value' => 'تغير مكان السكن', 'label' => 'تغير مكان السكن'],
            ['id' => 2, 'value' => 'الحاجة إلى تخصص طبي غير متوفر في المستشفى الحالي', 'label' => 'الحاجة إلى تخصص طبي غير متوفر في المستشفى الحالي'],
            ['id' => 3, 'value' => 'عدم رضا عن جودة الخدمة الطبية المقدمة', 'label' => 'عدم رضا عن جودة الخدمة الطبية المقدمة'],
            ['id' => 4, 'value' => 'صعوبة الوصول إلى المستشفى', 'label' => 'صعوبة الوصول إلى المستشفى'],
            ['id' => 5, 'value' => 'غير ذلك', 'label' => 'غير ذلك'],
        ];

        return response()->json([
            'success' => true, 
            'data' => $reasons
        ]);
    }

    // 6.2 تفاصيل الطلب
    public function show($orderId)
    {
        try {
            Log::info('Fetching order details for ID: ' . $orderId);

            $id = $orderId;
            $type = null;

            // تحديد نوع الطلب بناءً على البادئة
            if (strpos($orderId, 'C-') === 0) {
                $type = 'complaint';
                $id = substr($orderId, 2);
            } elseif (strpos($orderId, 'T-') === 0) {
                $type = 'transfer';
                $id = substr($orderId, 2);
            } else {
                // إذا لم يكن هناك بادئة، نحاول التخمين
                $transfer = PatientTransferRequest::find($orderId);
                if ($transfer) {
                    $type = 'transfer';
                    $id = $orderId;
                } else {
                    $complaint = Complaint::find($orderId);
                    if ($complaint) {
                        $type = 'complaint';
                        $id = $orderId;
                    }
                }
            }

            if (!$type) {
                Log::warning('Could not determine order type for ID: ' . $orderId);
                return response()->json([
                    'success' => false, 
                    'message' => 'الطلب غير موجود.'
                ], 404);
            }

            Log::info('Parsed order - Type: ' . $type . ', ID: ' . $id);

            if ($type === 'complaint') {
                $order = Complaint::find($id);
                if (!$order) {
                    Log::warning('Complaint not found: ' . $id);
                    return response()->json([
                        'success' => false, 
                        'message' => 'الشكوى غير موجودة.'
                    ], 404);
                }
                
                $content = $order->message;
                $reply = $order->reply_message;
                $orderType = 'شكوى';
                $displayId = 'C-' . $order->id;
                $status = $this->translateComplaintStatus($order->status); // ترجمة الحالة
            } else {
                $order = PatientTransferRequest::with('toHospital')->find($id);
                if (!$order) {
                    Log::warning('Transfer request not found: ' . $id);
                    return response()->json([
                        'success' => false, 
                        'message' => 'طلب النقل غير موجود.'
                    ], 404);
                }
                
                $hospitalName = $order->toHospital->name ?? 'مستشفى';
                $requestReason = $order->reason ?? '';
                
                // عرض المحتوى المخزن في حقل reason
                $content = "طلب نقل إلى: $hospitalName\n\nسبب النقل: $requestReason";
                
                $reply = $order->rejection_reason;
                $orderType = 'نقل';
                $displayId = 'T-' . $order->id;
                $status = $this->translateTransferStatus($order->status); // ترجمة الحالة
            }

            Log::info('Order found:', [
                'id' => $order->id,
                'type' => $orderType,
                'status' => $order->status
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id'      => $displayId,
                    'date'    => $order->created_at->format('Y-m-d'), // التاريخ فقط
                    'status'  => $status, // الحالة المترجمة
                    'status_raw' => $order->status, // الحالة الأصلية
                    'content' => $content,
                    'reply'   => $reply ?? null,
                    'type'    => $orderType,
                    'real_id' => $order->id,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching order details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب تفاصيل الطلب.'
            ], 500);
        }
    }
}