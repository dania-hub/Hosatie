<?php

namespace App\Observers;

use App\Models\InternalSupplyRequest;

class InternalSupplyRequestObserver
{
    public function creating(InternalSupplyRequest $request)
    {
        // ملء pharmacy_id تلقائياً من requested_by (المستخدم)
        if ($request->requested_by && !$request->pharmacy_id) {
            $user = \App\Models\User::find($request->requested_by);
            
            if ($user && $user->pharmacy_id) {
                $request->pharmacy_id = $user->pharmacy_id;
            }
        }
    }
    
    public function updating(InternalSupplyRequest $request)
    {
        // نفس المنطق عند التحديث (إذا لزم الأمر)
        if ($request->requested_by && !$request->pharmacy_id) {
            $user = \App\Models\User::find($request->requested_by);
            
            if ($user && $user->pharmacy_id) {
                $request->pharmacy_id = $user->pharmacy_id;
            }
        }
    }
}
