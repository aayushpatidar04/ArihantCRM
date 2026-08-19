<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Services\ConversationAccessService;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return app(ConversationAccessService::class)
            ->canAccessCustomer($user, $customer);
    }
}