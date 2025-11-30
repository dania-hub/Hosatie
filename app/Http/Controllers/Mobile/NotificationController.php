<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Notification; // سننشئ الموديل المتوافق

class NotificationController extends BaseApiController
{
    // 5.1 عرض قائمة الإشعارات
    public function index(Request $request)
    {
        // جلب الإشعارات من جدول 'notification'
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function ($n) {
                return [
                    'id'         => $n->id,
                    'type'       => $n->type,   // 'عادي' أو 'مستعجل' حسب الميجريشن
                    'title'      => $n->title,
                    'body'       => $n->message, // العمود اسمه message
                    'created_at' => $n->created_at->diffForHumans(),
                    'is_read'    => (bool)$n->is_read, // العمود اسمه is_read
                ];
            });

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    // 7.1 تحديث حالة الإشعارات (مقروءة)
    public function markAsRead(Request $request)
    {
        $userId = $request->user()->id;
        
        $query = Notification::where('user_id', $userId)->where('is_read', false);

        if ($request->has('notification_ids')) {
             $query->whereIn('id', $request->notification_ids);
        }

        $query->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'الإشعارات تم تعليمها كمقروءة' ]);
    }
}
