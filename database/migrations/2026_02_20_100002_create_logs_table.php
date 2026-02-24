<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('level', ['debug', 'info', 'warn', 'error', 'audit']);
            $table->string('module'); // e.g. 'auth', 'sales', 'inventory'
            $table->string('action'); // e.g. 'login', 'create_sale', 'update_price'
            $table->text('message');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->json('meta')->nullable(); // extra context data
            $table->timestamps();

            // Indexes for fast querying in analytics
            $table->index('level');
            $table->index('module');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['restock', 'sale', 'adjustment', 'damage', 'return']);
            $table->integer('quantity_before');
            $table->integer('quantity_change'); // positive = in, negative = out
            $table->integer('quantity_after');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('system_logs');
    }
};
