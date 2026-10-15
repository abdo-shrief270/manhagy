<?php

declare(strict_types=1);

namespace App\Modules\Sites\Http\Controllers;

use App\Modules\Sites\Support\Directory;
use App\Modules\Sites\Support\Slugs;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «منصتك»: the address, the look and the student rules of the account's platform. */
final class SiteController
{
    public function __construct(
        private readonly Directory $sites,
        private readonly CurrentTenant $tenant,
        private readonly Auditor $audit,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->present($this->sites->forTenant($this->tenant->idOrFail()))]);
    }

    public function update(Request $request): JsonResponse
    {
        $site = $this->sites->model($this->tenant->idOrFail());
        $request->merge(['slug' => Slugs::normalize((string) $request->input('slug', $site->slug))]);
        $data = $request->validate([
            'slug' => ['sometimes', 'string', 'min:3', 'max:30', Rule::unique('sites', 'slug')->ignore($site->id)],
            'title' => ['sometimes', 'string', 'min:2', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'color' => ['sometimes', Rule::in(Directory::COLORS)],
            'device_limit' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'registration_open' => ['sometimes', 'boolean'],
            'require_parent_phone' => ['sometimes', 'boolean'],
        ], [], ['slug' => 'اللينك', 'title' => 'اسم المنصة', 'device_limit' => 'عدد الأجهزة']);

        if (isset($data['slug']) && ! Slugs::valid($data['slug'])) {
            throw new DomainRuleException('اللينك حروف إنجليزي صغيرة وأرقام وشرطة بس (من 3 لـ 30)، ومش من الأسماء المحجوزة.', 'site_slug_invalid');
        }

        $before = $site->slug;
        $site->update($data);
        $this->audit->record('site.updated', $before !== $site->slug ? "غيّر لينك المنصة من {$before} لـ {$site->slug}" : 'عدّل إعدادات المنصة', $site);

        return response()->json(['data' => $this->present($site->toSummary())]);
    }

    /** @param  array<string, mixed>  $site */
    private function present(array $site): array
    {
        $host = (string) config('services.sites.host');

        return [...$site, 'url' => $host !== '' ? "https://{$site['slug']}.{$host}" : null, 'colors' => Directory::COLORS];
    }
}
