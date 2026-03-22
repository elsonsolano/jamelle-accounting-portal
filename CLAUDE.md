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

The app tracks monthly expenses per branch. The central entity chain is:

```
Branch → ExpensePeriod (branch + month + year, unique) → ExpenseEntry (rows in the sheet)
                                                        → GrossSales (per branch per period)
```

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
| POST | `/gross-sales` | `GrossSalesController::upsert` | Upsert gross sales for one branch/period |

### Report Routes

| Route | Controller Method | Purpose |
|---|---|---|
| `GET /reports/consolidated` | `ReportController::consolidatedExpense` | Matrix: categories × branches for a month/year |
| `GET /reports/{period}/summary` | `ReportController::summary` | Category totals + operating income for one period |
| `GET /reports/{period}/operating-income` | `ReportController::operatingIncome` | Gross sales, expenses, VAT/ITR breakdown |

`ExpenseCalculatorService` (`app/Services/`) is used exclusively by `ReportController`. It is bound as a singleton in `AppServiceProvider`. The show-page calculations are done client-side in Alpine.js.

### Key Model Behaviors

- **`ExpenseEntry`** auto-sets `created_by` / `updated_by` (FK to `users`) via `boot()` model events (`creating` sets both; `updating` sets only `updated_by`). Both are included in API responses and displayed as "by [name]" under each entry's particular in the table.
- **`ExpenseEntry`** has a `sort_order` integer; default scope in `ExpensePeriod::expenseEntries()` is `sort_order ASC` then `date DESC`.
- **`GrossSales`** is a separate table (period_id + branch_id) — one row per branch per period.
- All money columns are `decimal(15,2)`.
- **`User`** has a nullable `branch_id` FK and a `branch()` BelongsTo relationship (used by `ExpenseEntryPolicy`).

### Authorization

Four roles (seeded via `RoleSeeder`):

| Role | Access |
|---|---|
| **Superadmin** | `manage users` permission — can access `/users` (create/list users). No expense data bypass. |
| **Admin** | `Gate::before()` bypass in `AppServiceProvider` — full access to everything. |
| **Accountant** | Can add/edit expense entries scoped to their `branch_id` via `ExpenseEntryPolicy`. |
| **Viewer** | Read-only. |

`@can('manage users')` in Blade is true for both Superadmin (explicit permission) and Admin (Gate::before bypass).

User management routes (`GET/POST /users`) are behind `can:manage users` middleware.

### Tailwind v4 Notes

CSS entry point is `resources/css/app.css` using `@import 'tailwindcss'`. The `@tailwindcss/forms` plugin uses `strategy: 'class'`. Source scanning covers `resources/**/*.blade.php` and `resources/**/*.js`. The stale `@source '../../app/Livewire/**/*.php'` line in `app.css` is harmless but can be removed.

Dynamic Tailwind classes for category badge colors are defined as plain strings inside the `CATEGORY_COLORS` JS constant in `show.blade.php` — Tailwind's scanner picks them up from the blade file.

### Seeded Reference Data

- **Branches:** Head Office, SM Lanang, SM Ecoland, Ayala Abreeza
- **26 expense categories** (see `ExpenseCategorySeeder`) — names are the keys in `CATEGORY_COLORS` in `show.blade.php`; name changes must be reflected in both places.
- **Default admin:** `admin@example.com` / `password`
- **Default superadmin:** `superadmin@example.com` / `password`
