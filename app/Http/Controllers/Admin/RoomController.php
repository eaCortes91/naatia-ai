<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRoom($request);

        Room::query()->create([
            'hotel_id' => $this->hotelId(),
            'room_type_id' => $data['room_type_id'] ?? null,
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? '',
            'capacidad' => (int) $data['capacidad'],
            'inventario_total' => (int) $data['inventario_total'],
            'weekday_rate' => (float) $data['weekday_rate'],
            'weekend_rate' => (float) $data['weekend_rate'],
            'base_status' => $data['base_status'] ?? 'libre',
            'activo' => $request->boolean('activo', true),
        ]);

        return redirect()->back()->with('status', 'Habitación creada.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        abort_unless($room->hotel_id === $this->hotelId(), 403);

        $data = $this->validateRoom($request);

        $room->update([
            'room_type_id' => $data['room_type_id'] ?? null,
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? '',
            'capacidad' => (int) $data['capacidad'],
            'inventario_total' => (int) $data['inventario_total'],
            'weekday_rate' => (float) $data['weekday_rate'],
            'weekend_rate' => (float) $data['weekend_rate'],
            'base_status' => $data['base_status'] ?? 'libre',
            'activo' => $request->has('activo'),
        ]);

        return redirect()->back()->with('status', 'Habitación actualizada.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        abort_unless($room->hotel_id === $this->hotelId(), 403);

        $room->delete();

        return redirect()->back()->with('status', 'Habitación eliminada.');
    }

    private function validateRoom(Request $request): array
    {
        return $request->validate([
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'nombre' => ['required', 'string', 'max:120'],
            'capacidad' => ['required', 'integer', 'min:1'],
            'inventario_total' => ['required', 'integer', 'min:1'],
            'weekday_rate' => ['required', 'numeric', 'min:0'],
            'weekend_rate' => ['required', 'numeric', 'min:0'],
            'base_status' => ['nullable', 'string', 'max:30'],
            'activo' => ['nullable', 'boolean'],
            'descripcion' => ['nullable', 'string'],
        ]);
    }

    private function hotelId(): int
    {
        return (int) (auth()->user()->hotel_id ?? 1);
    }
}
