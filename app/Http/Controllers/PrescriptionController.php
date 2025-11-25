<?php

namespace App\Http\Controllers;

use App\Http\Resources\PrescriptionResource;
use Illuminate\Http\Request;

class PrescriptionController extends BaseApiController
{
    public function patientPrescriptions(Request $request)
    {
        try {
            $user = $request->user();

            // Ensure only patients use this endpoint (optional, based on your type field)
            if ($user->type !== 'patient') {
                return $this->sendError('Only patients can view prescriptions.', [], 403);
            }

            // Fetch prescriptions with drugs and doctor info
            $prescriptions = $user->prescriptionsAsPatient()
                ->with([
                    'doctor:id,full_name',
                    'drugs.drug:id,name,generic_name,strength,form'
                ])
                ->orderByDesc('start_date')
                ->get();

            return $this->sendSuccess(
                PrescriptionResource::collection($prescriptions),
                'Prescriptions retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Patient Prescriptions Error');
        }
    }
}
