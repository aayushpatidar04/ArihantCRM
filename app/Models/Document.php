<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'team_id',
        'message_id',
        'uploaded_by',
        'original_filename',
        'stored_filename',
        'disk',
        'path',
        'mime_type',
        'size',
        'source',
        'status',
        'notes',
        'encryption_key_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected $appends = [
        'formatted_size',
        'url',
    ];

    /*
    |--------------------------------------------------------------------------
    | Customer
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Team
    |--------------------------------------------------------------------------
    */

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Message
    |--------------------------------------------------------------------------
    */

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Uploaded By
    |--------------------------------------------------------------------------
    */

    public function uploadedBy()
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Formatted Size
    |--------------------------------------------------------------------------
    */

    public function getFormattedSizeAttribute(): string
    {
        $size = $this->size;

        if ($size < 1024) {
            return $size . ' B';
        }

        if ($size < 1024 * 1024) {
            return round($size / 1024, 2) . ' KB';
        }

        if ($size < 1024 * 1024 * 1024) {
            return round(
                $size / (1024 * 1024),
                2
            ) . ' MB';
        }

        return round(
            $size / (1024 * 1024 * 1024),
            2
        ) . ' GB';
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }
}