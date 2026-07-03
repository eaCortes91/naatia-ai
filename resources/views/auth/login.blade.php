<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login · NAATIA</title>
  <link rel="icon" type="image/jpeg" href="/images/ai-bot-logo.jpg" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --ai-bg: #eef4f4;
      --ai-card: #ffffff;
      --ai-border: #d5e4e3;
      --ai-dark: #173845;
      --ai-accent: #1fb7b2;
    }
    .ai-title { font-family: 'Orbitron', sans-serif; letter-spacing: 0.06em; }
  </style>
</head>
<body class="min-h-screen bg-[var(--ai-bg)] text-[var(--ai-dark)]">
  <div class="min-h-screen grid place-items-center p-4 sm:p-6">
    <div class="w-full max-w-md bg-[var(--ai-card)] rounded-3xl shadow-xl border border-[var(--ai-border)] p-6 sm:p-8 relative z-10">
      <div class="flex flex-col items-center text-center mb-5">
        <img src="/images/ai-bot-logo.jpg" alt="NAATIA logo" class="h-16 w-16 rounded-full object-cover object-top shadow-sm border border-[var(--ai-border)] bg-white" />
        <h1 class="ai-title text-2xl mt-3">NAATIA</h1>
        <p class="text-sm text-slate-500 mt-1">Panel inteligente para hoteles</p>
      </div>

      @if($errors->any())
        <div class="mb-4 rounded-xl bg-[#e9f8f7] text-[#166a69] p-3 text-sm">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="/login" class="space-y-4">
        @csrf
        <div>
          <label class="text-sm">Correo</label>
          <input type="email" name="email" required class="mt-1 w-full rounded-xl border border-[var(--ai-border)] px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[var(--ai-accent)]/40" />
        </div>
        <div>
          <label class="text-sm">Contraseña</label>
          <input type="password" name="password" required class="mt-1 w-full rounded-xl border border-[var(--ai-border)] px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[var(--ai-accent)]/40" />
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" /> Recordarme</label>
        <button class="w-full rounded-xl bg-[var(--ai-dark)] text-white py-2.5 font-medium hover:bg-[#0f2d38] transition">Entrar</button>
      </form>
    </div>
  </div>
</body>
</html>
