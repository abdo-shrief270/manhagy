<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Console\GenerateVapidKeysCommand;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Notifications\Support\Notifier;
use App\Modules\Notifications\Support\PushSender;
use App\Modules\Notifications\Support\WebPushSender;
use App\Support\Modules\ModuleServiceProvider;

final class NotificationsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [];

    public function register(): void
    {
        $this->app->bind(PushSender::class, WebPushSender::class);
        $this->app->bind(Notifications::class, Notifier::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateVapidKeysCommand::class]);
        }
    }
}
