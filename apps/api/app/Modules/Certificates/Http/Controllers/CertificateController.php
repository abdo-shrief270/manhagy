<?php

declare(strict_types=1);

namespace App\Modules\Certificates\Http\Controllers;

use App\Modules\Certificates\Models\Certificate;
use App\Modules\Certificates\Models\CertificateSetting;
use App\Modules\Certificates\Support\Issuer;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Sites\Contracts\SiteDirectory;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Certificates: checking one (anyone), the student's, and the teacher's settings and list. */
final class CertificateController
{
    public function __construct(private readonly Issuer $issuer) {}

    public function verify(string $code, SiteDirectory $sites): JsonResponse
    {
        $found = Certificate::withoutTenancy()->where('code', Issuer::normalize($code))->first() ?? abort(404);
        $site = $sites->forTenant($found->tenant_id);

        return response()->json(['data' => [
            ...$found->toPublic(),
            'url' => $this->issuer->url($found),
            'site' => ['title' => $site['title'], 'url' => $site['url'], 'color' => $site['color']],
        ]])->header('Cache-Control', 'public, max-age=300');
    }

    public function mine(LearnerDirectory $learners): JsonResponse
    {
        $learner = $learners->current() ?? abort(401);

        return response()->json(['data' => Certificate::query()->where('learner_id', $learner['learner_id'])->whereNull('revoked_at')->latest('issued_at')->get()
            ->map(fn (Certificate $c) => [...$c->toPublic(), 'course_id' => $c->course_id, 'url' => $this->issuer->url($c)])->all()]);
    }

    public function status(string $course, LearnerDirectory $learners, CourseCatalog $courses): JsonResponse
    {
        $learner = $learners->current() ?? abort(401);
        $courses->course($course) ?? abort(404);

        return response()->json(['data' => $this->issuer->status($learner['learner_id'], $course)]);
    }

    public function claim(string $course, LearnerDirectory $learners, CourseCatalog $courses): JsonResponse
    {
        $learner = $learners->current() ?? abort(401);
        $courses->course($course) ?? abort(404);
        $this->issuer->issue($learner['learner_id'], $course);

        return response()->json(['data' => $this->issuer->status($learner['learner_id'], $course)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['course_id' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:80']]);
        $rows = Certificate::query()
            ->when($data['course_id'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
            ->when($data['q'] ?? null, fn ($q, $text) => $q->where(fn ($w) => $w->where('learner_name', 'ilike', "%{$text}%")->orWhere('code', Issuer::normalize($text))))
            ->latest('issued_at')->paginate(50);

        return response()->json([
            'data' => collect($rows->items())->map(fn (Certificate $c) => [...$c->toSummary(), 'url' => $this->issuer->url($c)])->all(),
            'meta' => ['total' => $rows->total(), 'last_page' => $rows->lastPage()],
        ]);
    }

    public function settings(string $course, CourseCatalog $courses): JsonResponse
    {
        $courses->course($course, evenUnpublished: true) ?? abort(404);
        $settings = CertificateSetting::for($course);

        return response()->json(['data' => [...$settings->toSummary(), 'course_id' => $course, 'issued' => Certificate::query()->where('course_id', $course)->count()]]);
    }

    public function updateSettings(Request $request, string $course, CourseCatalog $courses): JsonResponse
    {
        $courses->course($course, evenUnpublished: true) ?? abort(404);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'min_exam_percent' => ['nullable', 'integer', 'between:1,100'],
            'title' => ['required', 'string', 'min:2', 'max:80'],
            'signer_name' => ['nullable', 'string', 'max:80'],
            'signer_title' => ['nullable', 'string', 'max:80'],
        ], [], ['title' => 'عنوان الشهادة', 'min_exam_percent' => 'أقل نسبة نجاح']);
        $settings = CertificateSetting::for($course);
        $settings->fill($data)->save();

        return $this->settings($course, $courses);
    }

    public function revoke(Request $request, string $certificate, Auditor $auditor): JsonResponse
    {
        $found = Certificate::query()->findOrFail($certificate);
        if ($found->revoked_at !== null) {
            throw new DomainRuleException('الشهادة دي ملغية فعلاً.', 'certificate_revoked');
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], [], ['reason' => 'السبب']);
        $found->update(['revoked_at' => now(), 'revoke_reason' => $data['reason']]);
        $auditor->record('certificates.revoked', "إلغاء شهادة {$found->learner_name} في «{$found->course_title}»: {$data['reason']}", $found);

        return response()->json(['data' => $found->toSummary()]);
    }
}
