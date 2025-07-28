<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VunaAccomodationHistory extends Model
{
    use HasFactory;
    protected $table = 'vuna_accomodation_histories';
    protected $fillable = [
        'user_id',
        'student_id',
        'vu_session_id',
        'vuna_accomodation_id',
        'vuna_accomodation_category_id',
        'vuna_acc_cate_room_id',
        'vuna_acc_cate_flat_id',
        'vuna_acc_cate_bunk_id',
        'tuition_fee_id',
        'bunk',
        'bunk_position',
        'is_free',
        'status',
        'created_at',
        'updated_at',
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }
}
