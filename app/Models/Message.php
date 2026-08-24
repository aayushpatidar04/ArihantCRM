<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'team_id',
        'whatsapp_number_id',
        'sent_by',
        'whatsapp_message_id',
        'direction',
        'type',
        'body',
        'status',
        'failure_reason',
        'is_forwarded',
        'delivered_at',
        'read_at',
        'media_id',
        'media_mime_type',
        'media_filename',
        'media_caption',
        'metadata',
        'reaction_to_message_id',
    ];

    protected function casts(): array
    {
        return [
            'is_forwarded' => 'boolean',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

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
    | WhatsApp Number
    |--------------------------------------------------------------------------
    */

    public function whatsappNumber()
    {
        return $this->belongsTo(
            WhatsappNumber::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sent By
    |--------------------------------------------------------------------------
    */

    public function sentBy()
    {
        return $this->belongsTo(
            User::class,
            'sent_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Document
    |--------------------------------------------------------------------------
    */

    public function document()
    {
        return $this->hasOne(Document::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function reactionToMessage()
    {
        return $this->belongsTo(
            self::class,
            'reaction_to_message_id'
        );
    }

    public function reactions()
    {
        return $this->hasMany(
            self::class,
            'reaction_to_message_id'
        );
    }
}