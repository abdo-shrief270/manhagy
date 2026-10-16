<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Marketplace\Models\MarketCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** «سوق منهجي»: opting in, search, reviews by enrolled students, the commission on market sales. */
class MarketplaceTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.market.url' => 'https://courses.manhagy.test', 'services.market.commission_percent' => 15]);
        $this->setUpPlatform();
    }

    private function list(array $extra = []): array
    {
        return $this->asTeacher()->putJson('/api/v1/marketplace', ['listed' => true, 'headline' => 'مدرس فيزياء تالتة ثانوي', ...$extra])->assertOk()->json('data');
    }

    private function fund(int $amount): void
    {
        $this->asTeacher()->postJson('/api/v1/wallet/adjust', ['learner_id' => $this->learnerId, 'amount' => $amount, 'note' => 'شحن'])->assertSuccessful();
    }

    private function asAdmin(): static
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
        Sanctum::actingAs(PlatformAdmin::query()->firstOrCreate(['email' => 'admin@manhagy.test'], ['name' => 'الإدارة', 'password' => 'secret-password']), ['admin']);

        return $this;
    }

    public function test_courses_show_only_after_the_teacher_opts_in_and_are_found_in_arabic(): void
    {
        $this->assertSame(0, $this->getJson('/api/v1/public/market/courses')->assertOk()->json('meta.total'));
        $state = $this->asTeacher()->getJson('/api/v1/marketplace')->assertOk()->json('data');
        $this->assertSame('not_listed', $state['blocker']);

        $state = $this->list();
        $this->assertNull($state['blocker']);
        $this->assertTrue($state['courses'][0]['in_market']);
        $this->assertDatabaseHas('audit_log', ['action' => 'marketplace.listed']);

        $this->withoutToken();
        // Words of the title and of the subject's path (its stage here), Arabic folded («إ» = «ا»).
        $stage = DB::table('market_courses')->value('node_label');
        $stageName = explode(' › ', $stage)[0];
        $found = $this->getJson('/api/v1/public/market/courses?q='.urlencode('فيزياء '.str_replace('إ', 'ا', $stageName)))->assertOk()->json();
        $this->assertSame(1, $found['meta']['total']);
        $card = $found['data'][0];
        $this->assertSame('school', $card['kind']);
        $this->assertSame('mr-mohamed', $card['teacher']['slug']);
        $this->assertStringEndsWith("/courses/{$this->courseId}?via=market", $card['url']);
        $this->assertSame(0, $this->getJson('/api/v1/public/market/courses?q='.urlencode('كيمياء'))->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/v1/public/market/courses?kind=skills')->json('meta.total'));

        // Browsing the tree: the stage holding the course counts it.
        $stages = collect($this->getJson('/api/v1/public/market/nodes')->assertOk()->json('data'));
        $this->assertSame(1, $stages->firstWhere('name', $stageName)['courses']);
        $this->assertTrue($stages->contains('kind', 'field'));
        $grade = DB::table('curriculum_nodes')->whereNull('parent_id')->where('name', $stageName)->value('id');
        $this->assertSame(1, $this->getJson("/api/v1/public/market/courses?node={$grade}")->json('meta.total'));

        $course = $this->getJson("/api/v1/public/market/courses/{$this->courseId}")->assertOk()->json('data');
        $this->assertSame('الحصة 1', $course['syllabus'][0]['lessons'][0]['title']);
        $teacher = $this->getJson('/api/v1/public/market/teachers/mr-mohamed')->assertOk()->json('data');
        $this->assertSame(1, $teacher['courses_count']);
        $this->assertSame(1, $this->getJson('/api/v1/public/market')->assertOk()->json('data.courses'));

        // Hiding a course, then leaving the market.
        $this->list(['hidden_courses' => [$this->courseId]]);
        $this->withoutToken()->getJson("/api/v1/public/market/courses/{$this->courseId}")->assertNotFound();
        $this->list(['listed' => false]);
        $this->assertSame(0, DB::table('market_courses')->count());
    }

    public function test_only_enrolled_students_review_and_the_rating_reaches_the_market(): void
    {
        $this->list();
        $this->asStudent()->getJson("/api/v1/learner/courses/{$this->courseId}/review")->assertOk()->assertJsonPath('data.can_review', false);
        $this->asStudent()->putJson("/api/v1/learner/courses/{$this->courseId}/review", ['rating' => 5])->assertUnprocessable()->assertJsonPath('code', 'review_not_enrolled');

        $this->fund(30000);
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertCreated();
        $this->asStudent()->putJson("/api/v1/learner/courses/{$this->courseId}/review", ['rating' => 4, 'comment' => 'شرح حلو جداً'])->assertOk()->assertJsonPath('data.first_name', 'يوسف');
        $this->asStudent()->putJson("/api/v1/learner/courses/{$this->courseId}/review", ['rating' => 5, 'comment' => 'شرح حلو جداً'])->assertOk();
        $this->assertSame(1, DB::table('course_reviews')->count());

        $card = $this->withoutToken()->getJson("/api/v1/public/market/courses/{$this->courseId}")->json('data');
        $this->assertEquals(5, $card['rating']);
        $this->assertSame(1, $card['distribution'][5]);
        $this->assertSame('شرح حلو جداً', $card['reviews_list'][0]['comment']);

        // The admins hide an abusive one: it leaves the rating.
        $review = $this->asAdmin()->getJson('/api/v1/admin/market/reviews')->assertOk()->json('data.0');
        $this->patchJson("/api/v1/admin/market/reviews/{$review['id']}", ['hidden' => true])->assertOk();
        $this->assertNull($this->withoutToken()->getJson("/api/v1/public/market/courses/{$this->courseId}")->json('data.rating'));
        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'market_review_hidden']);
    }

    public function test_a_sale_from_the_market_owes_a_commission_and_an_unpaid_one_pauses_the_listing(): void
    {
        $this->list();
        $this->fund(60000);
        // From the teacher's own link: no commission.
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertCreated();
        $this->assertSame(0, MarketCommission::withoutTenancy()->count());
        // From the market: 15% of 300 ج.
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy", ['via' => 'market'])->assertCreated();
        $row = MarketCommission::withoutTenancy()->sole();
        $this->assertSame(4500, $row->commission);

        $summary = $this->asTeacher()->getJson('/api/v1/marketplace/commissions')->assertOk()->json('meta.summary');
        $this->assertSame(4500, $summary['due']);
        $this->assertFalse($summary['overdue']);

        // Unpaid past the deadline: out of the market overnight, back once the admins settle it.
        config(['services.market.pay_within_days' => 5]);
        $this->travel(6)->days();
        $this->artisan('market:sync --all')->assertSuccessful();
        $this->assertSame(0, DB::table('market_courses')->count());
        $this->assertSame('unpaid', $this->asTeacher()->getJson('/api/v1/marketplace')->json('data.blocker'));

        $owing = $this->asAdmin()->getJson('/api/v1/admin/market/commissions')->assertOk()->json('data');
        $this->assertTrue($owing[0]['overdue']);
        $this->assertSame(1, $this->getJson('/api/v1/admin/badges')->json('data.market'));
        $this->postJson("/api/v1/admin/market/commissions/{$owing[0]['tenant_id']}/settle", ['reference' => 'IP-7781'])->assertOk()->assertJsonPath('data.amount', 4500);
        $this->assertSame(1, DB::table('market_courses')->count());
        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'market_commissions_settled']);
    }

    public function test_a_market_link_to_a_course_not_in_the_market_owes_nothing(): void
    {
        $this->fund(30000);
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy", ['via' => 'market'])->assertCreated();
        $this->assertSame(0, MarketCommission::withoutTenancy()->count());
    }

    public function test_trainers_sign_up_and_teach_skills(): void
    {
        $skill = DB::table('curriculum_nodes')->where('kind', 'skill')->where('name', 'Python')->first();
        $this->assertNotNull($skill);
        $this->assertSame('field', DB::table('curriculum_nodes')->where('id', $skill->parent_id)->value('kind'));
        $this->assertContains('trainer', collect($this->getJson('/api/v1/shop-types')->json('data'))->pluck('value')->all());

        $this->asTeacher()->postJson('/api/v1/courses', ['curriculum_node_id' => $skill->id, 'title' => 'Python من الصفر', 'price' => 0])->assertCreated();
    }
}
