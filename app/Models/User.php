<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'is_active',
        'last_seen_at',
        'team_id',
        'bitrix_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = [
        'avatar_url',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }
    public function isTeamAdmin(): bool
    {
        return $this->hasRole('team_admin');
    }
    public function isExecutive(): bool
    {
        return $this->hasRole('executive');
    }

    /*
    |--------------------------------------------------------------------------
    | Primary Team
    |--------------------------------------------------------------------------
    */

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Additional Team Access
    |--------------------------------------------------------------------------
    */

    public function teamAccess()
    {
        return $this->hasMany(UserTeamAccess::class);
    }

    public function accessibleTeams()
    {
        return $this->belongsToMany(
            Team::class,
            'user_team_access'
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */

    public function assignedCustomers()
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    public function oldOwnerCustomers()
    {
        return $this->hasMany(Customer::class, 'old_owner_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sent_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public function uploadedDocuments()
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=10b981&color=fff';
    }
}