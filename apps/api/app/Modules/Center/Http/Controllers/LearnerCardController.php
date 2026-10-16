<?php

declare(strict_types=1);

namespace App\Modules\Center\Http\Controllers;

use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Member;
use App\Modules\Center\Support\Cards;
use App\Modules\Center\Support\Timetable;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;

/** The student's card on their phone (the same QR as the printed one) and their groups. */
final class LearnerCardController
{
    public function __invoke(LearnerDirectory $learners, CurrentTenant $tenant): JsonResponse
    {
        $learner = $learners->current() ?? abort(401);
        $groups = Group::query()->whereIn('id', Member::query()->where('learner_id', $learner['learner_id'])->whereNull('left_at')->pluck('group_id'))->get();

        return response()->json(['data' => [
            'name' => $learner['name'],
            'token' => Cards::token($tenant->idOrFail(), $learner['learner_id']),
            'groups' => $groups->map(fn (Group $g) => ['name' => $g->name, 'room' => $g->room, 'schedule' => Timetable::label($g->schedule)])->all(),
        ]]);
    }
}
