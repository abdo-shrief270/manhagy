<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A student's balance on one platform; moves only with a wallet_transactions row, under a row lock.
        Schema::create('learner_wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->bigInteger('balance')->default(0); // piasters
            $table->timestamps();
            $table->unique(['tenant_id', 'learner_id']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->bigInteger('seq')->generatedAs()->always();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('type', 16); // code | transfer | purchase | adjustment
            $table->bigInteger('amount'); // + in, − out
            $table->bigInteger('balance_after');
            $table->string('reference', 64)->nullable();
            $table->string('note', 200)->nullable();
            $table->string('by_name', 120)->nullable();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'learner_id', 'seq']);
        });

        Schema::create('wallet_topups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method', 16); // instapay | wallet | other
            $table->string('reference', 64)->nullable(); // the transfer's reference / sender
            $table->string('proof_path')->nullable();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->unsignedInteger('approved_amount')->nullable();
            $table->string('reason', 200)->nullable();
            $table->string('decided_by_name', 120)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('wallet_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('transfers_enabled')->default(false);
            $table->string('instapay', 60)->nullable();
            $table->string('wallet_number', 20)->nullable();
            $table->string('instructions', 500)->nullable();
            $table->unsignedInteger('min_topup')->default(1000);
            $table->timestamps();
        });
    }
};
