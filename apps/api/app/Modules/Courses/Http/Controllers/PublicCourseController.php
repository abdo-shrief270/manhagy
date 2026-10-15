<?php

declare(strict_types=1);

namespace App\Modules\Courses\Http\Controllers;

use App\Modules\Courses\Support\Catalog;
use App\Modules\Sites\Contracts\SiteDirectory;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;

/** A teacher's platform before signing in: the published courses. */
final class PublicCourseController
{
    public function index(string $slug, SiteDirectory $sites, CurrentTenant $tenant, Catalog $catalog): JsonResponse
    {
        $site = $sites->bySlug($slug) ?? abort(404);
        $courses = $tenant->runAs($site['tenant_id'], fn () => $catalog->published());

        return response()->json(['data' => array_map(fn (array $c) => array_intersect_key($c, array_flip([
            'id', 'title', 'description', 'price', 'access_days', 'grade', 'lessons_count', 'free_previews',
        ])), $courses)])->header('Cache-Control', 'public, max-age=60');
    }
}
