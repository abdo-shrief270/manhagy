<?php

declare(strict_types=1);

namespace App\Modules\Learners\Support;

use Propaganistas\LaravelPhone\PhoneNumber;

/** Egyptian phones as E.164 (input may be local: 010…). */
final class Phone
{
    public static function e164(mixed $phone): mixed
    {
        if (! is_string($phone) || trim($phone) === '') {
            return $phone;
        }
        try {
            return (new PhoneNumber(trim($phone), 'EG'))->formatE164();
        } catch (\Throwable) {
            return $phone;
        }
    }
}
