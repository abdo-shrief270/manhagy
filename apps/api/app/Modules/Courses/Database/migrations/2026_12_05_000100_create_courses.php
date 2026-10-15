<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('curriculum_node_id'); // a teachable node: subject / unit / university course
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->default(0); // piasters; 0 = free for every student
            $table->unsignedSmallInteger('access_days')->nullable(); // how long an enrollment lasts; null = until the course closes
            $table->string('status', 12)->default('draft'); // draft | published | archived
            $table->unsignedInteger('sort')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('course_chapters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title', 150);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('course_lessons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('chapter_id')->constrained('course_chapters')->cascadeOnDelete();
            $table->string('kind', 8); // video | pdf | exam
            $table->string('title', 150);
            $table->uuid('media_id')->nullable(); // video / pdf: a media_assets row
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('free_preview')->default(false);
            $table->timestamp('publish_at')->nullable(); // opens at; null = with the course
            $table->boolean('requires_previous')->default(false);
            $table->unsignedSmallInteger('max_views')->nullable(); // null = unlimited
            $table->unsignedTinyInteger('count_percent')->default(70); // a view counts after this share of the video
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['course_id', 'sort']);
        });
    }
};
