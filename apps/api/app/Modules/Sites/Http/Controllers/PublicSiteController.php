<?php

declare(strict_types=1);

namespace App\Modules\Sites\Http\Controllers;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Sites\Contracts\SiteDirectory;
use Illuminate\Http\JsonResponse;

/** What the student platform shows before anyone signs in. */
final class PublicSiteController
{
    public function __invoke(string $slug, SiteDirectory $sites, ShopDirectory $accounts): JsonResponse
    {
        $site = $sites->bySlug($slug) ?? abort(404);

        return response()->json(['data' => [
            'slug' => $site['slug'],
            'title' => $site['title'],
            'tagline' => $site['tagline'],
            'color' => $site['color'],
            'teacher' => $accounts->find($site['tenant_id'])?->name,
            'registration_open' => $site['registration_open'],
            'require_parent_phone' => $site['require_parent_phone'],
        ]])->header('Cache-Control', 'public, max-age=60');
    }
}
