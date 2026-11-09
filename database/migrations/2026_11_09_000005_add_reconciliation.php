<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_statements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->uuid('order_reference')->index();
            $table->unsignedInteger('amount');
            $table->timestamps();
        });
        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('status', 16);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('matched')->default(0);
            $table->unsignedInteger('anomalies')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('reconciliation_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->char('fingerprint', 64)->unique();
            $table->uuid('order_reference')->index();
            $table->uuid('correlation_id')->nullable();
            $table->string('source', 12);
            $table->string('kind', 24);
            $table->string('severity', 12);
            $table->string('status', 12);
            $table->date('day');
            $table->json('evidence');
            $table->string('reason', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['day', 'status', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_findings');
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('payment_statements');
    }
};
