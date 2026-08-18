<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTeamAccess extends Model
{
    use HasFactory;

    protected $table = 'user_team_access';

    protected $fillable = [
        'user_id',
        'team_id',
    ];

    /*
    |--------------------------------------------------------------------------
    | User
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
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
}