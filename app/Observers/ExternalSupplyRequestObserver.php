<?php

namespace App\Observers;

use App\Models\ExternalSupplyRequest;

class ExternalSupplyRequestObserver
{
    public function creating(ExternalSupplyRequest $request)
    {
        if ($request->requested_by) {
            $user = \App\Models\User::with('hospital')->find($request->requested_by);
            
            if ($user) {
                // ملء hospital_id من المستخدم
                if (!$request->hospital_id && $user->hospital_id) {
                    $request->hospital_id = $user->hospital_id;
                }
                
                // ملء supplier_id من المستشفى
                if (!$request->supplier_id && $user->hospital && $user->hospital->supplier_id) {
                    $request->supplier_id = $user->hospital->supplier_id;
                }
            }
        }
    }
    
    public function updating(ExternalSupplyRequest $request)
    {
        // نفس المنطق عند التحديث (إذا لزم الأمر)
        if ($request->requested_by && (!$request->hospital_id || !$request->supplier_id)) {
            $user = \App\Models\User::with('hospital')->find($request->requested_by);
            
            if ($user) {
                if (!$request->hospital_id && $user->hospital_id) {
                    $request->hospital_id = $user->hospital_id;
                }
                
                if (!$request->supplier_id && $user->hospital && $user->hospital->supplier_id) {
                    $request->supplier_id = $user->hospital->supplier_id;
                }
            }
        }
    }
}
