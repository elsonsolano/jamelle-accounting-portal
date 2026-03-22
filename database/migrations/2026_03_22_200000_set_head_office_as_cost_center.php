<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branches')
            ->where('name', 'Head Office')
            ->update(['is_cost_center' => true]);
    }

    public function down(): void
    {
        DB::table('branches')
            ->where('name', 'Head Office')
            ->update(['is_cost_center' => false]);
    }
};
