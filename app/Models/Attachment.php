<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;
    protected $table = 'attachments';
    protected $fillable = [
        'exeat_request_id', 'file_url', 'uploaded_by', 'uploaded_at'
    ];

    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class);
    }
    // Optionally, you can add relationships to Student or Staff if needed
}
