<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('whatsapp.team.{teamId}', function ($user, $teamId) {
    return (int) $user->team_id === (int) $teamId
        && $user->hasAnyRole([
            'team_admin',
            'executive',
        ]);
});

Broadcast::channel(
    'customer.{customerId}.messages',
    function ($user, $customerId) {
        return Customer::query()
            ->whereKey($customerId)
            ->where(function ($query) use ($user) {
                $query
                    ->whereHas('assignedTo', function ($q) use ($user) {
                        $q->where('id', $user->id);
                    })
                    ->orWhereHas('oldOwner', function ($q) use ($user) {
                        $q->where('id', $user->id);
                    });
            })
            ->exists();
    }
);