<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('title', 200);
            $table->string('provider', 30)->nullable();
            $table->string('provider_id', 100)->nullable();
            $table->string('path')->nullable();
            $table->string('status', 15)->default('uploading');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('uploaded_by_name', 120)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'kind', 'created_at']);
        });
    }
};
