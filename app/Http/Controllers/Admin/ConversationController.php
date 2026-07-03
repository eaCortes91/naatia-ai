<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $hotelId = (int) (auth()->user()->hotel_id ?? 1);

        $conversations = Conversation::query()
            ->with(['contact'])
            ->where('hotel_id', $hotelId)
            ->orderByDesc('ultimo_mensaje_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $selectedConversationId = (int) $request->query('conversation_id', 0);
        $selectedConversation = null;

        if ($selectedConversationId > 0) {
            $selectedConversation = Conversation::query()
                ->with(['contact', 'messages' => fn ($q) => $q->orderBy('id')->limit(300)])
                ->where('hotel_id', $hotelId)
                ->find($selectedConversationId);
        }

        if (! $selectedConversation && $conversations->isNotEmpty()) {
            $selectedConversation = Conversation::query()
                ->with(['contact', 'messages' => fn ($q) => $q->orderBy('id')->limit(300)])
                ->where('hotel_id', $hotelId)
                ->find($conversations->first()->id);
        }

        return view('admin.conversations', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
        ]);
    }

    public function exportJson(Conversation $conversation)
    {
        $hotelId = (int) (auth()->user()->hotel_id ?? 1);
        abort_unless((int) $conversation->hotel_id === $hotelId, 403);

        $conversation->load(['contact', 'messages' => fn ($q) => $q->orderBy('id')]);

        $payload = [
            'conversation' => [
                'id' => $conversation->id,
                'hotel_id' => $conversation->hotel_id,
                'canal' => $conversation->canal,
                'estado' => $conversation->estado,
                'ultimo_mensaje_at' => optional($conversation->ultimo_mensaje_at)?->toIso8601String(),
                'contact' => [
                    'id' => $conversation->contact?->id,
                    'nombre' => $conversation->contact?->nombre,
                    'telefono' => $conversation->contact?->telefono,
                ],
            ],
            'messages' => $conversation->messages->map(fn ($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'body' => $m->body,
                'message_type' => $m->message_type,
                'external_id' => $m->external_id,
                'created_at' => optional($m->created_at)?->toIso8601String(),
                'raw_payload_json' => $m->raw_payload_json,
            ])->values()->all(),
        ];

        $filename = 'conversation-' . $conversation->id . '.json';

        return Response::make(
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}
