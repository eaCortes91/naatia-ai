<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $hotelId = (int) (auth()->user()->hotel_id ?? 1);
        [$start, $end] = $this->resolveRange($request);

        $totalRevenue = PaymentAttempt::query()
            ->whereHas('reservation', fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $paymentsCount = PaymentAttempt::query()
            ->whereHas('reservation', fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $reservationsTotal = Reservation::query()->where('hotel_id', $hotelId)->whereBetween('created_at', [$start, $end])->count();
        $reservationsConfirmed = Reservation::query()->where('hotel_id', $hotelId)->where('status', 'confirmed')->whereBetween('created_at', [$start, $end])->count();

        $dailyPayments = PaymentAttempt::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END),0) as total')
            ->whereHas('reservation', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->pluck('total', 'day');

        $dailyReservations = Reservation::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('hotel_id', $hotelId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->pluck('total', 'day');

        $days = $start->diffInDays($end) + 1;
        $labels = collect(range(0, max(0, $days - 1)))
            ->map(fn ($d) => $start->copy()->addDays($d)->toDateString())
            ->values();

        $paymentsSeries = $labels->map(fn ($day) => (float) ($dailyPayments[$day] ?? 0))->values();
        $reservationsSeries = $labels->map(fn ($day) => (int) ($dailyReservations[$day] ?? 0))->values();

        $statusBreakdown = Reservation::query()
            ->selectRaw('status, COUNT(*) as total')
            ->where('hotel_id', $hotelId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.analytics', [
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'kpis' => [
                'revenue' => (float) $totalRevenue,
                'payments' => $paymentsCount,
                'reservations_total' => $reservationsTotal,
                'reservations_confirmed' => $reservationsConfirmed,
            ],
            'labels' => $labels,
            'paymentsSeries' => $paymentsSeries,
            'reservationsSeries' => $reservationsSeries,
            'statusLabels' => $statusBreakdown->keys()->values(),
            'statusSeries' => $statusBreakdown->values(),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $hotelId = (int) (auth()->user()->hotel_id ?? 1);
        [$start, $end] = $this->resolveRange($request);

        $payments = PaymentAttempt::query()
            ->whereHas('reservation', fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->get(['id', 'reservation_id', 'provider', 'status', 'amount', 'created_at']);

        $filename = 'analytics_' . $start->toDateString() . '_to_' . $end->toDateString() . '.csv';

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['payment_id', 'reservation_id', 'provider', 'status', 'amount', 'created_at']);
            foreach ($payments as $p) {
                fputcsv($out, [$p->id, $p->reservation_id, $p->provider, $p->status, $p->amount, $p->created_at]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function resolveRange(Request $request): array
    {
        $start = $request->query('start_date')
            ? now()->parse((string) $request->query('start_date'))->startOfDay()
            : now()->subDays(27)->startOfDay();

        $end = $request->query('end_date')
            ? now()->parse((string) $request->query('end_date'))->endOfDay()
            : now()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }
}
