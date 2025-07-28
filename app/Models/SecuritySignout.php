<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecuritySignout extends Model
{
    use HasFactory;
    protected $table = 'security_signouts';
    protected $fillable = [
        'exeat_request_id', 'security_id', 'signout_time', 'signin_time', 'status'
    ];

    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class);
    }
    public function security()
    {
        return $this->belongsTo(Staff::class, 'security_id');
    }
}
