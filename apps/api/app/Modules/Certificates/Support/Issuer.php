<?php

declare(strict_types=1);

namespace App\Modules\Certificates\Support;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Access\Contracts\LessonAccess;
use App\Modules\Assessments\Contracts\ExamResults;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Certificates\Models\CertificateSetting;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Sites\Contracts\SiteDirectory;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * A student earns a course's certificate by completing every lesson (an exam lesson by submitting
 * it) and, when the teacher sets a minimum, scoring at least that in each exam (best graded try).
 */
final class Issuer
{
    /** Codes: 10 characters, no look-alikes (0/O, 1/I/L). */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private readonly CourseCatalog $courses,
        private readonly Enrollments $enrollments,
        private readonly LessonAccess $lessons,
        private readonly ExamResults $exams,
        private readonly LearnerDirectory $learners,
        private readonly SiteDirectory $sites,
        private readonly CurrentTenant $tenant,
    ) {}

    /** @return array<string, mixed> where the student stands: offered, completed / total, each exam, eligible, the certificate */
    public function status(string $learnerId, string $courseId): array
    {
        $settings = CertificateSetting::for($courseId);
        $outline = $this->courses->outline($courseId);
        $ids = array_column($outline, 'id');
        $done = $this->lessons->completed($learnerId, $ids);
        $examLessons = array_values(array_filter($outline, fn (array $l) => $l['kind'] === 'exam'));
        $best = $this->exams->bestPercents($learnerId, array_column($examLessons, 'id'));
        $min = $settings->min_exam_percent;
        $exams = array_map(fn (array $l) => [
            'lesson_id' => $l['id'], 'title' => $l['title'], 'best' => $best[$l['id']] ?? null,
            'passed' => $min === null || ($best[$l['id']] ?? -1) >= $min,
        ], $examLessons);
        $certificate = Certificate::query()->where('course_id', $courseId)->where('learner_id', $learnerId)->first();

        return [
            'offered' => $settings->enabled,
            'min_exam_percent' => $min,
            'enrolled' => $this->enrollments->wasEnrolled($learnerId, $courseId),
            'completed' => count($done),
            'total' => count($ids),
            'exams' => $exams,
            'eligible' => $settings->enabled && $ids !== [] && count($done) === count($ids)
                && array_reduce($exams, fn (bool $ok, array $e) => $ok && $e['passed'], true)
                && $this->enrollments->wasEnrolled($learnerId, $courseId),
            'certificate' => $certificate === null ? null : [...$certificate->toPublic(), 'url' => $this->url($certificate)],
        ];
    }

    public function issue(string $learnerId, string $courseId): Certificate
    {
        $existing = Certificate::query()->where('course_id', $courseId)->where('learner_id', $learnerId)->first();
        if ($existing !== null) {
            return $existing;
        }
        $status = $this->status($learnerId, $courseId);
        if (! $status['offered']) {
            throw new DomainRuleException('الكورس ده مالوش شهادة.', 'certificate_not_offered');
        }
        if (! $status['eligible']) {
            throw new DomainRuleException('خلّص كل الدروس'.($status['min_exam_percent'] !== null ? " وانجح في الامتحانات بـ {$status['min_exam_percent']}% على الأقل" : '').' الأول.', 'certificate_not_earned');
        }
        $course = $this->courses->course($courseId, evenUnpublished: true) ?? abort(404);
        $settings = CertificateSetting::for($courseId);
        $learner = $this->learners->find($learnerId) ?? abort(404);
        $site = $this->sites->forTenant($this->tenant->idOrFail());
        $outline = $this->courses->outline($courseId);
        $percents = array_filter(array_column($status['exams'], 'best'), fn ($p) => $p !== null);

        return DB::transaction(fn () => Certificate::query()->create([
            'code' => $this->newCode(),
            'learner_id' => $learnerId, 'course_id' => $courseId,
            'learner_name' => $learner['name'], 'course_title' => $course['title'], 'teacher_name' => $site['title'],
            'title' => $settings->title, 'signer_name' => $settings->signer_name, 'signer_title' => $settings->signer_title,
            'minutes' => intdiv(array_sum(array_map(fn (array $l) => $l['kind'] === 'video' ? (int) $l['duration_seconds'] : 0, $outline)), 60),
            'exam_percent' => $percents === [] ? null : (int) round(array_sum($percents) / count($percents)),
            'issued_at' => now(),
        ]));
    }

    /** The certificate's page on the teacher's platform (anyone with the link can check it). */
    public function url(Certificate $certificate): string
    {
        return $this->sites->forTenant($certificate->tenant_id)['url'].'/certificates/'.$certificate->code;
    }

    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    private function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Certificate::withoutTenancy()->where('code', $code)->exists());

        return $code;
    }
}
