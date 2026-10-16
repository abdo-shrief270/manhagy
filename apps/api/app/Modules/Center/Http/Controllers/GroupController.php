<?php

declare(strict_types=1);

namespace App\Modules\Center\Http\Controllers;

use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Member;
use App\Modules\Center\Models\Payment;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Teacher;
use App\Modules\Center\Support\Cards;
use App\Modules\Center\Support\Door;
use App\Modules\Center\Support\Timetable;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Groups, their students and sessions; the roster of a session; the students' cards. */
final class GroupController
{
    public function __construct(
        private readonly LearnerDirectory $learners,
        private readonly CurriculumTree $tree,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(): JsonResponse
    {
        $groups = Group::query()->orderByDesc('active')->orderBy('name')->get();
        $counts = Member::query()->whereNull('left_at')->selectRaw('group_id, count(*) as n')->groupBy('group_id')->pluck('n', 'group_id');
        $teachers = Teacher::query()->pluck('name', 'id');

        return response()->json(['data' => $groups->map(fn (Group $g) => [...$this->summary($g, $teachers->all()), 'members' => (int) ($counts[$g->id] ?? 0)])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $group = Group::query()->create($this->validated($request, true));
        Timetable::ensureAhead($group);

        return response()->json(['data' => $this->detail($group)], 201);
    }

    public function show(string $group): JsonResponse
    {
        return response()->json(['data' => $this->detail(Group::query()->findOrFail($group))]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $found = Group::query()->findOrFail($group);
        $found->update($this->validated($request, false));
        Timetable::ensureAhead($found);

        return response()->json(['data' => $this->detail($found)]);
    }

    public function addMember(Request $request, string $group): JsonResponse
    {
        $found = Group::query()->findOrFail($group);
        $data = $request->validate(['learner_id' => ['required', 'uuid'], 'plan' => ['sometimes', Rule::in(['session', 'monthly'])]]);
        $learner = $this->learners->find($data['learner_id']) ?? throw new DomainRuleException('الطالب ده مش مسجّل على منصتك.', 'learner_unknown', 404);
        if (Member::query()->where('group_id', $found->id)->where('learner_id', $learner['learner_id'])->whereNull('left_at')->exists()) {
            throw new DomainRuleException('الطالب ده في المجموعة فعلاً.', 'already_member');
        }
        if ($found->capacity !== null && Member::query()->where('group_id', $found->id)->whereNull('left_at')->count() >= $found->capacity) {
            throw new DomainRuleException('المجموعة كاملة.', 'group_full');
        }
        Member::query()->create(['group_id' => $found->id, 'learner_id' => $learner['learner_id'], 'plan' => $data['plan'] ?? ($found->monthly_fee ? 'monthly' : 'session'), 'joined_at' => now()]);

        return response()->json(['data' => $this->detail($found)], 201);
    }

    public function removeMember(string $group, string $member): JsonResponse
    {
        $found = Group::query()->findOrFail($group);
        Member::query()->where('group_id', $found->id)->whereNull('left_at')->findOrFail($member)->update(['left_at' => now()]);

        return response()->json(['data' => $this->detail($found)]);
    }

    /** Opens (or finds) the session happening now, for the door. */
    public function openSession(string $group): JsonResponse
    {
        $found = Group::query()->findOrFail($group);

        return response()->json(['data' => $this->roster(Timetable::current($found))]);
    }

    public function cancelSession(Request $request, string $session): JsonResponse
    {
        $data = $request->validate(['topic' => ['nullable', 'string', 'max:160']]);
        $found = Session::query()->findOrFail($session);
        $found->update(['status' => 'cancelled', 'topic' => $data['topic'] ?? $found->topic]);

        return response()->json(['data' => ['id' => $found->id, 'status' => $found->status]]);
    }

    /** Today's sessions (Cairo), for the door's picker. */
    public function today(): JsonResponse
    {
        $from = now()->setTimezone('Africa/Cairo')->startOfDay()->utc();
        $sessions = Session::query()->where('status', 'scheduled')->whereBetween('starts_at', [$from, $from->copy()->addDay()])->orderBy('starts_at')->get();
        $groups = Group::query()->whereIn('id', $sessions->pluck('group_id'))->get()->keyBy('id');
        $present = Attendance::query()->whereIn('session_id', $sessions->pluck('id'))->selectRaw('session_id, count(*) as n')->groupBy('session_id')->pluck('n', 'session_id');
        $members = Member::query()->whereIn('group_id', $groups->keys())->whereNull('left_at')->selectRaw('group_id, count(*) as n')->groupBy('group_id')->pluck('n', 'group_id');

        return response()->json(['data' => $sessions->map(fn (Session $s) => [
            'id' => $s->id, 'group_id' => $s->group_id, 'group' => $groups[$s->group_id]->name ?? '', 'room' => $groups[$s->group_id]->room ?? null,
            'starts_at' => $s->starts_at->toIso8601String(), 'minutes' => $s->minutes,
            'present' => (int) ($present[$s->id] ?? 0), 'members' => (int) ($members[$s->group_id] ?? 0),
        ])->all()]);
    }

    /** A session's roster: who came, who paid, who's absent (with the parent's phone for WhatsApp). */
    public function session(string $session): JsonResponse
    {
        return response()->json(['data' => $this->roster(Session::query()->findOrFail($session))]);
    }

    /** The cards to print (a group's students) with their signed QR tokens. */
    public function cards(Request $request): JsonResponse
    {
        $data = $request->validate(['group_id' => ['required', 'uuid']]);
        $group = Group::query()->findOrFail($data['group_id']);
        $tenantId = $this->tenant->idOrFail();

        return response()->json(['data' => Member::query()->where('group_id', $group->id)->whereNull('left_at')->get()->map(function (Member $m) use ($tenantId, $group): array {
            $learner = $this->learners->find($m->learner_id);

            return ['learner_id' => $m->learner_id, 'name' => $learner['name'] ?? '', 'phone' => $learner['phone'] ?? null, 'group' => $group->name, 'token' => Cards::token($tenantId, $m->learner_id)];
        })->sortBy('name')->values()->all()]);
    }

    /** @return array<string, mixed> */
    private function roster(Session $session): array
    {
        $group = Group::query()->findOrFail($session->group_id);
        $door = app(Door::class);
        $attendance = Attendance::query()->where('session_id', $session->id)->get()->keyBy('learner_id');
        $members = Member::query()->where('group_id', $group->id)->whereNull('left_at')->get();
        $tenantId = $this->tenant->idOrFail();
        $rows = $members->map(function (Member $m) use ($attendance, $door, $group, $session, $tenantId): array {
            $learner = $this->learners->find($m->learner_id);
            $a = $attendance[$m->learner_id] ?? null;

            return [
                'learner_id' => $m->learner_id, 'name' => $learner['name'] ?? '', 'phone' => $learner['phone'] ?? null,
                'parent_phone' => $learner['parent_phone'] ?? null, 'plan' => $m->plan, 'present' => $a !== null, 'late' => (bool) $a?->late,
                'scanned_at' => $a?->scanned_at?->toIso8601String(), 'owes' => $door->owes($group, $session, $m, $m->learner_id),
                'token' => Cards::token($tenantId, $m->learner_id),
            ];
        });
        // Present without being a member (a visitor), shown too.
        $guests = $attendance->keys()->diff($members->pluck('learner_id'))->map(function (string $id) use ($attendance): array {
            $learner = $this->learners->find($id);

            return ['learner_id' => $id, 'name' => $learner['name'] ?? '', 'phone' => $learner['phone'] ?? null, 'parent_phone' => null, 'plan' => 'guest', 'present' => true, 'late' => $attendance[$id]->late, 'scanned_at' => $attendance[$id]->scanned_at->toIso8601String(), 'owes' => 0, 'token' => null];
        });

        return [
            'id' => $session->id, 'group_id' => $group->id, 'group' => $group->name, 'room' => $group->room,
            'starts_at' => $session->starts_at->toIso8601String(), 'minutes' => $session->minutes, 'status' => $session->status,
            'session_fee' => $group->session_fee, 'monthly_fee' => $group->monthly_fee,
            'collected' => (int) Payment::query()->where('group_id', $group->id)->where(fn ($q) => $q->where('session_id', $session->id))->sum('amount'),
            'students' => $rows->concat($guests)->sortBy([['present', 'desc'], ['name', 'asc']])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Group $group): array
    {
        $teachers = Teacher::query()->pluck('name', 'id')->all();
        $members = Member::query()->where('group_id', $group->id)->whereNull('left_at')->orderBy('joined_at')->get();
        $upcoming = Session::query()->where('group_id', $group->id)->where('starts_at', '>=', now()->subHours(3))->orderBy('starts_at')->limit(8)->get();

        return [
            ...$this->summary($group, $teachers),
            'members' => $members->map(function (Member $m): array {
                $learner = $this->learners->find($m->learner_id);

                return ['id' => $m->id, 'learner_id' => $m->learner_id, 'name' => $learner['name'] ?? '', 'phone' => $learner['phone'] ?? null, 'plan' => $m->plan, 'joined_at' => $m->joined_at->toIso8601String()];
            })->all(),
            'sessions' => $upcoming->map(fn (Session $s) => ['id' => $s->id, 'starts_at' => $s->starts_at->toIso8601String(), 'minutes' => $s->minutes, 'status' => $s->status, 'present' => Attendance::query()->where('session_id', $s->id)->count()])->all(),
        ];
    }

    /**
     * @param  array<string, string>  $teachers
     * @return array<string, mixed>
     */
    private function summary(Group $g, array $teachers): array
    {
        return [
            'id' => $g->id, 'name' => $g->name, 'teacher_id' => $g->teacher_id, 'teacher' => $g->teacher_id ? ($teachers[$g->teacher_id] ?? null) : null,
            'grade_node_id' => $g->grade_node_id, 'grade' => $g->grade_node_id ? $this->tree->label($g->grade_node_id) : null, 'course_id' => $g->course_id,
            'room' => $g->room, 'schedule' => $g->schedule, 'schedule_label' => Timetable::label($g->schedule),
            'session_fee' => $g->session_fee, 'monthly_fee' => $g->monthly_fee, 'capacity' => $g->capacity, 'active' => $g->active,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:120'],
            'teacher_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('center_teachers', 'id')->where('tenant_id', $this->tenant->idOrFail())],
            'grade_node_id' => ['sometimes', 'nullable', 'uuid'],
            'course_id' => ['sometimes', 'nullable', 'uuid'],
            'room' => ['sometimes', 'nullable', 'string', 'max:60'],
            'schedule' => [$required, 'array', 'max:7'],
            'schedule.*.day' => ['required', 'integer', 'between:0,6'],
            'schedule.*.time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'schedule.*.minutes' => ['nullable', 'integer', 'min:15', 'max:300'],
            'session_fee' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'monthly_fee' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'اسم المجموعة', 'schedule' => 'المواعيد']);
        if (isset($data['schedule'])) {
            $data['schedule'] = array_map(fn (array $s) => ['day' => (int) $s['day'], 'time' => $s['time'], 'minutes' => (int) ($s['minutes'] ?? 90)], array_values($data['schedule']));
        }

        return $data;
    }
}
