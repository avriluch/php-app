<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    public function send(string $recipientEmail, string $subject, string $view, array $data = []): void
    {
        $apiKey = config('services.brevo.api_key');

        if (empty($apiKey)) {
            Log::error('BREVO_API_KEY no configurada; no se envió email a ' . $recipientEmail);

            return;
        }

        $htmlContent = view($view, $data)->render();

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'name' => config('mail.from.name'),
                'email' => config('mail.from.address'),
            ],
            'to' => [
                ['email' => $recipientEmail],
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ]);

        if (! $response->successful()) {
            Log::warning(sprintf(
                'Brevo email failed for %s: %s %s',
                $recipientEmail,
                $response->status(),
                $response->body(),
            ));
        }
    }
}
