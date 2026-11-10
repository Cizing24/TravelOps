<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->timestamp('cancel_available_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedSmallInteger('cancel_attempts')->default(0);
            $table->string('cancel_error', 40)->nullable();
        });
        Schema::table('supplier_statements', fn (Blueprint $table) => $table->timestamp('cancelled_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('supplier_statements', fn (Blueprint $table) => $table->dropColumn('cancelled_at'));
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->dropIndex(['cancel_available_at']);
            $table->dropColumn(['cancel_available_at', 'cancelled_at', 'cancel_attempts', 'cancel_error']);
        });
    }
};
