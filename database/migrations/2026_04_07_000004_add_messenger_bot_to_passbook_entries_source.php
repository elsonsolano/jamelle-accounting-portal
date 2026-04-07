<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE passbook_entries MODIFY COLUMN source ENUM('manual', 'paymaya_auto', 'messenger_bot') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE passbook_entries MODIFY COLUMN source ENUM('manual', 'paymaya_auto') NOT NULL DEFAULT 'manual'");
    }
};
