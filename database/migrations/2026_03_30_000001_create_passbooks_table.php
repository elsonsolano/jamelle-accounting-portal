<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passbooks');
    }
};
