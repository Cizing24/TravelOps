<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->unique();
            $t->string('scenario', 24)->default('success');
            $t->timestamps();
        });

        Schema::create('venues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('supplier_id')->constrained();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('merchant_users', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('venue_id')->constrained();
            $t->unique(['user_id', 'venue_id']);
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('supplier_id')->constrained();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('kind', 12);
            $t->text('description');
            $t->unsignedInteger('price');
            $t->unsignedSmallInteger('valid_days');
            $t->boolean('listed')->default(false)->index();
            $t->timestamps();
        });

        Schema::create('product_entitlements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('venue_id')->constrained();
            $t->unique(['product_id', 'venue_id']);
            $t->timestamps();
        });

        Schema::create('inventory_balances', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('product_id')->unique()->constrained();
            $t->unsignedInteger('available');
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->uuid('reference')->unique();
            $t->uuid('idempotency_key');
            $t->string('status', 24);
            $t->unsignedInteger('total');
            $t->char('currency', 3)->default('TWD');
            $t->dateTime('pay_by');
            $t->unique(['user_id', 'idempotency_key']);
            $t->index(['status', 'pay_by']);
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('supplier_id')->constrained();
            $t->string('name');
            $t->unsignedInteger('unit_price');
            $t->unsignedSmallInteger('quantity');
            $t->unsignedSmallInteger('valid_days');
            $t->json('venues');
            $t->timestamps();
        });

        Schema::create('inventory_reservations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->foreignId('product_id')->constrained();
            $t->unsignedSmallInteger('quantity');
            $t->string('status', 16)->index();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->uuid('idempotency_key')->unique();
            $t->string('status', 16);
            $t->unsignedInteger('amount');
            $t->dateTime('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->string('provider', 16);
            $t->string('external_event_id', 64);
            $t->char('body_hash', 64);
            $t->string('outcome', 32);
            $t->unique(['provider', 'external_event_id']);
            $t->timestamps();
        });

        Schema::create('outbox_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->uuid('trace_id')->unique();
            $t->string('status', 20);
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('cycle_attempts')->default(0);
            $t->dateTime('available_at');
            $t->dateTime('queued_until')->nullable();
            $t->string('last_error')->nullable();
            $t->index(['status', 'available_at', 'queued_until']);
            $t->timestamps();
        });

        Schema::create('integration_attempts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('outbox_event_id')->constrained();
            $t->unsignedSmallInteger('number');
            $t->string('result', 24);
            $t->string('error')->nullable();
            $t->unique(['outbox_event_id', 'number']);
            $t->timestamps();
        });

        Schema::create('tickets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->unsignedSmallInteger('sequence');
            $t->char('code_hash', 64)->unique();
            $t->text('code');
            $t->string('status', 16)->index();
            $t->unsignedSmallInteger('valid_days');
            $t->dateTime('first_used_at')->nullable();
            $t->dateTime('expires_at')->nullable()->index();
            $t->unique(['order_id', 'sequence']);
            $t->timestamps();
        });

        Schema::create('ticket_entitlements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('ticket_id')->constrained();
            $t->foreignId('venue_id')->constrained();
            $t->string('venue_name');
            $t->string('status', 16);
            $t->unique(['ticket_id', 'venue_id']);
            $t->timestamps();
        });

        Schema::create('redemptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('ticket_entitlement_id')->unique()->constrained();
            $t->foreignId('venue_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->uuid('trace_id')->unique();
            $t->dateTime('redeemed_at')->index();
            $t->timestamps();
        });

        Schema::create('refunds', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->uuid('idempotency_key')->unique();
            $t->string('status', 16)->index();
            $t->unsignedInteger('amount');
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->foreignId('order_id')->nullable()->constrained();
            $t->uuid('trace_id')->index();
            $t->string('action', 50)->index();
            $t->json('details')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'refunds', 'redemptions', 'ticket_entitlements', 'tickets', 'integration_attempts', 'outbox_events', 'webhook_events', 'payments', 'inventory_reservations', 'order_items', 'orders', 'inventory_balances', 'product_entitlements', 'products', 'merchant_users', 'venues', 'suppliers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
