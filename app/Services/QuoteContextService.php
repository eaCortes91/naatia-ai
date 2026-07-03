<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Rate;
use App\Models\Room;

class QuoteContextService
{
    public function buildQuoteContext(Hotel $hotel, string $userMessage, array $reservationContext = []): array
    {
        $hotel->loadMissing([
            'rooms' => fn ($q) => $q->where('activo', true),
            'rates' => fn ($q) => $q
                ->where(function ($query) {
                    $today = now()->toDateString();

                    $query->whereNull('fecha_inicio')
                        ->orWhere('fecha_inicio', '<=', $today);
                })
                ->where(function ($query) {
                    $today = now()->toDateString();

                    $query->whereNull('fecha_fin')
                        ->orWhere('fecha_fin', '>=', $today);
                })
                ->with('room'),
        ]);

        $message = trim($userMessage);
        $guests = $this->detectGuests($message);

        $rooms = $hotel->rooms->values();
        $rates = $hotel->rates->values();
        $nightsBreakdown = $reservationContext['nights_breakdown'] ?? [];

        $preferredRoom = $this->detectPreferredRoom($rooms->all(), $message);
        $matchedRoom = $preferredRoom;

        if (! $matchedRoom) {
            $matchedRoom = $this->suggestRoomByCapacity($rooms->all(), $guests);
        }

        $nightlyPricing = $this->buildNightlyPricing($rates->all(), $matchedRoom, $nightsBreakdown);

        $availability = null;
        if ($matchedRoom && ! empty($reservationContext['check_in']) && ! empty($reservationContext['check_out'])) {
            $availability = app(AvailabilityService::class)->getAvailability(
                $hotel,
                $matchedRoom,
                (string) $reservationContext['check_in'],
                (string) $reservationContext['check_out']
            );
        }

        $quoteSummary = $this->buildQuoteSummary(
            guests: $guests,
            matchedRoom: $matchedRoom,
            reservationContext: $reservationContext,
            nightlyPricing: $nightlyPricing,
            availability: $availability
        );

        return [
            'guests' => $guests,
            'preferred_room' => $preferredRoom ? $this->roomPayload($preferredRoom) : null,
            'matched_room' => $matchedRoom ? $this->roomPayload($matchedRoom) : null,
            'matched_rate' => null,
            'nightly_pricing' => $nightlyPricing,
            'availability' => $availability,
            'quote_summary' => $quoteSummary,
        ];
    }

