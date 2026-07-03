<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Alertas Recepción</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#fffaf7] text-[#5f2f26]">
<div class="max-w-5xl mx-auto py-8 px-6">
  @include('admin.partials.nav')
  <h1 class="text-2xl font-semibold mb-6">Alertas de recepción</h1>
  @if(session('status'))<div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('status') }}</div>@endif
  <div class="space-y-3">
    @forelse($alerts as $alert)
      <div class="bg-white rounded-2xl shadow p-4 border transition hover:-translate-y-0.5 hover:shadow-lg {{ $alert->status === 'pending' ? 'border-amber-400' : 'border-gray-200' }}">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="font-medium">{{ $alert->title }}</div>
            <div class="text-sm text-gray-600">{{ $alert->body }}</div>
            <div class="text-xs text-gray-500 mt-1">Tipo: {{ $alert->type }} · Estado: {{ $alert->status }} · Vence: {{ optional($alert->due_at)->format('Y-m-d H:i') ?? 'N/A' }}</div>
          </div>
          @if($alert->status === 'pending')
          <form method="POST" action="/admin/alerts/{{ $alert->id }}/resolve">
            @csrf
            <button class="bg-black text-white px-3 py-2 rounded text-sm">Resolver</button>
          </form>
          @endif
        </div>
      </div>
    @empty
      <div class="bg-white rounded shadow p-4 text-sm text-gray-600">Sin alertas por ahora.</div>
    @endforelse
  </div>
</div>
</body>
</html>
