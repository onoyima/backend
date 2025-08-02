<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    // Allow mass assignment for these fields
    protected $fillable = [
        'name',
        'abb',
        'faculty_id',
        'category',
        'status',
    ];

    /**
     * The default attribute values.
     */
    protected $attributes = [
        'category' => 'academic',
        'status' => 1,
    ];

    /**
     * Relationships
     */
    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    /**
     * Casts
     */
    protected $casts = [
        'status' => 'integer',
    ];
}
