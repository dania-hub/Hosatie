<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $table = 'complaint';

    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'message',
        'status',
        'replied_by',
        'reply_message',
        'replied_at'
        // ,'created_at', 'updated_at'
       
    ];

    protected $casts = [
        'replied_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function replier()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
