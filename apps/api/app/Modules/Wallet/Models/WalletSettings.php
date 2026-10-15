<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $transfers_enabled
 * @property string|null $instapay
 * @property string|null $wallet_number
 * @property string|null $instructions
 * @property int $min_topup piasters
 */
#[Fillable(['tenant_id', 'transfers_enabled', 'instapay', 'wallet_number', 'instructions', 'min_topup'])]
final class WalletSettings extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'wallet_settings';

    protected $attributes = ['transfers_enabled' => false, 'min_topup' => 1000];

    protected function casts(): array
    {
        return ['transfers_enabled' => 'boolean', 'min_topup' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'transfers_enabled' => $this->transfers_enabled, 'instapay' => $this->instapay, 'wallet_number' => $this->wallet_number,
            'instructions' => $this->instructions, 'min_topup' => $this->min_topup,
        ];
    }
}
