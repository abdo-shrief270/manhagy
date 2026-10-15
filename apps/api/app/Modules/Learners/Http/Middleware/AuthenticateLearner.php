<?php

declare(strict_types=1);

namespace App\Modules\Learners\Http\Middleware;

use App\Modules\Learners\Models\Learner;
use App\Modules\Learners\Models\LearnerDevice;
use App\Modules\Learners\Models\LearnerProfile;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:sanctum on the student's routes: a student's token, on the platform it was
 * issued for (sets the current tenant), from a device that is still allowed, not blocked.
 */
final class AuthenticateLearner
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $learner = $request->user();
        $token = $learner instanceof Learner ? $learner->currentAccessToken() : null;
        if ($token === null || ! $learner->tokenCan('learner')) {
            throw new DomainRuleException('سجّل دخول كطالب الأول.', 'learner_only', Response::HTTP_FORBIDDEN);
        }

        $tenantId = $this->ability($token->abilities, 'site');
        $deviceId = $this->ability($token->abilities, 'device');
        $this->tenant->set($tenantId);

        $device = $deviceId === null ? null : LearnerDevice::query()->find($deviceId);
        if ($device === null || $device->learner_id !== $learner->id) {
            $token->delete();
            throw new DomainRuleException('الجهاز ده اتشال من حسابك. سجّل دخول تاني.', 'device_removed', Response::HTTP_UNAUTHORIZED);
        }
        $profile = LearnerProfile::query()->where('learner_id', $learner->id)->first();
        if ($profile === null || $profile->blocked_at !== null) {
            throw new DomainRuleException('حسابك موقوف على المنصة دي. كلّم المدرس.', 'learner_blocked', Response::HTTP_FORBIDDEN);
        }
        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinutes(5))) {
            $device->update(['last_seen_at' => now()]);
            $profile->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }

    /** @param  list<string>  $abilities */
    private function ability(array $abilities, string $prefix): ?string
    {
        foreach ($abilities as $ability) {
            if (str_starts_with($ability, "{$prefix}:")) {
                return substr($ability, strlen($prefix) + 1);
            }
        }

        return null;
    }
}
