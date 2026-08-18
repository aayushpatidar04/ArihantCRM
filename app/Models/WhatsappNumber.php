<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappNumber extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'phone_number_id',
        'waba_id',
        'business_account_id',
        'phone_number',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'code_verification_status',
        'access_token',
        'access_token_expires_at',
        'is_active',
        'last_connected_at',
        'last_webhook_at',
        'last_connection_check_at',
        'last_connection_error',
        'meta_whatsapp_setting_id',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'is_active' => 'boolean',
            'last_connection_check_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Team
    |--------------------------------------------------------------------------
    */

    public function metaWhatsappSetting()
    {
        return $this->belongsTo(MetaWhatsappSetting::class, 'meta_whatsapp_setting_id');
    }

    public function teams()
    {
        return $this->hasMany(Team::class, 'whatsapp_number_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function whatsappTemplates(): HasMany
    {
        return $this->hasMany(
            WhatsappTemplate::class,
            'whatsapp_number_id'
        );
    }
}