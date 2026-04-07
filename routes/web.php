<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\ExpensePeriodController;
use App\Http\Controllers\ExpenseEntryController;
use App\Http\Controllers\GrossSalesController;
use App\Http\Controllers\PassbookController;
use App\Http\Controllers\PassbookEntryController;
use App\Http\Controllers\PaymayaController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesEntryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Messenger Bot Webhook (no auth, no CSRF)
Route::get('/messenger/webhook', [MessengerController::class, 'verify'])->name('messenger.verify');
Route::post('/messenger/webhook', [MessengerController::class, 'webhook'])->name('messenger.webhook');

// Google OAuth callback (must be outside auth middleware)
Route::get('/paymaya/auth/callback', [PaymayaController::class, 'handleGoogleCallback'])->name('paymaya.auth.callback');

// Auth routes (Laravel Breeze / manual)
Route::middleware('guest')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);
});
Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    // Gross Sales (legacy input on expense period show page)
    Route::post('/gross-sales', [GrossSalesController::class, 'upsert'])
        ->name('gross-sales.upsert');

    // Sales module
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('/sales/{period}', [SalesController::class, 'show'])->name('sales.show');

    // Sales Entries (JSON API)
    Route::post('/sales-entries', [SalesEntryController::class, 'store'])->name('sales-entries.store');
    Route::put('/sales-entries/{salesEntry}', [SalesEntryController::class, 'update'])->name('sales-entries.update');
    Route::delete('/sales-entries/{salesEntry}', [SalesEntryController::class, 'destroy'])->name('sales-entries.destroy');

    // PayMaya Sync
    Route::get('/paymaya', [PaymayaController::class, 'index'])->name('paymaya.index');
    Route::get('/paymaya/auth', [PaymayaController::class, 'redirectToGoogle'])->name('paymaya.auth');
    Route::post('/paymaya/sync-now', [PaymayaController::class, 'syncNow'])->name('paymaya.sync-now');
    Route::post('/paymaya/search-sync', [PaymayaController::class, 'searchAndSync'])->name('paymaya.search-sync');
    Route::delete('/paymaya/imports/{import}', [PaymayaController::class, 'destroyImport'])->name('paymaya.destroy');

    // Passbooks
    Route::get('/passbooks', [PassbookController::class, 'index'])->name('passbooks.index');
    Route::get('/passbooks/create', [PassbookController::class, 'create'])->name('passbooks.create')->middleware('can:manage users');
    Route::post('/passbooks', [PassbookController::class, 'store'])->name('passbooks.store')->middleware('can:manage users');
    Route::patch('/passbooks/{passbook}', [PassbookController::class, 'update'])->name('passbooks.update')->middleware('can:manage users');
    Route::get('/passbooks/{passbook}', [PassbookController::class, 'show'])->name('passbooks.show');
    Route::get('/passbooks/{passbook}/entries/create', [PassbookEntryController::class, 'create'])->name('passbook-entries.create');
    Route::post('/passbooks/{passbook}/entries', [PassbookEntryController::class, 'store'])->name('passbook-entries.store');
    Route::get('/passbook-entries/{passbookEntry}/edit', [PassbookEntryController::class, 'edit'])->name('passbook-entries.edit');
    Route::put('/passbook-entries/{passbookEntry}', [PassbookEntryController::class, 'update'])->name('passbook-entries.update');
    Route::delete('/passbook-entries/{passbookEntry}', [PassbookEntryController::class, 'destroy'])->name('passbook-entries.destroy');

    // User management (Superadmin + Admin)
    Route::middleware('can:manage users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    // Deposit Slip Submissions (Messenger Bot)
    Route::get('/deposit-slips', [MessengerController::class, 'submissions'])->name('deposit-slips.index');
    Route::get('/deposit-slips/{submission}/image', [MessengerController::class, 'serveImage'])->name('deposit-slips.image');
    Route::post('/deposit-slips/{submission}/review', [MessengerController::class, 'markReviewed'])->name('deposit-slips.review');

    // Reports
    Route::get('/reports/consolidated', [ReportController::class, 'consolidatedExpense'])
        ->name('reports.consolidated');
    Route::get('/reports/branch-summary', [ReportController::class, 'branchSummary'])
        ->name('reports.branch-summary');
    Route::get('/reports/{period}/summary', [ReportController::class, 'summary'])
        ->name('reports.summary');
    Route::get('/reports/{period}/operating-income', [ReportController::class, 'operatingIncome'])
        ->name('reports.operating-income');
});
