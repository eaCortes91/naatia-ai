<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppReplyJob;
use App\Models\BotFollowUp;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $hubMode = $request->query('hub.mode', $request->query('hub_mode'));
        $hubVerifyToken = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $hubChallenge = $request->query('hub.challenge', $request->query('hub_challenge'));

        if ($hubMode === 'subscribe' && hash_equals((string) env('WHATSAPP_VERIFY_TOKEN', ''), (string) $hubVerifyToken)) {
            return response((string) $hubChallenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        if (! $this->isValidSignature($request)) {
            Log::warning('WhatsApp webhook signature validation failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'forbidden'], 403);
        }

        $payload = $request->json()->all();

        Log::info('WhatsApp Cloud API webhook payload', $payload);

        $messageData = null;

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $messages = data_get($change, 'value.messages', []);

                if (is_array($messages) && ! empty($messages)) {
                    $messageData = $messages[0];
                    break 2;
                }
            }
        }

        if (! is_array($messageData)) {
            return response()->json(['status' => 'ignored']);
        }

        $senderPhone = $this->normalizePhone((string) ($messageData['from'] ?? ''));
        $messageType = (string) ($messageData['type'] ?? 'text');
        $messageBody = $messageData['text']['body'] ?? null;

        if ($messageType === 'interactive') {
            $buttonId = (string) data_get($messageData, 'interactive.button_reply.id', '');
            $buttonTitle = (string) data_get($messageData, 'interactive.button_reply.title', '');

            if (str_starts_with($buttonId, 'pkg_add_yes_')) {
                $packageId = (int) str_replace('pkg_add_yes_', '', $buttonId);
                $messageBody = $packageId > 0
                    ? ('si, quiero agregar el paquete package_id:' . $packageId)
                    : 'si, quiero agregar el paquete';
            } else {
                $messageBody = match ($buttonId) {
                    'pkg_add_yes' => 'si, quiero agregar el paquete',
                    'pkg_add_no_room' => 'no, quiero reservar solo habitacion',
                    default => ($buttonTitle !== '' ? $buttonTitle : null),
                };
            }
        }

        $externalId = $messageData['id'] ?? null;

        if (! $senderPhone || ! $messageBody || ! $externalId) {
            return response()->json(['status' => 'ignored']);
        }

        $contact = Contact::query()
            ->where('telefono', $senderPhone)
            ->first();

        if (! $contact) {
            $defaultHotel = Hotel::query()
                ->where('id', 1)
                ->where('activo', true)
                ->first();

            if (! $defaultHotel) {
                $defaultHotel = Hotel::query()->where('activo', true)->orderBy('id')->first();
            }

            if (! $defaultHotel) {
                return response()->json(['status' => 'ignored']);
            }

            $contact = Contact::query()->create([
                'hotel_id' => $defaultHotel->id,
                'telefono' => $senderPhone,
                'nombre' => 'Contacto WhatsApp',
                'status' => 'nuevo',
            ]);
        }

        $hotel = Hotel::query()
            ->where('id', (int) $contact->hotel_id)
            ->where('activo', true)
            ->first();

        if (! $hotel) {
            return response()->json(['status' => 'ignored']);
        }

        $conversation = Conversation::query()
            ->where('hotel_id', $hotel->id)
            ->where('contact_id', $contact->id)
            ->where('canal', 'whatsapp')
            ->latest('id')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::query()->create([
                'hotel_id' => $hotel->id,
                'contact_id' => $contact->id,
                'canal' => 'whatsapp',
                'estado' => 'bot',
            ]);
        }

        $message = new Message();
        $message->conversation_id = $conversation->id;
        $message->sender_type = 'user';
        $message->body = $messageBody;
        $message->external_id = $externalId;
        $message->message_type = 'text';
        $message->raw_payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $message->save();

        BotFollowUp::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now()]);

        $replyDelaySeconds = (int) config('services.whatsapp.reply_delay_seconds', 4);
        if ($replyDelaySeconds < 0) {
            $replyDelaySeconds = 0;
        }

        ProcessWhatsAppReplyJob::dispatch($conversation->id)
            ->delay(now()->addSeconds($replyDelaySeconds));

        Log::info('ProcessWhatsAppReplyJob dispatched', [
            'conversation_id' => $conversation->id,
            'delay_seconds' => $replyDelaySeconds,
        ]);

        $now = now();

        $contact->last_interaction_at = $now;
        $contact->save();

        $conversation->ultimo_mensaje_at = $now;
        $conversation->save();

        return response()->json(['status' => 'ok']);
    }

    private function normalizePhone(string $rawPhone): string
    {
        return preg_replace('/\D+/', '', $rawPhone) ?? '';
    }

    private function isValidSignature(Request $request): bool
    {
        $appSecret = trim((string) env('WHATSAPP_APP_SECRET', ''));
        if ($appSecret === '') {
            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $raw = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $raw, $appSecret);

        return hash_equals($expected, $signature);
    }
}
