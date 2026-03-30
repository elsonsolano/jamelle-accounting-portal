<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paymaya_imports', function (Blueprint $table) {
            $table->id();
            $table->string('gmail_message_id')->unique();
            $table->string('subject');
            $table->date('credit_date');
            $table->enum('status', ['processed', 'duplicate', 'failed'])->default('processed');
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymaya_imports');
    }
};
