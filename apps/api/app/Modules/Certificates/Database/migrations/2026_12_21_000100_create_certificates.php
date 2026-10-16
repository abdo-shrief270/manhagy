<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('course_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('min_exam_percent')->nullable(); // null = finishing the lessons is enough
            $table->string('title', 80)->default('شهادة إتمام');
            $table->string('signer_name', 80)->nullable();
            $table->string('signer_title', 80)->nullable();
            $table->timestamps();
        });

        // A copy of the names at the time it was given: it stays what was printed.
        Schema::create('certificates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->uuid('learner_id');
            $table->uuid('course_id');
            $table->string('learner_name', 120);
            $table->string('course_title', 150);
            $table->string('teacher_name', 120);
            $table->string('title', 80);
            $table->string('signer_name', 80)->nullable();
            $table->string('signer_title', 80)->nullable();
            $table->unsignedInteger('minutes')->default(0); // the course's video time
            $table->unsignedTinyInteger('exam_percent')->nullable(); // average of the best exam results
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 200)->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'learner_id']);
        });
    }
};
