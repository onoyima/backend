<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VunaAccomodation extends Model
{
    use HasFactory;
    protected $table = 'vuna_accomodations';
    protected $fillable = [
        'name',
        'gender',
        'room_capacity',
        'price',
        'status',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public function hostelAdmins()
    {
        return $this->hasMany(HostelAdminAssignment::class, 'vuna_accomodation_id');
    }
}
