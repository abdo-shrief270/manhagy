<?php

declare(strict_types=1);

namespace App\Modules\Learners\Http\Controllers;

use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Learners\Models\Learner;
use App\Modules\Learners\Models\LearnerDevice;
use App\Modules\Learners\Models\LearnerProfile;
use App\Modules\Learners\Support\Phone;
use App\Modules\Learners\Support\SignIn;
use App\Modules\Sites\Contracts\SiteDirectory;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/** A student registering / signing in on a teacher's platform, and their own session. */
final class LearnerAuthController
{
    public function __construct(
        private readonly SiteDirectory $sites,
        private readonly SignIn $signIn,
        private readonly CurriculumTree $curriculum,
    ) {}

    public function register(Request $request, string $slug): JsonResponse
    {
        $site = $this->site($slug);
        $request->merge(['phone' => Phone::e164($request->input('phone')), 'parent_phone' => Phone::e164($request->input('parent_phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'phone:EG'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
            'grade_id' => ['nullable', 'uuid'],
            'parent_phone' => [$site['require_parent_phone'] ? 'required' : 'nullable', 'phone:EG', 'different:phone'],
            'consent' => ['accepted'],
            ...$this->deviceRules(),
        ], [], ['name' => 'الاسم', 'phone' => 'رقم الموبايل', 'password' => 'كلمة السر', 'parent_phone' => 'رقم ولي الأمر', 'consent' => 'الموافقة']);

        if (Learner::query()->where('phone', $data['phone'])->exists()) {
            throw new DomainRuleException('الرقم ده عنده حساب على منهجي. ادخل بيه بنفس كلمة السر.', 'phone_taken', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (isset($data['grade_id']) && $this->curriculum->node($data['grade_id']) === null) {
            throw new DomainRuleException('اختار صفك من القايمة.', 'grade_missing', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $token = DB::transaction(function () use ($data, $site): string {
            $learner = Learner::query()->create(['name' => $data['name'], 'phone' => $data['phone'], 'password' => $data['password'], 'last_login_at' => now()]);

            return $this->signIn->handle($learner, $site, $data['device_key'], $data['device_name'], [
                'grade_node_id' => $data['grade_id'] ?? null,
                'parent_phone' => $data['parent_phone'] ?? null,
                'consent_at' => now(),
            ]);
        });

        return response()->json(['token' => $token], Response::HTTP_CREATED);
    }

    public function login(Request $request, string $slug): JsonResponse
    {
        $site = $this->site($slug);
        $request->merge(['phone' => Phone::e164($request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', 'string'], 'password' => ['required', 'string'], ...$this->deviceRules()]);

        $key = 'learner-login:'.sha1($data['phone'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new DomainRuleException('محاولات كتير غلط. جرّب بعد '.ceil(RateLimiter::availableIn($key) / 60).' دقيقة.', 'login_locked', Response::HTTP_TOO_MANY_REQUESTS);
        }
        $learner = Learner::query()->where('phone', $data['phone'])->first();
        if ($learner === null || ! Hash::check($data['password'], $learner->getAuthPassword())) {
            RateLimiter::hit($key, 15 * 60);
            throw new DomainRuleException('الموبايل أو كلمة السر غلط.', 'invalid_credentials', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        RateLimiter::clear($key);

        return response()->json(['token' => $this->signIn->handle($learner, $site, $data['device_key'], $data['device_name'])]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Learner $learner */
        $learner = $request->user();
        $profile = LearnerProfile::query()->where('learner_id', $learner->id)->firstOrFail();

        return response()->json(['data' => [
            'name' => $learner->name,
            'phone' => $learner->phone,
            'grade_id' => $profile->grade_node_id,
            'grade' => $profile->grade_node_id ? $this->curriculum->label($profile->grade_node_id) : null,
            'parent_phone' => $profile->parent_phone,
            'devices' => LearnerDevice::query()->where('learner_id', $learner->id)->orderBy('created_at')->get()
                ->map(fn (LearnerDevice $d) => ['id' => $d->id, 'name' => $d->name, 'last_seen_at' => $d->last_seen_at?->toIso8601String(), 'current' => $request->user()->currentAccessToken()->name === "device:{$d->id}"]),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Learner $learner */
        $learner = $request->user();
        $request->merge(['parent_phone' => Phone::e164($request->input('parent_phone'))]);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'grade_id' => ['sometimes', 'nullable', 'uuid'],
            'parent_phone' => ['sometimes', 'nullable', 'phone:EG'],
        ]);
        if (! empty($data['grade_id']) && $this->curriculum->node($data['grade_id']) === null) {
            throw new DomainRuleException('اختار صفك من القايمة.', 'grade_missing', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (isset($data['name'])) {
            $learner->update(['name' => $data['name']]);
        }
        $profile = LearnerProfile::query()->where('learner_id', $learner->id)->firstOrFail();
        $profile->update(array_filter([
            'grade_node_id' => array_key_exists('grade_id', $data) ? $data['grade_id'] : $profile->grade_node_id,
            'parent_phone' => array_key_exists('parent_phone', $data) ? $data['parent_phone'] : $profile->parent_phone,
        ], fn ($v) => true));

        return $this->me($request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function site(string $slug): array
    {
        return $this->sites->bySlug($slug) ?? abort(404);
    }

    /** @return array<string, list<string>> */
    private function deviceRules(): array
    {
        return [
            'device_key' => ['required', 'string', 'min:16', 'max:100'],
            'device_name' => ['required', 'string', 'max:120'],
        ];
    }
}
