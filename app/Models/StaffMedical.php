<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffMedical extends Model
{
    use HasFactory;
    protected $table = 'staff_medicals';
    protected $fillable = [
        'staff_id',
        'physical',
        'blood_group',
        'condition',
        'allergies',
        'genotype',
        'created_at',
        'updated_at',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
