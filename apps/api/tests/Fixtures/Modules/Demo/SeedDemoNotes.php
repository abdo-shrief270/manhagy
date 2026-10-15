<?php

namespace Tests\Fixtures\Modules\Demo;

use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** First time the demo module is enabled for an account, it gets three notes (like a real module's defaults). */
final class SeedDemoNotes extends ModuleListener
{
    protected function module(): string
    {
        return 'demo';
    }

    protected function shouldReact(DomainEvent $event): bool
    {
        return $event instanceof ModuleEnabled && $event->moduleKey === 'demo';
    }

    protected function react(DomainEvent $event): void
    {
        if (DemoNote::query()->exists()) {
            return;
        }
        foreach (['أول', 'تاني', 'تالت'] as $name) {
            DemoNote::create(['name' => $name]);
        }
    }
}
