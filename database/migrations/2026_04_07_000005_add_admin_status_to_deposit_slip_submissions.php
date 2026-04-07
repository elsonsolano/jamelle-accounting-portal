<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_slip_submissions', function (Blueprint $table) {
            $table->enum('admin_status', ['pending', 'approved', 'rejected'])->default('pending')->after('is_duplicate');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_slip_submissions', function (Blueprint $table) {
            $table->dropColumn('admin_status');
        });
    }
};
