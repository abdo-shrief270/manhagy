<?php

declare(strict_types=1);

namespace App\Modules\Learners\Http\Middleware;

use App\Modules\Learners\Models\Learner;
use App\Modules\Learners\Support\PhoneChecks;
use App\Support\Exceptions\DomainRuleException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** After `learner`: watching, codes, buying and exams need a confirmed phone (while verification is on). */
final class RequireVerifiedPhone
{
    public function handle(Request $request, Closure $next): Response
    {
        $learner = $request->user();
        if (PhoneChecks::enabled() && $learner instanceof Learner && $learner->phone_verified_at === null) {
            throw new DomainRuleException('أكّد رقمك الأول: ابعت الكود على واتساب من موبايلك.', 'phone_unverified', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
