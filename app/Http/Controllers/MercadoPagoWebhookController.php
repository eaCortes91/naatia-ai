<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        if (! $this->isAuthorizedWebhook($request)) {
            Log::warning('MercadoPago webhook authorization failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'forbidden'], 403);
        }

        $payload = $request->all();
        Log::info('MercadoPago webhook payload', $payload);

        $token = env('MERCADOPAGO_ACCESS_TOKEN');
        if (! $token) {
            return response()->json(['status' => 'missing_token'], 500);
        }

        $topic = (string) (data_get($payload, 'topic') ?? data_get($payload, 'type') ?? '');
        $paymentId = data_get($payload, 'data.id') ?? data_get($payload, 'id') ?? $request->query('data_id');

        if ($topic === 'merchant_order') {
            $merchantOrderId = data_get($payload, 'id');
            if (! $merchantOrderId) {
                $merchantOrderId = $this->extractIdFromResource((string) data_get($payload, 'resource'));
            }

            if ($merchantOrderId) {
                $merchantOrder = Http::withToken($token)
                    ->timeout(30)
                    ->get('https://api.mercadopago.com/merchant_orders/' . $merchantOrderId);

                if ($merchantOrder->successful()) {
                    $paymentId = data_get($merchantOrder->json(), 'payments.0.id')
                        ?? data_get($merchantOrder->json(), 'payments.1.id')
                        ?? $paymentId;
                }
            }
        }

        if (! $paymentId) {
            return response()->json(['status' => 'ignored']);
        }

        $response = Http::withToken($token)
            ->timeout(30)
            ->get('https://api.mercadopago.com/v1/payments/' . $paymentId);

        if (! $response->successful()) {
            Log::error('MercadoPago payment fetch failed', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
                'body' => $response->body(),
                'topic' => $topic,
            ]);

            return response()->json(['status' => 'fetch_failed'], 500);
        }

        $payment = $response->json();
        $reservationId = (int) (data_get($payment, 'external_reference') ?: data_get($payment, 'metadata.reservation_id'));
        $status = (string) data_get($payment, 'status');

        if (! $reservationId) {
            return response()->json(['status' => 'ignored']);
        }

        $reservation = Reservation::query()->find($reservationId);
        if (! $reservation) {
            return response()->json(['status' => 'ignored']);
        }

        $attempt = PaymentAttempt::query()
            ->where('reservation_id', $reservation->id)
            ->where('provider', 'mercadopago')
            ->latest('id')
            ->first();

        $normalizedStatus = $status === 'approved' ? 'paid' : $status;

        $isDuplicateStatus = false;
        if ($attempt) {
            $isSamePaymentRef = (string) ($attempt->provider_ref ?? '') === (string) $paymentId;
            $isSameStatus = (string) ($attempt->status ?? '') === $normalizedStatus;
            $isDuplicateStatus = $isSamePaymentRef && $isSameStatus;

            $attempt->provider_ref = (string) $paymentId;
            $attempt->payload_json = $payment;
            $attempt->status = $normalizedStatus;
            if ($status === 'approved' && ! $attempt->paid_at) {
                $attempt->paid_at = now();
            }
            $attempt->save();
        }

        if ($status === 'approved') {
            $alreadyMarkedPaid = $reservation->status === 'paid_pending_availability_check';

            if (! $alreadyMarkedPaid) {
                $reservation->status = 'paid_pending_availability_check';
                $reservation->confirmed_at = now();
                $reservation->save();
            }

            if (! $isDuplicateStatus && ! $alreadyMarkedPaid) {
                $this->notifyGuest($reservation, '¡Pago recibido! ✅ Ya registramos tu pago. En breve te confirmamos disponibilidad final por este medio.');
            }
        } elseif (in_array($status, ['pending', 'in_process'], true)) {
            if (! $isDuplicateStatus) {
                $this->notifyGuest($reservation, 'Tu pago quedó pendiente. En cuanto Mercado Pago lo confirme, te avisamos por aquí.');
            }
        } elseif (in_array($status, ['rejected', 'cancelled'], true)) {
            if (! $isDuplicateStatus) {
                $this->notifyGuest($reservation, 'Tu pago no se pudo completar. Si quieres, te genero un nuevo link ahora mismo 🙌');
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function extractIdFromResource(?string $resource): ?string
    {
        if (! $resource) {
            return null;
        }

        $parts = explode('/', trim($resource));

        return end($parts) ?: null;
    }

    private function notifyGuest(Reservation $reservation, string $text): void
    {
        $contact = Contact::query()->find($reservation->contact_id);
        $to = $contact?->telefono ? (string) $contact->telefono : null;

        if (! $to) {
            return;
        }

        if (str_starts_with($to, '521')) {
            $to = '52' . substr($to, 3);
        }

        Http::withToken(env('WHATSAPP_ACCESS_TOKEN'))
            ->post('https://graph.facebook.com/v22.0/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);
    }

    private function isAuthorizedWebhook(Request $request): bool
    {
        $token = trim((string) env('MERCADOPAGO_WEBHOOK_TOKEN', ''));
        if ($token !== '') {
            $provided = (string) ($request->query('token') ?? $request->header('X-Webhook-Token') ?? '');

            if (! hash_equals($token, $provided)) {
                return false;
            }
        }

        $signatureSecret = trim((string) env('MERCADOPAGO_WEBHOOK_SECRET', ''));
        if ($signatureSecret === '') {
            return true;
        }

        $xSignature = (string) $request->header('x-signature', '');
        $xRequestId = (string) $request->header('x-request-id', '');

        if ($xSignature === '' || $xRequestId === '') {
            return false;
        }

        parse_str(str_replace(',', '&', $xSignature), $parts);
        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (! $ts || ! $v1) {
            return false;
        }

        $dataId = (string) (data_get($request->all(), 'data.id') ?? data_get($request->all(), 'id') ?? '');

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $hash = hash_hmac('sha256', $manifest, $signatureSecret);

        return hash_equals($hash, (string) $v1);
    }
}
