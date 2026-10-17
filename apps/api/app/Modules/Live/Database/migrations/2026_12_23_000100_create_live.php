<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One per live lesson: a room on the LiveKit server (live-{id}), recorded by its Egress.
        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('lesson_id')->unique();
            $table->string('status', 10)->default('idle'); // idle | live | ended
            $table->string('chat_mode', 10)->default('open'); // open | questions | off
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->string('egress_id', 64)->nullable();
            $table->string('recording', 12)->default('none'); // none | recording | collecting | processing | ready | failed
            $table->uuid('replay_media_id')->nullable();
            $table->string('recording_error', 300)->nullable();
            // OBS (LiveKit Ingress, RTMP): made on demand.
            $table->string('ingress_id', 64)->nullable();
            $table->string('ingress_url', 300)->nullable();
            $table->text('ingress_key')->nullable(); // encrypted
            $table->timestamps();
            $table->index(['status', 'recording']);
        });

        // A student watching: one open per student (a newer one ends the others), credited by beats.
        Schema::create('live_attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->uuid('learner_id');
            $table->uuid('device_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('last_beat_at');
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 16)->nullable(); // left | replaced | stale | session_ended
            $table->index(['session_id', 'ended_at']);
            $table->index(['learner_id', 'ended_at']);
        });

        Schema::create('live_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->bigInteger('seq')->generatedAs()->always();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->uuid('learner_id')->nullable(); // null = the teacher / an assistant
            $table->string('author_name', 120);
            $table->string('kind', 10); // chat | question
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->boolean('answered')->default(false);
            $table->boolean('hidden')->default(false);
            $table->timestamp('created_at');
            $table->index(['session_id', 'seq']);
        });

        // A raised hand: the teacher lets the student speak (microphone, and the camera if allowed).
        Schema::create('live_hands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->uuid('learner_id');
            $table->string('name', 120);
            $table->string('status', 10); // raised | speaking | done
            $table->boolean('camera')->default(false);
            $table->timestamp('raised_at');
            $table->timestamp('decided_at')->nullable();
            $table->unique(['session_id', 'learner_id']);
        });

        Schema::create('live_polls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->string('question', 300);
            $table->jsonb('options');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('live_poll_votes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('poll_id')->constrained('live_polls')->cascadeOnDelete();
            $table->uuid('learner_id');
            $table->unsignedTinyInteger('option');
            $table->timestamp('created_at');
            $table->unique(['poll_id', 'learner_id']);
        });
    }
};
