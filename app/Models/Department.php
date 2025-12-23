<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'department';

    protected $fillable = [
        'hospital_id',
        'head_user_id',
        'name',
        'status',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }
    
}
