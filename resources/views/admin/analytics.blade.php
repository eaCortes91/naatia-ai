<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Analytics</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-[#eef4f4] text-[#173845]">
<div class="max-w-7xl mx-auto py-8 px-4">
  @include('admin.partials.nav')

  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <h1 class="text-2xl font-semibold">Analytics</h1>
    <a href="/admin/operations" class="text-sm text-[#1d6a66] underline">Ver operación</a>
  </div>

  <form method="GET" action="/admin/analytics" class="bg-white rounded-2xl shadow p-4 mb-5 grid sm:grid-cols-4 gap-3 items-end">
    <div>
      <label class="text-xs text-slate-500">Fecha inicio</label>
      <input type="date" name="start_date" value="{{ $startDate }}" class="w-full border rounded-xl px-3 py-2" />
    </div>
    <div>
      <label class="text-xs text-slate-500">Fecha fin</label>
      <input type="date" name="end_date" value="{{ $endDate }}" class="w-full border rounded-xl px-3 py-2" />
    </div>
    <button class="bg-[#173845] text-white rounded-xl px-4 py-2">Aplicar rango</button>
    <div class="flex gap-2">
      <a href="/admin/analytics/export.csv?start_date={{ $startDate }}&end_date={{ $endDate }}" class="bg-[#1f6a67] text-white rounded-xl px-3 py-2 text-sm">Exportar CSV</a>
      <button type="button" onclick="window.print()" class="bg-slate-700 text-white rounded-xl px-3 py-2 text-sm">PDF (imprimir)</button>
    </div>
  </form>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-2xl p-4 shadow"><div class="text-xs text-slate-500">Ingresos cobrados</div><div class="text-2xl font-semibold">${{ number_format($kpis['revenue'],2) }}</div></div>
    <div class="bg-white rounded-2xl p-4 shadow"><div class="text-xs text-slate-500">Pagos aprobados</div><div class="text-2xl font-semibold">{{ $kpis['payments'] }}</div></div>
    <div class="bg-white rounded-2xl p-4 shadow"><div class="text-xs text-slate-500">Reservas totales</div><div class="text-2xl font-semibold">{{ $kpis['reservations_total'] }}</div></div>
    <div class="bg-white rounded-2xl p-4 shadow"><div class="text-xs text-slate-500">Reservas confirmadas</div><div class="text-2xl font-semibold">{{ $kpis['reservations_confirmed'] }}</div></div>
  </div>

  <div class="grid lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl shadow p-4">
      <h2 class="font-semibold mb-3">Cobros por día</h2>
      <div class="h-72"><canvas id="paymentsChart"></canvas></div>
    </div>

    <div class="bg-white rounded-2xl shadow p-4">
      <h2 class="font-semibold mb-3">Reservas por día</h2>
      <div class="h-72"><canvas id="reservationsChart"></canvas></div>
    </div>

    <div class="bg-white rounded-2xl shadow p-4 lg:col-span-2">
      <h2 class="font-semibold mb-3">Distribución por estatus</h2>
      <div class="h-80 max-w-xl mx-auto"><canvas id="statusChart"></canvas></div>
    </div>
  </div>
</div>

<script>
  const labels = @json($labels);
  const paymentsSeries = @json($paymentsSeries);
  const reservationsSeries = @json($reservationsSeries);
  const statusLabels = @json($statusLabels);
  const statusSeries = @json($statusSeries);

  const baseOpts = { responsive: true, maintainAspectRatio: false };

  new Chart(document.getElementById('paymentsChart'), {
    type: 'line',
    data: { labels, datasets: [{ label: 'MXN', data: paymentsSeries, borderColor: '#1fb7b2', backgroundColor: 'rgba(31,183,178,.18)', fill: true, tension: .3 }] },
    options: baseOpts,
  });

  new Chart(document.getElementById('reservationsChart'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Reservas', data: reservationsSeries, backgroundColor: '#173845' }] },
    options: baseOpts,
  });

  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels: statusLabels, datasets: [{ data: statusSeries, backgroundColor: ['#1fb7b2','#173845','#f59e0b','#ef4444','#6366f1','#94a3b8','#10b981','#f97316'] }] },
    options: baseOpts,
  });
</script>
</body>
</html>
