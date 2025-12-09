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
            'name'        => $this->full_name,
            'birth'       => $this->birth_date
                               ? $this->birth_date->format('Y-m-d')
                               : null,
            'phone'       => $this->phone,
            'email'       => $this->email,
            'file_number' => 'FILE-' . $this->id,
            'status'      => $this->status,
        ];
    }
}
