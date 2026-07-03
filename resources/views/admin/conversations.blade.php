<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Conversaciones - NAATIA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#eef4f4] text-[#173845]">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6">
        @include('admin.partials.nav')

        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Conversaciones del bot</h1>
            <p class="text-sm text-[#4b6974]">Monitorea cómo responde el bot por contacto.</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl shadow p-4 lg:col-span-1 max-h-[75vh] overflow-auto">
                <h2 class="font-semibold mb-3">Contactos recientes</h2>
                <div class="space-y-2 text-sm">
                    @forelse($conversations as $conversation)
                        @php
                            $active = $selectedConversation && $selectedConversation->id === $conversation->id;
                            $phone = $conversation->contact->telefono ?? 'Sin número';
                            $name = $conversation->contact->nombre ?? 'Sin nombre';
                        @endphp
                        <a href="/admin/conversations?conversation_id={{ $conversation->id }}"
                           class="block border rounded-xl p-3 {{ $active ? 'bg-[#173845] text-white border-[#173845]' : 'hover:bg-[#f5fbfb]' }}">
                            <div class="font-semibold">{{ $name }}</div>
                            <div class="text-xs {{ $active ? 'text-[#c8e6e4]' : 'text-[#4b6974]' }}">{{ $phone }}</div>
                            <div class="text-[11px] {{ $active ? 'text-[#c8e6e4]' : 'text-[#7a9199]' }} mt-1">
                                {{ optional($conversation->ultimo_mensaje_at)->format('Y-m-d H:i') ?? 'Sin actividad' }}
                            </div>
                        </a>
                    @empty
                        <div class="text-[#4b6974]">No hay conversaciones registradas.</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow p-4 lg:col-span-2 max-h-[75vh] overflow-auto">
                @if($selectedConversation)
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold">{{ $selectedConversation->contact->nombre ?? 'Contacto' }}</div>
                            <div class="text-sm text-[#4b6974]">{{ $selectedConversation->contact->telefono ?? 'Sin número' }}</div>
                        </div>
                        <a href="/admin/conversations/{{ $selectedConversation->id }}/export.json" class="text-xs bg-[#173845] text-white px-3 py-2 rounded-lg hover:opacity-90">
                            Exportar JSON
                        </a>
                    </div>

                    <div class="space-y-3 text-sm">
                        @forelse($selectedConversation->messages as $message)
                            @php $isBot = $message->sender_type === 'bot'; @endphp
                            <div class="flex {{ $isBot ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[85%] rounded-2xl px-3 py-2 {{ $isBot ? 'bg-[#173845] text-white' : 'bg-[#f3f7f7] border border-[#d9e6e5]' }}">
                                    <div class="text-[11px] mb-1 {{ $isBot ? 'text-[#c8e6e4]' : 'text-[#6b8590]' }}">{{ $isBot ? 'BOT' : 'USUARIO' }}</div>
                                    <div class="whitespace-pre-wrap">{{ $message->body }}</div>
                                    <div class="text-[10px] mt-1 {{ $isBot ? 'text-[#c8e6e4]' : 'text-[#6b8590]' }}">{{ optional($message->created_at)->format('Y-m-d H:i:s') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-[#4b6974]">No hay mensajes en esta conversación.</div>
                        @endforelse
                    </div>
                @else
                    <div class="text-[#4b6974]">Selecciona una conversación para ver el detalle.</div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
