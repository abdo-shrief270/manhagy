<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('chapter_id')->nullable()->constrained('course_chapters')->nullOnDelete();
            $table->string('kind', 12); // mcq | true_false | fill
            $table->text('body');
            $table->json('options')->nullable(); // mcq: [{id, text}]
            $table->json('answer'); // mcq: option id; true_false: bool; fill: [accepted texts]
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->string('difficulty', 8)->default('medium'); // easy | medium | hard
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'course_id']);
        });

        // The exam behind a course lesson of kind exam.
        Schema::create('exams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->unique()->constrained('course_lessons')->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->unsignedTinyInteger('attempts_allowed')->default(1);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->unsignedTinyInteger('pass_percent')->default(50);
            $table->string('show_answers', 12)->default('after_close'); // after_submit | after_close | never
            $table->timestamps();
        });

        Schema::create('exam_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedSmallInteger('points');
            $table->unsignedSmallInteger('sort');
            $table->unique(['exam_id', 'question_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->unsignedInteger('seed'); // the student's own order, the same when they come back
            $table->timestamp('started_at');
            $table->timestamp('ends_at'); // fixed by the server at the start
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 12)->default('in_progress'); // in_progress | submitted
            $table->string('submitted_by', 8)->nullable(); // learner | timer
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('max_score');
            $table->unsignedTinyInteger('percent')->nullable();
            $table->unsignedSmallInteger('leaves')->default(0); // left the exam page
            $table->timestamps();
            $table->unique(['exam_id', 'learner_id', 'number']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('questions')->restrictOnDelete();
            $table->json('answer');
            $table->boolean('correct')->nullable();
            $table->unsignedSmallInteger('points_awarded')->default(0);
            $table->timestamp('answered_at');
            $table->unique(['attempt_id', 'question_id']);
        });
    }
};
