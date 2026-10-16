<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Sites\Contracts\SiteDirectory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * What a parent sees about their child for the last N days: lessons watched, what's still
 * unwatched, exam results — read-only SQL with an explicit tenant_id. The link is a signed,
 * relative URL (14 days) that the student site opens at /p/{tenant}/{learner}.
 */
final class ParentReport
{
    public const LINK_DAYS = 14;

    public function __construct(
        private readonly LearnerDirectory $learners,
        private readonly SiteDirectory $sites,
        private readonly CurriculumTree $curriculum,
    ) {}

    /** @return array<string, mixed>|null */
    public function build(string $t, string $learnerId, int $days): ?array
    {
        $learner = $this->learners->find($learnerId);
        if ($learner === null) {
            return null;
        }
        $from = now()->subDays($days);
        $site = $this->sites->forTenant($t);

        $courses = DB::table('enrollments as e')->join('courses as c', 'c.id', '=', 'e.course_id')
            ->where('e.tenant_id', $t)->where('e.learner_id', $learnerId)->whereNull('e.revoked_at')
            ->where(fn ($q) => $q->whereNull('e.ends_at')->orWhere('e.ends_at', '>', now()))
            ->distinct()->get(['c.id', 'c.title', 'e.ends_at']);
        $courseIds = $courses->pluck('id')->all();

        $watched = DB::table('playback_sessions as s')->join('course_lessons as l', 'l.id', '=', 's.lesson_id')
            ->where('s.tenant_id', $t)->where('s.learner_id', $learnerId)->where('s.started_at', '>=', $from)
            ->groupBy('l.id', 'l.title')->orderByRaw('max(s.started_at) desc')
            ->get(['l.id', 'l.title', DB::raw('sum(s.watched_seconds) as seconds'), DB::raw('max(s.started_at) as last_at')]);

        $missing = DB::table('course_lessons as l')->join('courses as c', 'c.id', '=', 'l.course_id')
            ->leftJoin('lesson_progress as p', fn ($j) => $j->on('p.lesson_id', '=', 'l.id')->where('p.learner_id', $learnerId))
            ->where('l.tenant_id', $t)->whereIn('l.course_id', $courseIds)->where('c.status', 'published')
            ->where(fn ($q) => $q->whereNull('l.publish_at')->orWhere('l.publish_at', '<=', now()))
            ->whereNull('p.completed_at')->orderBy('l.publish_at')->orderBy('l.sort')->limit(50)
            ->get(['l.id', 'l.title', 'l.kind']);

        $exams = DB::table('exam_attempts as a')->join('exams as x', 'x.id', '=', 'a.exam_id')->join('course_lessons as l', 'l.id', '=', 'x.lesson_id')
            ->where('a.tenant_id', $t)->where('a.learner_id', $learnerId)->where('a.status', 'submitted')->where('a.submitted_at', '>=', $from)
            ->orderByDesc('a.submitted_at')->get(['l.title', 'a.score', 'a.max_score', 'a.percent', 'a.pending_grading', 'a.submitted_at']);

        $minutes = (int) round($watched->sum('seconds') / 60);
        $lastExam = $exams->first();

        return [
            'student' => $learner['name'],
            'grade' => $learner['grade_node_id'] ? $this->curriculum->label($learner['grade_node_id']) : null,
            'teacher' => $site['title'],
            'days' => $days,
            'from' => $from->toIso8601String(),
            'courses' => $courses->map(fn ($c) => ['title' => $c->title, 'ends_at' => $c->ends_at ? Carbon::parse($c->ends_at)->toIso8601String() : null])->all(),
            'watched' => $watched->map(fn ($w) => ['title' => $w->title, 'minutes' => (int) round($w->seconds / 60), 'last_at' => Carbon::parse($w->last_at)->toIso8601String()])->all(),
            'watched_minutes' => $minutes,
            'missing' => $missing->map(fn ($m) => ['title' => $m->title, 'kind' => $m->kind])->all(),
            'exams' => $exams->map(fn ($e) => [
                'title' => $e->title, 'score' => (int) $e->score, 'max_score' => (int) $e->max_score, 'percent' => (int) $e->percent,
                'pending_grading' => (int) $e->pending_grading, 'submitted_at' => Carbon::parse($e->submitted_at)->toIso8601String(),
            ])->all(),
            // The variables of the parent_report WhatsApp template.
            'message' => [
                'student' => $learner['name'],
                'teacher' => $site['title'],
                'subject' => $courses->pluck('title')->implode('، ') ?: null,
                'watched' => $watched->isEmpty() ? 'ولا درس الأسبوع ده' : $watched->count().' '.($watched->count() === 1 ? 'درس' : 'دروس')." ({$minutes} دقيقة)",
                'exam' => $lastExam === null ? null : "{$lastExam->title}: {$lastExam->score} من {$lastExam->max_score}",
                'attendance' => null,
                'missing' => $missing->isEmpty() ? null : $missing->take(3)->pluck('title')->implode('، ').($missing->count() > 3 ? ' و'.($missing->count() - 3).' كمان' : ''),
            ],
        ];
    }

    /** The link the parent opens (the student site's /p page, carrying the API signature). */
    public function link(string $t, string $learnerId, int $days): string
    {
        $signed = URL::temporarySignedRoute('parent.report', now()->addDays(self::LINK_DAYS), ['tenant' => $t, 'learner' => $learnerId, 'days' => $days], absolute: false);
        $query = (string) parse_url($signed, PHP_URL_QUERY);
        $base = $this->sites->forTenant($t)['url'] ?? rtrim((string) config('app.url'), '/');

        return "{$base}/p/{$t}/{$learnerId}?{$query}";
    }
}
