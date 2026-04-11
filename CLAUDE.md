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

# Manually trigger PayMaya settlement sync
php artisan paymaya:sync

# Debug PayMaya email MIME structure and parser output
php artisan paymaya:debug

# Manually send Messenger deposit slip reminder to all registered staff
php artisan messenger:send-reminder
```

## Architecture Overview

### Stack
- **Laravel 13** / **PHP 8.3**, **Alpine.js** (CDN via `<script defer>` in `layouts/app.blade.php`), **Tailwind CSS v4** (via `@tailwindcss/vite` — no `tailwind.config.js`)
- **spatie/laravel-permission** for roles/permissions, **maatwebsite/excel** for import/export
- Session driver: `database`. DB: MySQL `accounting_portal`, root, no password (local WAMP).
- No Livewire — was removed due to instability. Do not reintroduce it.
- No `google/apiclient` — Gmail API is called via raw Guzzle HTTP requests to avoid Windows path length issues with `google/apiclient-services`.

### Core Domain

The app tracks monthly expenses and sales per branch. The central entity chain is:

```
Branch → ExpensePeriod (branch + month + year, unique) → ExpenseEntry (expense rows)
                                                        → SalesEntry (sales rows)
                                                        → GrossSales (vat_itr per branch per period)
       → Passbook (bank account per branch)             → PassbookEntry (ledger rows)
```

`Branch` has an `is_cost_center` boolean. Cost center branches (Head Office) have expenses only — no sales, operating income, VAT/ITR, or net operating income.

### The Expense Period Show Page (`expense-periods/{id}`)

This is the most complex page. It is a single Alpine.js component (`x-data="periodApp()"`) defined inline in `resources/views/expense-periods/show.blade.php`. The PHP controller passes all data as JSON via `@json()` blade directives on page load. All subsequent mutations go through `fetch()` calls to the existing JSON API routes.

**Data flow:**
1. `ExpensePeriodController::show()` loads entries (with `creator`/`updater` names), categories, branches, and gross sales — all passed as blade variables.
2. Alpine.js holds the `entries` array in memory and reacts to add/edit/delete/search/sort without page reloads.
3. All computed panels (running totals, sidebar breakdowns, operating income, net operating income) are Alpine.js getters derived from the in-memory `entries` array — no server round-trips needed.
4. Mutations (add/edit/delete entry, save gross sales) call the JSON API endpoints and update the local array from the response.

**Right sidebar (Expenses tab)** differs by branch type:
- **Revenue branches** — three panels: Operational Expenses (subtotal), Overhead Expenses (subtotal), Operating Income (Gross Sales − Total Expenses, VAT/ITR, Net Operating Income)
- **Cost center branches** — single amber-styled "Overhead Expenses — All categories" panel using the `allCategoryTotals` getter (which is an alias of `categoryTotals`). No operational/overhead split, no Operating Income panel.

**Sales tab stats** — when sales entries exist, three stat cards appear above the table: Average Daily Sales, Biggest Day (with date), Lowest Day (with date). Computed via `avgDailySales`, `biggestSales`, `lowestSales` getters.

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

**Consolidated report cost center indicator:** Cost center branch column headers carry a `*` superscript and a footnote below the table explains that all their expenses are overhead in nature regardless of which section (Operational/Overhead) they appear in. The matrix structure is uniform across all branches — the footnote is the only differentiation.

`ExpenseCalculatorService` (`app/Services/`) is used exclusively by `ReportController`. It is bound as a singleton in `AppServiceProvider`. The show-page calculations are done client-side in Alpine.js.

### Key Model Behaviors

- **`ExpenseEntry`** auto-sets `created_by` / `updated_by` (FK to `users`) via `boot()` model events (`creating` sets both; `updating` sets only `updated_by`). Both are included in API responses and displayed as "by [name]" under each entry in the table.
- **`ExpenseEntry`** has a `sort_order` integer; default scope in `ExpensePeriod::expenseEntries()` is `sort_order ASC` then `date DESC`.
- **`SalesEntry`** follows the same `created_by`/`updated_by` boot pattern as `ExpenseEntry`. Sales are itemized entries — the total for a period is `SalesEntry::whereIn('period_id', ...)->sum('amount')`, not `GrossSales.amount`.
- **`GrossSales`** stores `vat_itr` (decimal 15,2, default 0) per branch per period — this is the VAT/ITR estimate used by the Branch Summary report. The `amount` field on `GrossSales` is a legacy single-value field and is no longer the source of truth for sales totals.
- All money columns are `decimal(15,2)`.
- **`User`** has a nullable `branch_id` FK and a `branch()` BelongsTo relationship.
- **`PassbookEntry`** follows the same `created_by`/`updated_by` boot pattern. Has a `source` enum (`manual` | `paymaya_auto` | `messenger_bot`) — auto-synced entries show an "Auto Sync" badge in the ledger view.
- **`AppSetting`** is a general-purpose key/value store (`app_settings` table, `key` as primary key). Use `AppSetting::get($key, $default)` and `AppSetting::set($key, $value)` for runtime-mutable config that must survive Railway deploys (e.g. OAuth tokens). Do not use it for static config — keep that in `.env`.

### Branch Summary Report (`/reports/branch-summary`)

- Aggregates `SalesEntry.amount` for Total Sales and `GrossSales.vat_itr` for VAT/ITR across a flexible date range (from/to month+year).
- Cost center branches show `—` for all revenue-side columns. Their expenses are included in `grandExpenses` and reduce the overall Operating Income, but they are excluded from `grandSales` and `grandVatItr`.
- Grand total formula: `grandOperating = grandSales - grandExpenses` (all branches), `grandNet = grandOperating - grandVatItr` (non-cost-center branches only).
- VAT/ITR is editable inline (single-month view only) via a hover-to-edit pencil icon that saves to `POST /gross-sales`.

### Authorization

Four roles (seeded via `RoleSeeder`):

| Role | Access |
|---|---|
| **Superadmin** | `manage users` permission — can access `/users` (create/list/edit users). No expense data bypass. |
| **Admin** | `Gate::before()` bypass in `AppServiceProvider` — full access to everything. |
| **Accountant** | Can add/edit/delete expense entries on **any** branch (no branch restriction). Can create expense periods. |
| **Viewer** | Read-only. |

`@can('manage users')` in Blade is true for both Superadmin (explicit permission) and Admin (Gate::before bypass).

### Passbooks (`/passbooks`)

Bank account ledger per branch. Entity chain:

```
Passbook (branch_id, bank_name, account_number, opening_balance, opening_date)
    → PassbookEntry (date, particulars, type, amount, source, linked_entry_id, expense_entry_id)
