<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low','medium','high','urgent'])->default('medium');
            $table->enum('status', ['todo','in_progress','review','done','blocked'])->default('todo');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['event_period_id','status','due_date']);
        });
        Schema::create('task_updates', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('status', ['todo','in_progress','review','done','blocked']);
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('division_notes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->longText('content');
            $table->enum('note_type', ['info','decision','issue','follow_up'])->default('info');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->index(['event_period_id','division_id','created_at']);
        });
        Schema::create('mentor_feedback', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->text('feedback_text');
            $table->timestamps();
        });
        Schema::create('documents', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('title');
            $table->string('document_type')->default('lainnya');
            $table->string('file_path');
            $table->string('original_name');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['documents','mentor_feedback','division_notes','task_updates','tasks'] as $name) Schema::dropIfExists($name);
    }
};
