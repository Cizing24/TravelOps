<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160)->unique();
            $table->timestamps();
        });
        Schema::create('store_users', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->primary(['store_id', 'user_id']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->constrained();
            $table->string('source_channel', 12)->default('online');
            $table->string('payment_method', 16)->default('mock_online');
            $table->unique(['store_id', 'idempotency_key']);
            $table->index(['store_id', 'created_at']);
        });
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('order_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('kind', 16);
            $table->integer('delta');
            $table->string('reason', 500);
            $table->timestamp('created_at');
            $table->unique(['order_id', 'kind']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['store_id']);
            $table->dropUnique(['store_id', 'idempotency_key']);
            $table->dropIndex(['store_id', 'created_at']);
            $table->dropColumn(['store_id', 'source_channel', 'payment_method']);
        });
        Schema::dropIfExists('store_users');
        Schema::dropIfExists('stores');
    }
};
