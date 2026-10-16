<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Teachers who teach at the center (contacts with a share, not users).
        Schema::create('center_teachers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('subject', 80)->nullable();
            $table->string('share_kind', 12)->default('percent'); // percent (of what was collected) | per_student (each attendance)
            $table->unsignedInteger('share_value')->default(0); // percent 0–100, or piasters per attendance
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('center_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('center_teachers')->nullOnDelete(); // null = the account itself
            $table->string('name', 120);
            $table->uuid('grade_node_id')->nullable();
            $table->uuid('course_id')->nullable(); // the online course whose lessons absent students can watch
            $table->string('room', 60)->nullable();
            $table->json('schedule'); // [{day: 0–6 (Sunday = 0), time: "16:00", minutes: 90}]
            $table->unsignedInteger('session_fee')->default(0); // piasters
            $table->unsignedInteger('monthly_fee')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('center_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('center_groups')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('plan', 8)->default('session'); // session | monthly
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->index(['group_id', 'left_at']);
        });

        Schema::create('center_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('center_groups')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('minutes')->default(90);
            $table->string('status', 10)->default('scheduled'); // scheduled | cancelled
            $table->string('topic', 160)->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'starts_at']);
            $table->index(['tenant_id', 'starts_at']);
        });

        // The id comes from the scanning device: a scan sent twice (offline, then again) is saved once.
        Schema::create('center_attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('center_sessions')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->boolean('late')->default(false);
            $table->timestamp('scanned_at');
            $table->string('by_name', 120)->nullable();
            $table->timestamp('created_at');
            $table->unique(['session_id', 'learner_id']);
        });

        Schema::create('center_handovers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete(); // whose drawer
            $table->string('user_name', 120);
            $table->bigInteger('expected');
            $table->bigInteger('counted');
            $table->bigInteger('difference');
            $table->string('note', 200)->nullable();
            $table->string('received_by_name', 120)->nullable();
            $table->timestamp('created_at');
        });

        // Money at the door; append-only (a mistake is a reversal row). Cash sits in the collector's drawer until handed over.
        Schema::create('center_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->bigInteger('seq')->generatedAs()->always();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('center_groups')->cascadeOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('center_sessions')->nullOnDelete();
            $table->string('kind', 8); // session | month
            $table->char('period', 7)->nullable(); // month: YYYY-MM
            $table->bigInteger('amount');
            $table->uuid('reverses_id')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('by_name', 120)->nullable();
            $table->foreignUuid('handover_id')->nullable()->constrained('center_handovers')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'user_id', 'handover_id']);
        });

        Schema::create('center_settlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->bigInteger('seq')->generatedAs()->always();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('center_teachers')->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->bigInteger('due');
            $table->bigInteger('paid');
            $table->string('note', 200)->nullable();
            $table->string('by_name', 120)->nullable();
            $table->timestamp('created_at');
        });
    }
};
