<?php

declare(strict_types=1);

namespace App\Modules\Live\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $session_id
 * @property string $question
 * @property list<string> $options
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'session_id', 'question', 'options', 'closed_at'])]
final class LivePoll extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['options' => 'array', 'closed_at' => 'datetime'];
    }

    /** @return array<string, mixed> with the counts when `$withResults` */
    public function toSummary(bool $withResults, ?int $myVote = null): array
    {
        $counts = DB::table('live_poll_votes')->where('poll_id', $this->id)->selectRaw('option, count(*) as n')->groupBy('option')->pluck('n', 'option');

        return [
            'id' => $this->id, 'question' => $this->question, 'options' => $this->options,
            'open' => $this->closed_at === null, 'my_vote' => $myVote,
            'results' => $withResults ? array_map(fn (int $i) => (int) ($counts[$i] ?? 0), array_keys($this->options)) : null,
            'votes' => (int) $counts->sum(),
        ];
    }
}
