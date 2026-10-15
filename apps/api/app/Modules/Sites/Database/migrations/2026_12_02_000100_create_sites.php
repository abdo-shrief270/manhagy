<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('slug', 30)->unique();
            $table->string('title', 120);
            $table->string('tagline', 200)->nullable();
            $table->string('color', 20)->default('indigo');
            $table->unsignedTinyInteger('device_limit')->default(2);
            $table->boolean('registration_open')->default(true);
            $table->boolean('require_parent_phone')->default(false);
            $table->timestamps();
        });
    }
};
