<?php

namespace App\Policies;

use App\Models\ExpensePeriod;
use App\Models\User;

class ExpensePeriodPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Accountant']);
    }

    public function delete(User $user, ExpensePeriod $period): bool
    {
        return $user->hasRole('Admin');
    }
}
