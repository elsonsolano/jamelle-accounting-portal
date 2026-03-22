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
            // Accountants can only delete entries in their assigned branch's periods
            $branchId = $user->branch_id ?? null;
            return $branchId && $entry->period->branch_id === $branchId;
        }

        return false;
    }
}
