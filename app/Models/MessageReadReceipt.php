<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageReadReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'reader_id',
        'reader_type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function reader()
    {
        return $this->morphTo('reader');
    }
}