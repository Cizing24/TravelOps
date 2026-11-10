<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('scenario', 16)->default('success');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('cycle_attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->index(['status', 'available_at']);
        });
        Schema::create('refund_statements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->uuid('order_reference')->index();
            $table->unsignedInteger('amount');
            $table->string('result', 16);
            $table->timestamp('ready_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_statements');
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropIndex(['status', 'available_at']);
            $table->dropColumn(['scenario', 'attempts', 'cycle_attempts', 'available_at', 'processed_at']);
        });
    }
};
