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
                'status'           => 'pending',
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

    // 6.2 تفاصيل الطلب (يجب معرفة النوع من الـ ID أو تمرير النوع)
    // الأفضل: تمرير النوع كـ Parameter، أو البحث في الجدولين.
    // سنفترض هنا أن الـ ID المرسل هو "T-5" أو "C-3" كما أرسلناه في الـ index
    public function show($mixedId)
    {
        $type = $mixedId[0]; // 'T' or 'C'
        $id = substr($mixedId, 2); // الرقم بعد الفاصلة

        if ($type === 'C') {
            $order = Complaint::find($id);
            $content = $order->message;
            $reply = $order->reply_message;
        } else {
            $order = PatientTransferRequest::with('toHospital')->find($id);
             // تحتاج علاقة toHospital
            $content = "طلب نقل إلى: " . ($order->toHospital->name ?? 'مستشفى');
            $reply = $order->rejection_reason;
        }

        if (!$order) {
             return response()->json(['success' => false, 'message' => 'الطلب غير موجود.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'      => $mixedId,
                'date'    => $order->created_at->format('Y-m-d'),
                'status'  => $order->status,
                'content' => $content,
                'reply'   => $reply, // الرد (سبب الرفض أو رد الشكوى)
            ]
        ]);
    }
}
