<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('phone', 30)->nullable();
        });

        Schema::create('event_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->date('event_date')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft')->index();
            $table->timestamps();
        });
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['event_period_id','slug']);
        });
        Schema::create('committee_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 40);
            $table->string('position_title')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['event_period_id','user_id','role','division_id'], 'member_role_division_unique');
            $table->index(['event_period_id','role']);
        });
        Schema::create('sop_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->default('umum');
            $table->text('content');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 90)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->timestamps();
            $table->index(['event_period_id','created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('sop_guides');
        Schema::dropIfExists('committee_members');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('event_periods');
        Schema::table('users', fn(Blueprint $table) => $table->dropColumn(['is_super_admin','is_active','phone']));
    }
};
