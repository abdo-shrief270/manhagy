<?php

declare(strict_types=1);

namespace App\Modules\Learners\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * A student's account, one for the whole platform (their phone). Signs in on a teacher's platform
 * with a Sanctum token carrying `learner`, `site:{tenant}` and `device:{id}`; never an account user.
 *
 * @property string $id
 * @property string $name
 * @property string $phone E.164
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $last_login_at
 */
#[Fillable(['name', 'phone', 'password', 'phone_verified_at', 'last_login_at'])]
#[Hidden(['password'])]
final class Learner extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected function casts(): array
    {
        return ['password' => 'hashed', 'phone_verified_at' => 'datetime', 'last_login_at' => 'datetime'];
    }
}
