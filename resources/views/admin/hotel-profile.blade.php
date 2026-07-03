<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Perfil del hotel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#eef4f4] text-[#173845]">
<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6">
    @include('admin.partials.nav')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Perfil del hotel</h1>
        <p class="text-sm text-[#4b6974]">Configura información oficial para WhatsApp y operación.</p>
    </div>

    @if(session('status')) <div class="bg-[#e8f6f4] text-[#1d6a66] p-3 rounded-xl mb-4">{{ session('status') }}</div> @endif
    @if($errors->any())
        <div class="bg-[#f6ece9] text-[#7a4638] p-3 rounded-xl mb-4">
            <ul class="list-disc pl-5 text-sm">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="/admin/hotel-profile" class="bg-white rounded-2xl shadow p-6 space-y-5">
        @csrf

        <div class="grid md:grid-cols-3 gap-3">
            <div><label class="text-xs">Nombre</label><input name="nombre" value="{{ old('nombre', $hotel->nombre) }}" class="w-full border rounded-xl px-3 py-2" required /></div>
            <div><label class="text-xs">Teléfono</label><input name="telefono" value="{{ old('telefono', $hotel->telefono) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <div><label class="text-xs">Email</label><input name="email" value="{{ old('email', $hotel->email) }}" class="w-full border rounded-xl px-3 py-2" /></div>
        </div>

        <div class="grid md:grid-cols-2 gap-3">
            <div><label class="text-xs">Dirección</label><input name="address_line" value="{{ old('address_line', $hotel->address_line) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <div><label class="text-xs">Colonia / Barrio</label><input name="neighborhood" value="{{ old('neighborhood', $hotel->neighborhood) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <div><label class="text-xs">Ciudad</label><input name="city" value="{{ old('city', $hotel->city) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <div><label class="text-xs">Estado</label><input name="state" value="{{ old('state', $hotel->state) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <div><label class="text-xs">CP</label><input name="postal_code" value="{{ old('postal_code', $hotel->postal_code) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-xs">Latitud</label><input type="number" step="0.00000001" name="latitude" value="{{ old('latitude', $hotel->latitude) }}" class="w-full border rounded-xl px-3 py-2" /></div>
                <div><label class="text-xs">Longitud</label><input type="number" step="0.00000001" name="longitude" value="{{ old('longitude', $hotel->longitude) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-3">
            <div><label class="text-xs">Check-in</label><input name="check_in_time" value="{{ old('check_in_time', $hotel->check_in_time) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <div><label class="text-xs">Check-out</label><input name="check_out_time" value="{{ old('check_out_time', $hotel->check_out_time) }}" class="w-full border rounded-xl px-3 py-2" /></div>
            <label class="flex items-end gap-2 pb-2"><input type="checkbox" name="pet_friendly" value="1" @checked(old('pet_friendly', $hotel->pet_friendly)) /> Pet friendly</label>
        </div>

        <div><label class="text-xs">Amenidades</label><textarea name="amenities_text" rows="3" class="w-full border rounded-xl px-3 py-2">{{ old('amenities_text', $hotel->amenities_text) }}</textarea></div>
        <div><label class="text-xs">Políticas</label><textarea name="policies_text" rows="3" class="w-full border rounded-xl px-3 py-2">{{ old('policies_text', $hotel->policies_text) }}</textarea></div>

        <details class="border rounded-xl p-3">
            <summary class="cursor-pointer text-sm font-medium">Avanzado (prompt/saludo)</summary>
            <div class="mt-3 space-y-3">
                <div><label class="text-xs">Saludo base</label><textarea name="saludo_base" rows="2" class="w-full border rounded-xl px-3 py-2">{{ old('saludo_base', $hotel->saludo_base) }}</textarea></div>
                <div><label class="text-xs">Prompt base</label><textarea name="prompt_base" rows="4" class="w-full border rounded-xl px-3 py-2">{{ old('prompt_base', $hotel->prompt_base) }}</textarea></div>
            </div>
        </details>

        @if($hotel->latitude && $hotel->longitude)
            <a href="https://maps.google.com/?q={{ $hotel->latitude }},{{ $hotel->longitude }}" target="_blank" class="inline-flex text-sm text-[#1d6a66] underline">Abrir ubicación en Google Maps</a>
        @endif

        <button class="bg-[#173845] text-white rounded-xl px-5 py-2">Guardar cambios</button>
    </form>
</div>
</body>
</html>
