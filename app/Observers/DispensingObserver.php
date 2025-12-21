<?php

namespace App\Observers;

use App\Models\Dispensing;

class DispensingObserver
{
    public function creating(Dispensing $dispensing)
    {
        // ملء pharmacy_id تلقائياً من pharmacist (المستخدم)
        if ($dispensing->pharmacist_id && !$dispensing->pharmacy_id) {
            $pharmacist = \App\Models\User::find($dispensing->pharmacist_id);
            
            if ($pharmacist && $pharmacist->pharmacy_id) {
                $dispensing->pharmacy_id = $pharmacist->pharmacy_id;
            }
        }
    }
    
    public function updating(Dispensing $dispensing)
    {
        // نفس المنطق عند التحديث
        if ($dispensing->pharmacist_id && !$dispensing->pharmacy_id) {
            $pharmacist = \App\Models\User::find($dispensing->pharmacist_id);
            
            if ($pharmacist && $pharmacist->pharmacy_id) {
                $dispensing->pharmacy_id = $pharmacist->pharmacy_id;
            }
        }
    }
}
