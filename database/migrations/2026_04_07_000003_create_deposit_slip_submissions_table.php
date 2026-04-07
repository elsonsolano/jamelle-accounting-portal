<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_slip_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('fb_sender_id');
            $table->foreignId('messenger_staff_id')->nullable()->constrained('messenger_staff')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Claude Vision extracted fields
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('deposit_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('depositor_name')->nullable();

            // Parse status
            $table->enum('parse_status', ['success', 'failed', 'low_confidence'])->default('failed');
            $table->text('confidence_notes')->nullable();
            $table->boolean('is_duplicate')->default(false);

            // Passbook link (if matched and created)
            $table->foreignId('passbook_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('passbook_entry_id')->nullable()->constrained('passbook_entries')->nullOnDelete();

            // Stored image
            $table->string('image_path')->nullable();

            // Admin review
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_slip_submissions');
    }
};
