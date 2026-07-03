<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>NAATIA | Automatiza tus reservas desde WhatsApp</title>
    <link rel="icon" type="image/jpeg" href="/images/ai-bot-logo.jpg" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: radial-gradient(1100px 650px at 20% -10%, rgba(31,183,178,.22), transparent 60%),
                        radial-gradient(900px 500px at 100% 0%, rgba(18,58,78,.32), transparent 58%),
                        linear-gradient(160deg, #07141c 0%, #0b202b 45%, #0e1723 100%);
            color: #dce8ee;
            overflow-x: hidden;
        }

        .glass {
            backdrop-filter: blur(10px);
            background: rgba(14, 25, 36, .58);
            border: 1px solid rgba(109, 184, 211, .25);
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
        }

        .neon-btn {
            background: linear-gradient(90deg, #1fb7b2, #31d6d0);
            color: #03222b;
            font-weight: 700;
            box-shadow: 0 12px 30px rgba(31,183,178,.35);
        }

        .neon-btn:hover { filter: brightness(1.03); }

        .card-soft {
            background: rgba(15, 28, 40, .72);
            border: 1px solid rgba(109, 184, 211, .24);
            box-shadow: 0 14px 34px rgba(0,0,0,.24);
        }

        .chip {
            border: 1px solid rgba(109,184,211,.32);
            background: rgba(11,32,43,.55);
            color: #cfe5ee;
        }
    </style>
</head>
<body>
<div class="max-w-7xl mx-auto px-5 py-5">
    <header class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <img src="/images/ai-bot-logo.jpg" alt="NAATIA" class="h-10 w-10 rounded-xl object-cover shadow" />
            <div>
                <div class="font-bold text-white">NAATIA</div>
                <div class="text-xs text-cyan-200">Automatización hotelera con WhatsApp</div>
            </div>
        </div>
        <a href="https://wa.me/{{ $salesWhatsApp }}?text=Hola%2C%20quiero%20agendar%20una%20demo%20de%20NAATIA" class="glass px-4 py-2 rounded-xl text-sm text-cyan-100 hover:text-white">Agendar demo</a>
    </header>

    <section class="grid lg:grid-cols-2 gap-8 items-center py-8">
        <div>
            <h1 class="text-4xl md:text-6xl font-black leading-tight text-white">Responde 24/7 y deja de perder reservas por no contestar</h1>
            <p class="mt-5 text-lg text-cyan-100">NAATIA atiende huéspedes automáticamente, cotiza en tiempo real y genera pagos desde WhatsApp. Tú solo confirmas disponibilidad y listo.</p>
            <p class="mt-3 text-base font-semibold text-cyan-100">Convierte más conversaciones en reservas sin contratar más personal.</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="https://wa.me/{{ $demoWhatsApp }}?text=Quiero%20probar%20el%20bot%20en%20vivo" class="neon-btn px-6 py-3 rounded-2xl">Probar bot en vivo</a>
                <a href="https://wa.me/{{ $salesWhatsApp }}?text=Quiero%20agendar%20una%20demo" class="glass px-6 py-3 rounded-2xl font-semibold text-cyan-100">Agendar demo</a>
            </div>
        </div>

        <div class="glass rounded-3xl p-5">
            <div class="text-sm text-cyan-200 mb-3">Simulación en WhatsApp</div>
            <div class="space-y-3 text-sm">
                <div class="bg-[#f8fcff] text-[#173845] rounded-xl p-3 border border-[#d6ecea]">👤 Hola, ¿tienen habitación del 16 al 19 de abril para 2?</div>
                <div class="bg-[#173845] text-white rounded-xl p-3 ml-8">🤖 Sí 🙌 El total es 3,600 MXN. ¿Prefieres anticipo o total?</div>
                <div class="bg-[#f8fcff] text-[#173845] rounded-xl p-3 border border-[#d6ecea]">👤 Anticipo con tarjeta</div>
                <div class="bg-[#1fb7b2] text-white rounded-xl p-3 ml-8">🤖 Perfecto. Te dejo link para anticipo 1,080 MXN ✅</div>
            </div>
        </div>
    </section>

    <section class="mt-6 grid md:grid-cols-3 gap-4">
        <div class="glass rounded-2xl p-6">
            <div class="text-cyan-300 text-sm font-semibold">01</div>
            <h3 class="font-bold text-lg text-white mt-2">Conectamos tu operación</h3>
            <p class="text-sm text-cyan-100 mt-1">Cargamos habitaciones, tarifas y políticas para responder con precisión.</p>
        </div>
        <div class="glass rounded-2xl p-6">
            <div class="text-cyan-300 text-sm font-semibold">02</div>
            <h3 class="font-bold text-lg text-white mt-2">Atiende y vende por WhatsApp</h3>
            <p class="text-sm text-cyan-100 mt-1">Cotiza al instante, envía link de pago y acelera cierres sin fricción.</p>
        </div>
        <div class="glass rounded-2xl p-6">
            <div class="text-cyan-300 text-sm font-semibold">03</div>
            <h3 class="font-bold text-lg text-white mt-2">Control total</h3>
            <p class="text-sm text-cyan-100 mt-1">Panel para confirmar reservas y mantener la operación ordenada.</p>
        </div>
    </section>

    <section class="mt-10 glass rounded-2xl p-6 md:p-8">
        <div class="grid md:grid-cols-3 gap-4 items-center">
            <div class="md:col-span-2">
                <h3 class="text-2xl font-extrabold text-white">Nuevo: Videos IA para hoteles</h3>
                <p class="mt-2 text-sm text-cyan-100">Complementa NAATIA con contenido tipo reel para atraer más prospectos y convertir campañas de pago.</p>
            </div>
            <div class="md:text-right">
                <a href="/video-ia" class="inline-block neon-btn px-5 py-3 rounded-xl">Ver más</a>
            </div>
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-3xl font-extrabold text-white mb-6">Planes desde $1000 al mes</h2>

        <div class="grid md:grid-cols-3 gap-4">
            <div class="card-soft rounded-2xl p-6">
                <div class="text-xs uppercase tracking-wide text-cyan-300 font-semibold">Starter</div>
                <h3 class="text-2xl font-black text-white mt-1">Desde $1000</h3>
                <p class="text-sm text-cyan-100 mt-2">Automatiza tu atención en WhatsApp y responde más rápido a huéspedes.</p>
            </div>
            <div class="card-soft rounded-2xl p-6">
                <div class="text-xs uppercase tracking-wide text-teal-300 font-semibold">Growth</div>
                <h3 class="text-2xl font-black text-white mt-1">Más reservas</h3>
                <p class="text-sm text-cyan-100 mt-2">Convierte conversaciones en reservas automáticas con cobro integrado.</p>
            </div>
            <div class="rounded-2xl p-6 bg-gradient-to-br from-[#173845] to-[#1fb7b2] text-white shadow-xl">
                <div class="text-xs uppercase tracking-wide text-cyan-100 font-semibold">Pro</div>
                <h3 class="text-2xl font-black mt-1">Operación completa</h3>
                <p class="text-sm text-cyan-50 mt-2">Atiende, vende y administra clientes con control total de tu operación.</p>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mt-5">
            <div class="flex flex-wrap gap-3 text-sm mb-4">
                <span class="chip px-3 py-1 rounded-full">✔ Automatiza WhatsApp</span>
                <span class="chip px-3 py-1 rounded-full">✔ Cotiza y cobra automáticamente</span>
                <span class="chip px-3 py-1 rounded-full">✔ Convierte más conversaciones en reservas</span>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="https://wa.me/{{ $demoWhatsApp }}?text=Quiero%20probar%20la%20demo" class="neon-btn px-5 py-3 rounded-2xl">Probar demo</a>
                <a href="https://wa.me/{{ $salesWhatsApp }}?text=Quiero%20agendar%20llamada" class="bg-[#173845] text-white px-5 py-3 rounded-2xl font-semibold">Agendar llamada</a>
            </div>
        </div>
    </section>

    <footer class="mt-10 border-t border-cyan-900/50 pt-6 pb-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <div class="text-sm text-cyan-200">© {{ now()->year }} NAATIA</div>
        <div class="flex gap-2">
            <a href="https://wa.me/{{ $salesWhatsApp }}?text=Quiero%20agendar%20llamada" class="glass px-4 py-2 rounded-xl text-cyan-100 text-sm font-semibold">Agendar llamada</a>
            <a href="https://wa.me/{{ $demoWhatsApp }}?text=Quiero%20activar%20ahora" class="neon-btn px-4 py-2 rounded-xl text-sm font-semibold">Activar ahora</a>
        </div>
    </footer>
</div>
</body>
</html>
