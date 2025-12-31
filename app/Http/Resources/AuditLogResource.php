<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray($request)
    {
        // Extract details from JSON
        $newValues = json_decode($this->new_values, true);
        
        // Fallback values if JSON is empty
        $patientName = $newValues['full_name'] ?? 'غير معروف';
        $fileNumber  = $this->record_id; // Using User ID as File Number for now

        return [
            // Matches Vue: op.fileNumber
            'fileNumber'    => $fileNumber,
            
            // Matches Vue: op.name
            'name'          => $patientName,
            
            // Matches Vue: op.operationType
            'operationType' => $this->action, // e.g., "إضافة مريض جديد"
            
            // Matches Vue: op.operationDate
            'operationDate' => $this->created_at->format('Y/m/d'),
        ];
    }
}
