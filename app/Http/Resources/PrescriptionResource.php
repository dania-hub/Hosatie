<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'doctor_name'     => $this->doctor ? $this->doctor->full_name : null,
            'status'          => $this->status,
            'start_date'      => $this->start_date,
            'end_date'        => $this->end_date,
            'drugs'           => $this->drugs->map(function ($pd) {
                return [
                    'name'           => $pd->drug->name ?? null,
                    'generic_name'   => $pd->drug->generic_name ?? null,
                    'strength'       => $pd->drug->strength ?? null,
                    'form'           => $pd->drug->form ?? null,
                    'monthly_quantity'=> $pd->monthly_quantity,
                    'note'           => $pd->note,
                ];
            }),
        ];
    }
}