```

**Transaction types:** `deposit`, `withdrawal`, `transfer_in`, `transfer_out`, `bank_charge`, `interest`

**Transfer cascade:** Creating a `transfer_out` auto-creates a paired `transfer_in` in the target passbook. Both entries store each other's ID in `linked_entry_id`. Editing or deleting either side cascades to the linked entry automatically.

**Running balance** is computed in `PassbookController::show()` by iterating entries in date/id order from `opening_balance` — it is never stored.

**Passbook creation** is restricted to Admin/Superadmin (`can:manage users`). All roles can view passbooks and add/edit/delete entries.

### PayMaya Auto-Sync (`/paymaya`)

Automatically fetches PayMaya settlement emails from Gmail and posts deposits to the matching passbooks.

**Flow:**
1. `GmailService` authenticates via OAuth2 refresh token, then searches Gmail for emails from `noreply.settlement@maya.ph` with subject containing `SETTLEMENT BREAKDOWN` sent today.
2. The `.XLS` attachment is a UTF-16LE encoded HTML file disguised as Excel. `PaymayaSettlementParser` detects the BOM, converts to UTF-8, parses the HTML table, and extracts the `Amount credited` value per bank account (appears only on the first row of each bank account group).
3. Bank accounts are matched to passbooks by last 4 digits: `**1001` → `account_number LIKE '%1001'`.
4. Each matched bank account gets a `deposit` `PassbookEntry` with `source = paymaya_auto`.
5. Every processed email is recorded in `paymaya_imports` (keyed by `gmail_message_id`). Re-processing the same email creates a `duplicate` status record — admin must manually review/delete.

**Shared processing logic** lives in `PaymayaSyncService::processEmails()` and `processEmail()`. Both the `paymaya:sync` Artisan command and the `PaymayaController::searchAndSync()` web action delegate to this service — do not duplicate the logic.

**Manual subject search** (`POST /paymaya/search-sync`): The `/paymaya` page has a "Search & Process by Subject" form. It calls `GmailService::fetchSettlementEmailsBySubject(string $subject)` which searches by subject only (no date constraint), then runs the same `PaymayaSyncService` pipeline. Use this to recover missed emails when the cron fails.

**Date-range email filter:** Both fetch methods skip emails whose subject contains a date range pattern like `03/01 to 03/31` (monthly summary reports). Only single-date subjects like `Apr/07/2026` are processed. The filter lives in `GmailService::fetchEmailsByQuery()`.

**Key env vars:** `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `PAYMAYA_SENDER`

