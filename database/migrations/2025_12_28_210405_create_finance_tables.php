<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Finance Fees (Invoices)
        Schema::create('finance_fees', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            $table->foreignId('student_id')->constrained();

            $table->string('title');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('balance', 10, 2);

            $table->enum('status', ['pending', 'partial', 'cleared'])->default('pending');
            $table->date('due_date')->nullable();

            $table->timestamps();
        });

        // 2. Finance Transactions (The Cashbook)
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            $table->string('transaction_id')->nullable();
            $table->enum('type', ['income', 'expense']);

            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->text('note')->nullable();

            // For fee payments
            $table->foreignId('finance_fee_id')->nullable()->constrained();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_fees');
    }
};
