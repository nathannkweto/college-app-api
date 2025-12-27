<?php
// database/migrations/xxxx_xx_xx_create_finance_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;
    public function up(): void
    {
        // 1. Finance Fees Table
        Schema::create('finance_fees', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            // Link to Student
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->string('title'); // e.g., "Tuition Term 1"
            $table->decimal('total_amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->enum('status', ['pending', 'partial', 'cleared'])->default('pending');
            $table->date('due_date')->nullable();
            $table->date('last_payment_date')->nullable();

            $table->timestamps();
        });

        // 2. Finance Transactions Table
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            // Optional link to a specific fee (useful for audit, though not strictly required by prompt)
            $table->foreignId('finance_fee_id')->nullable()->constrained('finance_fees')->nullOnDelete();

            $table->string('transaction_id')->nullable(); // Bank Ref / Receipt ID
            $table->enum('type', ['income', 'expense']);
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->text('note')->nullable(); // "CLEARED" or "BALANCE"

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_fees');
    }
};
