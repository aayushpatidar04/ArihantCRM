<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'bitrix_lead_id',
        'assigned_to',
        'old_owner_id',
        'name',
        'phone',
        'email',
        'team_id',
        'notes',
        'status',
        'tags',
        'last_contacted_at',
        'bitrix_assigned_by_id',
        'bitrix_created_at',
        'bitrix_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'last_contacted_at' => 'datetime',
            'bitrix_created_at' => 'datetime',
            'bitrix_synced_at' => 'datetime',
        ];
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
    | Assigned Executive
    |--------------------------------------------------------------------------
    */

    public function assignedTo()
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Previous Owner
    |--------------------------------------------------------------------------
    */

    public function oldOwner()
    {
        return $this->belongsTo(
            User::class,
            'old_owner_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    public function messages()
    {
        return $this->hasMany(Message::class)
            ->orderBy('created_at', 'desc');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)
            ->latestOfMany()
            ->select([
                'messages.id',
                'messages.customer_id',
                'messages.direction',
                'messages.body',
                'messages.created_at',
            ]);
    }

    public function latestInboundMessageForNumber(int $whatsappNumberId) {
        return $this->messages()
            ->where('whatsapp_number_id', $whatsappNumberId)
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first();
    }

    public function hasOpenWhatsappWindow(int $whatsappNumberId): bool {
        $message = $this->latestInboundMessageForNumber(
            $whatsappNumberId
        );

        if (! $message) {
            return false;
        }

        return $message->created_at
            ->greaterThanOrEqualTo(now()->subHours(24));
    }


    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Unread Messages
    |--------------------------------------------------------------------------
    */

    public function getUnreadCountAttribute(): int
    {
        return $this->messages()
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Phone
    |--------------------------------------------------------------------------
    */

    public function getFormattedPhoneAttribute(): string
    {
        return '+' . ltrim($this->phone, '+');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}