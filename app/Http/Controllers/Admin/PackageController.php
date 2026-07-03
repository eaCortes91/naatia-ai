<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        HotelPackage::query()->create([
            'hotel_id' => (int) (auth()->user()->hotel_id ?? 1),
            'name' => $data['name'],
            'color' => $data['color'],
            'description' => $data['description'] ?? null,
            'price' => (float) $data['price'],
            'active' => true,
        ]);

        return redirect()->back()->with('status', 'Paquete creado.');
    }

    public function update(Request $request, HotelPackage $package): RedirectResponse
    {
        if ((int) $package->hotel_id !== (int) (auth()->user()->hotel_id ?? 0)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $package->update([
            'name' => $data['name'],
            'color' => $data['color'],
            'description' => $data['description'] ?? null,
            'price' => (float) $data['price'],
            'active' => $request->has('active'),
        ]);

        return redirect()->back()->with('status', 'Paquete actualizado.');
    }

    public function destroy(HotelPackage $package): RedirectResponse
    {
        if ((int) $package->hotel_id !== (int) (auth()->user()->hotel_id ?? 0)) {
            abort(403);
        }

        $package->delete();

        return redirect()->back()->with('status', 'Paquete eliminado.');
    }
}
