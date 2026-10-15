<?php

namespace Tests\Fixtures\Modules\Demo;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class DemoNote extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
