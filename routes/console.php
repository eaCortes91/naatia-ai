<?php

use App\Models\BotFollowUp;
use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:expire-holds', function () {
    $expired = Reservation::query()
        ->whereIn('status', ['awaiting_online_payment', 'awaiting_card_payment', 'pending_transfer_proof', 'pending_human_confirmation'])
        ->whereNotNull('hold_expires_at')
        ->where('hold_expires_at', '<', now())
        ->get();

    $count = 0;

    foreach ($expired as $reservation) {
        $reservation->status = 'hold_expired';
        $reservation->save();

        $contact = Contact::query()->find($reservation->contact_id);
        $to = $contact?->telefono ? (string) $contact->telefono : null;

        if ($to) {
            if (str_starts_with($to, '521')) {
                $to = '52' . substr($to, 3);
            }

            $text = "Tu pre-reserva #{$reservation->id} expiró por tiempo de pago. Si gustas, te genero un nuevo link ahora mismo 🙌";

            Http::withToken(env('WHATSAPP_ACCESS_TOKEN'))
                ->post('https://graph.facebook.com/v22.0/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);
        }

        $count++;
    }

    $this->info("Reservas expiradas: {$count}");
})->purpose('Expire reservation holds and notify guest');

Artisan::command('followups:send-due', function () {
    $due = BotFollowUp::query()
        ->whereNull('sent_at')
        ->whereNull('cancelled_at')
        ->where('scheduled_at', '<=', now())
        ->whereIn('type', ['package_nudge', 'reengagement_nudge'])
        ->with(['conversation.contact', 'conversation.hotel'])
        ->limit(50)
        ->get();

    $sent = 0;

    foreach ($due as $item) {
        $conversation = $item->conversation;
        $contact = $conversation?->contact;

        if (! $conversation || ! $contact) {
            $item->cancelled_at = now();
            $item->save();
            continue;
        }

        $to = (string) ($contact->telefono ?? '');
        if ($to === '') {
            $item->cancelled_at = now();
            $item->save();
            continue;
        }

        if (str_starts_with($to, '521')) {
            $to = '52' . substr($to, 3);
        }

        if ((int) now()->format('H') >= 21 || (int) now()->format('H') < 8) {
            $item->scheduled_at = now()->addDay()->setTime(9, 0, 0);
            $item->save();
            continue;
        }

        if ($item->type === 'reengagement_nudge') {
            $text = (string) data_get($item->payload_json, 'message', 'Hola 🙌 Solo te doy seguimiento por aquí. Si quieres, te comparto opciones y cotización para cerrar tu reserva.');
        } else {
            $packageName = data_get($item->payload_json, 'package_name', 'nuestro paquete especial');
            $packagePrice = (float) data_get($item->payload_json, 'package_price', 0);
            $priceText = number_format($packagePrice, 2);

            $text = "Hola 🙌 Solo para darte seguimiento: tenemos un paquete que puede ajustarse a tu estancia ({$packageName} desde {$priceText} MXN). Si quieres, te paso detalles y cerramos tu reserva.";
        }

        $resp = Http::withToken(env('WHATSAPP_ACCESS_TOKEN'))
            ->post('https://graph.facebook.com/v22.0/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);

        if ($resp->successful()) {
            $item->sent_at = now();
            $item->save();
            $sent++;
        }
    }

    $this->info("Followups enviados: {$sent}");
})->purpose('Send due package follow-ups');
