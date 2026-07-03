<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomInventoryDay;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $hotel = Hotel::query()->where('id', $this->hotelId())->firstOrFail();
        $rooms = Room::query()
            ->with('roomType')
            ->where('hotel_id', $hotel->id)
            ->orderBy('id')
            ->get();

        $roomTypes = $hotel->roomTypes()->orderBy('name')->get();
        $services = $hotel->services()->latest('id')->limit(20)->get();
        $packages = $hotel->packages()->latest('id')->limit(20)->get();
        $mediaAssets = $hotel->mediaAssets()->latest('id')->limit(60)->get();

        return view('admin.inventory', [
            'hotel' => $hotel,
            'rooms' => $rooms,
            'roomTypes' => $roomTypes,
            'services' => $services,
            'packages' => $packages,
            'mediaAssets' => $mediaAssets,
        ]);
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'inventario_total' => ['required', 'integer', 'min:0'],
        ]);

        $room->update([
            'inventario_total' => (int) $data['inventario_total'],
        ]);

        return redirect()->back()->with('status', 'Inventario actualizado.');
    }

    public function blockRange(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date'],
            'blocked_units' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $room = Room::query()->where('hotel_id', $this->hotelId())->findOrFail($data['room_id']);
        $start = Carbon::parse($data['fecha_inicio'])->startOfDay();
        $end = Carbon::parse($data['fecha_fin'])->startOfDay();

        if ($end->lt($start)) {
            return redirect()->back()->with('error', 'Rango de fechas inválido.');
        }

        $current = $start->copy();
        while ($current->lte($end)) {
            $inv = RoomInventoryDay::query()->firstOrCreate(
                [
                    'hotel_id' => $room->hotel_id,
                    'room_id' => $room->id,
                    'fecha' => $current->toDateString(),
                ],
                [
                    'total_units' => (int) $room->inventario_total,
                    'reserved_units' => 0,
                    'blocked_units' => 0,
                ]
            );

            $inv->update([
                'total_units' => (int) $room->inventario_total,
                'blocked_units' => (int) $data['blocked_units'],
                'note' => $data['note'] ?? $inv->note,
            ]);

            $current->addDay();
        }

        return redirect()->back()->with('status', 'Bloqueo aplicado.');
    }

    private function hotelId(): int
    {
        return (int) (auth()->user()->hotel_id ?? 1);
    }
}
