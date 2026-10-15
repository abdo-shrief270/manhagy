<?php

declare(strict_types=1);

namespace App\Modules\Media;

use App\Modules\Media\Contracts\MediaLibrary;
use App\Modules\Media\Contracts\VideoHost;
use App\Modules\Media\Support\Hosts\FakeVideoHost;
use App\Modules\Media\Support\Library;
use App\Support\Modules\ModuleServiceProvider;
use InvalidArgumentException;

final class MediaServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoHost::class, fn () => match (config('services.video.driver', 'fake')) {
            'fake' => new FakeVideoHost,
            default => throw new InvalidArgumentException('Unknown video driver ['.config('services.video.driver').'].'),
        });
        $this->app->bind(MediaLibrary::class, Library::class);
    }
}
