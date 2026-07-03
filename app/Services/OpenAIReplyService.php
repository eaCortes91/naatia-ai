<?php

namespace App\Services;

use App\Models\Hotel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIReplyService
{
    public function generateReply($hotel, string $userMessage, array $conversationHistory = []): string
    {
        $fallback = 'Con gusto te cotizo 🙌 Compárteme por favor fecha de entrada, fecha de salida y para cuántas personas sería.';

        try {
            $apiKey = (string) config('services.openai.api_key', '');
            $model = (string) config('services.openai.model', 'gpt-4.1-mini');

            if (empty($apiKey)) {
                return $fallback;
            }

            if (! $hotel instanceof Hotel) {
                return $fallback;
            }

            $hotel->loadMissing([
                'rooms' => fn ($q) => $q->where('activo', true),
                'rates' => fn ($q) => $q->where(function ($query) {
                    $today = now()->toDateString();

                    $query
                        ->whereNull('fecha_inicio')
                        ->orWhere('fecha_inicio', '<=', $today);
                })->where(function ($query) {
                    $today = now()->toDateString();

                    $query
                        ->whereNull('fecha_fin')
                        ->orWhere('fecha_fin', '>=', $today);
                })->with('room'),
                'faqs' => fn ($q) => $q->where('activo', true),
            ]);

            $hotelName = trim((string) ($hotel->nombre ?? 'Hotel'));
            $promptBase = trim((string) ($hotel->prompt_base ?? ''));
            $saludoBase = trim((string) ($hotel->saludo_base ?? ''));

            $roomsText = $hotel->rooms
                ->map(function ($room) {
                    $nombre = trim((string) ($room->nombre ?? ''));
                    $capacidad = (string) ($room->capacidad ?? 'N/D');
                    $descripcion = trim((string) ($room->descripcion ?? ''));

                    return "- Nombre: {$nombre} | Capacidad: {$capacidad} | Descripción: {$descripcion}";
                })
                ->implode("\n");

            $roomsText = $roomsText !== '' ? $roomsText : 'No hay información disponible';

            $ratesText = $hotel->rates
                ->map(function ($rate) {
                    $tipoDia = trim((string) ($rate->tipo_dia ?? ''));
                    $precio = (string) ($rate->precio ?? 'N/D');
                    $roomName = trim((string) ($rate->room?->nombre ?? 'Sin habitación relacionada'));

                    return "- Tipo de día: {$tipoDia} | Precio: {$precio} | Habitación: {$roomName}";
                })
                ->implode("\n");

            $ratesText = $ratesText !== '' ? $ratesText : 'No hay información disponible';

            $faqsText = $hotel->faqs
                ->map(function ($faq) {
                    $pregunta = trim((string) ($faq->pregunta ?? ''));
                    $respuesta = trim((string) ($faq->respuesta ?? ''));

                    return "- Pregunta: {$pregunta}\n  Respuesta: {$respuesta}";
                })
                ->implode("\n");

            $faqsText = $faqsText !== '' ? $faqsText : 'No hay información disponible';

            $mapsUrl = ($hotel->latitude && $hotel->longitude)
                ? ('https://maps.google.com/?q=' . $hotel->latitude . ',' . $hotel->longitude)
                : null;

            $hotelProfile = trim(implode("\n", array_filter([
                'Dirección: ' . trim(implode(', ', array_filter([
                    $hotel->address_line,
                    $hotel->neighborhood,
                    $hotel->city,
                    $hotel->state,
                    $hotel->postal_code ? ('CP ' . $hotel->postal_code) : null,
                ]))),
                ($hotel->latitude && $hotel->longitude) ? ('Ubicación: ' . $hotel->latitude . ', ' . $hotel->longitude) : null,
                $mapsUrl ? ('Google Maps: ' . $mapsUrl) : null,
                $hotel->check_in_time ? ('Check-in: ' . $hotel->check_in_time) : null,
                $hotel->check_out_time ? ('Check-out: ' . $hotel->check_out_time) : null,
                'Pet friendly: ' . (($hotel->pet_friendly ?? false) ? 'Sí' : 'No'),
                $hotel->amenities_text ? ('Amenidades: ' . $hotel->amenities_text) : null,
                $hotel->policies_text ? ('Políticas: ' . $hotel->policies_text) : null,
            ])));

            $contextBlock = trim(
                "Nombre del hotel:\n{$hotelName}\n\n" .
                "Ficha operativa del hotel:\n" . ($hotelProfile !== '' ? $hotelProfile : 'No hay información disponible') . "\n\n" .
                "Prompt base del hotel:\n" . ($promptBase !== '' ? $promptBase : 'No hay información disponible') . "\n\n" .
                "Saludo base:\n" . ($saludoBase !== '' ? $saludoBase : 'No hay información disponible') . "\n\n" .
                "Habitaciones activas:\n{$roomsText}\n\n" .
                "Tarifas activas:\n{$ratesText}\n\n" .
                "FAQs activas:\n{$faqsText}"
            );

            $systemPrompt = trim(
                "Eres la recepcionista virtual del hotel.\n\n" .
                "Reglas estrictas:\n" .
                "1) Nunca inventes precios, tarifas, disponibilidad, políticas o servicios.\n" .
                "2) Solo usa información explícitamente presente en el contexto proporcionado.\n" .
                "3) Si falta un dato, dilo con honestidad.\n" .
                "4) Si el usuario pregunta por precio y no hay tarifa exacta en contexto, pide explícitamente fecha de entrada, fecha de salida y número de huéspedes.\n" .
                "5) Nunca respondas 'no tengo tarifas' sin dar siguiente paso; siempre guía con una pregunta accionable para cotizar.\n" .
                "6) Si el usuario pregunta por disponibilidad y no existe disponibilidad real en contexto, no inventes que hay habitaciones disponibles.\n" .
                "6) Mantén respuestas breves, cálidas, humanas y orientadas a reservar.\n" .
                "7) Evita repetir saludo (hola/buenas) en mensajes consecutivos; saluda solo al inicio o si pasaron horas sin interacción.\n" .
                "8) Si el usuario pide informes generales, primero resume valor del hotel y luego haz 1 pregunta de calificación (fechas o número de huéspedes), sin asumir habitación específica.\n" .
                "9) No insistas con cierre de venta en cada mensaje; alterna entre ayudar y guiar. Si el usuario no está listo para reservar, responde informativo y amable sin presión.\n" .
                "10) Entrega solo texto limpio para WhatsApp, sin markdown complejo ni bloques largos.\n\n" .
                "Contexto real del hotel:\n{$contextBlock}"
            );

            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
            ];

            foreach ($conversationHistory as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $role = $item['role'] ?? null;
                $content = trim((string) ($item['content'] ?? ''));

                if (! in_array($role, ['system', 'user', 'assistant'], true) || $content === '') {
                    continue;
                }

                $messages[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }

            $messages[] = [
                'role' => 'user',
                'content' => trim($userMessage),
            ];

            Log::info('OpenAI request payload summary', [
                'model' => $model,
                'messages_count' => count($messages),
                'user_message' => $userMessage,
            ]);

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.4,
                ]);

            if (! $response->successful()) {
                Log::error('OpenAI HTTP error response', [
                    'status_code' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $fallback;
            }

            $reply = data_get($response->json(), 'choices.0.message.content');

            Log::info('OpenAI successful response content', [
                'content' => $reply,
            ]);

            if (! is_string($reply) || trim($reply) === '') {
                return $fallback;
            }

            return preg_replace('/\s+/u', ' ', trim($reply)) ?: $fallback;
        } catch (\Throwable $e) {
            Log::error('OpenAI exception while generating reply', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return $fallback;
        }
    }
}
