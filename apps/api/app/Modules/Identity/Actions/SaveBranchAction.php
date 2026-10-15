<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\AccountKind;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Saves a branch. A second (or later) branch is for centers only (a teacher works from one place).
 */
final class SaveBranchAction
{
    public function __construct(
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{name?: string, address?: string|null, governorate?: string|null, area?: string|null, latitude?: float|null, longitude?: float|null, phone?: string|null, is_active?: bool}  $data
     */
    public function handle(string $tenantId, array $data, ?Branch $branch = null): Branch
    {
        $creating = $branch === null;

        // A teacher works from one place; a center may have several.
        if ($creating && Branch::query()->exists() && ! in_array(AccountKind::Center, Tenant::query()->findOrFail($tenantId)->types(), true)) {
            throw new DomainRuleException('الفروع الإضافية للسناتر بس.', 'branches_for_centers', 403);
        }

        if ($branch?->is_main && ($data['is_active'] ?? true) === false) {
            throw new DomainRuleException('مينفعش توقف الفرع الرئيسي.', 'main_branch_immutable');
        }

        return DB::transaction(function () use ($tenantId, $data, $branch, $creating): Branch {
            $branch ??= new Branch([
                'tenant_id' => $tenantId,
                'is_main' => false,
                'is_active' => true,
                'invoice_prefix' => 'B'.(Branch::query()->count() + 1),
            ]);

            $branch->fill(array_intersect_key($data, array_flip(['name', 'address', 'governorate', 'area', 'latitude', 'longitude', 'phone', 'is_active'])))->save();

            $this->audit->record(
                $creating ? 'branches.created' : 'branches.updated',
                ($creating ? 'أضاف' : 'عدّل')." الفرع «{$branch->name}»",
                $branch,
            );

            return $branch;
        });
    }
}
