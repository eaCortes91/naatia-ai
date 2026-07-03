<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReceptionistAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReceptionistAlertController extends Controller
{
    public function index(): View
    {
        $alerts = ReceptionistAlert::query()
            ->where('hotel_id', $this->hotelId())
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('admin.alerts', ['alerts' => $alerts]);
    }

    public function resolve(ReceptionistAlert $alert): RedirectResponse
    {
        abort_unless($alert->hotel_id === $this->hotelId(), 403);

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Alerta marcada como resuelta.');
    }

    private function hotelId(): int
    {
        return (int) (auth()->user()->hotel_id ?? 1);
    }
}
