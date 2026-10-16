<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-wide (a student's phone is one account): the student sends the code by WhatsApp
        // from that phone; the inbound message confirms it.
        Schema::create('learner_phone_checks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('phone', 20); // E.164
            $table->string('purpose', 8); // verify | reset
            $table->char('code_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['phone', 'purpose']);
        });
    }
};
