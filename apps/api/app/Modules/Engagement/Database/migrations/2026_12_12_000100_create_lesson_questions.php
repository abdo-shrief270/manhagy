<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->foreignUuid('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('position')->nullable(); // second of the video it was asked at
            $table->string('status', 10)->default('open'); // open | answered | hidden
            $table->text('answer')->nullable();
            $table->boolean('public')->default(false); // the answer is shown to every student of the lesson
            $table->string('answered_by_name', 120)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['lesson_id', 'learner_id']);
        });
    }
};