**Gmail OAuth setup:** Visit `/paymaya` → Connect Gmail (one-time). The refresh token is stored in the `app_settings` table (key: `google_refresh_token`) — not in `.env`. This survives Railway deploys without any manual copy-paste. To re-authorize after token expiry, click **Reconnect Gmail** on the same page.

**Railway cron:** The worker service (`accounting-worker`) runs `php artisan schedule:run` on a `*/5 * * * *` schedule. All scheduled commands are defined in `routes/console.php` — do not add new Railway cron services, add to the Laravel scheduler instead. Current schedule: `paymaya:sync` Mon–Fri 23:00 PHT (15:00 UTC), `messenger:send-reminder` daily 10:00 PHT (02:00 UTC). **Important:** Cron expressions in `routes/console.php` must be written in **PHT (Asia/Manila)** because `APP_TIMEZONE=Asia/Manila` — Laravel interprets cron expressions in the app timezone, not UTC. Schedule times must be on 5-minute boundaries (`:00`, `:05`, etc.) to be caught reliably by the `*/5 * * * *` Railway cron.

**SSL on Windows:** `GmailService` auto-detects `C:/wamp64/cacert.pem` for local WAMP; falls back to system CA bundle on Linux/Railway.

### Messenger Bot (`/deposit-slips`, `/messenger/utils`)

Staff submit bank deposit slip photos via Facebook Messenger → Claude Vision extracts data → `PassbookEntry` auto-created → admin reviews at `/deposit-slips`.

**Flow:**
1. Staff message the Facebook Page "Jamelle Corp BOT". First-time senders are prompted for their Employee Code.
2. Employee Code is validated via `GET {EMPLOYEE_API_URL}?code={code}` (returns `{ exists: true/false }`).
3. On success, staff are saved as `MessengerStaff` (state: `active`) and sent the registration confirmation message.
4. Subsequent image messages are processed by `DepositSlipParserService` using Claude Haiku vision — extracts bank name, account number, amount, date, reference number.
5. A `DepositSlipSubmission` is created and a matching `PassbookEntry` (source: `messenger_bot`) is auto-posted if a passbook is found.

**Key models:**
- `MessengerStaff` — `fb_sender_id` → `employee_code` → `branch_id`, `state` (`pending_code` | `active`)
- `DepositSlipSubmission` — `parse_status` (`success` | `low_confidence` | `failed`), `admin_status` (`pending` | `approved` | `rejected`), `is_duplicate`

**Key files:**
- `app/Http/Controllers/MessengerController.php` — webhook handler + admin CRUD + utilities
- `app/Services/MessengerService.php` — Graph API calls (sendText, downloadImage, getSenderName)
- `app/Services/DepositSlipParserService.php` — Claude Vision parsing + passbook matching
- `resources/views/messenger/utils.blade.php` — manual reminder trigger + registered staff list

**Bot status (as of 2026-04-08):** Dev mode only — Facebook Business Portfolio approval pending. Only app admins/testers can use it. Testers accept invites at `https://developers.facebook.com/settings/developer/requests/`.

**Daily reminder:** `messenger:send-reminder` command sends a 10 AM PHT reminder to all `MessengerStaff` records. Manually trigger via `/messenger/utils` or `php artisan messenger:send-reminder`.

**Deposit slip images** are stored on Cloudflare R2 (not local disk). `DepositSlipParserService::storeImage()` writes to `Storage::disk('r2')`. `MessengerController::serveImage()` generates a 5-minute signed temporary URL and redirects — it does not proxy the image. The `r2` disk in `config/filesystems.php` uses the S3 driver with `use_path_style_endpoint: true` and auto-detects `C:/wamp64/cacert.pem` for SSL on WAMP. The `image_path` column on `deposit_slip_submissions` stores the R2 object key (e.g. `deposit-slips/2026/04/08/slip_xxx.jpg`).

