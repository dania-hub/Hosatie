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

        // 1. جلب الشكاوى
        $complaints = Complaint::where('patient_id', $userId)
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id'          => (string) $item->id,
                    'orderNumber' => 'C-' . $item->id,
                    'type'        => 'شكوى',
                    'type_value'  => 'complaint',
                    'status'      => $item->status, // إرجاع القيمة كما هي
                    'date'        => $item->created_at->format('Y-m-d H:i:s'),
                    'created_at'  => $item->created_at->format('Y-m-d H:i:s'),
                    'content'     => $item->message ?? '',
                    'real_id'     => $item->id,
                    'order_type'  => 'complaint',
                    'status_raw'  => $item->status
                ];
            });

        Log::info('Found ' . $complaints->count() . ' complaints');

        // 2. جلب طلبات النقل
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
                    'status'      => $this->translateStatus($item->status),
                    'date'        => $item->created_at->format('Y-m-d H:i:s'),
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
            'content' => 'required|string|min:5',
            'transfer_to_hospital_id' => 'required_if:type,transfer|exists:hospital,id',
            'reason'  => 'nullable|string',
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
            // جرب القيم العربية الأكثر احتمالاً
            $possibleStatuses = [
                'جديد',           // الأكثر احتمالاً
                'قيد المراجعة',   // احتمال ثاني
                'مستلم',          // احتمال ثالث
                'مفتوح',          // احتمال رابع
                'تحت الدراسة',    // احتمال خامس
            ];
            
            $inserted = false;
            $lastId = null;
            $usedStatus = null;
            
            foreach ($possibleStatuses as $status) {
                try {
                    $sql = "INSERT INTO complaint (patient_id, message, status, created_at, updated_at) 
                            VALUES (?, ?, ?, NOW(), NOW())";
                    
                    DB::insert($sql, [
                        $user->id,
                        $request->input('content'),
                        $status
                    ]);
                    
                    $lastId = DB::getPdo()->lastInsertId();
                    $inserted = true;
                    $usedStatus = $status;
                    Log::info('Complaint created with Arabic status: ' . $status);
                    break;
                    
                } catch (\Exception $statusError) {
                    Log::warning('Failed with Arabic status ' . $status . ': ' . $statusError->getMessage());
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
                        'status' => $usedStatus
                    ]
                ], 201);
            } else {
                // جرب عدم إرسال حقل status (ليستخدم القيمة الافتراضية)
                try {
                    $sql = "INSERT INTO complaint (patient_id, message, created_at, updated_at) 
                            VALUES (?, ?, NOW(), NOW())";
                    
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
                            'type' => 'complaint'
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
            // إنشاء طلب نقل
            $transfer = PatientTransferRequest::create([
                'patient_id'       => $user->id,
                'from_hospital_id' => $user->hospital_id,
                'to_hospital_id'   => $request->transfer_to_hospital_id,
                'requested_by'     => $user->id,
                'reason'           => $request->input('reason') ?? $request->input('content'),
                'status'           => 'pending',
            ]);

            Log::info('Transfer request created successfully:', [
                'id' => $transfer->id,
                'patient_id' => $transfer->patient_id,
                'from_hospital' => $transfer->from_hospital_id,
                'to_hospital' => $transfer->to_hospital_id,
                'status' => $transfer->status
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'تم إرسال طلب النقل بنجاح.',
                'data' => [
                    'id' => $transfer->id,
                    'type' => 'transfer',
                    'status' => $transfer->status
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
                
                if (!empty(trim($requestReason))) {
                    $content = "طلب نقل إلى: $hospitalName\n\nسبب الطلب:\n$requestReason";
                } else {
                    $content = "طلب نقل إلى: $hospitalName";
                }
                
                $reply = $order->rejection_reason;
                $orderType = 'نقل';
                $displayId = 'T-' . $order->id;
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
                    'date'    => $order->created_at->format('Y-m-d H:i:s'),
                    'status'  => $order->status, // إرجاع القيمة كما هي
                    'status_raw' => $order->status,
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

    // دالة ترجمة الحالة (لطلبات النقل فقط)
    private function translateStatus($status)
    {
        $translations = [
            'pending' => 'قيد المراجعة',
            'approved' => 'مقبول',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
        ];
        
        return $translations[$status] ?? $status;
    }
}