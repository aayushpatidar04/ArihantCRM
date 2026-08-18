<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'whatsapp_number_id',
        'parent_team_id',
        'external_department_id',
        'is_active',
        'hierarchy_path',
        'last_assigned_executive_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    // Primary users belonging to this team
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Users having additional access through pivot
    public function accessibleUsers()
    {
        return $this->belongsToMany(
            User::class,
            'user_team_access',
            'team_id',
            'user_id'
        )
        ->withTimestamps()
        ->withPivot('id');
    }




    public function teamAdmins()
    {
        return $this->users()
            ->role('team_admin');
    }

    /*
    |--------------------------------------------------------------------------
    | Executives
    |--------------------------------------------------------------------------
    */

    public function executives()
    {
        return $this->hasMany(User::class)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'executive');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */

    public function customers()
    {
        return $this->hasMany(Customer::class);
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
    | Documents
    |--------------------------------------------------------------------------
    */

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Number
    |--------------------------------------------------------------------------
    */

    public function whatsappNumber()
    {
        return $this->belongsTo(WhatsappNumber::class, 'whatsapp_number_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Parent / Child Teams
    |--------------------------------------------------------------------------
    */

    public function parentTeam()
    {
        return $this->belongsTo(
            Team::class,
            'parent_team_id'
        );
    }

    public function childTeams()
    {
        return $this->hasMany(
            Team::class,
            'parent_team_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Round Robin
    |--------------------------------------------------------------------------
    */

    public function lastAssignedExecutive()
    {
        return $this->belongsTo(
            User::class,
            'last_assigned_executive_id'
        );
    }
}