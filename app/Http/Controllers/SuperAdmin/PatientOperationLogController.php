<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Drug;
use Illuminate\Http\Request;

class PatientOperationLogController extends BaseApiController
{
    public function index(Request $request)
    {
        // Filter for patient-related tables only
        $patientTables = [
            'users', 
            'prescription', 
            'prescription_drug', 
            'prescription_drugs', 
            'dispensings'
        ];

        $query = AuditLog::whereIn('table_name', $patientTables)
            ->with(['user', 'patientUser', 'hospital']);

        // Search
        if ($request->has('search') && $request->input('search') != '') {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhereHas('patientUser', function($p) use ($search) {
                      $p->where('full_name', 'like', "%{$search}%")
                        ->orWhere('file_number', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->latest()->get();

        // Filter out non-patient user records if table is 'users'
        $filteredLogs = $logs->filter(function ($log) {
            if ($log->table_name === 'users') {
                // Check if the affected user is a patient
                $user = User::find($log->record_id);
                if ($user && $user->type !== 'patient') {
                    return false;
                }
                // If user deleted or not found, try to check old/new values for type
                if (!$user) {
                    $vals = json_decode($log->new_values ?? $log->old_values, true);
                    if (isset($vals['type']) && $vals['type'] !== 'patient') {
                        return false;
                    }
                }
            }
            return true;
        });

        $data = $filteredLogs->map(function ($log) {
            $patientName = $this->getPatientName($log);
            $fileNumber = $this->getFileNumber($log);
            
            // Format the operation text
            $formattedText = $this->formatOperationText($log);

            return [
                'id'              => $log->id,
                'file_number'     => $fileNumber,
                'full_name'       => $patientName,
                'operation_label' => $formattedText['label'],
                'operation_body'  => $formattedText['body'],
                'operation_type'  => $formattedText['label'], // For filtering
                'date'            => $log->created_at?->format('Y/m/d'),
            ];
        })->values(); // Reset keys after filter

        return response()->json($data);
    }

    /**
     * جلب اسم المريض
     */
    private function getPatientName($log)
    {
        // Logic copied and simplified from OperationLogSuperController
        if ($log->patientUser) {
            return $log->patientUser->full_name;
        }

        if ($log->table_name === 'users') {
            $patient = User::find($log->record_id);
            return $patient ? $patient->full_name : '-';
        }

        // Fallback to JSON parsing if needed (simplified for brevity, assuming relation works mostly)
        $values = json_decode($log->new_values ?? $log->old_values, true);
        if (isset($values['patient_name'])) return $values['patient_name'];
        if (isset($values['full_name'])) return $values['full_name'];
        
        return '-';
    }

    /**
     * الحصول على رقم الملف
     */
    private function getFileNumber($log)
    {
        if ($log->patientUser) {
            return $log->patientUser->file_number ?? $log->patientUser->id;
        }
        
        // For patients, record_id is often the user_id which is the file number in some contexts, 
        // or we need to look up the user.
        if ($log->table_name === 'users') {
            $user = User::find($log->record_id);
            return $user ? ($user->file_number ?? $user->id) : $log->record_id;
        }

        return $log->record_id;
    }

    /**
     * تنسيق نص العملية
     */
    private function formatOperationText($log)
    {
        $newValues = $log->new_values ? json_decode($log->new_values, true) : [];
        $oldValues = $log->old_values ? json_decode($log->old_values, true) : [];
        
        $action = $log->action;
        $label = $action;
        $body = '';

        // Translate Action
        $translations = [
            'create_patient' => 'إضافة مريض',
            'update_patient' => 'تعديل بيانات مريض',
            'delete_patient' => 'حذف مريض',
            'create' => 'إضافة',
            'update' => 'تعديل',
            'delete' => 'حذف',
        ];
        
        if (isset($translations[$action])) {
            $label = $translations[$action];
        }

        // Specific formatting
        if ($action === 'update_patient') {
            $body = 'تم تعديل البيانات الشخصية';
        } elseif ($action === 'create_patient') {
            $body = 'تم فتح ملف جديد';
        } elseif (in_array($log->table_name, ['prescription_drug', 'prescription_drugs'])) {
            $label = 'عملية على الأدوية';
            $drugName = '';
            if (isset($newValues['drug_id'])) {
                $drug = Drug::find($newValues['drug_id']);
                $drugName = $drug ? $drug->name : '';
            }
            $body = $drugName ? "دواء: $drugName" : '';
        }

        return ['label' => $label, 'body' => $body];
    }
}
