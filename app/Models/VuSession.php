<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VuSession extends Model
{
    use HasFactory;

    protected $table = 'vu_sessions';

    protected $fillable = [
        'session',
        'start_date',
        'end_date',
        'is_adm_processed',
        'is_hostel_processed',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_adm_processed' => 'boolean',
        'is_hostel_processed' => 'boolean',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the current active session (status = 1)
     */
    public static function getCurrentSession()
    {
        return self::where('status', 1)->first();
    }

    /**
     * Check if this session is the current active session
     */
    public function isCurrentSession()
    {
        return $this->status === 1;
    }

    /**
     * Scope to get only active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Get accommodation histories for this session
     */
    public function accommodationHistories()
    {
        return $this->hasMany(VunaAccomodationHistory::class, 'vu_session_id');
    }

    /**
     * Get the session name in a readable format
     */
    public function getSessionNameAttribute()
    {
        return $this->session;
    }

    /**
     * Check if the session is within the given date range
     */
    public function isWithinDateRange($date)
    {
        return $date >= $this->start_date && $date <= $this->end_date;
    }
}