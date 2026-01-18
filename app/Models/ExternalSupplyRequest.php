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
        'priority',
        'notes',
        'rejection_reason',
    ];
    protected $casts = [
        'handeled_at' => 'datetime',
        'notes' => 'array',
    ];

    /**
     * Add a note to the conversation thread.
     */
    public function addNote(string $message, \App\Models\User $user)
    {
        $notes = $this->notes ?? [];
        if (!is_array($notes)) {
             $notes = [];
        }

        $notes[] = [
            'by' => $user->type === 'supplier_admin' ? 'supplier_admin' : 'super_admin',
            'user_id' => $user->id,
            'user_name' => $user->full_name ?? 'Unknown',
            'message' => substr(trim($message), 0, 500),
            'created_at' => now()->toIso8601String(),
        ];

        $this->notes = $notes;
        $this->save();
    }

    /**
     * Get normalized messages for API.
     */
    public function getMessagesAttribute()
    {
        return $this->notes ?? [];
    }

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

    // ملاحظة: rejected_by غير موجود في الجدول
    // يمكن استخدام notes لحفظ معلومات الرفض

    public function items()
    {
        return $this->hasMany(ExternalSupplyRequestItem::class, 'request_id');
    }
}
