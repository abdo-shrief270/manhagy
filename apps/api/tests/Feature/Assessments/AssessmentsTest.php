<?php

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Models\ExamAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** Closed-question exams: the bank, the exam lesson, the server's clock, per-student order, auto-grading, results. */
class AssessmentsTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    private string $examLesson;

    /** @var array<string, string> */
    private array $q = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform();
        $this->asTeacher();
        $chapter = $this->getJson("/api/v1/courses/{$this->courseId}")->json('data.chapters.0.id');
        $detail = $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $chapter, 'kind' => 'exam', 'title' => 'امتحان الفصل الأول'])->assertCreated()->json('data');
        $this->examLesson = $detail['chapters'][0]['lessons'][1]['id'];
        $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $chapter, 'kind' => 'video', 'title' => 'بعد الامتحان', 'media_id' => DB::table('media_assets')->value('id'), 'requires_previous' => true])->assertCreated();

        $this->q['mcq'] = $this->postJson('/api/v1/assessments/questions', [
            'course_id' => $this->courseId, 'kind' => 'mcq', 'body' => 'وحدة قياس الشغل؟', 'options' => ['نيوتن', 'جول', 'وات', 'باسكال'], 'correct' => 1, 'points' => 2,
        ])->assertCreated()->json('data.id');
        $this->q['tf'] = $this->postJson('/api/v1/assessments/questions', ['course_id' => $this->courseId, 'kind' => 'true_false', 'body' => 'السرعة كمية متجهة', 'correct' => false])->assertCreated()->json('data.id');
        $this->q['fill'] = $this->postJson('/api/v1/assessments/questions', ['course_id' => $this->courseId, 'kind' => 'fill', 'body' => 'الطاقة لا تفنى ولا ...', 'accepted' => ['تستحدث', 'تُستحدَث']])->assertCreated()->json('data.id');
    }

    private function setUpExam(array $settings = []): void
    {
        $this->asTeacher()->putJson("/api/v1/assessments/exams/{$this->examLesson}", [
            'duration_minutes' => 20, 'attempts_allowed' => 1, 'show_answers' => 'after_submit',
            'questions' => [['id' => $this->q['mcq']], ['id' => $this->q['tf']], ['id' => $this->q['fill'], 'points' => 3]],
            ...$settings,
        ])->assertOk();
    }

    private function enroll(?string $learnerId = null): void
    {
        $this->asTeacher()->postJson('/api/v1/enrollments', ['learner_id' => $learnerId ?? $this->learnerId, 'course_id' => $this->courseId])->assertCreated();
    }

    public function test_the_question_bank(): void
    {
        $mcq = $this->getJson("/api/v1/assessments/questions?course_id={$this->courseId}&kind=mcq")->assertOk()->json('data.0');
        $this->assertSame(['b', [['id' => 'a', 'text' => 'نيوتن'], ['id' => 'b', 'text' => 'جول'], ['id' => 'c', 'text' => 'وات'], ['id' => 'd', 'text' => 'باسكال']]], [$mcq['answer'], $mcq['options']]);
        $this->postJson('/api/v1/assessments/questions', ['course_id' => $this->courseId, 'kind' => 'mcq', 'body' => 'سؤال', 'options' => ['أ'], 'correct' => 0])->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->postJson('/api/v1/assessments/questions', ['course_id' => $this->courseId, 'kind' => 'mcq', 'body' => 'سؤال', 'options' => ['أ', 'ب'], 'correct' => 5])->assertUnprocessable()->assertJsonPath('code', 'question_answer_missing');
        $this->postJson('/api/v1/assessments/questions', ['course_id' => $this->courseId, 'kind' => 'fill', 'body' => 'سؤال', 'correct' => true])->assertUnprocessable()->assertJsonValidationErrors('correct');

        $this->patchJson("/api/v1/assessments/questions/{$this->q['mcq']}", ['correct' => 2])->assertOk()->assertJsonPath('data.answer', 'c');
        // Not in any exam yet: deleted; in an exam: retired.
        $this->deleteJson("/api/v1/assessments/questions/{$this->q['tf']}")->assertNoContent();
        $this->setUpExam(['questions' => [['id' => $this->q['mcq']]]]);
        $this->deleteJson("/api/v1/assessments/questions/{$this->q['mcq']}")->assertOk()->assertJsonPath('data.active', false);
    }

    public function test_a_student_sits_the_exam(): void
    {
        $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->assertForbidden()->assertJsonPath('code', 'not_enrolled');
        $this->enroll();
        $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->assertStatus(409)->assertJsonPath('code', 'exam_not_ready');
        $this->setUpExam();

        // The lesson outline sends the student to the exam.
        $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->examLesson}/open")->assertOk()->assertJsonPath('data.kind', 'exam');
        $overview = $this->asStudent()->getJson("/api/v1/learner/exams/{$this->examLesson}")->assertOk()->json('data');
        $this->assertSame([true, 3, 6, 0], [$overview['ready'], $overview['questions_count'], $overview['max_score'], $overview['attempts_used']]);

        $paper = $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->assertCreated()->json('data');
        $this->assertSame(20, (int) round(now()->diffInMinutes($paper['ends_at'])));
        $this->assertArrayNotHasKey('correct_answer', $paper['questions'][0]);
        $mcq = collect($paper['questions'])->firstWhere('id', $this->q['mcq']);
        $this->assertArrayNotHasKey('answer', $mcq['options'][0]);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd'], array_column($mcq['options'], 'id'));

        // Coming back = the same attempt, the same order.
        $again = $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->assertOk()->json('data');
        $this->assertSame([$paper['id'], array_column($paper['questions'], 'id')], [$again['id'], array_column($again['questions'], 'id')]);

        $attempt = $paper['id'];
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['mcq']}", ['answer' => 'z'])->assertUnprocessable()->assertJsonPath('code', 'answer_invalid');
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['mcq']}", ['answer' => 'a'])->assertOk();
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['mcq']}", ['answer' => 'b'])->assertOk(); // changed their mind
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['tf']}", ['answer' => true])->assertOk();
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['fill']}", ['answer' => '  تستحدث '])->assertOk();
        $this->asStudent()->postJson("/api/v1/learner/attempts/{$attempt}/leave")->assertOk()->assertJsonPath('data.leaves', 1);
        $this->assertSame('b', collect($this->asStudent()->getJson("/api/v1/learner/attempts/{$attempt}")->json('data.questions'))->firstWhere('id', $this->q['mcq'])['answer']);

        $result = $this->asStudent()->postJson("/api/v1/learner/attempts/{$attempt}/submit")->assertOk()->json('data');
        $this->assertSame([5, 6, 83, true, true], [$result['score'], $result['max_score'], $result['percent'], $result['passed'], $result['answers_visible']]);
        $review = collect($result['review'])->keyBy('id');
        $this->assertSame([true, false, true], [$review[$this->q['mcq']]['correct'], $review[$this->q['tf']]['correct'], $review[$this->q['fill']]['correct']]);

        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['tf']}", ['answer' => false])->assertStatus(409)->assertJsonPath('code', 'attempt_closed');
        $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->assertForbidden()->assertJsonPath('code', 'attempts_used_up');

        // Submitting completes the exam lesson: the next one (requires_previous) opens.
        $lessons = collect($this->asStudent()->getJson("/api/v1/learner/courses/{$this->courseId}")->json('data.chapters.0.lessons'))->keyBy('title');
        $this->assertTrue($lessons['امتحان الفصل الأول']['completed']);
        $this->assertNull($lessons['بعد الامتحان']['locked']);

        // The teacher's results; the questions can't change any more, the dates can.
        $this->asTeacher();
        $results = $this->getJson("/api/v1/assessments/exams/{$this->examLesson}/results")->assertOk()->json('data');
        $this->assertSame([1, 83, 100], [$results['summary']['attempts'], $results['summary']['average_percent'], $results['summary']['pass_percent']]);
        $this->assertSame(['يوسف أحمد', 1], [$results['attempts'][0]['learner_name'], $results['attempts'][0]['leaves']]);
        $this->assertSame([$this->q['tf'], 0], [$results['questions'][0]['id'], $results['questions'][0]['correct_percent']], 'hardest first');
        $this->putJson("/api/v1/assessments/exams/{$this->examLesson}", ['duration_minutes' => 20, 'questions' => [['id' => $this->q['mcq']]]])
            ->assertUnprocessable()->assertJsonPath('code', 'exam_has_attempts');
        $this->setUpExam(['closes_at' => now()->addDay()->toIso8601String()]);
        $this->patchJson("/api/v1/assessments/questions/{$this->q['tf']}", ['correct' => true])->assertUnprocessable()->assertJsonPath('code', 'question_answered');
        $this->patchJson("/api/v1/assessments/questions/{$this->q['tf']}", ['body' => 'السرعة المتجهة كمية متجهة'])->assertOk();
    }

    public function test_the_server_clock_ends_the_attempt(): void
    {
        $this->enroll();
        $this->setUpExam(['attempts_allowed' => 2, 'show_answers' => 'after_close', 'closes_at' => now()->addHours(2)->toIso8601String()]);
        $attempt = $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->json('data.id');
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['mcq']}", ['answer' => 'b'])->assertOk();

        // Within the grace seconds an answer still counts; after them, refused and submitted for the student.
        $this->travel(20 * 60 + 5)->seconds();
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['tf']}", ['answer' => false])->assertOk();
        $this->travel(10)->seconds();
        $this->asStudent()->putJson("/api/v1/learner/attempts/{$attempt}/answers/{$this->q['fill']}", ['answer' => 'تستحدث'])
            ->assertStatus(409)->assertJsonPath('code', 'attempt_closed')->assertJsonPath('submitted_by', 'timer');
        $result = $this->asStudent()->getJson("/api/v1/learner/attempts/{$attempt}/result")->assertOk()->json('data');
        $this->assertSame([3, 'timer', false, null], [$result['score'], $result['submitted_by'], $result['answers_visible'], $result['review']], 'answers only after the exam closes');

        // A second attempt the student left open is closed by the scheduler.
        $second = $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->assertCreated()->json('data');
        $this->assertNotSame($attempt, $second['id']);
        $this->travel(21)->minutes();
        $this->artisan('assessments:close-expired')->assertSuccessful();
        $this->assertSame(['submitted', 'timer', 0], [ExamAttempt::withoutTenancy()->find($second['id'])->status, ExamAttempt::withoutTenancy()->find($second['id'])->submitted_by, ExamAttempt::withoutTenancy()->find($second['id'])->score]);

        $this->travel(2)->hours();
        $this->assertTrue($this->asStudent()->getJson("/api/v1/learner/attempts/{$attempt}/result")->json('data.answers_visible'));
        $this->asStudent()->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->assertStatus(409)->assertJsonPath('code', 'exam_closed');
    }

    public function test_each_student_gets_their_own_order(): void
    {
        $this->enroll();
        $more = [];
        for ($i = 0; $i < 7; $i++) {
            $more[] = ['id' => $this->asTeacher()->postJson('/api/v1/assessments/questions', ['course_id' => $this->courseId, 'kind' => 'true_false', 'body' => "سؤال {$i}", 'correct' => true])->json('data.id')];
        }
        $this->asTeacher()->putJson("/api/v1/assessments/exams/{$this->examLesson}", ['duration_minutes' => 10, 'questions' => $more])->assertOk();

        $orders = [];
        foreach (['01012345487' => null, '01112345670' => 'منة', '01212345671' => 'علي'] as $phone => $name) {
            $token = $name === null ? $this->studentToken : $this->registerStudent($phone, $name);
            if ($name !== null) {
                $this->enroll(DB::table('learners')->where('phone', '+2'.$phone)->value('id'));
            }
            $orders[] = array_column($this->asStudent($token)->postJson("/api/v1/learner/exams/{$this->examLesson}/start")->json('data.questions'), 'id');
        }
        $this->assertGreaterThan(1, count(array_unique(array_map('json_encode', $orders))));
    }
}
