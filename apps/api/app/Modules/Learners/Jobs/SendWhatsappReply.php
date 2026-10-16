<?php

declare(strict_types=1);

namespace App\Modules\Learners\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;

/** A reply in the chat the student opened (inside WhatsApp's 24 h window). Only with a Cloud API token; never fails loudly. */
final class SendWhatsappReply implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 2;

    public function __construct(public readonly string $phone, public readonly string $text) {}

    public function handle(): void
    {
        $token = (string) config('services.verify.token');
        $phoneId = (string) config('services.verify.phone_number_id');
        if ($token === '' || $phoneId === '') {
            return;
        }
        Http::withToken($token)->timeout(10)->post('https://graph.facebook.com/'.config('services.verify.graph_version')."/{$phoneId}/messages", [
            'messaging_product' => 'whatsapp', 'to' => ltrim($this->phone, '+'), 'type' => 'text', 'text' => ['body' => $this->text],
        ]);
    }
}
