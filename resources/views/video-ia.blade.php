<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>NAATIA Studio | Video IA para Hoteles</title>
    <link rel="icon" type="image/jpeg" href="/images/ai-bot-logo.jpg" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: radial-gradient(1100px 650px at 20% -10%, rgba(31,183,178,.25), transparent 60%),
                        radial-gradient(900px 500px at 100% 0%, rgba(18,58,78,.35), transparent 58%),
                        linear-gradient(160deg, #07141c 0%, #0b202b 45%, #0e1723 100%);
            color: #dce8ee;
            overflow-x: hidden;
        }

        .starfield::before,
        .starfield::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image: radial-gradient(rgba(255,255,255,.35) 1px, transparent 1px);
            background-size: 3px 3px;
            opacity: .12;
            animation: drift 24s linear infinite;
        }

        .starfield::after {
            opacity: .07;
            transform: scale(1.5);
            animation-duration: 40s;
        }

        .nebula-fx {
            position: fixed;
            inset: -20% -10%;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(40% 35% at 20% 30%, rgba(31,183,178,.14), transparent 70%),
                radial-gradient(45% 40% at 80% 20%, rgba(90,120,255,.12), transparent 72%),
                radial-gradient(35% 30% at 55% 75%, rgba(186,95,255,.10), transparent 70%);
            filter: blur(12px);
            animation: nebulaMove 18s ease-in-out infinite alternate;
        }

        @keyframes nebulaMove {
            from { transform: translate3d(-2%, -1%, 0) scale(1); }
            to { transform: translate3d(2%, 1%, 0) scale(1.05); }
        }

        @keyframes drift {
            from { transform: translateY(0px); }
            to { transform: translateY(-120px); }
        }

        .glass {
            background: rgba(14, 25, 36, .58);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(109, 184, 211, .25);
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
        }

        .neon-btn {
            background: linear-gradient(90deg, #1fb7b2, #31d6d0);
            color: #03222b;
            font-weight: 700;
            box-shadow: 0 12px 30px rgba(31,183,178,.35);
        }

        .hero-video-overlay {
            background: linear-gradient(120deg, rgba(4,12,18,.75), rgba(9,27,36,.45));
        }

        .hero-dynamic {
            transition: box-shadow .35s ease, border-color .35s ease, transform .35s ease;
            box-shadow: 0 18px 60px rgba(0,0,0,.45);
        }
    </style>
</head>
<body class="starfield">
    <div class="nebula-fx"></div>
    <div class="max-w-7xl mx-auto px-5 py-6 relative z-10">
        <a href="/" class="text-sm text-[#9bc7d3] hover:text-white hover:underline">← Volver a NAATIA</a>

        <section id="heroPanel" class="hero-dynamic mt-6 relative rounded-3xl overflow-hidden border border-cyan-900/40 shadow-2xl">
            <video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline poster="/images/ai-bot-logo.jpg">
                <source src="/videos/ia/hero-video.mp4" type="video/mp4" />
            </video>
            <div class="absolute inset-0 hero-video-overlay"></div>

            <div class="relative z-10 min-h-[500px] md:min-h-[620px] p-6 md:p-12 flex items-end">
                <div class="glass rounded-2xl p-6 md:p-8 max-w-2xl">
                    <div class="text-xs tracking-[.18em] uppercase text-cyan-200 mb-2">NAATIA Studio</div>
                    <h1 class="text-4xl md:text-6xl font-black leading-tight text-white">Videos IA que venden tu hotel</h1>
                    <p class="mt-4 text-base md:text-lg text-cyan-100">Contenido vertical premium para campañas, redes y remarketing. Más alcance, más interés, más reservas.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="https://wa.me/{{ $salesWhatsApp }}?text=Hola%2C%20quiero%20informaci%C3%B3n%20de%20videos%20con%20IA%20para%20mi%20hotel" class="neon-btn px-6 py-3 rounded-xl">Quiero videos con IA</a>
                        <a href="#muestras" class="glass px-6 py-3 rounded-xl text-cyan-100">Ver muestras</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="muestras" class="mt-12">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl md:text-3xl font-extrabold text-white">Portafolio creativo para marcas hoteleras</h2>
                <div class="hidden md:block text-xs text-cyan-200">Desliza →</div>
            </div>

            <div class="md:hidden flex justify-end gap-2 mb-3">
                <button id="reelsPrev" class="glass h-10 w-10 rounded-lg text-cyan-100">←</button>
                <button id="reelsNext" class="glass h-10 w-10 rounded-lg text-cyan-100">→</button>
            </div>

            <div id="reelsTrack" class="flex md:grid md:grid-cols-4 gap-5 overflow-x-auto md:overflow-visible snap-x snap-mandatory pb-2">
                <div class="glass rounded-2xl p-3 min-w-[82%] sm:min-w-[58%] md:min-w-0 snap-start">
                    <video class="w-full aspect-[9/16] rounded-xl bg-black object-cover" controls preload="metadata">
                        <source src="/videos/ia/hero-video.mp4" type="video/mp4" />
                    </video>
                    <div class="text-xs mt-2 text-cyan-200">Hero · Impacto principal</div>
                </div>
                <div class="glass rounded-2xl p-3 min-w-[82%] sm:min-w-[58%] md:min-w-0 snap-start">
                    <video class="w-full aspect-[9/16] rounded-xl bg-black object-cover" controls preload="metadata">
                        <source src="/videos/ia/reel-1.mp4" type="video/mp4" />
                    </video>
                    <div class="text-xs mt-2 text-cyan-200">Reel 1 · Hotel intro</div>
                </div>
                <div class="glass rounded-2xl p-3 min-w-[82%] sm:min-w-[58%] md:min-w-0 snap-start">
                    <video class="w-full aspect-[9/16] rounded-xl bg-black object-cover" controls preload="metadata">
                        <source src="/videos/ia/reel-2.mp4" type="video/mp4" />
                    </video>
                    <div class="text-xs mt-2 text-cyan-200">Reel 2 · Experiencia</div>
                </div>
                <div class="glass rounded-2xl p-3 min-w-[82%] sm:min-w-[58%] md:min-w-0 snap-start">
                    <video class="w-full aspect-[9/16] rounded-xl bg-black object-cover" controls preload="metadata">
                        <source src="/videos/ia/reel-3.mp4" type="video/mp4" />
                    </video>
                    <div class="text-xs mt-2 text-cyan-200">Reel 3 · Oferta</div>
                </div>
            </div>
        </section>

        <section class="mt-12 grid md:grid-cols-3 gap-5">
            <article class="glass rounded-2xl p-6 md:p-7">
                <h3 class="text-xl font-bold text-white">Servicio premium para hoteles</h3>
                <p class="mt-2 text-cyan-100 text-sm">Creamos video IA, fotografía AI-enhanced y dirección creativa para elevar tu marca y mejorar conversiones.</p>
                <ul class="mt-4 text-sm text-cyan-100 space-y-1 list-disc pl-5">
                    <li>Guión + concepto por tipo de huésped</li>
                    <li>Edición vertical optimizada para ads</li>
                    <li>Entrega lista para Instagram, Facebook y TikTok</li>
                    <li>Acompañamiento para campañas</li>
                </ul>
            </article>

            <article class="glass rounded-2xl p-6 md:p-7">
                <h3 class="text-xl font-bold text-white">Producción híbrida: IA + material real</h3>
                <p class="mt-2 text-cyan-100 text-sm">Combinamos tus tomas reales del hotel con escenas generadas por IA para lograr videos más auténticos, memorables y diferenciados.</p>
                <ul class="mt-4 text-sm text-cyan-100 space-y-1 list-disc pl-5">
                    <li>Integramos clips reales de habitaciones y amenidades</li>
                    <li>Refuerzo visual IA para storytelling y lujo</li>
                    <li>Consistencia de marca en cada pieza</li>
                </ul>
            </article>

            <article class="glass rounded-2xl p-6 md:p-7">
                <h3 class="text-xl font-bold text-white">Propuesta flexible a tu presupuesto</h3>
                <p class="mt-2 text-cyan-100 text-sm">Diseñamos un esquema de producción acorde a tu meta comercial: volumen mensual, campañas estacionales o piezas de alto impacto.</p>
                <ul class="mt-4 text-sm text-cyan-100 space-y-1 list-disc pl-5">
                    <li>Paquetes escalables por objetivos</li>
                    <li>Roadmap de contenido según temporada</li>
                    <li>Enfoque en retorno y ocupación</li>
                </ul>
            </article>
        </section>

        <div class="mt-6">
            <a href="https://wa.me/{{ $salesWhatsApp }}?text=Quiero%20cotizar%20videos%20IA%20para%20mi%20hotel" class="inline-block neon-btn px-6 py-3 rounded-xl">Hablar por WhatsApp</a>
        </div>
    </div>

    <script>
      (() => {
        const track = document.getElementById('reelsTrack');
        const prev = document.getElementById('reelsPrev');
        const next = document.getElementById('reelsNext');
        if (track && prev && next) {
          const step = () => Math.round(track.clientWidth * 0.85);

          prev.addEventListener('click', () => {
            track.scrollBy({ left: -step(), behavior: 'smooth' });
          });

          next.addEventListener('click', () => {
            track.scrollBy({ left: step(), behavior: 'smooth' });
          });
        }

        const hero = document.getElementById('heroPanel');
        if (!hero) return;

        const onScrollGlow = () => {
          const y = Math.min(window.scrollY || 0, 400);
          const intensity = y / 400;
          const blur = 60 + Math.round(40 * intensity);
          const glow = (0.20 + (0.30 * intensity)).toFixed(2);

          hero.style.boxShadow = `0 22px ${blur}px rgba(22, 214, 220, ${glow}), 0 18px 60px rgba(0,0,0,.45)`;
          hero.style.borderColor = `rgba(109, 184, 211, ${0.35 + intensity * 0.35})`;
          hero.style.transform = `translateY(${-(intensity * 2)}px)`;
        };

        window.addEventListener('scroll', onScrollGlow, { passive: true });
        onScrollGlow();
      })();
    </script>
</body>
</html>
