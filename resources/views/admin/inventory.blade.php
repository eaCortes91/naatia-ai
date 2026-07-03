<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inventario - {{ $hotel->nombre }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#eef4f4] text-[#173845]">
    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6">
        @include('admin.partials.nav')

        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Inventario manual</h1>
            <p class="text-sm text-[#4b6974]">Hotel: {{ $hotel->nombre }}</p>
        </div>

        @if(session('status')) <div class="bg-[#e8f6f4] text-[#1d6a66] p-3 rounded-xl mb-4">{{ session('status') }}</div> @endif
        @if(session('error')) <div class="bg-[#f6ece9] text-[#7a4638] p-3 rounded-xl mb-4">{{ session('error') }}</div> @endif
        @if($errors->any())
            <div class="bg-[#fef2f2] text-[#991b1b] p-3 rounded-xl mb-4 text-sm">
                <div class="font-semibold mb-1">No se pudo guardar:</div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <button onclick="openModal('typeModal')" class="bg-white border border-[#d5e4e3] rounded-2xl p-5 text-left shadow transition hover:-translate-y-1 hover:shadow-lg">
                <div class="text-lg font-semibold">Tipo de habitación</div>
                <p class="text-sm text-[#4b6974] mt-1">Crear tipos con color y descripción.</p>
            </button>
            <button onclick="openModal('roomModal')" class="bg-white border border-[#d5e4e3] rounded-2xl p-5 text-left shadow transition hover:-translate-y-1 hover:shadow-lg">
                <div class="text-lg font-semibold">Habitación</div>
                <p class="text-sm text-[#4b6974] mt-1">Agregar habitaciones con costos y capacidad.</p>
            </button>
            <button onclick="openModal('serviceModal')" class="bg-white border border-[#d5e4e3] rounded-2xl p-5 text-left shadow transition hover:-translate-y-1 hover:shadow-lg">
                <div class="text-lg font-semibold">Servicios</div>
                <p class="text-sm text-[#4b6974] mt-1">Alta de servicios del hotel con costo.</p>
            </button>
            <button onclick="openModal('packageModal')" class="bg-white border border-[#d5e4e3] rounded-2xl p-5 text-left shadow transition hover:-translate-y-1 hover:shadow-lg">
                <div class="text-lg font-semibold">Paquetes</div>
                <p class="text-sm text-[#4b6974] mt-1">Crear paquetes promocionales con color.</p>
            </button>
        </div>

        <div class="bg-white rounded-2xl shadow p-6 mb-8">
            <h2 class="text-lg font-semibold mb-3">Imágenes (hotel, habitaciones y paquetes)</h2>
            <form method="POST" action="/admin/media-assets" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end mb-4">
                @csrf
                <div>
                    <label class="text-xs">Tipo</label>
                    <select name="entity_type" id="mediaEntityType" class="border rounded-xl px-3 py-2 w-full" required>
                        <option value="hotel">Hotel general</option>
                        <option value="room">Habitación</option>
                        <option value="package">Paquete</option>
                        <option value="service">Servicio</option>
                    </select>
                </div>
                <div id="mediaRoomWrap" class="hidden">
                    <label class="text-xs">Habitación</label>
                    <select name="room_id" class="border rounded-xl px-3 py-2 w-full">
                        <option value="">Selecciona</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="mediaPackageWrap" class="hidden">
                    <label class="text-xs">Paquete</label>
                    <select name="package_id" class="border rounded-xl px-3 py-2 w-full">
                        <option value="">Selecciona</option>
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="mediaServiceWrap" class="hidden">
                    <label class="text-xs">Servicio</label>
                    <select name="service_id" class="border rounded-xl px-3 py-2 w-full">
                        <option value="">Selecciona</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs">Caption opcional</label>
                    <input type="text" name="caption" class="border rounded-xl px-3 py-2 w-full" placeholder="Ej. Suite con vista" />
                </div>
                <div>
                    <label class="text-xs">Imagen</label>
                    <input type="file" name="image" accept="image/*" class="border rounded-xl px-3 py-2 w-full" required />
                </div>
                <button class="bg-[#173845] text-white rounded-xl py-2 px-4">Subir imagen</button>
            </form>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @forelse($mediaAssets as $asset)
                    <div class="border rounded-xl p-2">
                        <img src="{{ $asset->url }}" alt="media" class="w-full h-28 object-cover rounded-lg mb-2" />
                        <div class="text-xs text-[#4b6974] mb-2">
                            <strong>{{ strtoupper($asset->entity_type) }}</strong>
                            @if($asset->caption) · {{ $asset->caption }} @endif
                        </div>
                        <form method="POST" action="/admin/media-assets/{{ $asset->id }}/delete" onsubmit="return confirm('¿Eliminar imagen?')">
                            @csrf
                            <button class="w-full bg-[#e25555] text-white rounded-lg py-1 text-xs">Eliminar</button>
                        </form>
                    </div>
                @empty
                    <div class="text-sm text-[#4b6974]">Aún no hay imágenes cargadas.</div>
                @endforelse
            </div>
        </div>

        <div id="typeModal" class="hidden fixed inset-0 z-50 bg-black/30 p-4 overflow-auto">
            <div class="max-w-xl mx-auto bg-white rounded-2xl p-5 mt-10">
                <div class="flex justify-between items-center mb-4"><h2 class="text-xl font-semibold">Crear tipo de habitación</h2><button onclick="closeModal('typeModal')">✕</button></div>
                <form method="POST" action="/admin/room-types" class="space-y-3">
                    @csrf
                    <input type="text" name="name" placeholder="Nombre" class="w-full border rounded-xl px-3 py-2" required />
                    <textarea name="description" placeholder="Descripción" class="w-full border rounded-xl px-3 py-2"></textarea>
                    <input type="color" name="color" value="#1fb7b2" class="w-16 h-10 border rounded" />
                    <button class="w-full bg-[#173845] text-white rounded-xl py-2">Guardar tipo</button>
                </form>
            </div>
        </div>

        <div id="roomModal" class="hidden fixed inset-0 z-50 bg-black/30 p-4 overflow-auto">
            <div class="max-w-2xl mx-auto bg-white rounded-2xl p-5 mt-10">
                <div class="flex justify-between items-center mb-4"><h2 class="text-xl font-semibold">Crear habitación</h2><button onclick="closeModal('roomModal')">✕</button></div>
                <form method="POST" action="/admin/rooms" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @csrf
                    <input type="text" name="nombre" placeholder="Nombre o número" class="border rounded-xl px-3 py-2" required />
                    <select name="room_type_id" class="border rounded-xl px-3 py-2">
                        <option value="">Tipo</option>
                        @foreach($roomTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="capacidad" min="1" placeholder="Capacidad" class="border rounded-xl px-3 py-2" required />
                    <input type="number" name="inventario_total" min="1" placeholder="Unidades" class="border rounded-xl px-3 py-2" required />
                    <input type="number" step="0.01" name="weekday_rate" min="0" placeholder="Costo entre semana" class="border rounded-xl px-3 py-2" required />
                    <input type="number" step="0.01" name="weekend_rate" min="0" placeholder="Costo fin de semana" class="border rounded-xl px-3 py-2" required />
                    <textarea name="descripcion" placeholder="Descripción" class="border rounded-xl px-3 py-2 md:col-span-2"></textarea>
                    <button class="md:col-span-2 bg-[#173845] text-white rounded-xl py-2">Crear habitación</button>
                </form>
            </div>
        </div>

        <div id="serviceModal" class="hidden fixed inset-0 z-50 bg-black/30 p-4 overflow-auto">
            <div class="max-w-xl mx-auto bg-white rounded-2xl p-5 mt-10">
                <div class="flex justify-between items-center mb-4"><h2 class="text-xl font-semibold">Crear servicio</h2><button onclick="closeModal('serviceModal')">✕</button></div>
                <form method="POST" action="/admin/services" class="space-y-3">
                    @csrf
                    <input type="text" name="name" placeholder="Nombre del servicio" class="w-full border rounded-xl px-3 py-2" required />
                    <textarea name="description" placeholder="Descripción" class="w-full border rounded-xl px-3 py-2"></textarea>
                    <input type="number" step="0.01" name="price" min="0" placeholder="Costo" class="w-full border rounded-xl px-3 py-2" required />
                    <button class="w-full bg-[#173845] text-white rounded-xl py-2">Guardar servicio</button>
                </form>
            </div>
        </div>

        <div id="packageModal" class="hidden fixed inset-0 z-50 bg-black/30 p-4 overflow-auto">
            <div class="max-w-xl mx-auto bg-white rounded-2xl p-5 mt-10">
                <div class="flex justify-between items-center mb-4"><h2 class="text-xl font-semibold">Crear paquete</h2><button onclick="closeModal('packageModal')">✕</button></div>
                <form method="POST" action="/admin/packages" class="space-y-3">
                    @csrf
                    <input type="text" name="name" placeholder="Nombre del paquete" class="w-full border rounded-xl px-3 py-2" required />
                    <textarea name="description" placeholder="Descripción" class="w-full border rounded-xl px-3 py-2"></textarea>
                    <input type="number" step="0.01" name="price" min="0" placeholder="Costo" class="w-full border rounded-xl px-3 py-2" required />
                    <input type="color" name="color" value="#1fb7b2" class="w-16 h-10 border rounded" />
                    <button class="w-full bg-[#173845] text-white rounded-xl py-2">Guardar paquete</button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">Habitaciones actuales</h2>
            <div class="space-y-4">
                @foreach($rooms as $room)
                    <form method="POST" action="/admin/rooms/{{ $room->id }}" class="grid md:grid-cols-10 gap-2 items-end border rounded-xl p-3">
                        @csrf
                        <div class="md:col-span-2"><label class="text-xs">Nombre</label><input name="nombre" class="border rounded px-2 py-2 w-full" value="{{ $room->nombre }}" /></div>
                        <div><label class="text-xs">Tipo</label><select name="room_type_id" class="border rounded px-2 py-2 w-full"><option value="">Sin tipo</option>@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected($room->room_type_id===$type->id)>{{ $type->name }}</option>@endforeach</select></div>
                        <div><label class="text-xs">Cap.</label><input type="number" name="capacidad" value="{{ $room->capacidad }}" class="border rounded px-2 py-2 w-full" /></div>
                        <div><label class="text-xs">Unidades</label><input type="number" name="inventario_total" value="{{ $room->inventario_total }}" class="border rounded px-2 py-2 w-full" /></div>
                        <div><label class="text-xs">Semana</label><input type="number" step="0.01" name="weekday_rate" value="{{ $room->weekday_rate }}" class="border rounded px-2 py-2 w-full" /></div>
                        <div><label class="text-xs">Finde</label><input type="number" step="0.01" name="weekend_rate" value="{{ $room->weekend_rate }}" class="border rounded px-2 py-2 w-full" /></div>
                        <div><label class="text-xs">Status base</label><select name="base_status" class="border rounded px-2 py-2 w-full">@foreach(['libre','reservada','ocupada','mantenimiento','bloqueada'] as $s)<option value="{{ $s }}" @selected($room->base_status===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                        <div class="flex items-center gap-1"><input type="checkbox" name="activo" value="1" @checked($room->activo) /><span class="text-xs">Activa</span></div>
                        <button class="bg-[#173845] text-white px-4 py-2 rounded">Guardar</button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-semibold mb-2">Servicios</h3>
                <div class="text-sm space-y-2">
                    @forelse($services as $service)
                        <div class="border rounded-xl p-2 space-y-2">
                            <form method="POST" action="/admin/services/{{ $service->id }}" class="space-y-2">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <input name="name" value="{{ $service->name }}" class="border rounded px-2 py-1" />
                                    <input type="number" step="0.01" name="price" value="{{ $service->price }}" class="border rounded px-2 py-1" />
                                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="active" value="1" @checked($service->active) /> Activo</label>
                                </div>
                                <textarea name="description" class="w-full border rounded px-2 py-1" placeholder="Descripción">{{ $service->description }}</textarea>
                                <button class="bg-[#173845] text-white px-3 py-1 rounded">Guardar</button>
                            </form>
                            <form method="POST" action="/admin/services/{{ $service->id }}/delete" onsubmit="return confirm('¿Eliminar servicio?')" class="text-right">
                                @csrf
                                <button class="bg-[#e25555] text-white px-3 py-1 rounded">Eliminar</button>
                            </form>
                        </div>
                    @empty
                        <div class="text-[#4b6974]">Sin servicios aún.</div>
                    @endforelse
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-semibold mb-2">Paquetes</h3>
                <div class="text-sm space-y-2">
                    @forelse($packages as $package)
                        <div class="border rounded-xl p-2 space-y-2">
                            <form method="POST" action="/admin/packages/{{ $package->id }}" class="space-y-2">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                                    <input name="name" value="{{ $package->name }}" class="border rounded px-2 py-1" />
                                    <input type="number" step="0.01" name="price" value="{{ $package->price }}" class="border rounded px-2 py-1" />
                                    <input type="color" name="color" value="{{ $package->color }}" class="border rounded h-9" />
                                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="active" value="1" @checked($package->active) /> Activo</label>
                                </div>
                                <textarea name="description" class="w-full border rounded px-2 py-1" placeholder="Descripción">{{ $package->description }}</textarea>
                                <button class="bg-[#173845] text-white px-3 py-1 rounded">Guardar</button>
                            </form>
                            <form method="POST" action="/admin/packages/{{ $package->id }}/delete" onsubmit="return confirm('¿Eliminar paquete?')" class="text-right">
                                @csrf
                                <button class="bg-[#e25555] text-white px-3 py-1 rounded">Eliminar</button>
                            </form>
                        </div>
                    @empty
                        <div class="text-[#4b6974]">Sin paquetes aún.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
      const openModal = (id) => document.getElementById(id)?.classList.remove('hidden');
      const closeModal = (id) => document.getElementById(id)?.classList.add('hidden');

      const mediaTypeSelect = document.getElementById('mediaEntityType');
      const mediaRoomWrap = document.getElementById('mediaRoomWrap');
      const mediaPackageWrap = document.getElementById('mediaPackageWrap');
      const mediaServiceWrap = document.getElementById('mediaServiceWrap');

      const syncMediaEntityFields = () => {
        const type = mediaTypeSelect?.value;
        mediaRoomWrap?.classList.toggle('hidden', type !== 'room');
        mediaPackageWrap?.classList.toggle('hidden', type !== 'package');
        mediaServiceWrap?.classList.toggle('hidden', type !== 'service');
      };

      mediaTypeSelect?.addEventListener('change', syncMediaEntityFields);
      syncMediaEntityFields();

      window.addEventListener('click', (e) => {
        ['typeModal','roomModal','serviceModal','packageModal'].forEach(id => {
          const modal = document.getElementById(id);
          if (modal && e.target === modal) closeModal(id);
        });
      });
    </script>
</body>
</html>
