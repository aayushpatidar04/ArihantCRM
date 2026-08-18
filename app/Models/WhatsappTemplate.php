<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp_number_id',
        'template_id',
        'name',
        'language',
        'category',
        'status',
        'components',
        'local_config',
        'is_enabled',
        'last_synced_at',
    ];

    protected $casts = [
        'components' => 'array',
        'local_config' => 'array',
        'is_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function whatsappNumber(): BelongsTo
    {
        return $this->belongsTo(
            WhatsappNumber::class,
            'whatsapp_number_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isApproved(): bool
    {
        return strtoupper((string) $this->status) === 'APPROVED';
    }

    public function hasHeaderMedia(): bool
    {
        foreach ($this->components ?? [] as $component) {
            if (
                strtoupper($component['type'] ?? '') === 'HEADER' &&
                ! empty($component['format']) &&
                in_array(
                    strtoupper($component['format']),
                    ['IMAGE', 'VIDEO', 'DOCUMENT'],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    public function headerMediaType(): ?string
    {
        foreach ($this->components ?? [] as $component) {
            if (
                strtoupper($component['type'] ?? '') === 'HEADER'
            ) {
                $format = strtoupper(
                    $component['format'] ?? ''
                );

                if (
                    in_array(
                        $format,
                        ['IMAGE', 'VIDEO', 'DOCUMENT'],
                        true
                    )
                ) {
                    return $format;
                }
            }
        }

        return null;
    }

    public function headerMediaUrl(): ?string
    {
        return data_get(
            $this->local_config,
            'header_media_url'
        );
    }

    public function variableMappings(): array
    {
        return data_get(
            $this->local_config,
            'variables',
            []
        );
    }
}