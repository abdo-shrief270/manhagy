<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Support;

final class Money
{
    /** 12550 → «125.5 ج» */
    public static function format(int $piasters): string
    {
        return rtrim(rtrim(number_format($piasters / 100, 2, '.', ','), '0'), '.').' ج';
    }
}
