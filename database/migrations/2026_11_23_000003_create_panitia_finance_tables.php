<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budgets', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount_planned', 15, 2);
            $table->timestamps();
        });
        Schema::create('expense_requests', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount_requested', 15, 2);
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
        Schema::create('financial_transactions', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('expense_request_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('transaction_type', ['income','expense']);
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('proof_file')->nullable();
            $table->string('proof_name')->nullable();
            $table->timestamps();
            $table->index(['event_period_id','transaction_date']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('budgets');
    }
};
