<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Fees (The Demand)
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('student_id')->constrained();

            $table->string('title'); // "Tuition 2025"
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        // 2. Transactions (The Payment/Adjustment)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('transaction_id')->unique(); // Bank Ref / Receipt No

            $table->foreignId('fee_id')->nullable()->constrained(); // Nullable if general credit
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['income', 'expense']); // Income = Student Paid
            $table->boolean('is_fee_payment')->default(false);

            $table->date('date');
            $table->text('note')->nullable();
            $table->timestamps();

            // Index for finance reports
            $table->index(['type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_tables');
    }
};
