<?php

declare(strict_types=1);

namespace App\Modules\Learners\Support;

use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Learners\Models\Learner;
use App\Modules\Learners\Models\LearnerProfile;
use Illuminate\Contracts\Auth\Factory as Auth;

final class Directory implements LearnerDirectory
{
    public function __construct(private readonly Auth $auth) {}

    public function current(): ?array
    {
        $learner = $this->auth->guard('sanctum')->user();

        if (! $learner instanceof Learner || ($found = $this->find($learner->id)) === null) {
            return null;
        }
        $device = collect($learner->currentAccessToken()?->abilities ?? [])->first(fn (string $a) => str_starts_with($a, 'device:'));

        return [...$found, 'device_id' => $device === null ? null : substr($device, 7)];
    }

    public function find(string $learnerId): ?array
    {
        $profile = LearnerProfile::query()->with('learner')->where('learner_id', $learnerId)->first();

        return $profile === null ? null : [
            'learner_id' => $profile->learner_id,
            'profile_id' => $profile->id,
            'name' => $profile->learner->name,
            'phone' => $profile->learner->phone,
            'grade_node_id' => $profile->grade_node_id,
            'parent_phone' => $profile->parent_phone,
            'blocked' => $profile->blocked_at !== null,
        ];
    }
}
