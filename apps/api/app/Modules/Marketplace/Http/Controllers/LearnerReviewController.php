<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Marketplace\Models\CourseReview;
use App\Modules\Marketplace\Support\Indexer;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A student rates a course they were given (1–5 stars + an optional word); shown by first name. */
final class LearnerReviewController
{
    public function __construct(
        private readonly LearnerDirectory $learners,
        private readonly Enrollments $enrollments,
        private readonly CourseCatalog $courses,
    ) {}

    public function show(string $course): JsonResponse
    {
        $learner = $this->learners->current() ?? abort(401);
        $this->courses->course($course) ?? abort(404);
        $review = CourseReview::query()->where('course_id', $course)->where('learner_id', $learner['learner_id'])->first();

        return response()->json(['data' => [
            'can_review' => $this->enrollments->wasEnrolled($learner['learner_id'], $course),
            'review' => $review === null ? null : [...$review->toPublic(), 'hidden' => $review->hidden],
        ]]);
    }

    public function save(Request $request, string $course, Indexer $indexer): JsonResponse
    {
        $learner = $this->learners->current() ?? abort(401);
        $this->courses->course($course) ?? abort(404);
        if (! $this->enrollments->wasEnrolled($learner['learner_id'], $course)) {
            throw new DomainRuleException('اشترك في الكورس الأول عشان تقيّمه.', 'review_not_enrolled');
        }
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [], ['rating' => 'التقييم', 'comment' => 'رأيك']);

        $review = CourseReview::query()->updateOrCreate(
            ['course_id' => $course, 'learner_id' => $learner['learner_id']],
            ['rating' => $data['rating'], 'comment' => trim((string) ($data['comment'] ?? '')) ?: null, 'first_name' => self::firstName($learner['name'])],
        );
        $indexer->refreshRating($course);

        return response()->json(['data' => [...$review->toPublic(), 'hidden' => $review->hidden]]);
    }

    private static function firstName(string $name): string
    {
        return mb_substr(explode(' ', trim($name))[0] ?: 'طالب', 0, 60);
    }
}
