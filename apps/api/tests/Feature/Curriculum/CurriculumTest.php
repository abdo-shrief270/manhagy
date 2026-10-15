<?php

namespace Tests\Feature\Curriculum;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Curriculum\Contracts\CurriculumAdmin;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Curriculum\Enums\NodeKind;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** The platform's curriculum tree: browsed by anyone, completed by teachers' suggestions, kept by the admins. */
class CurriculumTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private function child(?string $parent, string $name): array
    {
        $children = $this->getJson('/api/v1/public/curriculum'.($parent ? "?parent={$parent}" : ''))->assertOk()->json('data');

        return collect($children)->firstWhere('name', $name) ?? $this->fail("«{$name}» not under {$parent}");
    }

    private function asAdmin(): static
    {
        Sanctum::actingAs(PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@manhagy.test', 'password' => 'secret-password']), ['admin']);

        return $this;
    }

    public function test_the_egyptian_tree_is_there_from_the_start(): void
    {
        $roots = array_column($this->getJson('/api/v1/public/curriculum')->assertOk()->assertHeader('Cache-Control')->json('data'), 'name');
        $this->assertSame(['ابتدائي', 'إعدادي', 'ثانوي عام'], array_slice($roots, 0, 3));
        $this->assertContains('جامعة القاهرة', $roots);

        $stage = $this->child(null, 'ثانوي عام');
        $grade = $this->child($stage['id'], 'الصف التالت الثانوي');
        $track = $this->child($grade['id'], 'علمي علوم');
        $physics = $this->child($track['id'], 'فيزياء');
        $this->assertSame(['subject', 'مادة', true, false], [$physics['kind'], $physics['kind_label'], $physics['teachable'], $physics['has_children']]);

        $node = $this->getJson("/api/v1/public/curriculum/{$physics['id']}")->assertOk()->json('data');
        $this->assertSame(['ثانوي عام', 'الصف التالت الثانوي', 'علمي علوم', 'فيزياء'], array_column($node['path'], 'name'));
        $this->assertSame('ثانوي عام › الصف التالت الثانوي › علمي علوم › فيزياء', app(CurriculumTree::class)->label($physics['id']));

        // Search folds Arabic spellings (أ / ا, ة / ه) and needs every word.
        $found = $this->getJson('/api/v1/public/curriculum/search?q='.urlencode('فيزياء التالت'))->assertOk()->json('data');
        $this->assertNotEmpty($found);
        $this->assertTrue(collect($found)->every(fn ($n) => str_contains($n['label'], 'التالت')));
        $this->assertNotEmpty($this->getJson('/api/v1/public/curriculum/search?q='.urlencode('الاعدادي'))->json('data'));
        $this->getJson('/api/v1/public/curriculum/'.str()->uuid())->assertNotFound();
    }

    public function test_a_teacher_suggests_what_is_missing_and_the_admins_review_it(): void
    {
        $cairo = $this->child(null, 'جامعة القاهرة');
        $owner = $this->actingAsOwnerOf();

        // Only the levels that fit: a faculty under a university.
        $this->postJson('/api/v1/curriculum/suggestions', ['parent_id' => $cairo['id'], 'kind' => 'course', 'name' => 'محاسبة'])
            ->assertUnprocessable()->assertJsonPath('code', 'curriculum_kind_not_allowed');
        $faculty = $this->postJson('/api/v1/curriculum/suggestions', ['parent_id' => $cairo['id'], 'kind' => 'faculty', 'name' => 'كلية التجارة'])
            ->assertCreated()->assertJsonPath('data.status', 'review')->json('data');
        $this->assertSame('جامعة القاهرة › كلية التجارة', $faculty['label']);
        // The same name again (another spelling) is the same node.
        $this->postJson('/api/v1/curriculum/suggestions', ['parent_id' => $cairo['id'], 'kind' => 'faculty', 'name' => 'كليه التجاره'])
            ->assertCreated()->assertJsonPath('data.id', $faculty['id']);

        // Not public until reviewed.
        $this->assertSame([], $this->getJson("/api/v1/public/curriculum?parent={$cairo['id']}")->json('data'));

        // An assistant without the permission can't suggest.
        Sanctum::actingAs(User::factory()->create(['tenant_id' => $owner->tenant_id]));
        $this->postJson('/api/v1/curriculum/suggestions', ['parent_id' => $cairo['id'], 'kind' => 'faculty', 'name' => 'كلية الهندسة'])->assertForbidden();

        $review = $this->asAdmin()->getJson('/api/v1/admin/curriculum?status=review')->assertOk();
        $this->assertSame(1, $review->json('meta.review'));
        $this->assertSame(['كلية التجارة'], array_column($review->json('data'), 'name'));
        $this->assertSame('المالك', $review->json('data.0.suggested_by'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/badges')->json('data.curriculum'));

        $this->patchJson("/api/v1/admin/curriculum/{$faculty['id']}", ['status' => 'published'])->assertOk();
        $this->assertSame(['كلية التجارة'], array_column($this->getJson("/api/v1/public/curriculum?parent={$cairo['id']}")->json('data'), 'name'));
        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'curriculum_changed']);
    }

    public function test_the_admins_keep_the_tree_in_shape(): void
    {
        $this->asAdmin();
        $uni = $this->postJson('/api/v1/admin/curriculum', ['kind' => 'university', 'name' => 'جامعة النيل'])->assertCreated()->json('data');
        $this->postJson('/api/v1/admin/curriculum', ['kind' => 'faculty', 'name' => 'كلية بلا جامعة'])->assertUnprocessable()->assertJsonPath('code', 'curriculum_kind_not_allowed');
        $this->postJson('/api/v1/admin/curriculum', ['kind' => 'university', 'name' => 'جامعه النيل'])->assertUnprocessable()->assertJsonPath('code', 'curriculum_exists');

        $faculty = $this->postJson('/api/v1/admin/curriculum', ['parent_id' => $uni['id'], 'kind' => 'faculty', 'name' => 'كلية الهندسة'])->assertCreated()->json('data');
        $year = $this->postJson('/api/v1/admin/curriculum', ['parent_id' => $faculty['id'], 'kind' => 'year', 'name' => 'الفرقة الأولى'])->assertCreated()->json('data');
        $course = $this->postJson('/api/v1/admin/curriculum', ['parent_id' => $year['id'], 'kind' => 'course', 'name' => 'رياضيات 1', 'code' => 'MTH101'])->assertCreated()->json('data');
        $this->assertSame('MTH101', $course['code']);

        // No loops, and nothing deleted while something hangs under it.
        $this->patchJson("/api/v1/admin/curriculum/{$faculty['id']}", ['parent_id' => $year['id']])->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/curriculum/{$faculty['id']}")->assertUnprocessable()->assertJsonPath('code', 'curriculum_has_children');
        $this->deleteJson("/api/v1/admin/curriculum/{$course['id']}")->assertOk();

        // Hidden = gone from the public tree.
        $this->patchJson("/api/v1/admin/curriculum/{$uni['id']}", ['status' => 'hidden'])->assertOk();
        $this->assertNotContains('جامعة النيل', array_column($this->getJson('/api/v1/public/curriculum')->json('data'), 'name'));
        $this->assertSame(['جامعة النيل'], array_column($this->getJson('/api/v1/admin/curriculum?q='.urlencode('النيل'))->json('data'), 'name'));

        // Only admins.
        $this->actingAsOwnerOf();
        $this->getJson('/api/v1/admin/curriculum')->assertForbidden();
    }

    public function test_the_contract_lists_every_level(): void
    {
        $this->assertSame(array_map(fn (NodeKind $k) => $k->value, NodeKind::cases()), CurriculumAdmin::KINDS);
    }
}
