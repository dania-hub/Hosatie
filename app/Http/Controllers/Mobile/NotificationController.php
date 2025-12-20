<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends BaseApiController
{
    // 5.1 عرض قائمة الإشعارات للمستخدم
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($n) {
                return [
                    'id'          => $n->id,
                    'type'        => $n->type,               
                    'title'       => $n->title ?? 'إشعار',
                    'body'        => $n->message ?? '',     
                    'created_at' => $n->created_at->format('Y-m-d\TH:i:s.v\Z'),  
                    'is_read'     => (bool) $n->is_read, 
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $notifications
        ]);
    }

    // 5.2 تعليم الإشعارات كمقروءة (كلها أو بعضها)
   public function markAsRead(Request $request)
{
    $user = $request->user();
    
    $query = Notification::where('user_id', $user->id)
                        ->where('is_read', 0);  // 0 = غير مقروء

    if ($request->filled('notification_ids')) {
        $ids = $request->input('notification_ids');
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $query->whereIn('id', $ids);
    }

    $updatedCount = $query->update([
        'is_read' => 1,  // نحدثه إلى 1 (مقروء)
    ]);

    return response()->json([
        'success' => true,
        'message' => 'تم تعليم الإشعارات كمقروءة بنجاح',
        'updated_count' => $updatedCount,
    ]);
}
}