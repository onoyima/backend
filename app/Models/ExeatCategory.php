<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExeatCategory extends Model
{
    use HasFactory;
    protected $table = 'exeat_categories';
    protected $fillable = [
        'name', // e.g. Medical, Holiday, Daily, Sleepover, Emergency
        'description',
        'status',
        'created_at',
        'updated_at',
    ];

    public function exeatRequests()
    {
        return $this->hasMany(ExeatRequest::class, 'category_id');
    }
}