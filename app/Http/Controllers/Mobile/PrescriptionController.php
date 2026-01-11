<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use App\Http\Resources\PrescriptionResource;
use App\Models\Prescription;
use Illuminate\Http\Request;

class PrescriptionController extends BaseApiController
{
    /**
     * FR-10 & FR-12: View Active Prescriptions (Current Quota)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $prescriptions = Prescription::with(['doctor', 'drugs' => function($q) {
                    $q->where('drugs.status', '!=', \App\Models\Drug::STATUS_ARCHIVED);
                }])
                ->where('patient_id', $user->id) 
                ->where('status', 'active')      
                ->latest()
                ->get();

            return $this->sendSuccess(
                PrescriptionResource::collection($prescriptions), 
                'الروشتات النشطة الحالية تم جلبها بنجاح'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في جلب الروشتات النشطة الحالية');
        }
    }

    /**
     * FR-11: View Prescription Details
     */
    public function show($id, Request $request)
    {
        try {
            $user = $request->user();

            $prescription = Prescription::with(['doctor', 'drugs' => function($q) {
                    $q->where('drugs.status', '!=', \App\Models\Drug::STATUS_ARCHIVED);
                }])
                ->where('patient_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$prescription) {
                return $this->sendError('لم يتم ايجاد الروشيتة .', [], 404);
            }

            return $this->sendSuccess(
                new PrescriptionResource($prescription),
                'Details retrieved.'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في جلب تفاصيل الروشيتة');
        }
    }

    /**
     * FR-13: View History (Cancelled or Expired)
     * Note: Dispensing history is in a different table 'dispensing'. 
     * Do you want 'Prescription History' or 'Dispensing History'?
     * Assuming Prescription History for now based on FR text.
     */
        // 6.3 سجل صرف الدواء
    public function history(Request $request)
    {
        // نحتاج موديل Dispensing
        $history = \App\Models\Dispensing::with(['drug', 'pharmacist', 'pharmacy'])
            ->where('patient_id', $request->user()->id)
            ->where('reverted', false) // نجلب الصرف الناجح فقط
            ->latest('dispense_month')
            ->get()
            ->map(function ($item) {
                return [
                    'medicine_name'   => $item->drug->name ?? 'Unknown',
                    'date'            => $item->created_at->format('Y-m-d'),
                    'pharmacist_name' => $item->pharmacist->full_name ?? 'Unknown',
                    'pharmacy_name'   => $item->pharmacy->name ?? 'Unknown',
                    'quantity'        => $item->quantity_dispensed,
                ];
            });

        return response()->json(['success' => true, 'data' => $history]);
    }

    }

