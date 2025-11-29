<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    protected $fillable = ['user_id', 'action', 'table_name', 'record_id', 'old_values', 'new_values', 'ip_address','created_at', 'updated_at'];

    // Who performed the action
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
