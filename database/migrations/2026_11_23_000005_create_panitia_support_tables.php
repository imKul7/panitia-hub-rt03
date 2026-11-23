<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prizes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('distributed_quantity')->default(0);
            $table->decimal('estimated_unit_cost',15,2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('prize_distributions', function(Blueprint $table) {
            $table->id();
            $table->foreignId('prize_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributed_by')->constrained('users');
            $table->string('recipient_name');
            $table->unsignedInteger('quantity');
            $table->timestamp('distributed_at');
            $table->timestamps();
        });
        Schema::create('inventory_items', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_in_use')->default(0);
            $table->string('condition')->default('baik');
            $table->string('location')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_movements', function(Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('movement_type',['borrow','return']);
            $table->unsignedInteger('quantity');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('gallery_items', function(Blueprint $table) {
            $table->id();
            $table->foreignId('event_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('title');
            $table->string('file_path');
            $table->string('mime_type',80);
            $table->boolean('consent_confirmed')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['gallery_items','inventory_movements','inventory_items','prize_distributions','prizes'] as $name) Schema::dropIfExists($name);
    }
};
