<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripePaymentService
{
    public function createCheckoutLink(Reservation $reservation, float $amount, string $paymentPlan): ?array
    {
        $secretKey = trim((string) config('services.stripe.secret_key', ''));
        if ($secretKey === '') {
            return null;
        }

        $currency = strtolower((string) config('services.stripe.currency', 'mxn'));
        $baseUrl = rtrim((string) env('APP_URL', ''), '/');

        if ($baseUrl === '') {
            $baseUrl = 'https://naatia.com';
        }

        $unitAmount = (int) round($amount * 100);
        $label = $paymentPlan === 'deposit' ? 'Anticipo' : 'Pago total';

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->timeout(25)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $baseUrl . '/payment/success?session_id={CHECKOUT_SESSION_ID}&reservation=' . $reservation->id,
                'cancel_url' => $baseUrl . '/payment/failure?reservation=' . $reservation->id,
                'payment_method_types[]' => 'card',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => $unitAmount,
                'line_items[0][price_data][product_data][name]' => $label . ' - Reservación #' . $reservation->id,
                'metadata[reservation_id]' => (string) $reservation->id,
                'metadata[payment_plan]' => $paymentPlan,
            ]);

        if (! $response->successful()) {
            Log::error('Stripe checkout session creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'reservation_id' => $reservation->id,
            ]);

            return null;
        }

        $json = $response->json();
        $url = (string) data_get($json, 'url', '');

        if ($url === '') {
            return null;
        }

        return [
            'id' => (string) data_get($json, 'id', ''),
            'url' => $url,
            'raw' => $json,
        ];
    }
}
