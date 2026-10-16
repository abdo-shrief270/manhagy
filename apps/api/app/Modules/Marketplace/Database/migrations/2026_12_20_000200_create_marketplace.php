<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The account's choice: off until the teacher opts in.
        Schema::create('market_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('listed')->default(false);
            $table->string('headline', 120)->nullable(); // «مدرس فيزياء تالتة ثانوي — 15 سنة خبرة»
            $table->text('about')->nullable();
            $table->jsonb('hidden_courses')->default('[]');
            $table->timestamps();
        });

        // The search index: one row per listed course, rebuilt from the other modules' contracts.
        Schema::create('market_courses', function (Blueprint $table): void {
            $table->uuid('course_id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('site_slug', 30);
            $table->string('site_url', 200);
            $table->string('teacher', 120);
            $table->string('headline', 120)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->text('search_name');
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('access_days')->nullable();
            $table->uuid('node_id');
            $table->string('node_label', 500);
            $table->string('root_kind', 20); // stage | university | field
            $table->unsignedInteger('lessons_count')->default(0);
            $table->unsignedInteger('free_previews')->default(0);
            $table->unsignedInteger('students')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('indexed_at');
            $table->index('tenant_id');
        });
        DB::statement('ALTER TABLE market_courses ADD COLUMN node_ids uuid[] NOT NULL DEFAULT \'{}\'');
        DB::statement('CREATE INDEX market_courses_node_ids ON market_courses USING gin (node_ids)');
        DB::statement('CREATE INDEX market_courses_search ON market_courses USING gin (search_name gin_trgm_ops)');

        Schema::create('course_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('learner_id');
            $table->string('first_name', 60);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('hidden')->default(false); // by the platform admins
            $table->timestamps();
            $table->unique(['course_id', 'learner_id']);
        });

    }
};
