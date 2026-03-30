<?php

namespace App\Policies;

use App\Models\ExpenseEntry;
use App\Models\User;

class ExpenseEntryPolicy
{
    public function delete(User $user, ExpenseEntry $entry): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Accountant')) {
            return true;
        }

        return false;
    }
}
