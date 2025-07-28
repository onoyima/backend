<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffContact extends Model
{
    use HasFactory;
    protected $table = 'staff_contacts';
    protected $fillable = [
        'staff_id',
        'contact_type',
        'contact_value',
        'is_primary',
        'created_at',
        'updated_at',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
