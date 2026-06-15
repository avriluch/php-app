<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $mode = config('services.paypal.mode', 'sandbox');

        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
    }

    /**
     * Obtener Access Token seguro
     */
    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            Log::error('PayPal Auth Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Error autenticando con PayPal');
        }

        $token = $response->json('access_token');

        if (!$token) {
            throw new \Exception('PayPal no devolvió access token');
        }

        return $token;
    }

    /**
     * Crear orden de pago
     */
    public function createOrder(float $amount, string $currency = 'USD', string $referenceId = ''): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $referenceId,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]],
            ]);

        if (!$response->successful()) {
            Log::error('PayPal Create Order Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $referenceId,
            ]);

            throw new \Exception('Error creando orden en PayPal');
        }

        $data = $response->json();

        if (!isset($data['id'])) {
            Log::error('PayPal Invalid Create Order Response', $data);

            throw new \Exception('Respuesta inválida de PayPal');
        }

        return $data;
    }

    /**
     * Capturar orden de pago
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withBody('{}', 'application/json')
            ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        if (!$response->successful()) {
            Log::error('PayPal Capture Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_id' => $orderId,
            ]);

            throw new \Exception('Error capturando pago en PayPal');
        }

        return $response->json();
    }
}