<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        HotelService::query()->create([
            'hotel_id' => (int) (auth()->user()->hotel_id ?? 1),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => (float) $data['price'],
            'active' => true,
        ]);

        return redirect()->back()->with('status', 'Servicio creado.');
    }

    public function update(Request $request, HotelService $service): RedirectResponse
    {
        if ((int) $service->hotel_id !== (int) (auth()->user()->hotel_id ?? 0)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $service->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => (float) $data['price'],
            'active' => $request->has('active'),
        ]);

        return redirect()->back()->with('status', 'Servicio actualizado.');
    }

    public function destroy(HotelService $service): RedirectResponse
    {
        if ((int) $service->hotel_id !== (int) (auth()->user()->hotel_id ?? 0)) {
            abort(403);
        }

        $service->delete();

        return redirect()->back()->with('status', 'Servicio eliminado.');
    }
}
