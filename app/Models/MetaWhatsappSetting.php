<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetaWhatsappSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'meta_whatsapp_settings';

    protected $fillable = [
        'name',
        'app_id',
        'app_secret',
        'access_token',
        'verify_token',
        'webhook_url',
        'is_active',
        'last_webhook_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',

        // Sensitive Meta credentials
        'app_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'verify_token' => 'encrypted',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * WhatsApp numbers connected to this Meta configuration.
     *
     * One Meta App can manage multiple WhatsApp numbers.
     */
    public function whatsappNumbers()
    {
        return $this->hasMany(
            WhatsappNumber::class,
            'meta_whatsapp_setting_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Only active Meta WhatsApp configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether this Meta configuration
     * currently has any active WhatsApp numbers.
     */
    public function hasActiveNumbers(): bool
    {
        return $this->whatsappNumbers()
            ->where('is_active', true)
            ->exists();
    }


    /**
     * Count active WhatsApp numbers.
     */
    public function activeNumbersCount(): int
    {
        return $this->whatsappNumbers()
            ->where('is_active', true)
            ->count();
    }
}