<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomInventoryDay;
use Carbon\Carbon;

class AvailabilityService
{
    public function getAvailability(Hotel $hotel, Room $room, string $checkIn, string $checkOut): array
    {
        $start = Carbon::parse($checkIn)->startOfDay();
        $end = Carbon::parse($checkOut)->startOfDay();

        if ($end->lte($start)) {
            return [
                'is_available' => false,
                'reason' => 'Rango de fechas inválido.',
                'nights' => [],
            ];
        }

        $nights = [];
        $isAvailable = true;

        $current = $start->copy();
        while ($current->lt($end)) {
            $inv = RoomInventoryDay::query()->firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'room_id' => $room->id,
                    'fecha' => $current->toDateString(),
                ],
                [
                    'total_units' => (int) $room->inventario_total,
                    'reserved_units' => 0,
                    'blocked_units' => 0,
                ]
            );

            $freeUnits = max(0, (int) $inv->total_units - (int) $inv->reserved_units - (int) $inv->blocked_units);
            if ($freeUnits < 1) {
                $isAvailable = false;
            }

            $nights[] = [
                'date' => $current->toDateString(),
                'free_units' => $freeUnits,
                'total_units' => (int) $inv->total_units,
            ];

            $current->addDay();
        }

        return [
            'is_available' => $isAvailable,
            'reason' => $isAvailable ? null : 'Sin inventario disponible en al menos una noche.',
            'nights' => $nights,
        ];
    }
}
