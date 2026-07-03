<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoPaymentService
{
    public function createCheckoutLink(Reservation $reservation, float $amount, string $paymentPlan): ?array
    {
        $token = env('MERCADOPAGO_ACCESS_TOKEN');

        if (! $token) {
            return null;
        }

        $baseUrl = rtrim((string) env('APP_URL', ''), '/');
        $successUrl = env('PAYMENT_SUCCESS_URL', $baseUrl . '/payment/success');
        $failureUrl = env('PAYMENT_FAILURE_URL', $baseUrl . '/payment/failure');
        $pendingUrl = env('PAYMENT_PENDING_URL', $baseUrl . '/payment/pending');
        $webhookUrl = env('MERCADOPAGO_WEBHOOK_URL', $baseUrl . '/api/payments/mercadopago/webhook');

        $payload = [
            'items' => [[
                'title' => 'Reserva hotel #' . $reservation->id,
                'quantity' => 1,
                'currency_id' => 'MXN',
                'unit_price' => round($amount, 2),
            ]],
            'external_reference' => (string) $reservation->id,
            'back_urls' => [
                'success' => $successUrl,
                'failure' => $failureUrl,
                'pending' => $pendingUrl,
            ],
            'notification_url' => $webhookUrl,
            'auto_return' => 'approved',
            'metadata' => [
                'reservation_id' => $reservation->id,
                'payment_plan' => $paymentPlan,
            ],
        ];

        $response = Http::withToken($token)
            ->timeout(30)
            ->post('https://api.mercadopago.com/checkout/preferences', $payload);

        if (! $response->successful()) {
            Log::error('MercadoPago preference error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'reservation_id' => $reservation->id,
            ]);

            return null;
        }

        $json = $response->json();

        $forceSandbox = filter_var(env('MERCADOPAGO_FORCE_SANDBOX_INIT_POINT', false), FILTER_VALIDATE_BOOL);
        $preferredInitPoint = $forceSandbox
            ? (data_get($json, 'sandbox_init_point') ?: data_get($json, 'init_point'))
            : (data_get($json, 'init_point') ?: data_get($json, 'sandbox_init_point'));

        return [
            'id' => data_get($json, 'id'),
            'init_point' => $preferredInitPoint,
            'raw' => $json,
        ];
    }
}
