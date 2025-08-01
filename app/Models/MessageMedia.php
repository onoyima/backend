<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageMedia extends Model
{
    use HasFactory;

    protected $table = 'message_media';

    protected $fillable = [
        'message_id',
        'filename',
        'original_filename',
        'mime_type',
        'file_size',
        'file_path',
        'thumbnail_path',
        'metadata',
        'disk',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function getFileUrl(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function getFileSizeFormatted(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}