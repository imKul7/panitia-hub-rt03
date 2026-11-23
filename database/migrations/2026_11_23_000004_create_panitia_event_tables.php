<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitions', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('Umum');
            $table->text('rules')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
        Schema::create('participants', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('full_name');
            $table->string('phone',30)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone',30)->nullable();
            $table->string('age_group')->nullable();
            $table->timestamps();
        });
        Schema::create('competition_registrations', function(Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');
            $table->string('registration_code')->unique();
            $table->enum('status',['registered','confirmed','cancelled'])->default('registered');
            $table->timestamps();
            $table->unique(['competition_id','participant_id']);
        });
        Schema::create('competition_results', function(Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedTinyInteger('rank_position');
            $table->string('public_display_name')->nullable();
            $table->timestamp('publication_consent_at')->nullable();
            $table->foreignId('publication_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['competition_id','participant_id']);
            $table->unique(['competition_id','rank_position']);
        });
        Schema::create('event_schedules', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->enum('category',['lomba','malam_puncak','umum'])->default('umum');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
        Schema::create('announcements', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->longText('content');
            $table->enum('category',['umum','penting','jadwal','hasil'])->default('umum');
            $table->enum('status',['draft','published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['announcements','event_schedules','competition_results','competition_registrations','participants','competitions'] as $name) Schema::dropIfExists($name);
    }
};
