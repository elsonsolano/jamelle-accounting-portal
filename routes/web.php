<?php

use App\Http\Controllers\ExpensePeriodController;
use App\Http\Controllers\ExpenseEntryController;
use App\Http\Controllers\GrossSalesController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/expense-periods');

// Auth routes (Laravel Breeze / manual)
Route::middleware('guest')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);
});
Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth'])->group(function () {

    // Expense Periods
    Route::resource('expense-periods', ExpensePeriodController::class);

    // Expense Entries (JSON API)
    Route::post('/expense-entries', [ExpenseEntryController::class, 'store'])
        ->name('expense-entries.store');
    Route::put('/expense-entries/{expenseEntry}', [ExpenseEntryController::class, 'update'])
        ->name('expense-entries.update');
    Route::delete('/expense-entries/{expenseEntry}', [ExpenseEntryController::class, 'destroy'])
        ->name('expense-entries.destroy');
    Route::post('/expense-entries/reorder', [ExpenseEntryController::class, 'reorder'])
        ->name('expense-entries.reorder');

    // Gross Sales
    Route::post('/gross-sales', [GrossSalesController::class, 'upsert'])
        ->name('gross-sales.upsert');

    // User management (Superadmin + Admin)
    Route::middleware('can:manage users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });

    // Reports
    Route::get('/reports/consolidated', [ReportController::class, 'consolidatedExpense'])
        ->name('reports.consolidated');
    Route::get('/reports/{period}/summary', [ReportController::class, 'summary'])
        ->name('reports.summary');
    Route::get('/reports/{period}/operating-income', [ReportController::class, 'operatingIncome'])
        ->name('reports.operating-income');
});