    private function detectGuests(string $text): ?int
    {
        if (preg_match('/\b(\d{1,2})\s*(?:personas?|huéspedes?|adultos?)\b/iu', $text, $m)) {
            $n = (int) $m[1];
            return $n > 0 ? $n : null;
        }

        $normalized = $this->normalizeText($text);
        $wordToNumber = [
            'una' => 1,
            'un' => 1,
            'uno' => 1,
            'dos' => 2,
            'tres' => 3,
            'cuatro' => 4,
            'cinco' => 5,
            'seis' => 6,
            'siete' => 7,
            'ocho' => 8,
            'nueve' => 9,
            'diez' => 10,
        ];

        foreach ($wordToNumber as $word => $number) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\s+(?:persona|personas|huesped|huespedes|adulto|adultos)\b/u', $normalized)) {
                return $number;
            }
        }

        return null;
    }

    private function detectPreferredRoom(array $rooms, string $text): ?Room
    {
        $normalizedText = $this->normalizeText($text);

        foreach ($rooms as $room) {
            if (! $room instanceof Room) {
                continue;
            }

            $roomName = $this->normalizeText((string) $room->nombre);
            if ($roomName !== '' && str_contains($normalizedText, $roomName)) {
                return $room;
            }
        }

        return null;
    }

    private function suggestRoomByCapacity(array $rooms, ?int $guests): ?Room
    {
        $validRooms = array_values(array_filter($rooms, fn ($r) => $r instanceof Room));

        usort($validRooms, fn (Room $a, Room $b) => (int) $a->capacidad <=> (int) $b->capacidad);

        if ($guests === null) {
            // Without guests we should not auto-pick a room yet.
            return null;
        }

        foreach ($validRooms as $room) {
            if ((int) $room->capacidad >= $guests) {
                return $room;
            }
        }

        return $validRooms[0] ?? null;
    }

    private function buildNightlyPricing(array $rates, ?Room $room, array $nightsBreakdown): array
    {
        $result = [
            'lines' => [],
            'total_estimated' => null,
            'currency' => 'MXN',
            'can_calculate_total' => false,
            'missing_reasons' => [],
        ];

        if (! $room) {
            $result['missing_reasons'][] = 'No se pudo confirmar habitación.';
            return $result;
        }

        if (empty($nightsBreakdown)) {
            $result['missing_reasons'][] = 'No se pudieron confirmar fechas completas.';
            return $result;
        }

        $total = 0.0;
        $allMatched = true;

        foreach ($nightsBreakdown as $night) {
            $type = $night['type'] ?? null;
            $date = $night['date'] ?? null;

            $rate = $this->findRateForNight($rates, $room->id, $type);

            if ($rate) {
                $price = (float) $rate->precio;
            } else {
                $price = $this->fallbackRoomRateForNight($room, $type);
            }

            if ($price === null) {
                $allMatched = false;
                $result['lines'][] = [
                    'date' => $date,
                    'type' => $type,
                    'price' => null,
                ];
                continue;
            }

            $total += $price;

            $result['lines'][] = [
                'date' => $date,
                'type' => $type,
                'price' => $price,
            ];
        }

        if ($allMatched) {
            $result['can_calculate_total'] = true;
            $result['total_estimated'] = $total;
        } else {
            $result['missing_reasons'][] = 'Falta al menos una tarifa por tipo de noche para calcular total exacto.';
        }

        return $result;
    }

    private function findRateForNight(array $rates, int $roomId, ?string $nightType): ?Rate
    {
        if (! $nightType) {
            return null;
        }

        $targetType = $nightType === 'fin_de_semana' ? 'fin de semana' : 'entre semana';

        foreach ($rates as $rate) {
            if (! $rate instanceof Rate) {
                continue;
            }

            if ((int) $rate->room_id !== $roomId) {
                continue;
            }

            if ($this->normalizeText((string) $rate->tipo_dia) === $this->normalizeText($targetType)) {
                return $rate;
            }
        }

        return null;
    }

    private function fallbackRoomRateForNight(Room $room, ?string $nightType): ?float
    {
        if (! $nightType) {
            return null;
        }

        if ($nightType === 'fin_de_semana') {
            return isset($room->weekend_rate) ? (float) $room->weekend_rate : null;
        }

        return isset($room->weekday_rate) ? (float) $room->weekday_rate : null;
    }

    private function buildQuoteSummary(
        ?int $guests,
        ?Room $matchedRoom,
        array $reservationContext,
        array $nightlyPricing,
        ?array $availability = null
    ): string {
        $checkIn = $reservationContext['check_in'] ?? null;
        $checkOut = $reservationContext['check_out'] ?? null;
        $nights = $reservationContext['nights'] ?? null;
        $weekdayNights = (int) ($reservationContext['weekday_nights'] ?? 0);
        $weekendNights = (int) ($reservationContext['weekend_nights'] ?? 0);

        $lines = [];
        $lines[] = 'Habitación: ' . ($matchedRoom?->nombre ?? 'No confirmada.');
        $lines[] = 'Huéspedes: ' . ($guests !== null ? (string) $guests : 'No confirmados.');

        if ($checkIn && $checkOut) {
            $lines[] = "Fechas: {$checkIn} a {$checkOut}.";
        } else {
            $lines[] = 'Fechas: No confirmadas.';
        }

        $lines[] = 'Noches: ' . ($nights !== null ? (string) $nights : 'No confirmadas.');
        $lines[] = "Desglose de noches: {$weekdayNights} entre semana, {$weekendNights} fin de semana.";

        if (($nightlyPricing['can_calculate_total'] ?? false) === true) {
            $total = number_format((float) $nightlyPricing['total_estimated'], 2, '.', '');
            $lines[] = "Total estimado: {$total} MXN.";
        } else {
            $reason = $nightlyPricing['missing_reasons'][0] ?? 'No hay información suficiente para calcular total exacto.';
            $lines[] = "Total estimado: No disponible ({$reason})";
        }

        if (is_array($availability)) {
            $lines[] = 'Disponibilidad preliminar: ' . (($availability['is_available'] ?? false) ? 'Sí' : 'No');
        }

        return 'Datos confirmados: ' . implode(' ', $lines);
    }

    private function roomPayload(Room $room): array
    {
        return [
            'id' => $room->id,
            'nombre' => $room->nombre,
            'capacidad' => (int) $room->capacidad,
            'descripcion' => $room->descripcion,
        ];
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $value);

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}
