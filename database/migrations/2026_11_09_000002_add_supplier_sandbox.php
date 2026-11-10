<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('adapter', 16)->default('synchronous');
        });
        Schema::create('supplier_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->uuid('request_key')->unique();
            $table->uuid('order_reference');
            $table->unsignedInteger('quantity');
            $table->boolean('webhook')->default(false);
            $table->dateTime('ready_at');
            $table->timestamps();
            $table->index(['supplier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_statements');
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('adapter'));
    }
};
