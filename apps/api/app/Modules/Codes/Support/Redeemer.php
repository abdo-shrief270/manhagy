<?php

declare(strict_types=1);

namespace App\Modules\Codes\Support;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Codes\Models\Code;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Wallet\Contracts\LearnerWallets;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A student using a code: once only, under the code's row lock; wrong tries are limited per
 * student and per IP so codes can't be guessed.
 */
final class Redeemer
{
    public const TRIES_PER_LEARNER = 5;

    public const TRIES_PER_IP = 20;

    public const DECAY_SECONDS = 900;

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Enrollments $enrollments,
        private readonly LearnerWallets $wallets,
        private readonly CourseCatalog $courses,
    ) {}

    /** @return array<string, mixed> */
    public function redeem(string $learnerId, string $input, ?string $ip): array
    {
        $keys = ["codes:learner:{$learnerId}" => self::TRIES_PER_LEARNER, 'codes:ip:'.($ip ?? 'none') => self::TRIES_PER_IP];
        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new DomainRuleException('جرّبت أكواد غلط كتير. استنى شوية وجرّب تاني.', 'code_attempts', 429, ['retry_after' => RateLimiter::availableIn($key)]);
            }
        }

        try {
            return $this->apply($learnerId, CodeFormat::normalize($input));
        } catch (DomainRuleException $e) {
            if (in_array($e->errorCode, ['code_mistyped', 'code_invalid', 'code_used', 'code_cancelled', 'code_expired'], true)) {
                foreach (array_keys($keys) as $key) {
                    RateLimiter::hit($key, self::DECAY_SECONDS);
                }
            }
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function apply(string $learnerId, string $code): array
    {
        if (! CodeFormat::valid($code)) {
            throw new DomainRuleException('الكود ده فيه غلط في الكتابة. راجعه حرف حرف.', 'code_mistyped');
        }

        return DB::transaction(function () use ($learnerId, $code): array {
            $row = Code::query()->with('batch')->where('hash', CodeFormat::hash($this->tenant->idOrFail(), $code))->lockForUpdate()->first()
                ?? throw new DomainRuleException('الكود ده مش موجود على المنصة دي.', 'code_invalid', 404);
            $batch = $row->batch;
            if ($row->status === 'used') {
                throw new DomainRuleException($row->used_by === $learnerId ? 'انت استخدمت الكود ده قبل كده.' : 'الكود ده اتستخدم قبل كده.', 'code_used');
            }
            if ($row->status === 'cancelled' || $batch->status === 'cancelled') {
                throw new DomainRuleException('الكود ده اتلغى. كلّم المدرس أو المكان اللي اشتريته منه.', 'code_cancelled');
            }
            if ($batch->expires_at !== null && $batch->expires_at->isPast()) {
                throw new DomainRuleException('الكود ده انتهت صلاحيته.', 'code_expired');
            }

            if ($batch->kind === 'course') {
                $enrollment = $this->enrollments->grant($learnerId, (string) $batch->course_id, 'code', $batch->days, null, $row->id);
                $row->update(['status' => 'used', 'used_by' => $learnerId, 'used_at' => now(), 'result_ref' => $enrollment['id']]);
                $course = $this->courses->course((string) $batch->course_id, evenUnpublished: true);

                return ['kind' => 'course', 'course' => ['id' => $course['id'], 'title' => $course['title']], 'enrollment' => $enrollment];
            }

            $tx = $this->wallets->post($learnerId, 'code', $batch->value, $row->id, "كود من {$batch->code()}");
            $row->update(['status' => 'used', 'used_by' => $learnerId, 'used_at' => now(), 'result_ref' => $tx['id']]);

            return ['kind' => 'balance', 'amount' => $batch->value, 'balance' => $tx['balance_after']];
        });
    }
}
