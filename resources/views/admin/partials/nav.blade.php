@php
  $items = [
    ['label' => 'Inventario', 'href' => '/admin/inventory', 'active' => request()->is('admin/inventory*')],
    ['label' => 'Calendario', 'href' => '/admin/calendar', 'active' => request()->is('admin/calendar*')],
    ['label' => 'Alertas', 'href' => '/admin/alerts', 'active' => request()->is('admin/alerts*')],
    ['label' => 'Operación', 'href' => '/admin/operations', 'active' => request()->is('admin/operations*')],
    ['label' => 'Analytics', 'href' => '/admin/analytics', 'active' => request()->is('admin/analytics*')],
    ['label' => 'Conversaciones', 'href' => '/admin/conversations', 'active' => request()->is('admin/conversations*')],
    ['label' => 'Hotel', 'href' => '/admin/hotel-profile', 'active' => request()->is('admin/hotel-profile*')],
  ];
@endphp

<div class="mb-4 sm:mb-6">
  <div class="mx-auto max-w-7xl bg-[#f6fbfb] border border-[#d7e8e7] rounded-2xl shadow-sm px-3 sm:px-4 py-3">
    <div class="flex items-center justify-between">
      <div class="font-semibold tracking-tight text-[#173845] text-sm sm:text-base">NAATIA Admin</div>

      <button id="menuToggle" class="sm:hidden inline-flex items-center justify-center h-9 w-9 rounded-lg border border-[#d7e8e7] text-[#173845]" aria-label="Abrir menú">
        ☰
      </button>

      <div class="hidden sm:flex items-center gap-2 text-sm text-[#275261]">
        @foreach($items as $item)
          <a href="{{ $item['href'] }}"
             class="whitespace-nowrap px-3 py-1.5 rounded-full transition {{ $item['active'] ? 'bg-[#173845] text-white' : 'hover:bg-[#e7f4f3]' }}">
            {{ $item['label'] }}
          </a>
        @endforeach
        <form method="POST" action="/logout" class="ml-2">
          @csrf
          <button title="Cerrar sesión" aria-label="Cerrar sesión" class="bg-[#e25555] text-white rounded-full h-9 w-9 shadow hover:opacity-90 transition inline-flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
              <path d="M10 17l5-5-5-5"/>
              <path d="M15 12H3"/>
              <path d="M21 4v16h-6"/>
            </svg>
          </button>
        </form>
      </div>
    </div>

    <div id="mobileMenu" class="hidden sm:hidden mt-3 border-t border-[#d7e8e7] pt-3">
      <nav class="grid grid-cols-2 gap-2 text-sm">
        @foreach($items as $item)
          <a href="{{ $item['href'] }}"
             class="px-3 py-2 rounded-xl transition text-center {{ $item['active'] ? 'bg-[#173845] text-white' : 'bg-white hover:bg-[#e7f4f3] text-[#275261]' }}">
            {{ $item['label'] }}
          </a>
        @endforeach
      </nav>
      <form method="POST" action="/logout" class="mt-3 flex justify-end">
        @csrf
        <button title="Cerrar sesión" aria-label="Cerrar sesión" class="bg-[#e25555] text-white rounded-full h-10 w-10 shadow hover:opacity-90 transition inline-flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            <path d="M10 17l5-5-5-5"/>
            <path d="M15 12H3"/>
            <path d="M21 4v16h-6"/>
          </svg>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
  (() => {
    const btn = document.getElementById('menuToggle');
    const menu = document.getElementById('mobileMenu');
    if (!btn || !menu) return;

    btn.addEventListener('click', () => {
      menu.classList.toggle('hidden');
      btn.textContent = menu.classList.contains('hidden') ? '☰' : '✕';
    });
  })();
</script>
