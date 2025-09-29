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
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function vuSession()
    {
        return $this->belongsTo(VuSession::class, 'vu_session_id');
    }

    public function accommodation()
    {
        return $this->belongsTo(VunaAccomodation::class, 'vuna_accomodation_id');
    }

    /**
     * Get the current accommodation for a student based on active session
     */
    public static function getCurrentAccommodationForStudent($studentId)
    {
        $currentSession = VuSession::getCurrentSession();
        
        if (!$currentSession) {
            return null;
        }

        return self::where('student_id', $studentId)
            ->where('vu_session_id', $currentSession->id)
            ->with('accommodation')
            ->first();
    }
}
