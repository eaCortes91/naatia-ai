<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Habitaciones {{ $selectedDate }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#fffaf7] text-[#5f2f26]">
<div class="max-w-6xl mx-auto py-8 px-4">
  @include('admin.partials.nav')
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Habitaciones del {{ $selectedDate }}</h1>
    <a href="/admin/calendar" class="text-sm text-blue-600">← Regresar al calendario</a>
  </div>

  @if(session('status'))<div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('status') }}</div>@endif

  @php
    $statusCounts = ['libre'=>0,'reservada'=>0,'ocupada'=>0,'mantenimiento'=>0,'bloqueada'=>0];
    foreach($rooms as $r){
      $st = $statuses[$r->id]->status ?? $r->base_status ?? 'libre';
      $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
    }
  @endphp
  <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
    @foreach($statusCounts as $label => $count)
      <div class="bg-white rounded-xl shadow p-3 text-sm">
        <div class="text-xs text-gray-500">{{ ucfirst($label) }}</div>
        <div class="text-lg font-semibold">{{ $count }}</div>
      </div>
    @endforeach
  </div>

  <form method="GET" class="bg-white rounded-xl shadow p-4 mb-4 grid md:grid-cols-4 gap-3 items-end">
    <div>
      <label class="text-xs text-gray-500">Filtrar por tipo</label>
      <select name="type_id" class="border rounded px-3 py-2 w-full">
        <option value="">Todos</option>
        @foreach($roomTypes as $type)
          <option value="{{ $type->id }}" @selected((string)$selectedTypeId === (string)$type->id)>{{ $type->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="text-xs text-gray-500">Filtrar por estado</label>
      <select name="status" class="border rounded px-3 py-2 w-full">
        <option value="">Todos</option>
        @foreach(['libre','reservada','ocupada','mantenimiento','bloqueada'] as $opt)
          <option value="{{ $opt }}" @selected($selectedStatus === $opt)>{{ ucfirst($opt) }}</option>
        @endforeach
      </select>
    </div>
    <div class="flex gap-2">
      <button class="bg-black text-white px-4 py-2 rounded">Aplicar</button>
      <a href="/admin/calendar/{{ $selectedDate }}" class="border px-4 py-2 rounded">Limpiar</a>
    </div>
  </form>

  <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($rooms as $room)
      @php
        $status = $statuses[$room->id]->status ?? $room->base_status ?? 'libre';
        $isWeekend = \Carbon\Carbon::parse($selectedDate)->isWeekend();
        $rate = $isWeekend ? $room->weekend_rate : $room->weekday_rate;
        $statusColor = match($status){
          'ocupada' => 'bg-[#e7ecef] text-[#294958]',
          'reservada' => 'bg-[#f6f1e8] text-[#715432]',
          'mantenimiento' => 'bg-[#ece8f8] text-[#574a7d]',
          'bloqueada' => 'bg-[#e8ecec] text-[#47555a]',
          default => 'bg-[#e8f6f4] text-[#1d6a66]',
        };
      @endphp
      <div class="bg-white rounded-2xl shadow p-4 border transition hover:-translate-y-0.5 hover:shadow-xl">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold">{{ $room->nombre }}</h2>
          <span class="text-xs px-2 py-1 rounded" style="background: {{ $room->roomType?->color ?? '#e2e8f0' }}22; color: {{ $room->roomType?->color ?? '#475569' }};">{{ $room->roomType?->name ?? 'Sin tipo' }}</span>
        </div>
        <div class="flex items-center gap-2 mt-2">
          <span class="text-xs px-2 py-1 rounded {{ $statusColor }}">{{ ucfirst($status) }}</span>
          <span class="text-xs text-gray-500">Capacidad: {{ $room->capacidad }} · Inventario: {{ $room->inventario_total }}</span>
        </div>
        <p class="text-sm mt-2">Costo del día: <strong>${{ number_format((float)$rate,2) }} MXN</strong></p>

        <form method="POST" action="/admin/calendar/day-status" class="mt-3 space-y-2">
          @csrf
          <input type="hidden" name="room_id" value="{{ $room->id }}" />
          <input type="hidden" name="date" value="{{ $selectedDate }}" />
          <select name="status" class="border rounded px-3 py-2 w-full text-sm">
            @foreach(['libre','reservada','ocupada','mantenimiento','bloqueada'] as $opt)
              <option value="{{ $opt }}" @selected($status === $opt)>{{ ucfirst($opt) }}</option>
            @endforeach
          </select>
          <input type="text" name="notes" placeholder="Notas" class="border rounded px-3 py-2 w-full text-sm" value="{{ $statuses[$room->id]->notes ?? '' }}" />
          <button class="bg-black text-white px-3 py-2 rounded text-sm">Guardar</button>
        </form>
      </div>
    @endforeach
  </div>
</div>
</body>
</html>
