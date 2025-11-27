<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionDrugResource extends JsonResource
{
    public function toArray($request)
    {
        // $this refers to the Drug model here
        return [
            'drug_id'       => $this->id,
            'name'          => $this->name,
            'strength'      => $this->strength,
            'unit'          => $this->unit,
            // Pivot data comes from the intermediate table
            'quantity'      => $this->pivot->monthly_quantity,
            'note'          => $this->pivot->note,
        ];
    }
}
