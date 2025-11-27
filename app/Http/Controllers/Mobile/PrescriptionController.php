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

            $prescriptions = Prescription::with(['doctor', 'drugs'])
                ->where('patient_id', $user->id) // <--- Changed to patient_id
                ->where('status', 'active')      // <--- FR-12 Logic
                ->latest()
                ->get();

            return $this->sendSuccess(
                PrescriptionResource::collection($prescriptions), 
                'Current active prescriptions retrieved.'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching prescriptions');
        }
    }

    /**
     * FR-11: View Prescription Details
     */
    public function show($id, Request $request)
    {
        try {
            $user = $request->user();

            $prescription = Prescription::with(['doctor', 'drugs'])
                ->where('patient_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$prescription) {
                return $this->sendError('Prescription not found.', [], 404);
            }

            return $this->sendSuccess(
                new PrescriptionResource($prescription),
                'Details retrieved.'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching details');
        }
    }

    /**
     * FR-13: View History (Cancelled or Expired)
     * Note: Dispensing history is in a different table 'dispensing'. 
     * Do you want 'Prescription History' or 'Dispensing History'?
     * Assuming Prescription History for now based on FR text.
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            $history = Prescription::with(['doctor', 'drugs'])
                ->where('patient_id', $user->id)
                ->whereIn('status', ['cancelled', 'suspended']) // Old/Inactive stuff
                ->latest()
                ->get();

            return $this->sendSuccess(
                PrescriptionResource::collection($history),
                'History retrieved.'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching history');
        }
    }
}
