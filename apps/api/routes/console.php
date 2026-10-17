<?php

use App\Support\Monitoring\Health;
use App\Support\Monitoring\QueueHeartbeat;
use Illuminate\Support\Facades\Schedule;

// Safety net for the outbox: normally events are published right after commit.
Schedule::command('events:relay')->everyFiveSeconds()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Heartbeats for /api/v1/health: the scheduler runs, and a queue worker picks up a job.
Schedule::call(fn () => Health::beat('scheduler'))->name('monitoring:scheduler-beat')->everyMinute();
Schedule::job(new QueueHeartbeat)->name('monitoring:queue-beat')->everyMinute();

// Renewal reminders to shop owners, mid-morning Cairo time (once per step and end date).
Schedule::command('assessments:close-expired')->everyMinute()->withoutOverlapping();
Schedule::command('center:schedule')->dailyAt('00:17')->withoutOverlapping();
Schedule::command('billing:remind')->dailyAt('08:07')->withoutOverlapping();
Schedule::command('market:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('market:sync --all')->dailyAt('03:41')->withoutOverlapping();
Schedule::command('live:sync')->everyMinute()->withoutOverlapping();
