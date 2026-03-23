# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Commands

```bash
# Full dev environment (server + queue + logs + vite hot-reload)
composer dev

# Run tests
composer test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run a single test method
php artisan test --filter=test_method_name

# Lint / fix code style (Laravel Pint)
./vendor/bin/pint

# Build frontend assets
npm run build

# Migrate and seed fresh
php artisan migrate:fresh --seed

# Seed only
php artisan db:seed
```

## Architecture Overview

### Stack
- **Laravel 13** / **PHP 8.3**, **Alpine.js** (CDN via `<script defer>` in `layouts/app.blade.php`), **Tailwind CSS v4** (via `@tailwindcss/vite` — no `tailwind.config.js`)
- **spatie/laravel-permission** for roles/permissions, **maatwebsite/excel** for import/export
- Session driver: `database`. DB: MySQL `accounting_portal`, root, no password (local WAMP).
- No Livewire — was removed due to instability. Do not reintroduce it.

### Core Domain

The app tracks monthly expenses and sales per branch. The central entity chain is:

```
Branch → ExpensePeriod (branch + month + year, unique) → ExpenseEntry (expense rows)
                                                        → SalesEntry (sales rows)
                                                        → GrossSales (vat_itr per branch per period)
```

`Branch` has an `is_cost_center` boolean. Cost center branches (Head Office) have expenses only — no sales, operating income, VAT/ITR, or net operating income.

### The Expense Period Show Page (`expense-periods/{id}`)

This is the most complex page. It is a single Alpine.js component (`x-data="expenseApp()"`) defined inline in `resources/views/expense-periods/show.blade.php`. The PHP controller passes all data as JSON via `@json()` blade directives on page load. All subsequent mutations go through `fetch()` calls to the existing JSON API routes.

**Data flow:**
1. `ExpensePeriodController::show()` loads entries (with `creator`/`updater` names), categories, branches, and gross sales — all passed as blade variables.
2. Alpine.js holds the `entries` array in memory and reacts to add/edit/delete/search/sort without page reloads.
3. All computed panels (running totals, category summary, operating income, net operating income) are Alpine.js getters derived from the in-memory `entries` array — no server round-trips needed.
4. Mutations (add/edit/delete entry, save gross sales) call the JSON API endpoints and update the local array from the response.

**Category color map** lives as a JS constant (`CATEGORY_COLORS`) in the `@push('scripts')` block of `show.blade.php`. If category names change, update it there too.

### JSON API Routes (all under `auth` middleware)

| Method | Route | Controller | Purpose |
|---|---|---|---|
| POST | `/expense-entries` | `ExpenseEntryController::store` | Create entry; returns entry with `category`, `creator`, `updater` |
| PUT | `/expense-entries/{id}` | `ExpenseEntryController::update` | Update entry; same response shape |
| DELETE | `/expense-entries/{id}` | `ExpenseEntryController::destroy` | Delete entry |
| POST | `/expense-entries/reorder` | `ExpenseEntryController::reorder` | Bulk sort_order update |
| POST | `/gross-sales` | `GrossSalesController::upsert` | Upsert `amount` and/or `vat_itr` for one branch/period — both fields are optional (omitting one leaves it unchanged) |
| POST | `/sales-entries` | `SalesEntryController::store` | Create sales entry |
| PUT | `/sales-entries/{id}` | `SalesEntryController::update` | Update sales entry |
| DELETE | `/sales-entries/{id}` | `SalesEntryController::destroy` | Delete sales entry |

### Report Routes

| Route | Controller Method | Purpose |
|---|---|---|
| `GET /reports/consolidated` | `ReportController::consolidatedExpense` | Matrix: categories × branches for a month/year |
| `GET /reports/branch-summary` | `ReportController::branchSummary` | Per-branch: Total Sales, Expenses, Operating Income, VAT/ITR, Net Operating Income — with flexible date range filter |
| `GET /reports/{period}/summary` | `ReportController::summary` | Category totals + operating income for one period |
| `GET /reports/{period}/operating-income` | `ReportController::operatingIncome` | Gross sales, expenses, VAT/ITR breakdown |

