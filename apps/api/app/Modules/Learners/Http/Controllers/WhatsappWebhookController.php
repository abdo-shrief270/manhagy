<?php

declare(strict_types=1);

namespace App\Modules\Learners\Http\Controllers;

use App\Modules\Learners\Jobs\SendWhatsappReply;
use App\Modules\Learners\Support\PhoneChecks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Meta's WhatsApp Cloud API calling us: the subscription handshake and inbound messages (signed with the app secret). */
final class WhatsappWebhookController
{
    public function handshake(Request $request): Response
    {
        $token = (string) config('services.verify.verify_token');
        abort_if($token === '' || $request->query('hub_mode') !== 'subscribe' || ! hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, PhoneChecks $checks): Response
    {
        $secret = (string) config('services.verify.app_secret');
        abort_if($secret === '', 404);
        $signature = (string) $request->header('X-Hub-Signature-256');
        abort_unless(hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature), 403);

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['messages'] ?? []) as $message) {
                    if (($message['type'] ?? null) !== 'text' || ! isset($message['from'], $message['text']['body'])) {
                        continue;
                    }
                    $phone = '+'.preg_replace('/\D+/', '', (string) $message['from']);
                    $purpose = $checks->confirm($phone, (string) $message['text']['body']);
                    if ($purpose !== null) {
                        SendWhatsappReply::dispatch($phone, $purpose === 'reset'
                            ? 'تمام ✅ ارجع للصفحة واكتب كلمة السر الجديدة.'
                            : 'تمام ✅ رقمك اتأكد على منهجي. ارجع للصفحة وكمّل.')->afterResponse();
                    }
                }
            }
        }

        return response('', 200);
    }
}
