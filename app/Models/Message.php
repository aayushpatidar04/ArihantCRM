<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            if ($message->direction === 'outbound') {
                $message->conversation_user_id ??= $message->sent_by;
                $message->conversation_team_id ??= $message->team_id;
            }
        });
    }

    protected $fillable = [
        'customer_id',
        'team_id',
        'whatsapp_number_id',
        'sent_by',
        'conversation_user_id',
        'conversation_team_id',
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
        'metadata'
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

    public function conversationUser()
    {
        return $this->belongsTo(User::class, 'conversation_user_id');
    }

    public function conversationTeam()
    {
        return $this->belongsTo(Team::class, 'conversation_team_id');
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
}