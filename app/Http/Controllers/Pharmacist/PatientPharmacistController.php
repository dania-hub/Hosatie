<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Dispensing;
use App\Models\Inventory;
use App\Models\Drug;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Prescription;
class PatientPharmacistController extends BaseApiController
{
    /**
     * GET /api/pharmacist/patients
     * List patients for the pharmacist view
     */
    public function index(Request $request)
    {
        // Fetch all users of type 'patient'
        $patients = User::where('type', 'patient')
            ->get()
            ->map(function ($p) {
                return [
                    'fileNumber' => $p->id, // Using ID as file number
                    'name' => $p->full_name ?? $p->name, // Use full_name if available
                    'nationalId' => $p->national_id,
                    'birthDate' => $p->birth_date,
                    'phone' => $p->phone,
                    'lastUpdated' => $p->updated_at,
                    'medications' => [], // To be implemented if there's a current prescriptions table
                    'dispensationHistory' => []
                ];
            });

        return $this->sendSuccess($patients, 'تم تحديث قائمة المرضى بنجاح.');
    }

    /**
     * POST /api/pharmacist/dispense
     * Record the dispensation of drugs
     */
     
      public function dispense(Request $request)
    {
        $request->validate([
            'patientFileNumber' => 'required|exists:users,id',
            'dispensedItems' => 'required|array',
            // ...
        ]);

        DB::beginTransaction();
        try {
            $patient = User::findOrFail($request->patientFileNumber);
            $pharmacistId = $request->user()->id ?? 1;
            $pharmacyId = 1; 

            // =========================================================
            // STEP 1: Find the EXISTING Active Prescription
            // =========================================================
            $prescription = Prescription::where('patient_id', $patient->id)
                ->where('status', 'active') // Only look for active ones
                ->latest() // Get the most recent one
                ->first();

            if (!$prescription) {
                throw new \Exception("لا توجد وصفة طبية نشطة لهذا المريض. يرجى مراجعة الطبيب.");
            }

            // =========================================================
            // STEP 2: Dispense Drugs LINKED to that Prescription
            // =========================================================
            foreach ($request->dispensedItems as $item) {
                
                // A. Find Drug
                $drug = Drug::where('name', $item['drugName'])->first();
                if (!$drug) throw new \Exception("الدواء غير موجود: " . $item['drugName']);

                // B. Check & Deduct Inventory
                $inventory = Inventory::where('drug_id', $drug->id)->first();
                if (!$inventory || $inventory->current_quantity < $item['quantity']) {
                    throw new \Exception("الكمية غير كافية: " . $item['drugName']);
                }
                $inventory->current_quantity -= $item['quantity'];
                $inventory->save();

                // C. Create Dispensing Record linked to existing Prescription
                Dispensing::create([
                    'prescription_id' => $prescription->id, // 👈 Using Existing ID
                    'patient_id' => $patient->id,
                    'drug_id' => $drug->id,
                    'pharmacist_id' => $pharmacistId,
                    'pharmacy_id' => $pharmacyId,
                    'dispense_month' => now()->format('Y-m-d'),
                    'quantity_dispensed' => $item['quantity'],
                ]);
            }

            DB::commit();
            return $this->sendSuccess([], 'تم صرف الأدوية بناءً على الوصفة النشطة.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في العملية: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/pharmacist/patients/{fileNumber}/dispensations
     * Get history for a specific patient
     */
    public function history($fileNumber)
    {
        $patient = User::where('id', $fileNumber)->where('type', 'patient')->first();

        if (!$patient) {
            return $this->sendError('المريض غير موجود.');
        }

        // Fetch dispensation history
        $dispensations = Dispensing::with(['drug', 'pharmacist']) // Relations needed
            ->where('patient_id', $patient->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by Date/Transaction if needed, but simple list is okay
        // Here we format it to match the view structure roughly
        $formattedHistory = $dispensations->map(function ($d) {
            return [
                'id' => $d->id,
                'date' => $d->created_at,
                'pharmacistName' => $d->pharmacist ? $d->pharmacist->name : 'غير معروف',
                'totalItems' => 1, // Since we store per row, each row is 1 item type
                'items' => [
                    [
                        'drugName' => $d->drug ? $d->drug->name : 'غير معروف', 
                        'quantity' => $d->quantity, 
                        'unit' => $d->drug->unit ?? 'علبة'
                    ]
                ]
            ];
        });

        $response = [
            'patientInfo' => [
                'fileNumber' => $patient->id,
                'name' => $patient->full_name ?? $patient->name,
                'nationalId' => $patient->national_id
            ],
            'dispensations' => $formattedHistory
        ];

        return $this->sendSuccess($response, 'تم جلب سجل المريض بنجاح.');
    }
}
