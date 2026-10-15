<?php

use App\Modules\Curriculum\Support\EgyptCurriculum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        Schema::create('curriculum_nodes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('kind', 20);
            $table->string('name', 160);
            $table->string('search_name', 160);
            $table->string('code', 40)->nullable(); // a university course's code
            $table->string('status', 10)->default('published'); // published | review | hidden
            $table->integer('sort')->default(0);
            $table->uuid('suggested_by_tenant')->nullable();
            $table->string('suggested_by_name', 120)->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'status', 'sort']);
            $table->index('status');
        });
        Schema::table('curriculum_nodes', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('curriculum_nodes')->restrictOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX curriculum_nodes_unique_name ON curriculum_nodes (COALESCE(parent_id, '00000000-0000-0000-0000-000000000000'::uuid), search_name)");
        DB::statement('CREATE INDEX curriculum_nodes_search ON curriculum_nodes USING gin (search_name gin_trgm_ops)');

        EgyptCurriculum::seed();
    }
};
