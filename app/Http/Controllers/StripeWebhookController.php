<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Models\ReceptionistAlert;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = trim((string) config('services.stripe.webhook_secret', ''));

        if ($secret === '' || ! $this->isValidSignature($payload, $signature, $secret)) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        $event = $request->json()->all();
        $eventType = (string) data_get($event, 'type', '');

        if (! in_array($eventType, ['checkout.session.completed', 'checkout.session.expired'], true)) {
            return response()->json(['status' => 'ignored']);
        }

        $sessionId = (string) data_get($event, 'data.object.id', '');
        $reservationId = (int) data_get($event, 'data.object.metadata.reservation_id', 0);

        if ($sessionId === '') {
            return response()->json(['status' => 'ignored']);
        }

        $payment = PaymentAttempt::query()
            ->where('provider', 'stripe')
            ->where('provider_ref', $sessionId)
            ->latest('id')
            ->first();

        if (! $payment && $reservationId > 0) {
            $payment = PaymentAttempt::query()
                ->where('provider', 'stripe')
                ->where('reservation_id', $reservationId)
                ->where('status', 'pending')
                ->latest('id')
                ->first();
        }

        if (! $payment) {
            Log::warning('Stripe webhook payment attempt not found', [
                'session_id' => $sessionId,
                'reservation_id' => $reservationId,
                'event_type' => $eventType,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        $payloadJson = $payment->payload_json;
        if (! is_array($payloadJson)) {
            $payloadJson = [];
        }

        $payloadJson['stripe_event'] = $event;

        if ($eventType === 'checkout.session.completed') {
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->payload_json = $payloadJson;
            $payment->save();

            $reservation = Reservation::query()->find($payment->reservation_id);
            if ($reservation) {
                $reservation->status = 'paid_pending_availability_check';
                $reservation->hold_expires_at = null;
                $reservation->save();

                ReceptionistAlert::query()->firstOrCreate([
                    'hotel_id' => $reservation->hotel_id,
                    'reservation_id' => $reservation->id,
                    'type' => 'payment_received',
                    'status' => 'pending',
                ], [
                    'title' => 'Pago recibido por Stripe',
                    'body' => "Reserva #{$reservation->id}: pago recibido. Confirmar disponibilidad final.",
                ]);

                $this->sendGuestPaymentConfirmationWhatsApp($reservation);
            }
        } else {
            $payment->status = 'expired';
            $payment->payload_json = $payloadJson;
            $payment->save();
        }

        return response()->json(['status' => 'ok']);
    }

    private function sendGuestPaymentConfirmationWhatsApp(Reservation $reservation): void
    {
        $reservation->loadMissing(['contact', 'room']);

        $to = (string) ($reservation->contact?->telefono ?? '');
        if ($to === '') {
            return;
        }

        if (str_starts_with($to, '521')) {
            $to = '52' . substr($to, 3);
        }

        $roomName = (string) ($reservation->room?->nombre ?? 'tu habitación');
        $checkIn = $reservation->check_in ? $reservation->check_in->format('d/m/Y') : 'N/D';
        $checkOut = $reservation->check_out ? $reservation->check_out->format('d/m/Y') : 'N/D';

        $text = "Pago recibido ✅ Tu pago fue confirmado para la reserva #{$reservation->id}.\nHabitación: {$roomName}\nFechas: {$checkIn} al {$checkOut}.\n\nEn breve recepción valida disponibilidad final y te confirma por este medio.";

        $token = (string) config('services.whatsapp.access_token', '');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $apiVersion = (string) config('services.whatsapp.api_version', 'v22.0');

        if ($token === '' || $phoneNumberId === '') {
            return;
        }

        Http::withToken($token)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);
    }

    private function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($k && $v) {
                $parts[$k] = $v;
            }
        }

        $timestamp = $parts['t'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (! $timestamp || ! $v1) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $v1);
    }
}
