<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;
    protected $table = 'audit_logs';
protected $fillable = [
  'user_id','hospital_id','action','table_name',
  'record_id','old_values','new_values','ip_address',
  'created_at','updated_at'
];

    // Who performed the action
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    // App\Models\AuditLog.php

public function patientUser()
{
    return $this->belongsTo(User::class, 'record_id');
}

}
