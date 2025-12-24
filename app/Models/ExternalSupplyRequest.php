<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalSupplyRequest extends Model
{
    use HasFactory;

    protected $table = 'external_supply_requests';

    protected $fillable = [
        'hospital_id',
        'supplier_id',
        'requested_by',
        'status',
        'handeled_by',
        'handeled_at',
    ];
protected $casts = [
        'handeled_at' => 'datetime',
    ];
    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'handeled_by');
    }

    public function items()
    {
        return $this->hasMany(ExternalSupplyRequestItem::class, 'request_id');
    }
}
