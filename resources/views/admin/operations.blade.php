<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Operación</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#fffaf7] text-[#5f2f26]">
<div class="max-w-7xl mx-auto py-8 px-4">
  @include('admin.partials.nav')
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Operación (Reservas y Pagos)</h1>
    <a href="/admin/inventory" class="text-sm text-blue-600">← Inventario</a>
  </div>

  @if(session('status'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-3 mb-4 text-sm">{{ session('status') }}</div>
  @endif

  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-2xl p-4 shadow transition hover:-translate-y-0.5 hover:shadow-lg"><div class="text-xs text-gray-500">Total</div><div class="text-xl font-semibold">{{ $kpis['total'] }}</div></div>
    <div class="bg-white rounded-2xl p-4 shadow transition hover:-translate-y-0.5 hover:shadow-lg"><div class="text-xs text-gray-500">Cotizadas</div><div class="text-xl font-semibold">{{ $kpis['quoted'] }}</div></div>
    <div class="bg-white rounded-2xl p-4 shadow transition hover:-translate-y-0.5 hover:shadow-lg"><div class="text-xs text-gray-500">Esperando pago</div><div class="text-xl font-semibold">{{ $kpis['awaiting_payment'] }}</div></div>
    <div class="bg-white rounded-2xl p-4 shadow transition hover:-translate-y-0.5 hover:shadow-lg"><div class="text-xs text-gray-500">Pagadas</div><div class="text-xl font-semibold">{{ $kpis['paid'] }}</div></div>
    <div class="bg-white rounded-2xl p-4 shadow transition hover:-translate-y-0.5 hover:shadow-lg"><div class="text-xs text-gray-500">Expiradas</div><div class="text-xl font-semibold">{{ $kpis['expired'] }}</div></div>
  </div>

  <div class="grid lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow p-4 overflow-auto">
      <h2 class="font-semibold mb-3">Reservas recientes</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500"><th>ID</th><th>Cliente</th><th>Habitación</th><th>Estatus</th><th>Total</th><th>Acciones</th></tr></thead>
        <tbody>
          @foreach($recentReservations as $r)
          <tr class="border-t align-top">
            <td class="py-2">#{{ $r->id }}</td>
            <td>{{ $r->contact?->telefono }}</td>
            <td>{{ $r->room?->nombre }}</td>
            <td>
              @php
                $statusClass = match($r->status) {
                  'paid_pending_availability_check' => 'bg-amber-100 text-amber-800',
                  'confirmed' => 'bg-emerald-100 text-emerald-800',
                  'availability_rejected' => 'bg-red-100 text-red-800',
                  default => 'bg-slate-100 text-slate-700',
                };
              @endphp
              <span class="text-xs px-2 py-1 rounded-full {{ $statusClass }}">{{ $r->status }}</span>
            </td>
            <td>${{ number_format((float)$r->total_amount,2) }}</td>
            <td>
              @if($r->status === 'paid_pending_availability_check')
                <div class="flex flex-wrap gap-1 min-w-[140px]">
                  <form method="POST" action="/admin/reservations/{{ $r->id }}/confirm">
                    @csrf
                    <button class="text-xs bg-emerald-600 text-white px-2 py-1 rounded">Confirmar</button>
                  </form>
                  <form id="reject-form-{{ $r->id }}" method="POST" action="/admin/reservations/{{ $r->id }}/reject">
                    @csrf
                    <input type="hidden" name="reason" id="reject-reason-{{ $r->id }}" />
                    <button type="button" onclick="rejectReservation({{ $r->id }})" class="text-xs bg-red-600 text-white px-2 py-1 rounded">No disp.</button>
                  </form>
                </div>
              @else
                <span class="text-xs text-gray-400">—</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="bg-white rounded-xl shadow p-4 overflow-auto">
      <h2 class="font-semibold mb-3">Pagos recientes</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500"><th>ID</th><th>Reserva</th><th>Proveedor</th><th>Estatus</th><th>Monto</th></tr></thead>
        <tbody>
          @foreach($recentPayments as $p)
          <tr class="border-t"><td class="py-2">#{{ $p->id }}</td><td>#{{ $p->reservation_id }}</td><td>{{ $p->provider }}</td><td>{{ $p->status }}</td><td>${{ number_format((float)$p->amount,2) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold mb-3">Respuestas rápidas (copiar)</h2>
      <div class="space-y-2">
        @foreach($quickReplies as $key => $text)
          <div class="border rounded-xl p-2">
            <div class="text-xs text-gray-500 mb-1">{{ ucfirst($key) }}</div>
            <div id="qr-{{ $key }}" class="text-sm whitespace-pre-line">{{ $text }}</div>
            <button onclick="copyQuickReply('qr-{{ $key }}')" class="mt-2 text-xs bg-[#173845] text-white px-3 py-1 rounded">Copiar</button>
          </div>
        @endforeach
      </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4">
      <div class="flex items-center justify-between mb-2">
        <h2 class="font-semibold">Estado de ficha del hotel</h2>
        <a href="/admin/hotel-profile" class="text-sm text-[#1d6a66] underline">Editar ficha</a>
      </div>
      @if(empty($missingProfile))
        <div class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl p-3">✅ Ficha completa y lista para respuestas del bot.</div>
      @else
        <div class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">
          ⚠️ Faltan campos: {{ implode(', ', $missingProfile) }}
        </div>
      @endif
    </div>
  </div>
</div>

<script>
  async function copyQuickReply(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const text = el.innerText.trim();
    try {
      await navigator.clipboard.writeText(text);
      alert('Texto copiado ✅');
    } catch (e) {
      console.error(e);
      alert('No se pudo copiar.');
    }
  }

  function rejectReservation(id) {
    const reason = prompt('Motivo de no disponibilidad (opcional):', '');
    const input = document.getElementById(`reject-reason-${id}`);
    const form = document.getElementById(`reject-form-${id}`);
    if (!input || !form) return;
    input.value = reason ?? '';
    form.submit();
  }
</script>
</body>
</html>
