<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'national_id' => $this->national_id,
            'name'        => $this->full_name, // Mapped to 'full_name' in DB
            'birth'       => $this->birth_date, // Assuming you add 'birth_date' column to users table or use pivot
            'phone'       => $this->phone,
            'email'       => $this->email,
            'file_number' => 'FILE-' . $this->id, // Simulated file number
            'status'      => $this->status,
        ];
    }
}
