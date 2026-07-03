<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'color' => ['required', 'string', 'max:20'],
        ]);

        RoomType::query()->create([
            'hotel_id' => $this->hotelId(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
            'active' => true,
        ]);

        return redirect()->back()->with('status', 'Tipo de habitación creado.');
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'color' => ['required', 'string', 'max:20'],
            'active' => ['nullable', 'boolean'],
        ]);

        abort_unless($roomType->hotel_id === $this->hotelId(), 403);

        $roomType->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
            'active' => $request->has('active'),
        ]);

        return redirect()->back()->with('status', 'Tipo de habitación actualizado.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        abort_unless($roomType->hotel_id === $this->hotelId(), 403);

        if ($roomType->rooms()->exists()) {
            return redirect()->back()->with('error', 'No se puede eliminar: este tipo tiene habitaciones asignadas.');
        }

        $roomType->delete();

        return redirect()->back()->with('status', 'Tipo de habitación eliminado.');
    }

    private function hotelId(): int
    {
        return (int) (auth()->user()->hotel_id ?? 1);
    }
}
