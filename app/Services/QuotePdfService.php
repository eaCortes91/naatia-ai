<?php

namespace App\Services;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class QuotePdfService
{
    public function generateReservationQuotePdf(Reservation $reservation): ?array
    {
        $reservation->loadMissing(['hotel', 'room', 'contact']);

        $hotel = $reservation->hotel;
        $room = $reservation->room;

        if (! $hotel || ! $room) {
            return null;
        }

        $html = view('pdf.quote', [
            'reservation' => $reservation,
            'hotel' => $hotel,
            'room' => $room,
            'contact' => $reservation->contact,
            'generatedAt' => now(),
        ])->render();

        $filename = 'cotizacion-reserva-' . $reservation->id . '-' . now()->format('YmdHis') . '.pdf';
        $relativePath = 'quotes/' . $filename;

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        Storage::disk('public')->put($relativePath, $pdf->output());

        $url = rtrim((string) config('app.url'), '/') . '/storage/' . $relativePath;

        return [
            'url' => $url,
            'filename' => $filename,
        ];
    }
}
