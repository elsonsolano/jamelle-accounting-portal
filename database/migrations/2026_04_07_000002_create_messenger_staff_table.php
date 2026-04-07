<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_staff', function (Blueprint $table) {
            $table->id();
            $table->string('fb_sender_id')->unique();
            $table->string('fb_name')->nullable();
            $table->string('employee_code', 50)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('state', ['pending_code', 'active'])->default('pending_code');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_staff');
    }
};
