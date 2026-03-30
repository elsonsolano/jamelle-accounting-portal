<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passbook_entries', function (Blueprint $table) {
            $table->enum('source', ['manual', 'paymaya_auto'])->default('manual')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('passbook_entries', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
