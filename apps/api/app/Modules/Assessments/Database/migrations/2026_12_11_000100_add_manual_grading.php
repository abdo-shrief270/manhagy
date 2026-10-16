<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->json('answer')->nullable()->change(); // essay / photo: graded by a person
        });
        // Essay / photo answers wait for an assistant: correct stays null until graded.
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->string('photo_path')->nullable();
            $table->text('comment')->nullable();
            $table->string('graded_by_name', 120)->nullable();
            $table->timestamp('graded_at')->nullable();
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pending_grading')->default(0);
            $table->timestamp('graded_at')->nullable(); // the last manual answer graded
        });
    }
};
