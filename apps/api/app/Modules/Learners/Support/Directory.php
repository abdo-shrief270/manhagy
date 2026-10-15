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

        return $learner instanceof Learner ? $this->find($learner->id) : null;
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
