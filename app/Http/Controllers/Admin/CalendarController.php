<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomDayStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->query('month')
            ? Carbon::parse($request->query('month') . '-01')->startOfMonth()
            : now()->startOfMonth();

        $rooms = Room::query()->where('hotel_id', $this->hotelId())->get();
        $statuses = RoomDayStatus::query()
            ->where('hotel_id', $this->hotelId())
            ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get();

        $heatmap = [];
        foreach (range(1, $date->daysInMonth) as $d) {
            $day = $date->copy()->day($d)->toDateString();
            $dayStatuses = $statuses->where('date', $day);
            $counts = [
                'ocupada' => $dayStatuses->where('status', 'ocupada')->count(),
                'reservada' => $dayStatuses->where('status', 'reservada')->count(),
                'mantenimiento' => $dayStatuses->where('status', 'mantenimiento')->count(),
                'bloqueada' => $dayStatuses->where('status', 'bloqueada')->count(),
                'libre' => max(0, $rooms->count() - $dayStatuses->whereIn('status', ['ocupada', 'reservada', 'mantenimiento', 'bloqueada'])->count()),
            ];
            $heatmap[$day] = $counts;
        }

        return view('admin.calendar.index', [
            'currentMonth' => $date,
            'daysInMonth' => $date->daysInMonth,
            'heatmap' => $heatmap,
        ]);
    }

    public function day(Request $request, string $date): View
    {
        $selectedDate = Carbon::parse($date)->toDateString();
        $typeId = $request->query('type_id');
        $statusFilter = $request->query('status');

        $roomsQuery = Room::query()
            ->with('roomType')
            ->where('hotel_id', $this->hotelId());

        if ($typeId) {
            $roomsQuery->where('room_type_id', (int) $typeId);
        }

        $rooms = $roomsQuery->orderBy('id')->get();

        $statuses = RoomDayStatus::query()
            ->where('hotel_id', $this->hotelId())
            ->where('date', $selectedDate)
            ->get()
            ->keyBy('room_id');

        if ($statusFilter) {
            $rooms = $rooms->filter(function ($room) use ($statuses, $statusFilter) {
                $status = $statuses[$room->id]->status ?? $room->base_status ?? 'libre';
                return $status === $statusFilter;
            })->values();
        }

        $roomTypes = \App\Models\RoomType::query()
            ->where('hotel_id', $this->hotelId())
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('admin.calendar.day', [
            'selectedDate' => $selectedDate,
            'rooms' => $rooms,
            'statuses' => $statuses,
            'roomTypes' => $roomTypes,
            'selectedTypeId' => $typeId,
            'selectedStatus' => $statusFilter,
        ]);
    }

    public function updateDayStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
        ]);

        RoomDayStatus::query()->updateOrCreate(
            [
                'hotel_id' => $this->hotelId(),
                'room_id' => (int) $data['room_id'],
                'date' => $data['date'],
            ],
            [
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()->back()->with('status', 'Estado actualizado.');
    }

    private function hotelId(): int
    {
        return (int) (auth()->user()->hotel_id ?? 1);
    }
}