**Env vars:** `MESSENGER_PAGE_ACCESS_TOKEN`, `MESSENGER_VERIFY_TOKEN`, `MESSENGER_APP_SECRET`, `EMPLOYEE_API_URL`, `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_PUBLIC_URL`

### Mobile Responsiveness

The app is fully mobile-responsive. Two patterns are used consistently — follow them when adding new pages or layouts:

**Stacked sidebar layouts** — any page with a main content area + right sidebar uses:
```html
<div class="flex flex-col lg:flex-row gap-5 lg:items-start">
    <div class="flex-1 min-w-0">...</div>      {{-- main content --}}
    <div class="w-full lg:w-80 lg:shrink-0">...</div>  {{-- sidebar --}}
</div>
```
`lg:items-start` (not `items-start`) is critical — `items-start` in `flex-col` mode prevents children from stretching full width, which breaks `overflow-x-auto` on inner tables.

**Tables inside cards** — do NOT put `overflow-hidden` on the card wrapper if the table needs horizontal scroll. Use an inner wrapper instead:
```html
<div class="bg-white rounded-xl border ...">   {{-- no overflow-hidden --}}
    <div class="px-5 py-4 ...">card header</div>
    <div class="overflow-x-auto">
        <table class="min-w-full ...">...</table>
    </div>
</div>
```
`overflow-hidden` on a parent clips the child's scroll container, making `overflow-x-auto` silently fail.

**Navigation** — `layouts/app.blade.php` has a hamburger menu (`x-data="{ navOpen: false }"`) that shows/hides on mobile. Desktop nav uses `hidden md:flex`.

### Tailwind v4 Notes

CSS entry point is `resources/css/app.css` using `@import 'tailwindcss'`. The `@tailwindcss/forms` plugin uses `strategy: 'class'`. Source scanning covers `resources/**/*.blade.php` and `resources/**/*.js`. The stale `@source '../../app/Livewire/**/*.php'` line in `app.css` is harmless but can be removed.

Dynamic Tailwind classes for category badge colors are defined as plain strings inside the `CATEGORY_COLORS` JS constant in `show.blade.php` — Tailwind's scanner picks them up from the blade file.

### Seeded Reference Data

- **Branches:** Head Office (cost center), SM Lanang, SM Ecoland, Ayala Abreeza, NCCC
- **32 expense categories** (see `ExpenseCategorySeeder`) — names are the keys in `CATEGORY_COLORS` in `show.blade.php`; name changes must be reflected in both places.
- **Default admin:** `admin@example.com` / `password`
- **Default superadmin:** `superadmin@example.com` / `password`

### Dashboard (`/`)

The dashboard accepts `?month=M&year=Y` query parameters to view any historical period. The selected month/year is persisted in the PHP session (`dashboard_month`, `dashboard_year`) so navigating away and returning restores the last selection. The dropdown shows the last 18 months. **Recent Sales and Recent Expenses panels are not period-filtered** — they always show the latest activity across all time.

### Expense Category Groupings

Categories are split into two groups used by the sidebar computed getters (`operationalCategoryTotals`, `overheadCategoryTotals`, `operationalTotal`, `overheadTotal`) in `show.blade.php`. The group membership is defined as hardcoded `Set` constants in those getters — if categories are added or renamed, update all four getters. The same lists are duplicated in `ReportController::consolidatedExpense()` — keep them in sync.

**Cost center branches bypass this split entirely** — the sidebar uses `allCategoryTotals` (all entries, no group filter) and shows one unified panel.

**Operational (12):** Staff Payroll and Allowance, Store Supplies, BIR & City Gov't Fees, Stocks Cost, Store Rental & CUSA, Pest Control, Hydro Lab, Tel, Cable, Internet & Cel., Fuel, Office Equipments, Logistics, Commissary Rental & Electricity

**Overhead (20):** Released 13th Month & SIL, Unreleased 13th Month, Store Maintenance, Equipment Maintenance, SSS Employer Share, Pag-ibig Employer Share, PHIC Employer Share, Representations, Other Expense, Service Incentive Leave(SIL), Retainer's Fee, Royalty Fee, Ads Fee, Ins., Renewals and Other Fees, Cashless Fees, Unreleased Separation/Retirement Pay, Released Separation/Retirement Pay, Miscellaneous, Loans Payable, Vehicle Maintenance
