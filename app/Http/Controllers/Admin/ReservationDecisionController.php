<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReservationDecisionController extends Controller
{
    public function confirm(Reservation $reservation): RedirectResponse
    {
        $this->guardHotelScope($reservation);

        $reservation->status = 'confirmed';
        $reservation->save();

        $reservation->loadMissing(['room', 'hotel']);

        $hotel = $reservation->hotel;
        $room = $reservation->room;

        $checkIn = optional($reservation->check_in)?->format('Y-m-d') ?? (string) $reservation->check_in;
        $checkOut = optional($reservation->check_out)?->format('Y-m-d') ?? (string) $reservation->check_out;

        $locationLine = trim(implode(', ', array_filter([
            $hotel?->address_line,
            $hotel?->neighborhood,
            $hotel?->city,
            $hotel?->state,
            $hotel?->postal_code ? ('CP ' . $hotel->postal_code) : null,
        ])));

        $mapsUrl = ($hotel?->latitude && $hotel?->longitude)
            ? ('https://maps.google.com/?q=' . $hotel->latitude . ',' . $hotel->longitude)
            : null;

        $summary = "¡Reserva confirmada! ✅\n"
            . "Reserva #{$reservation->id}\n"
            . "Habitación: " . ($room?->nombre ?? 'Por confirmar') . "\n"
            . "Huéspedes: {$reservation->guests}\n"
            . "Fechas: {$checkIn} a {$checkOut}\n"
            . "Check-in: " . ($hotel?->check_in_time ?? 'Por confirmar') . " | Check-out: " . ($hotel?->check_out_time ?? 'Por confirmar');

        if ($locationLine !== '') {
            $summary .= "\nUbicación: {$locationLine}";
        }

        if ($mapsUrl) {
            $summary .= "\nMaps: {$mapsUrl}";
        }

        $summary .= "\n\nSi quieres, también te comparto servicios disponibles (spa, temazcal, yoga).";

        $this->notifyGuest($reservation, $summary);

        return redirect()->back()->with('status', 'Reserva confirmada y notificada al huésped.');
    }

    public function reject(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->guardHotelScope($reservation);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $reservation->status = 'availability_rejected';
        $reservation->save();

        $reason = trim((string) ($data['reason'] ?? ''));
        $suffix = $reason !== '' ? " Motivo: {$reason}" : '';

        $this->notifyGuest(
            $reservation,
            "Gracias por tu pago 🙏 Estamos revisando una alternativa porque esa opción no quedó disponible al cierre operativo. Te propongo opciones equivalentes de inmediato.{$suffix}"
        );

        return redirect()->back()->with('status', 'Reserva marcada como no disponible y huésped notificado.');
    }

    private function guardHotelScope(Reservation $reservation): void
    {
        if ((int) $reservation->hotel_id !== (int) (auth()->user()->hotel_id ?? 0)) {
            abort(403);
        }
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

        $token = (string) config('services.whatsapp.access_token', '');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $apiVersion = (string) config('services.whatsapp.api_version', 'v22.0');

        if ($token === '' || $phoneNumberId === '') {
            Log::warning('ReservationDecisionController notifyGuest missing WhatsApp config', [
                'reservation_id' => $reservation->id,
                'has_token' => $token !== '',
                'has_phone_number_id' => $phoneNumberId !== '',
            ]);
            return;
        }

        $response = Http::withToken($token)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);

        if (! $response->successful()) {
            Log::warning('ReservationDecisionController notifyGuest WhatsApp send failed', [
                'reservation_id' => $reservation->id,
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
