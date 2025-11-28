<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'doctor_name'   => $this->doctor->full_name ?? 'Unknown',
            'status'        => $this->status, // active, cancelled, suspended
            'status_label'  => $this->getStatusLabel(),
            'start_date'    => $this->start_date->format('Y-m-d'),
            'end_date'      => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            
            // FR-11: Nested Medicines
            'medicines'     => PrescriptionDrugResource::collection($this->whenLoaded('drugs')),
        ];
    }

    private function getStatusLabel()
    {
        // FR-12 Logic: Translate status
        return match($this->status) {
            'active'    => 'متوفرة (Available)',
            'cancelled' => 'ملغاة (Cancelled)',
            'suspended' => 'معلقة (Suspended)',
            default     => $this->status,
        };
    }
}
