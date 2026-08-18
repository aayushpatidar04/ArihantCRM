<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$user->team_id) {
            return false;
        }

        /*
         * Primary team owns the customer.
         */
        if ((int) $customer->team_id === (int) $user->team_id) {
            return true;
        }

        /*
         * Customer assigned directly to this user.
         */
        if ((int) $customer->assigned_to === (int) $user->id) {
            return true;
        }

        /*
         * Previous owner can still access the conversation.
         */
        if ((int) $customer->old_owner_id === (int) $user->id) {
            return true;
        }

        return false;
    }
}