<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalSupplyRequest extends Model
{
    use HasFactory;

    protected $table = 'internal_supply_requests';

    protected $fillable = [
        'pharmacy_id',
        'requested_by',
        'handeled_by', // Use existing column
        'status',
    ];

    protected $casts = [
        'handeled_at' => 'datetime',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
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
        return $this->hasMany(InternalSupplyRequestItem::class, 'request_id');
    }
}
