<?php

declare(strict_types=1);

namespace App\Modules\Sites\Http\Controllers;

use App\Modules\Sites\Contracts\SiteDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Caddy's on_demand_tls "ask": a certificate for {slug}.<sites host> only when that platform
 * exists, so nobody can make the server request certificates for made-up names.
 */
final class SiteTlsController
{
    public function __invoke(Request $request, SiteDirectory $sites): Response
    {
        $domain = strtolower(trim((string) $request->query('domain')));
        $suffix = '.'.config('services.sites.host');
        $slug = str_ends_with($domain, $suffix) ? substr($domain, 0, -strlen($suffix)) : '';

        return response('', $slug !== '' && ! str_contains($slug, '.') && $sites->bySlug($slug) !== null ? 200 : 404);
    }
}
