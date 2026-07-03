<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelPackage;
use App\Models\HotelService;
use App\Models\MediaAsset;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MediaAssetController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $hotelId = (int) (auth()->user()->hotel_id ?? 1);

        $data = $request->validate([
            'entity_type' => ['required', 'in:hotel,room,package,service'],
            'room_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:6144'],
        ]);

        $entityType = (string) $data['entity_type'];
        $entityId = null;

        if ($entityType === 'room') {
            $entityId = (int) ($data['room_id'] ?? 0);
            $room = Room::query()->where('hotel_id', $hotelId)->find($entityId);
            if (! $room) {
                return redirect()->back()->with('error', 'La habitación seleccionada no es válida.');
            }
        }

        if ($entityType === 'package') {
            $entityId = (int) ($data['package_id'] ?? 0);
            $package = HotelPackage::query()->where('hotel_id', $hotelId)->find($entityId);
            if (! $package) {
                return redirect()->back()->with('error', 'El paquete seleccionado no es válido.');
            }
        }

        if ($entityType === 'service') {
            $entityId = (int) ($data['service_id'] ?? 0);
            $service = HotelService::query()->where('hotel_id', $hotelId)->find($entityId);
            if (! $service) {
                return redirect()->back()->with('error', 'El servicio seleccionado no es válido.');
            }
        }

        $path = $request->file('image')->store('media-assets', 'public');
        $url = rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($path, '/');

        MediaAsset::query()->create([
            'hotel_id' => $hotelId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'url' => $url,
            'caption' => $data['caption'] ?? null,
            'active' => true,
        ]);

        return redirect()->back()->with('status', 'Imagen subida correctamente.');
    }

    public function destroy(MediaAsset $mediaAsset): RedirectResponse
    {
        $hotelId = (int) (auth()->user()->hotel_id ?? 1);
        abort_unless((int) $mediaAsset->hotel_id === $hotelId, 403);

        $mediaAsset->delete();

        return redirect()->back()->with('status', 'Imagen eliminada.');
    }
}
