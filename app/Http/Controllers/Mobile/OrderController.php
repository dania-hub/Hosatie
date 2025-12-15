<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\PatientTransferRequest;
use App\Models\Hospital;

class OrderController extends BaseApiController
{
    // 4.1 عرض قائمة الطلبات (شكاوى + نقل)
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // 1. جلب الشكاوى
        $complaints = Complaint::where('patient_id', $userId)
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id'     => 'C-' . $item->id, // تمييز المعرف
                    'real_id'=> $item->id,
                    'type'   => 'complaint', // نوع الطلب
                    'date'   => $item->created_at->format('Y-m-d'),
                    'status' => $item->status, // قيد المراجعة...
                    'details'=> $item->message,
                ];
            });

        // 2. جلب طلبات النقل
        $transfers = PatientTransferRequest::where('patient_id', $userId)
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id'     => 'T-' . $item->id,
                    'real_id'=> $item->id,
                    'type'   => 'transfer',
                    'date'   => $item->created_at->format('Y-m-d'),
                    'status' => $item->status, // pending...
                    'details'=> 'طلب نقل إلى مستشفى ID: ' . $item->to_hospital_id,
                ];
            });

        // 3. دمج القائمتين وترتيبهم بالتاريخ
        $allOrders = $complaints->concat($transfers)->sortByDesc('date')->values();

        return response()->json(['success' => true, 'data' => $allOrders]);
    }

    // 4.2 إنشاء طلب جديد (توجيه حسب النوع)
    public function store(Request $request)
    {
        $request->validate([
            'type'    => 'required|in:complaint,transfer', // حدد الأنواع المسموحة
            'content' => 'required_if:type,complaint|string',
            'transfer_to_hospital_id' => 'required_if:type,transfer|exists:hospital,id',
            'reason'  => 'nullable|string', // لسبب النقل
        ]);

        $user = $request->user();

        if ($request->type === 'complaint') {
            // إنشاء شكوى
            Complaint::create([
                'patient_id' => $user->id,
                'message'    => $request->input('content'),
                'status'     => 'قيد المراجعة',
            ]);
        } elseif ($request->type === 'transfer') {
            // تأكد من وجود المستشفى الحالي للمريض (مهم)
            if (!$user->hospital_id) {
                return response()->json(['success' => false, 'message' => 'المريض غير مسجل في أي مستشفى حالياً للنقل منه.'], 400);
            }

            // إنشاء طلب نقل
            PatientTransferRequest::create([
                'patient_id'       => $user->id,
                'from_hospital_id' => $user->hospital_id,
                'to_hospital_id'   => $request->transfer_to_hospital_id,
                'requested_by'     => $user->id,
                'reason'           => $request->input('reason') ?? $request->input('content') ?? 'بدون سبب', // استخدم content كسبب إذا لم يرسل reason
                'status'           => 'قيد المراجعة',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'تم إرسال الطلب بنجاح.'], 201);
    }

    // 4.3 جلب قائمة المستشفيات
    public function hospitals()
    {
        $hospitals = Hospital::where('status', 'active')->select('id', 'name')->get();
        return response()->json(['success' => true, 'data' => $hospitals]);
    }

    // 6.2 تفاصيل الطلب
    public function show($mixedId)
    {
        try {
            $type = $mixedId[0]; // 'T' or 'C'
            $id = substr($mixedId, 2); // الرقم بعد الفاصلة

            if ($type === 'C') {
                $order = Complaint::find($id);
                if (!$order) {
                    return response()->json(['success' => false, 'message' => 'الشكوى غير موجودة.'], 404);
                }
                
                $content = $order->message;
                $reply = $order->reply_message;
            } else {
                $order = PatientTransferRequest::with('toHospital')->find($id);
                if (!$order) {
                    return response()->json(['success' => false, 'message' => 'طلب النقل غير موجود.'], 404);
                }
                
                // الحصول على اسم المستشفى
                $hospitalName = $order->toHospital->name ?? 'مستشفى';
                
                // الحصول على نص الطلب من حقل reason
                $requestReason = $order->reason ?? '';
                
                // بناء المحتوى الكامل
                if (!empty(trim($requestReason))) {
                    $content = "طلب نقل إلى: $hospitalName\n\nسبب الطلب:\n$requestReason";
                } else {
                    $content = "طلب نقل إلى: $hospitalName";
                }
                
                $reply = $order->rejection_reason;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'      => $mixedId,
                    'date'    => $order->created_at->format('Y-m-d'),
                    'status'  => $order->status,
                    'content' => $content,
                    'reply'   => $reply ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب تفاصيل الطلب.'
            ], 500);
        }
    }
}