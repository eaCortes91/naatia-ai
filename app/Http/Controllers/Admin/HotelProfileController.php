<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotelProfileController extends Controller
{
    public function edit(): View
    {
        $hotel = Hotel::query()->findOrFail((int) (auth()->user()->hotel_id ?? 1));

        return view('admin.hotel-profile', [
            'hotel' => $hotel,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $hotel = Hotel::query()->findOrFail((int) (auth()->user()->hotel_id ?? 1));

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:180'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_in_time' => ['nullable', 'string', 'max:20'],
            'check_out_time' => ['nullable', 'string', 'max:20'],
            'amenities_text' => ['nullable', 'string'],
            'policies_text' => ['nullable', 'string'],
            'saludo_base' => ['nullable', 'string'],
            'prompt_base' => ['nullable', 'string'],
        ]);

        $data['pet_friendly'] = $request->has('pet_friendly');

        $hotel->update($data);

        return redirect()->back()->with('status', 'Ficha del hotel actualizada.');
    }
}
