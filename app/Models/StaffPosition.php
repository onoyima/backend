<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffPosition extends Model
{
    use HasFactory;
    protected $table = 'staff_positions';
    protected $fillable = [
        'name',
        'status',
        'created_at',
        'updated_at',
    ];
}
