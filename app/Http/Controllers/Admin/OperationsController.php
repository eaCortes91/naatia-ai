<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(): View
    {
        $hotel = Hotel::query()->findOrFail($this->hotelId());

        $base = Reservation::query()->where('hotel_id', $this->hotelId());

        $kpis = [
            'total' => (clone $base)->count(),
            'quoted' => (clone $base)->where('status', 'quoted')->count(),
            'awaiting_payment' => (clone $base)->whereIn('status', ['awaiting_online_payment', 'awaiting_card_payment', 'pending_transfer_proof'])->count(),
            'paid' => (clone $base)->where('status', 'paid_pending_availability_check')->count(),
            'expired' => (clone $base)->where('status', 'hold_expired')->count(),
        ];

        $recentReservations = Reservation::query()
            ->with(['contact', 'room'])
            ->where('hotel_id', $this->hotelId())
            ->latest('id')
            ->limit(20)
            ->get();

        $recentPayments = PaymentAttempt::query()
            ->whereHas('reservation', fn ($q) => $q->where('hotel_id', $this->hotelId()))
            ->with('reservation.contact')
            ->latest('id')
            ->limit(20)
            ->get();

        $requiredProfile = [
            'address_line' => 'Dirección',
            'city' => 'Ciudad',
            'state' => 'Estado',
            'postal_code' => 'CP',
            'latitude' => 'Latitud',
            'longitude' => 'Longitud',
            'check_in_time' => 'Check-in',
            'check_out_time' => 'Check-out',
            'amenities_text' => 'Amenidades',
        ];

        $missingProfile = [];
        foreach ($requiredProfile as $key => $label) {
            $value = $hotel->{$key};
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missingProfile[] = $label;
            }
        }

        $mapsUrl = ($hotel->latitude && $hotel->longitude)
            ? ('https://maps.google.com/?q=' . $hotel->latitude . ',' . $hotel->longitude)
            : null;

        $quickReplies = [
            'ubicacion' => trim("📍 Estamos en " . implode(', ', array_filter([
                $hotel->address_line,
                $hotel->neighborhood,
                $hotel->city,
                $hotel->state,
                $hotel->postal_code ? ('CP ' . $hotel->postal_code) : null,
            ])) . ($mapsUrl ? ("\nGoogle Maps: " . $mapsUrl) : '')),
            'horarios' => trim(implode(' | ', array_filter([
                $hotel->check_in_time ? ('Check-in: ' . $hotel->check_in_time) : null,
                $hotel->check_out_time ? ('Check-out: ' . $hotel->check_out_time) : null,
            ]))),
            'amenidades' => $hotel->amenities_text ? ('Amenidades: ' . $hotel->amenities_text) : 'Amenidades no cargadas aún.',
            'pets' => ($hotel->pet_friendly ? 'Sí somos pet friendly 🐾' : 'Por ahora no somos pet friendly.'),
        ];

        return view('admin.operations', compact('kpis', 'recentReservations', 'recentPayments', 'hotel', 'missingProfile', 'quickReplies'));
    }

    private function hotelId(): int
    {
        return (int) (auth()->user()->hotel_id ?? 1);
    }
}
