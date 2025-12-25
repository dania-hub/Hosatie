<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'full_name'   => $this->full_name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'national_id' => $this->national_id,
            'type'        => $this->type,
            'status'      => $this->status,
            
            'hospital_id' => $this->hospital_id,
            'hospital_name' => $this->hospital ? $this->hospital->name : null,
            'hospital_code' => $this->hospital ? $this->hospital->code : null,
            'hospital_type' => $this->hospital ? $this->hospital->type : null,
            'hospital_city' => $this->hospital ? $this->hospital->city : null,
            'hospital_address' => $this->hospital ? $this->hospital->address : null,
            
            'hospital' => $this->whenLoaded('hospital', function () {
                return [
                    'id' => $this->hospital->id,
                    'name' => $this->hospital->name,
                    'code' => $this->hospital->code,
                    'type' => $this->hospital->type,
                    'city' => $this->hospital->city,
                    'address' => $this->hospital->address,
                    'phone' => $this->hospital->phone,
                ];
            }),
            
            // add other fields as needed
        ];
    }
}