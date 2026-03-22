<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('expense_periods')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->string('particular');
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_entries');
    }
};
