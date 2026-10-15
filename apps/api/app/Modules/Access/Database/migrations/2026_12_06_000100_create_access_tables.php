<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('source', 10); // manual | code | wallet | free
            $table->string('reference', 64)->nullable(); // the code / wallet entry it came from
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable(); // null = until the course closes
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 200)->nullable();
            $table->string('granted_by_name', 120)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'learner_id']);
            $table->index(['tenant_id', 'course_id']);
        });

        // Per student and lesson: views used, time watched, done.
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->unsignedSmallInteger('views')->default(0);
            $table->unsignedSmallInteger('extra_views')->default(0); // given by the teacher
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedInteger('pending_seconds')->default(0); // watched since the last counted view
            $table->unsignedInteger('last_position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'learner_id', 'lesson_id']);
        });

        // One open session per student: a newer one ends the others.
        Schema::create('playback_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->uuid('device_id')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('counted')->default(false);
            $table->string('ip', 45)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_beat_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 12)->nullable(); // stopped | replaced | expired
            $table->index(['tenant_id', 'learner_id', 'ended_at']);
        });
    }
};
