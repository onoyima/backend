<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostelSignout extends Model
{
    use HasFactory;
    protected $table = 'hostel_signouts';
    protected $fillable = [
        'exeat_request_id', 'hostel_admin_id', 'signout_time', 'signin_time', 'status'
    ];

    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class);
    }
    public function hostelAdmin()
    {
        return $this->belongsTo(Staff::class, 'hostel_admin_id');
    }
}