**Important:** `/reports/branch-summary` must be declared before `/reports/{period}/summary` in `routes/web.php` to avoid the wildcard swallowing it.

`ExpenseCalculatorService` (`app/Services/`) is used exclusively by `ReportController`. It is bound as a singleton in `AppServiceProvider`. The show-page calculations are done client-side in Alpine.js.

### Key Model Behaviors

- **`ExpenseEntry`** auto-sets `created_by` / `updated_by` (FK to `users`) via `boot()` model events (`creating` sets both; `updating` sets only `updated_by`). Both are included in API responses and displayed as "by [name]" under each entry in the table.
- **`ExpenseEntry`** has a `sort_order` integer; default scope in `ExpensePeriod::expenseEntries()` is `sort_order ASC` then `date DESC`.
- **`SalesEntry`** follows the same `created_by`/`updated_by` boot pattern as `ExpenseEntry`. Sales are itemized entries — the total for a period is `SalesEntry::whereIn('period_id', ...)->sum('amount')`, not `GrossSales.amount`.
- **`GrossSales`** stores `vat_itr` (decimal 15,2, default 0) per branch per period — this is the VAT/ITR estimate used by the Branch Summary report. The `amount` field on `GrossSales` is a legacy single-value field and is no longer the source of truth for sales totals.
- All money columns are `decimal(15,2)`.
- **`User`** has a nullable `branch_id` FK and a `branch()` BelongsTo relationship (used by `ExpenseEntryPolicy`).

### Branch Summary Report (`/reports/branch-summary`)

- Aggregates `SalesEntry.amount` for Total Sales and `GrossSales.vat_itr` for VAT/ITR across a flexible date range (from/to month+year).
- Cost center branches show `—` for all revenue-side columns. Their expenses are included in `grandExpenses` and reduce the overall Operating Income, but they are excluded from `grandSales` and `grandVatItr`.
- Grand total formula: `grandOperating = grandSales - grandExpenses` (all branches), `grandNet = grandOperating - grandVatItr` (non-cost-center branches only).
- VAT/ITR is editable inline (single-month view only) via a hover-to-edit pencil icon that saves to `POST /gross-sales`.

### Authorization

Four roles (seeded via `RoleSeeder`):

| Role | Access |
|---|---|
| **Superadmin** | `manage users` permission — can access `/users` (create/list users). No expense data bypass. |
| **Admin** | `Gate::before()` bypass in `AppServiceProvider` — full access to everything. |
| **Accountant** | Can add/edit expense entries scoped to their `branch_id` via `ExpenseEntryPolicy`. |
| **Viewer** | Read-only. |

`@can('manage users')` in Blade is true for both Superadmin (explicit permission) and Admin (Gate::before bypass).

### Tailwind v4 Notes

CSS entry point is `resources/css/app.css` using `@import 'tailwindcss'`. The `@tailwindcss/forms` plugin uses `strategy: 'class'`. Source scanning covers `resources/**/*.blade.php` and `resources/**/*.js`. The stale `@source '../../app/Livewire/**/*.php'` line in `app.css` is harmless but can be removed.

Dynamic Tailwind classes for category badge colors are defined as plain strings inside the `CATEGORY_COLORS` JS constant in `show.blade.php` — Tailwind's scanner picks them up from the blade file.

### Seeded Reference Data

- **Branches:** Head Office (cost center), SM Lanang, SM Ecoland, Ayala Abreeza
- **26 expense categories** (see `ExpenseCategorySeeder`) — names are the keys in `CATEGORY_COLORS` in `show.blade.php`; name changes must be reflected in both places.
- **Default admin:** `admin@example.com` / `password`
- **Default superadmin:** `superadmin@example.com` / `password`
