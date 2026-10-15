<?php

declare(strict_types=1);

namespace App\Modules\Learners\Support;

use App\Modules\Learners\Models\Learner;
use App\Modules\Learners\Models\LearnerDevice;
use App\Modules\Learners\Models\LearnerProfile;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student signing in on a teacher's platform: their profile there (made on first visit), and
 * this device — one more than the site's limit is refused until an assistant frees one.
 */
final class SignIn
{
    public const TOKEN_DAYS = 90;

    public function __construct(private readonly CurrentTenant $tenant) {}

    /**
     * @param  array{tenant_id: string, device_limit: int, registration_open: bool}  $site
     * @param  array<string, mixed>  $profile  fields for a new profile
     */
    public function handle(Learner $learner, array $site, string $deviceKey, string $deviceName, array $profile = []): string
    {
        return $this->tenant->runAs($site['tenant_id'], fn () => DB::transaction(function () use ($learner, $site, $deviceKey, $deviceName, $profile): string {
            $existing = LearnerProfile::query()->where('learner_id', $learner->id)->lockForUpdate()->first();
            if ($existing === null && ! $site['registration_open']) {
                throw new DomainRuleException('التسجيل على المنصة دي مقفول دلوقتي. كلّم المدرس.', 'registration_closed', Response::HTTP_FORBIDDEN);
            }
            $existing ??= LearnerProfile::create(['learner_id' => $learner->id, ...$profile]);
            if ($existing->blocked_at !== null) {
                throw new DomainRuleException('حسابك موقوف على المنصة دي. كلّم المدرس.', 'learner_blocked', Response::HTTP_FORBIDDEN);
            }

            $hash = hash('sha256', $deviceKey);
            $device = LearnerDevice::query()->where('learner_id', $learner->id)->where('device_hash', $hash)->first();
            if ($device === null) {
                $count = LearnerDevice::query()->where('learner_id', $learner->id)->count();
                if ($count >= $site['device_limit']) {
                    throw new DomainRuleException(
                        "حسابك مفتوح على {$count} أجهزة، ودي آخر عدد مسموح. اطلب من المساعد يشيل جهاز قديم.",
                        'device_limit',
                        Response::HTTP_CONFLICT,
                        ['limit' => $site['device_limit']],
                    );
                }
                $device = LearnerDevice::create(['learner_id' => $learner->id, 'device_hash' => $hash, 'name' => mb_substr($deviceName, 0, 120)]);
            }
            $device->update(['last_seen_at' => now(), 'name' => mb_substr($deviceName, 0, 120)]);
            $existing->update(['last_seen_at' => now()]);
            $learner->update(['last_login_at' => now()]);

            // One token per device: signing in again replaces it.
            $learner->tokens()->where('name', "device:{$device->id}")->delete();

            return $learner->createToken("device:{$device->id}", ['learner', "site:{$site['tenant_id']}", "device:{$device->id}"], now()->addDays(self::TOKEN_DAYS))->plainTextToken;
        }));
    }
}
