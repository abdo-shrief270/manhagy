<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One account per student for the whole platform (their phone); each teacher only sees
        // the student's profile on their own platform.
        Schema::create('learners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('phone', 20)->unique(); // E.164
            $table->string('password');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('learner_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->uuid('grade_node_id')->nullable(); // curriculum node (grade / track / year)
            $table->string('parent_phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason', 200)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'learner_id']);
        });

        Schema::create('learner_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('device_hash', 64); // sha256 of the key the app keeps on the device
            $table->string('name', 120);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'learner_id', 'device_hash']);
        });
    }
};
