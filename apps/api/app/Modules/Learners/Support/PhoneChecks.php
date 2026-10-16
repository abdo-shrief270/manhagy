<?php

declare(strict_types=1);

namespace App\Modules\Learners\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «ابعت الكود ده على واتساب»: the student sends a code from their own phone to the platform's
 * WhatsApp number, which proves the phone is theirs without the platform sending anything.
 * The inbound message (Meta's webhook) confirms the check whose phone = the sender and code = the text.
 */
final class PhoneChecks
{
    private const TABLE = 'learner_phone_checks';

    public static function enabled(): bool
    {
        return (string) config('services.verify.whatsapp') !== '';
    }

    /** @return array{code: string, text: string, whatsapp: string, link: string, expires_at: string} */
    public function start(string $phone, string $purpose): array
    {
        $code = (string) random_int(100000, 999999);
        $expires = now()->addMinutes((int) config('services.verify.minutes', 30));
        DB::table(self::TABLE)->where('phone', $phone)->where('purpose', $purpose)->whereNull('confirmed_at')->delete();
        DB::table(self::TABLE)->insert([
            'id' => (string) Str::uuid7(), 'phone' => $phone, 'purpose' => $purpose, 'code_hash' => $this->hash($phone, $code),
            'expires_at' => $expires, 'created_at' => now(),
        ]);
        $text = ($purpose === 'reset' ? 'استرجاع كلمة السر على منهجي: ' : 'تأكيد رقمي على منهجي: ').$code;
        $number = (string) config('services.verify.whatsapp');

        return ['code' => $code, 'text' => $text, 'whatsapp' => $number, 'link' => "https://wa.me/{$number}?text=".rawurlencode($text), 'expires_at' => $expires->toIso8601String()];
    }

    /**
     * An inbound WhatsApp message: confirms the open check of that phone whose code is in the text.
     *
     * @return string|null the purpose confirmed
     */
    public function confirm(string $phone, string $text): ?string
    {
        $digits = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        if (preg_match_all('/(?<!\d)\d{6}(?!\d)/', $digits, $m) === 0) {
            return null;
        }
        $hashes = array_map(fn (string $c) => $this->hash($phone, $c), $m[0]);
        $check = DB::table(self::TABLE)->where('phone', $phone)->whereIn('code_hash', $hashes)->whereNull('confirmed_at')
            ->where('expires_at', '>', now())->orderByDesc('created_at')->first();
        if ($check === null) {
            return null;
        }
        DB::table(self::TABLE)->where('id', $check->id)->update(['confirmed_at' => now()]);
        if ($check->purpose === 'verify') {
            DB::table('learners')->where('phone', $phone)->whereNull('phone_verified_at')->update(['phone_verified_at' => now(), 'updated_at' => now()]);
        }

        return $check->purpose;
    }

    /** Uses a confirmed reset check (once, within its time). */
    public function useReset(string $phone, string $code): bool
    {
        return DB::table(self::TABLE)->where('phone', $phone)->where('purpose', 'reset')->where('code_hash', $this->hash($phone, $code))
            ->whereNotNull('confirmed_at')->whereNull('used_at')->where('expires_at', '>', now())
            ->update(['used_at' => now()]) === 1;
    }

    public function isConfirmed(string $phone, string $purpose, string $code): bool
    {
        return DB::table(self::TABLE)->where('phone', $phone)->where('purpose', $purpose)->where('code_hash', $this->hash($phone, $code))
            ->whereNotNull('confirmed_at')->whereNull('used_at')->exists();
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', "{$phone}|{$code}", (string) config('app.key'));
    }
}
