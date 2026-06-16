<?php

namespace App\Mail\Transport;
use Illuminate\Mail\Transport\Transport;

use GuzzleHttp\Client;

class BrevoTransport extends Transport
{
    protected Client $client;
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->client = new Client();
        $this->apiKey = $apiKey;
    }

    public function send($message): void
    {
        $email = $message->getOriginalMessage();

        $to = collect($email->getTo())->map(function ($user) {
            return [
                'email' => $user->getAddress(),
                'name' => $user->getName() ?? null,
            ];
        })->values()->all();

        $this->client->post('https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'api-key' => $this->apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
            'json' => [
                'sender' => [
                    'name' => config('mail.from.name'),
                    'email' => config('mail.from.address'),
                ],
                'to' => $to,
                'subject' => $email->getSubject() ?? 'Sin asunto',
                'htmlContent' => method_exists($email, 'getHtmlBody')
                    ? $email->getHtmlBody()
                    : '',
            ],
        ]);
    }
}