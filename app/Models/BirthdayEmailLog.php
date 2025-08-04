<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BirthdayEmailLog extends Model
{
    use HasFactory;

    protected $table = 'birthday_email_logs';

    protected $fillable = [
        'email',
        'birthday_date',
    ];

    // Optional: Use Carbon for date formatting
    protected $dates = ['birthday_date', 'created_at', 'updated_at'];
}
