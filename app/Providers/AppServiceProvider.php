<?php

namespace App\Providers;

use App\Models\ExpenseEntry;
use App\Models\ExpensePeriod;
use App\Policies\ExpenseEntryPolicy;
use App\Policies\ExpensePeriodPolicy;
use App\Services\ExpenseCalculatorService;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExpenseCalculatorService::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(125);

        Gate::policy(ExpenseEntry::class, ExpenseEntryPolicy::class);
        Gate::policy(ExpensePeriod::class, ExpensePeriodPolicy::class);

        // Admin bypasses all gates
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Admin')) {
                return true;
            }
        });

        // Send already-authenticated users directly to the main page,
        // bypassing the / → /expense-periods intermediate redirect hop.
        RedirectIfAuthenticated::redirectUsing(fn () => route('dashboard'));
    }
}
