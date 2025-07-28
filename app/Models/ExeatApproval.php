<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExeatApproval extends Model
{
    use HasFactory;
    protected $table = 'exeat_approvals';
    protected $fillable = [
        'exeat_request_id', 'staff_id', 'role', 'status', 'comment', 'method'
    ];

    /**
     * The role of the staff member for this approval step (dean, deputy_dean, cmd, etc.)
     * Used to enforce workflow logic in controllers.
     */
    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class);
    }
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
