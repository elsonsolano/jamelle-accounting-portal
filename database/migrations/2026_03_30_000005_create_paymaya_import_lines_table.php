<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paymaya_import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('paymaya_imports')->cascadeOnDelete();
            $table->string('bank_account');
            $table->decimal('amount', 15, 2);
            $table->date('credit_date');
            $table->foreignId('passbook_id')->nullable()->constrained('passbooks')->nullOnDelete();
            $table->foreignId('passbook_entry_id')->nullable()->constrained('passbook_entries')->nullOnDelete();
            $table->enum('status', ['posted', 'duplicate', 'unmatched'])->default('posted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymaya_import_lines');
    }
};
