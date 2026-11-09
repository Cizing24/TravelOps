<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notification_email')->default(false);
            $table->boolean('notification_line')->default(false);
            $table->boolean('remind_expiry')->default(true);
        });
        Schema::table('tickets', fn (Blueprint $table) => $table->index(['status', 'expires_at']));
        Schema::create('notification_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('order_id')->nullable()->constrained();
            $table->foreignId('ticket_id')->nullable()->constrained();
            $table->string('template', 24);
            $table->string('event_key', 64);
            $table->string('title');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'template', 'event_key']);
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_message_id')->constrained();
            $table->string('channel', 12);
            $table->string('status', 12);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('cycle_attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_message_id', 'channel']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_messages');
        Schema::table('tickets', fn (Blueprint $table) => $table->dropIndex(['status', 'expires_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notification_email', 'notification_line', 'remind_expiry']));
    }
};
