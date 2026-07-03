<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Calendario</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#fffaf7] text-[#5f2f26]">
<div class="max-w-6xl mx-auto py-8 px-4">
  @include('admin.partials.nav')
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Calendario de habitaciones</h1>
    <a href="/admin/inventory" class="text-sm text-blue-600">← Regresar</a>
  </div>
  <div class="bg-white rounded-xl shadow p-4">
    <div class="grid grid-cols-7 gap-2 text-sm">
      @for($d=1;$d<=$daysInMonth;$d++)
        @php
          $date = $currentMonth->copy()->day($d)->toDateString();
          $h = $heatmap[$date] ?? ['libre'=>0,'reservada'=>0,'ocupada'=>0,'mantenimiento'=>0,'bloqueada'=>0];
          $severity = $h['ocupada'] + $h['reservada'] + $h['bloqueada'] + $h['mantenimiento'];
          $bg = $severity >= 6 ? 'bg-[#e7ecef]' : ($severity >= 3 ? 'bg-[#f6f1e8]' : 'bg-[#e8f6f4]');
        @endphp
        <a href="/admin/calendar/{{ $date }}" class="border rounded-lg p-3 hover:bg-slate-100 {{ $bg }}">
          <div class="font-medium">{{ $d }}</div>
          <div class="text-xs text-gray-500">{{ $currentMonth->copy()->day($d)->translatedFormat('D') }}</div>
          <div class="text-[10px] mt-1 text-slate-600">L:{{ $h['libre'] }} R:{{ $h['reservada'] }} O:{{ $h['ocupada'] }}</div>
        </a>
      @endfor
    </div>
  </div>
</div>
</body>
</html>
