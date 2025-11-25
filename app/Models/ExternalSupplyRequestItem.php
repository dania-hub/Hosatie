<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalSupplyRequestItem extends Model
{
    use HasFactory;

    protected $table = 'external_supply_request_item';

    protected $fillable = [
        'request_id',
        'drug_id',
        'requested_qty',
        'approved_qty',
        'fulfilled_qty',
    ];

    public function request()
    {
        return $this->belongsTo(ExternalSupplyRequest::class, 'request_id');
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
