<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passbook_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('particulars');
            $table->enum('type', ['deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'bank_charge', 'interest']);
            $table->decimal('amount', 15, 2);
            $table->foreignId('linked_entry_id')->nullable()->constrained('passbook_entries')->nullOnDelete();
            $table->foreignId('expense_entry_id')->nullable()->constrained('expense_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passbook_entries');
    }
};
