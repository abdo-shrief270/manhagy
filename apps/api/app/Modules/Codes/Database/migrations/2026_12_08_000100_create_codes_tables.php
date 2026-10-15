<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('kind', 12)->default('library'); // library | center | assistant | other
            $table->string('notes', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('code_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('number'); // CB-00001
            $table->string('label', 120)->nullable();
            $table->string('kind', 8); // course | balance
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->unsignedSmallInteger('days')->nullable(); // course: overrides the course's access_days
            $table->unsignedInteger('value')->default(0); // balance: piasters added to the wallet
            $table->unsignedInteger('unit_price')->default(0); // what one card sells for (the distributor's account)
            $table->unsignedInteger('count');
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('distributor_id')->nullable()->constrained('distributors')->nullOnDelete();
            $table->string('status', 10)->default('active'); // active | cancelled
            $table->string('cancelled_reason', 200)->nullable();
            $table->string('created_by_name', 120)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        // Only an HMAC of each code is kept: the codes themselves are shown once, when the batch is made.
        Schema::create('codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('code_batches')->cascadeOnDelete();
            $table->unsignedInteger('serial'); // the card's number within its batch
            $table->char('hash', 64)->unique();
            $table->string('status', 10)->default('unused'); // unused | used | cancelled
            $table->foreignUuid('used_by')->nullable()->constrained('learners')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->string('result_ref', 64)->nullable(); // the enrollment / wallet transaction it made
            $table->index(['batch_id', 'status']);
        });

        Schema::create('distributor_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->bigInteger('seq')->generatedAs()->always();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('distributor_id')->constrained('distributors')->cascadeOnDelete();
            $table->bigInteger('amount'); // collected from the distributor; a reversal is negative
            $table->uuid('reverses_id')->nullable();
            $table->string('note', 200)->nullable();
            $table->string('by_name', 120)->nullable();
            $table->timestamp('created_at');
        });
    }
};
