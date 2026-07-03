<?php

namespace App\Jobs;

use App\Models\BotFollowUp;
use App\Models\Conversation;
use App\Models\ConversationMemory;
use App\Models\HotelPackage;
use App\Models\HotelService;
use App\Models\MediaAsset;
use App\Models\Message;
use App\Models\PaymentAttempt;
use App\Models\ReceptionistAlert;
use App\Models\Reservation;
use App\Services\MercadoPagoPaymentService;
use App\Services\OpenAIReplyService;
use App\Services\QuotePdfService;
use App\Services\StripePaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppReplyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $conversationId
    ) {
    }

    public function handle(
        OpenAIReplyService $openAIReplyService
    ): void {
        $conversation = Conversation::query()
            ->with(['contact', 'hotel'])
            ->find($this->conversationId);

        if (! $conversation || ! $conversation->contact || ! $conversation->hotel) {
            return;
        }

        $lock = Cache::lock('conversation-reply:' . $conversation->id, 20);
        if (! $lock->get()) {
            return;
        }

        try {
        $pendingMessages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_type', 'user')
            ->whereNull('processed_at')
            ->orderBy('id')
            ->get();

        if ($pendingMessages->isEmpty()) {
            return;
        }

        $pendingIds = $pendingMessages->pluck('id')->all();

        $combinedUserMessage = $pendingMessages
            ->pluck('body')
            ->filter(fn ($body) => is_string($body) && trim($body) !== '')
            ->map(fn ($body) => trim((string) $body))
            ->implode("\n");

        if ($combinedUserMessage === '') {
            return;
        }

        $historyMessages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereNotIn('id', $pendingIds)
            ->whereIn('sender_type', ['user', 'bot'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->values()
            ->map(function (Message $historyMessage) {
                return [
                    'role' => $historyMessage->sender_type === 'bot' ? 'assistant' : 'user',
                    'content' => (string) $historyMessage->body,
                ];
            })
            ->all();

        $restaurantReply = $this->buildRestaurantReplyIfApplicable($conversation, $combinedUserMessage);
        if (is_string($restaurantReply) && $restaurantReply !== '') {
            $isRestaurantMenuIntent = $this->isRestaurantMenuIntent($combinedUserMessage);
            $restaurantMenuImageUrls = $isRestaurantMenuIntent
                ? $this->getRestaurantMenuImageUrls((int) $conversation->hotel_id)
                : [];
            Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'bot',
                'body' => $restaurantReply,
                'message_type' => 'text',
            ]);

            $waAccessToken = (string) config('services.whatsapp.access_token', '');
            $waPhoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
            $waApiVersion = (string) config('services.whatsapp.api_version', 'v22.0');
            if ($waAccessToken !== '' && $waPhoneNumberId !== '') {
                $this->sendWhatsAppText(
                    $waAccessToken,
                    $waApiVersion,
                    $waPhoneNumberId,
                    (string) $conversation->contact->telefono,
                    $restaurantReply
                );

                foreach ($restaurantMenuImageUrls as $menuImgUrl) {
                    $this->sendWhatsAppImage(
                        $waAccessToken,
                        $waApiVersion,
                        $waPhoneNumberId,
                        (string) $conversation->contact->telefono,
                        $menuImgUrl
                    );
                }
            }

            Message::query()->whereIn('id', $pendingIds)->update(['processed_at' => now()]);

            return;
        }

        $reservationService = app(\App\Services\ReservationContextService::class);
        $quoteService = app(\App\Services\QuoteContextService::class);

        $contextSeedText = trim(
            implode("\n", array_column($historyMessages, 'content')) . "\n" . $combinedUserMessage
        );

        $reservationContext = $reservationService->parseFromUserMessage($contextSeedText);
        $quoteContext = $quoteService->buildQuoteContext($conversation->hotel, $contextSeedText, $reservationContext);

        Log::info('Resolved reservation/quote context', [
            'conversation_id' => $conversation->id,
            'reservation_context' => $reservationContext,
            'quote_summary' => $quoteContext['quote_summary'] ?? null,
        ]);

        $reservation = $this->upsertQuotedReservation($conversation, $quoteContext, $reservationContext)
            ?? $this->findActiveReservation($conversation);

        if ($reservation) {
            $this->attachPendingPackageIfAny($conversation, $reservation);
            $reservation->refresh();
        }

        $paymentPlan = $this->detectPaymentPlan($combinedUserMessage);
        $paymentMethod = $this->detectPaymentMethod($combinedUserMessage);
        $packageAttachReply = $this->handlePackageAttachmentIfRequested($conversation, $reservation, $combinedUserMessage);
        $totalBreakdownReply = $this->buildTotalBreakdownReplyIfAsked($reservation, $combinedUserMessage);

        $this->capturePackagePreferenceSignals($conversation, $combinedUserMessage);

        $firstTouchGreetingReply = $this->buildFirstTouchGreetingReply($conversation, $historyMessages, $combinedUserMessage);
        $pdfReply = $this->buildQuotePdfReplyIfAsked($conversation, $combinedUserMessage, $reservation);
        $mediaReply = $this->buildMediaReplyIfAsked($conversation, $combinedUserMessage);
        $autoRoomImageUrls = $this->resolveAutoRoomImageUrls($conversation, $quoteContext, $mediaReply);
        $autoServiceImageUrls = $this->resolveAutoServiceImageUrls($conversation, $combinedUserMessage, $mediaReply);
        $welcomeImageUrls = $this->resolveWelcomeHotelImageUrls($conversation, $historyMessages, $mediaReply, $pdfReply);
        $packagesReply = $this->buildPackagesReplyIfAsked($conversation, $combinedUserMessage);
        $servicesReply = $this->buildServicesReplyIfAsked($conversation, $combinedUserMessage);
        $hotelInfoReply = $this->buildHotelInfoReplyIfAsked($conversation, $combinedUserMessage);
        $roomInfoReply = $this->buildRoomInfoReplyIfAsked($conversation, $combinedUserMessage, $quoteContext);
        $multiInfoReply = $this->buildCombinedInformationalReply([
            $roomInfoReply,
            $hotelInfoReply,
            $packagesReply,
            is_array($servicesReply) ? ($servicesReply['text'] ?? null) : null,
        ]);
        $stayPricingClarifyReply = $this->buildStayPricingClarifyReplyIfNeeded($combinedUserMessage);
        $missingStaySlotsReply = $this->buildMissingStaySlotsReplyIfNeeded($reservationContext, $quoteContext, $combinedUserMessage);
        $roomSelectionReply = $this->buildRoomSelectionReplyIfNeeded($conversation, $quoteContext, $reservationContext);
        if ($firstTouchGreetingReply) {
            $botReply = $firstTouchGreetingReply;
        } elseif ($pdfReply) {
            $botReply = $pdfReply['text'];
        } elseif ($mediaReply) {
            $botReply = $mediaReply['text'];
        } elseif ($packageAttachReply) {
            $botReply = $packageAttachReply;
        } elseif ($totalBreakdownReply) {
            $botReply = $totalBreakdownReply;
        } elseif ($multiInfoReply) {
            $botReply = $multiInfoReply;
        } elseif ($packagesReply) {
            $botReply = $packagesReply;
        } elseif ($servicesReply) {
            $botReply = (string) ($servicesReply['text'] ?? '');
        } elseif ($hotelInfoReply) {
            $botReply = $hotelInfoReply;
        } elseif ($stayPricingClarifyReply) {
            $botReply = $stayPricingClarifyReply;
        } elseif ($missingStaySlotsReply) {
            $botReply = $missingStaySlotsReply;
        } elseif ($roomSelectionReply) {
            $botReply = $roomSelectionReply;
        } elseif ($reservation) {
            if (! $this->reservationHasPaymentPlan($reservation) && ! $paymentPlan && ! $paymentMethod) {
                $botReply = $this->buildAskPaymentPlanReply($reservation);
            } elseif ($paymentPlan && ! $paymentMethod) {
                $this->savePaymentPlan($reservation, $paymentPlan);
                $this->saveConversationPaymentPlan($conversation, $paymentPlan);
                $botReply = $this->buildAskPaymentMethodReply($reservation, $paymentPlan);
            } elseif ($paymentMethod) {
                $resolvedPlan = $paymentPlan
                    ?? $this->getReservationPaymentPlan($reservation)
                    ?? $this->getConversationPaymentPlan($conversation);

                if (! $resolvedPlan) {
                    $botReply = $this->buildAskPaymentPlanReply($reservation);
                } else {
                    $this->savePaymentPlan($reservation, $resolvedPlan);
                    $this->saveConversationPaymentPlan($conversation, $resolvedPlan);
                    $botReply = $this->handlePaymentMethodSelection($reservation, $paymentMethod, $resolvedPlan);
                }
            } else {
                $deterministicReply = $this->buildDeterministicReply($quoteContext, $reservationContext);
                $botReply = is_string($deterministicReply) && $deterministicReply !== ''
                    ? $deterministicReply
                    : $this->buildAskPaymentPlanReply($reservation);
            }
        } else {
            $deterministicReply = $this->buildDeterministicReply($quoteContext, $reservationContext);

            if (is_string($deterministicReply) && $deterministicReply !== '') {
                $botReply = $deterministicReply;
            } else {
            $confirmedLines = [];

            if (! empty($reservationContext['normalized_summary'])) {
                $confirmedLines[] = $reservationContext['normalized_summary'];
            }

            if (! empty($quoteContext['quote_summary'])) {
                $confirmedLines[] = $quoteContext['quote_summary'];
            }

            if (! empty($confirmedLines)) {
                $historyMessages[] = [
                    'role' => 'system',
                    'content' => "DATOS CONFIRMADOS:\n- " . implode("\n- ", $confirmedLines) . "\n\nINSTRUCCIONES IMPORTANTES:\n- no vuelvas a preguntar por habitación si ya está confirmada\n- no vuelvas a preguntar por huéspedes si ya están confirmados\n- no vuelvas a preguntar por fechas si ya están confirmadas\n- no preguntes si es entre semana o fin de semana; eso ya fue resuelto\n- solo redacta la respuesta final",
                ];
            }

            $botReply = $openAIReplyService->generateReply($conversation->hotel, $combinedUserMessage, $historyMessages);
            $botReply = $this->enforceNoWeekendWeekdayReask($botReply, $quoteContext, $reservationContext) ?? $botReply;
            }
        }

        $botReply = $this->enforcePaymentFlowGuardrail($botReply, $reservation, $paymentPlan, $paymentMethod);
        $botReply = $this->enforcePricingLeadCaptureReply($botReply, $combinedUserMessage);

        $senderPhone = (string) $conversation->contact->telefono;
        $recipientPhone = $senderPhone;

        if (str_starts_with($recipientPhone, '521')) {
            $recipientPhone = '52' . substr($recipientPhone, 3);
        }

        $waAccessToken = (string) config('services.whatsapp.access_token', '');
        $waPhoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $waApiVersion = (string) config('services.whatsapp.api_version', 'v22.0');

        if ($waAccessToken === '' || $waPhoneNumberId === '') {
            Log::error('WhatsApp config missing for outbound message', [
                'conversation_id' => $conversation->id,
                'has_access_token' => $waAccessToken !== '',
                'has_phone_number_id' => $waPhoneNumberId !== '',
            ]);

            return;
        }

        $response = $this->sendWhatsAppText($waAccessToken, $waApiVersion, $waPhoneNumberId, $recipientPhone, $botReply);

        if (! $response->successful()) {
            Log::error('Failed to send WhatsApp response from job', [
                'conversation_id' => $conversation->id,
                'to' => $recipientPhone,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return;
        }

        $botReplyNormalized = mb_strtolower($botReply);

        if (str_contains($botReplyNormalized, 'quieres que agregue el paquete')) {
            $lastPackage = $this->getLastMentionedPackage($conversation);
            $yesId = $lastPackage ? ('pkg_add_yes_' . $lastPackage->id) : 'pkg_add_yes';

            $this->sendWhatsAppButtons(
                $waAccessToken,
                $waApiVersion,
                $waPhoneNumberId,
                $recipientPhone,
                '¿Qué prefieres?',
                [
                    ['id' => $yesId, 'title' => 'Agregar paquete'],
                    ['id' => 'pkg_add_no_room', 'title' => 'Solo habitación'],
                ]
            );
        }

        if (str_contains($botReplyNormalized, 'te ayudo a agregarlo a tu reservación')) {
            $lastPackage = $this->getLastMentionedPackage($conversation);

            if ($lastPackage) {
                $packageImageUrls = $this->pickRandomMediaUrls($conversation->hotel_id, 'package', (int) $lastPackage->id, 1);

                foreach ($packageImageUrls as $imgUrl) {
                    $this->sendWhatsAppImage($waAccessToken, $waApiVersion, $waPhoneNumberId, $recipientPhone, $imgUrl);
                }

                $this->sendWhatsAppButtons(
                    $waAccessToken,
                    $waApiVersion,
                    $waPhoneNumberId,
                    $recipientPhone,
                    '¿Lo agregamos a tu reservación?',
                    [
                        ['id' => 'pkg_add_yes_' . $lastPackage->id, 'title' => 'Agregar paquete'],
                        ['id' => 'pkg_add_no_room', 'title' => 'Solo habitación'],
                    ]
                );
            }
        }

        if ($pdfReply && ! empty($pdfReply['document_url'])) {
            $docResponse = $this->sendWhatsAppDocument(
                $waAccessToken,
                $waApiVersion,
                $waPhoneNumberId,
                $recipientPhone,
                (string) $pdfReply['document_url'],
                (string) ($pdfReply['filename'] ?? 'cotizacion.pdf')
            );

            if (! $docResponse->successful()) {
                Log::warning('Failed to send WhatsApp PDF document from job', [
                    'conversation_id' => $conversation->id,
                    'to' => $recipientPhone,
                    'document_url' => $pdfReply['document_url'] ?? null,
                    'status' => $docResponse->status(),
                    'response' => $docResponse->body(),
                ]);
            }
        }

        $imageUrlsToSend = [];

        if ($mediaReply && ! empty($mediaReply['images']) && is_array($mediaReply['images'])) {
            $imageUrlsToSend = array_merge($imageUrlsToSend, $mediaReply['images']);
        }

        if (! empty($autoRoomImageUrls)) {
            $imageUrlsToSend = array_merge($imageUrlsToSend, $autoRoomImageUrls);
        }

        if ($servicesReply && ! empty($servicesReply['images']) && is_array($servicesReply['images'])) {
            $imageUrlsToSend = array_merge($imageUrlsToSend, $servicesReply['images']);
        }

        if (! empty($autoServiceImageUrls)) {
            $imageUrlsToSend = array_merge($imageUrlsToSend, $autoServiceImageUrls);
        }

        if (! empty($welcomeImageUrls)) {
            $imageUrlsToSend = array_merge($imageUrlsToSend, $welcomeImageUrls);
        }

        $imageUrlsToSend = array_values(array_unique(array_filter(array_map(fn ($u) => is_string($u) ? trim($u) : '', $imageUrlsToSend))));

        $autoRoomImageSent = false;
        $autoServiceImageSent = false;
        $welcomeImageSent = false;

        foreach (array_slice($imageUrlsToSend, 0, 3) as $imageUrl) {
            $mediaResponse = $this->sendWhatsAppImage($waAccessToken, $waApiVersion, $waPhoneNumberId, $recipientPhone, $imageUrl);
            if (! $mediaResponse->successful()) {
                Log::warning('Failed to send WhatsApp image from job', [
                    'conversation_id' => $conversation->id,
                    'to' => $recipientPhone,
                    'image_url' => $imageUrl,
                    'status' => $mediaResponse->status(),
                    'response' => $mediaResponse->body(),
                ]);

                continue;
            }

            if (in_array($imageUrl, $autoRoomImageUrls, true)) {
                $autoRoomImageSent = true;
            }

            if (in_array($imageUrl, $autoServiceImageUrls, true)) {
                $autoServiceImageSent = true;
            }

            if (in_array($imageUrl, $welcomeImageUrls, true)) {
                $welcomeImageSent = true;
            }
        }

        if ($autoRoomImageSent) {
            $this->markAutoRoomImageSent($conversation);
        }

        if ($autoServiceImageSent) {
            $this->markAutoServiceImageSent($conversation);
        }

        if ($welcomeImageSent) {
            $this->markWelcomeImageSent($conversation);
        }

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'body' => $botReply,
            'external_id' => data_get($response->json(), 'messages.0.id'),
            'message_type' => 'text',
            'raw_payload_json' => json_encode($response->json(), JSON_UNESCAPED_UNICODE),
            'processed_at' => now(),
        ]);

        Message::query()
            ->whereIn('id', $pendingIds)
            ->update(['processed_at' => now()]);

        if ($reservation && ! $paymentMethod) {
            $this->schedulePackageFollowUpIfNeeded($conversation, $reservation, $quoteContext, $combinedUserMessage);
        }

        $this->scheduleReengagementFollowUpIfNeeded($conversation);

        $conversation->ultimo_mensaje_at = now();
        $conversation->save();

        $conversation->contact->last_interaction_at = now();
        $conversation->contact->save();
        } finally {
            optional($lock)->release();
        }
    }

    private function findActiveReservation(Conversation $conversation): ?Reservation
    {
        return Reservation::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [
                'quoted',
                'awaiting_online_payment',
                'awaiting_card_payment',
                'pending_transfer_proof',
                'pending_human_confirmation',
                'paid_pending_availability_check',
            ])
            ->latest('id')
            ->first();
    }

    private function upsertQuotedReservation(Conversation $conversation, array $quoteContext, array $reservationContext): ?Reservation
    {
        $matchedRoom = $quoteContext['matched_room'] ?? null;
        $preferredRoom = $quoteContext['preferred_room'] ?? null;
        $nightlyPricing = $quoteContext['nightly_pricing'] ?? [];
        $availability = $quoteContext['availability'] ?? null;
        $guests = $quoteContext['guests'] ?? null;

        $hasDates = ! empty($reservationContext['check_in']) && ! empty($reservationContext['check_out']) && ! empty($reservationContext['nights']);
        $hasRoom = is_array($matchedRoom) && ! empty($matchedRoom['id']);
        $hasExplicitRoomChoice = (is_array($preferredRoom) && ! empty($preferredRoom['id']))
            || ($hasRoom && (($availability['is_available'] ?? false) === true));
        $canCalculateTotal = ($nightlyPricing['can_calculate_total'] ?? false) === true && isset($nightlyPricing['total_estimated']);

        if (! $hasDates || ! $hasRoom || ! $hasExplicitRoomChoice || $guests === null || ! $canCalculateTotal) {
            return null;
        }

        $reservation = Reservation::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'quoted')
            ->latest('id')
            ->first();

        $meta = $reservation?->meta_json;
        if (! is_array($meta)) {
            $meta = [];
        }

        $meta['availability'] = $availability;
        $meta['source'] = 'bot_quote';

        if (isset($meta['payment_plan']) && is_string($meta['payment_plan'])) {
            $meta['payment_plan'] = in_array($meta['payment_plan'], ['deposit', 'full'], true) ? $meta['payment_plan'] : null;
        }

        return Reservation::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'status' => 'quoted',
            ],
            [
                'hotel_id' => $conversation->hotel_id,
                'contact_id' => $conversation->contact_id,
                'room_id' => (int) $matchedRoom['id'],
                'check_in' => (string) $reservationContext['check_in'],
                'check_out' => (string) $reservationContext['check_out'],
                'guests' => (int) $guests,
                'nights' => (int) $reservationContext['nights'],
                'total_amount' => (float) $nightlyPricing['total_estimated'],
                'currency' => 'MXN',
                'meta_json' => $meta,
            ]
        );
    }

    private function resolveAutoRoomImageUrls(Conversation $conversation, array $quoteContext, ?array $mediaReply): array
    {
        if ($mediaReply || ! $this->canSendAutoRoomImage($conversation)) {
            return [];
        }

        $matchedRoomId = (int) ($quoteContext['matched_room']['id'] ?? 0);
        if ($matchedRoomId <= 0) {
            return [];
        }

        $images = MediaAsset::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('entity_type', 'room')
            ->where('entity_id', $matchedRoomId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(1)
            ->pluck('url')
            ->filter()
            ->values()
            ->all();

        return is_array($images) ? $images : [];
    }

    private function resolveWelcomeHotelImageUrls(Conversation $conversation, array $historyMessages, ?array $mediaReply, ?array $pdfReply): array
    {
        if ($mediaReply || $pdfReply || ! $this->canSendWelcomeImage($conversation)) {
            return [];
        }

        $hasPriorAssistant = collect($historyMessages)->contains(fn ($m) => ($m['role'] ?? null) === 'assistant');
        if ($hasPriorAssistant) {
            return [];
        }

        $images = MediaAsset::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('entity_type', 'hotel')
            ->where('active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(1)
            ->pluck('url')
            ->filter()
            ->values()
            ->all();

        return is_array($images) ? $images : [];
    }

    private function canSendAutoRoomImage(Conversation $conversation): bool
    {
        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'auto_room_image_sent_at')
            ->latest('id')
            ->first();

        if (! $memory || ! is_string($memory->value_text) || trim($memory->value_text) === '') {
            return true;
        }

        try {
            return now()->diffInMinutes($memory->value_text) >= 180;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function markAutoRoomImageSent(Conversation $conversation): void
    {
        ConversationMemory::query()->updateOrCreate(
            ['contact_id' => $conversation->contact_id, 'key_name' => 'auto_room_image_sent_at'],
            ['value_text' => now()->toIso8601String()]
        );
    }

    private function resolveAutoServiceImageUrls(Conversation $conversation, string $message, ?array $mediaReply): array
    {
        if ($mediaReply) {
            return [];
        }

        $normalized = $this->normalizeText($message);
        $services = HotelService::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->get();

        $matchedService = $this->findMentionedService($services, $normalized);

        if (! $matchedService) {
            return [];
        }

        if (! $this->canSendAutoServiceImage($conversation)) {
            return [];
        }

        $images = MediaAsset::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('entity_type', 'service')
            ->where('entity_id', $matchedService->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(1)
            ->pluck('url')
            ->filter()
            ->values()
            ->all();

        return is_array($images) ? $images : [];
    }

    private function canSendAutoServiceImage(Conversation $conversation): bool
    {
        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'auto_service_image_sent_at')
            ->latest('id')
            ->first();

        if (! $memory || ! is_string($memory->value_text) || trim($memory->value_text) === '') {
            return true;
        }

        try {
            return now()->diffInMinutes($memory->value_text) >= 180;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function markAutoServiceImageSent(Conversation $conversation): void
    {
        ConversationMemory::query()->updateOrCreate(
            ['contact_id' => $conversation->contact_id, 'key_name' => 'auto_service_image_sent_at'],
            ['value_text' => now()->toIso8601String()]
        );
    }

    private function canSendWelcomeImage(Conversation $conversation): bool
    {
        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'welcome_image_sent_at')
            ->latest('id')
            ->first();

        return ! $memory;
    }

    private function markWelcomeImageSent(Conversation $conversation): void
    {
        ConversationMemory::query()->updateOrCreate(
            ['contact_id' => $conversation->contact_id, 'key_name' => 'welcome_image_sent_at'],
            ['value_text' => now()->toIso8601String()]
        );
    }

    private function buildFirstTouchGreetingReply(Conversation $conversation, array $historyMessages, string $message): ?string
    {
        $hasPriorAssistant = collect($historyMessages)->contains(fn ($m) => ($m['role'] ?? null) === 'assistant');
        if ($hasPriorAssistant) {
            return null;
        }

        $normalized = $this->normalizeText($message);

        $isDirectActionIntent = str_contains($normalized, 'check in')
            || str_contains($normalized, 'check-out')
            || str_contains($normalized, 'pagar')
            || str_contains($normalized, 'tarjeta')
            || str_contains($normalized, 'transferencia')
            || str_contains($normalized, 'anticipo');

        if ($isDirectActionIntent) {
            return null;
        }

        $hotelName = trim((string) ($conversation->hotel->nombre ?? 'el hotel'));

        return "Hola 👋 gracias por escribir a {$hotelName}. ¿En qué te puedo ayudar?";
    }

    private function buildQuotePdfReplyIfAsked(Conversation $conversation, string $message, ?Reservation $reservation): ?array
    {
        $normalized = mb_strtolower($message);
        $asksPdf = str_contains($normalized, 'pdf')
            || str_contains($normalized, 'cotizacion pdf')
            || str_contains($normalized, 'cotización pdf')
            || str_contains($normalized, 'manda cotizacion')
            || str_contains($normalized, 'manda cotización');

        if (! $asksPdf) {
            return null;
        }

        if (! $reservation) {
            return [
                'text' => 'Claro 🙌 Para enviarte la cotización en PDF solo compárteme fechas, huéspedes y habitación para generarla bien.',
            ];
        }

        $pdf = app(QuotePdfService::class)->generateReservationQuotePdf($reservation);

        if (! $pdf || empty($pdf['url'])) {
            return [
                'text' => 'Tu cotización está lista, pero tuve un detalle al generar el PDF. Si quieres te la envío por texto en este momento.',
            ];
        }

        return [
            'text' => 'Perfecto 🙌 Ya te comparto tu cotización en PDF.',
            'document_url' => (string) $pdf['url'],
            'filename' => (string) ($pdf['filename'] ?? 'cotizacion.pdf'),
        ];
    }

    private function buildMediaReplyIfAsked(Conversation $conversation, string $message): ?array
    {
        $normalized = mb_strtolower($message);
        $asksPhotos = str_contains($normalized, 'foto')
            || str_contains($normalized, 'fotos')
            || str_contains($normalized, 'imagen')
            || str_contains($normalized, 'imagenes')
            || str_contains($normalized, 'imágenes')
            || str_contains($normalized, 'muestrame')
            || str_contains($normalized, 'muéstrame');

        if (! $asksPhotos) {
            return null;
        }

        $packages = HotelPackage::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->get();

        $services = HotelService::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->get();

        $rooms = $conversation->hotel
            ->rooms()
            ->where('activo', true)
            ->get();

        $matchPackage = $packages->first(fn ($p) => str_contains($normalized, mb_strtolower((string) $p->name)));
        if ($matchPackage) {
            $images = $this->pickRandomMediaUrls($conversation->hotel_id, 'package', $matchPackage->id, 3);

            if (! empty($images)) {
                return [
                    'text' => "Claro 🙌 Te comparto fotos del paquete {$matchPackage->name}.",
                    'images' => $images,
                ];
            }

            return ['text' => "Aún no tengo fotos cargadas del paquete {$matchPackage->name}, pero te las consigo enseguida.", 'images' => []];
        }

        $matchService = $services->first(fn ($s) => str_contains($normalized, mb_strtolower((string) $s->name)));
        if ($matchService) {
            $images = $this->pickRandomMediaUrls($conversation->hotel_id, 'service', $matchService->id, 3);

            if (! empty($images)) {
                return [
                    'text' => "Claro 🙌 Te comparto fotos del servicio {$matchService->name}.",
                    'images' => $images,
                ];
            }

            return ['text' => "Aún no tengo fotos cargadas del servicio {$matchService->name}, pero te las comparto en breve.", 'images' => []];
        }

        $matchRoom = $rooms->first(fn ($r) => str_contains($normalized, mb_strtolower((string) $r->nombre)));
        if ($matchRoom) {
            $images = $this->pickRandomMediaUrls($conversation->hotel_id, 'room', $matchRoom->id, 3);

            if (! empty($images)) {
                return [
                    'text' => "Claro 🙌 Te comparto fotos de la habitación {$matchRoom->nombre}.",
                    'images' => $images,
                ];
            }

            return ['text' => "Aún no tengo fotos cargadas de {$matchRoom->nombre}, pero te las comparto en breve.", 'images' => []];
        }

        $asksRoomsGeneric = str_contains($normalized, 'habitacion')
            || str_contains($normalized, 'habitaciones')
            || str_contains($normalized, 'cuarto')
            || str_contains($normalized, 'cuartos')
            || str_contains($normalized, 'suite')
            || str_contains($normalized, 'suites');

        if ($asksRoomsGeneric) {
            $roomImages = $this->pickRandomMediaUrls($conversation->hotel_id, 'room', null, 3);

            if (! empty($roomImages)) {
                return [
                    'text' => 'Claro 🙌 Te comparto fotos de nuestras habitaciones.',
                    'images' => $roomImages,
                ];
            }

            return ['text' => 'Aún no tengo fotos de habitaciones cargadas, pero te las comparto en breve.', 'images' => []];
        }

        $asksHotel = str_contains($normalized, 'hotel') || str_contains($normalized, 'lugar') || str_contains($normalized, 'instalaciones');

        if ($asksHotel) {
            $mixed = array_values(array_unique(array_merge(
                $this->pickRandomMediaUrls($conversation->hotel_id, 'room', null, 1),
                $this->pickRandomMediaUrls($conversation->hotel_id, 'service', null, 1),
                $this->pickRandomMediaUrls($conversation->hotel_id, 'package', null, 1)
            )));

            if (count($mixed) < 3) {
                $mixed = array_values(array_unique(array_merge($mixed, $this->pickRandomMediaUrls($conversation->hotel_id, 'hotel', null, 3 - count($mixed)))));
            }

            if (! empty($mixed)) {
                return [
                    'text' => 'Claro 🙌 Te comparto un mini pack de fotos del hotel, habitaciones y experiencias.',
                    'images' => array_slice($mixed, 0, 3),
                ];
            }
        }

        $hotelImages = $this->pickRandomMediaUrls($conversation->hotel_id, 'hotel', null, 3);

        if (! empty($hotelImages)) {
            return [
                'text' => 'Claro 🙌 Te comparto algunas fotos del hotel.',
                'images' => $hotelImages,
            ];
        }

        return ['text' => 'Por ahora no tengo fotos cargadas en el sistema, pero te las compartimos enseguida.', 'images' => []];
    }

    private function pickRandomMediaUrls(int $hotelId, string $entityType, ?int $entityId, int $limit = 3): array
    {
        $query = MediaAsset::query()
            ->where('hotel_id', $hotelId)
            ->where('entity_type', $entityType)
            ->where('active', true);

        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }

        return $query->inRandomOrder()
            ->limit($limit)
            ->pluck('url')
            ->filter()
            ->values()
            ->all();
    }

    private function buildCombinedInformationalReply(array $replies): ?string
    {
        $chunks = [];

        foreach ($replies as $reply) {
            $text = is_string($reply) ? trim($reply) : '';
            if ($text === '') {
                continue;
            }

            if (! in_array($text, $chunks, true)) {
                $chunks[] = $text;
            }
        }

        if (empty($chunks)) {
            return null;
        }

        return implode("\n\n", array_slice($chunks, 0, 3));
    }

    private function buildRoomInfoReplyIfAsked(Conversation $conversation, string $message, array $quoteContext): ?string
    {
        $normalized = $this->normalizeText($message);
        $mentionsRoom = str_contains($normalized, 'habitacion')
            || str_contains($normalized, 'habitaciones')
            || str_contains($normalized, 'cuarto')
            || str_contains($normalized, 'suite')
            || str_contains($normalized, 'room');

        if (! $mentionsRoom) {
            return null;
        }

        $reserveIntent = str_contains($normalized, 'quiero')
            || str_contains($normalized, 'reserv')
            || str_contains($normalized, 'apartar')
            || str_contains($normalized, 'me quedo')
            || str_contains($normalized, 'selecciono');

        // If user is selecting/booking a room, let payment/reservation flow continue.
        if ($reserveIntent) {
            return null;
        }

        $asksInfo = str_contains($normalized, 'info')
            || str_contains($normalized, 'detalle')
            || str_contains($normalized, 'caracter')
            || str_contains($normalized, 'como es')
            || str_contains($normalized, 'amenidades');

        if (! $asksInfo) {
            return null;
        }

        $room = $quoteContext['preferred_room'] ?? $quoteContext['matched_room'] ?? null;
        if (! is_array($room) || empty($room['nombre'])) {
            return null;
        }

        $desc = trim((string) ($room['descripcion'] ?? ''));
        $cap = (int) ($room['capacidad'] ?? 0);

        $reply = "Sobre {$room['nombre']}: capacidad para {$cap} persona(s).";
        if ($desc !== '') {
            $reply .= " {$desc}";
        }

        return $reply;
    }

    private function buildHotelInfoReplyIfAsked(Conversation $conversation, string $message): ?string
    {
        $normalized = mb_strtolower($message);

        $asksLocation = str_contains($normalized, 'ubicacion') || str_contains($normalized, 'ubicación') || str_contains($normalized, 'direccion') || str_contains($normalized, 'dirección') || str_contains($normalized, 'donde estan') || str_contains($normalized, 'dónde están');
        $asksTimes = str_contains($normalized, 'check in') || str_contains($normalized, 'check-in') || str_contains($normalized, 'check out') || str_contains($normalized, 'checkout') || str_contains($normalized, 'hora de entrada') || str_contains($normalized, 'hora de salida');
        $asksAmenities = str_contains($normalized, 'amenidades') || str_contains($normalized, 'amenity') || str_contains($normalized, 'alberca') || str_contains($normalized, 'spa') || str_contains($normalized, 'temazcal') || str_contains($normalized, 'yoga');
        $asksPets = str_contains($normalized, 'pet') || str_contains($normalized, 'mascota') || str_contains($normalized, 'perrito') || str_contains($normalized, 'perro');
        $asksGeneralHotelInfo = str_contains($normalized, 'info del hotel')
            || str_contains($normalized, 'informacion del hotel')
            || str_contains($normalized, 'información del hotel')
            || str_contains($normalized, 'sobre el hotel')
            || str_contains($normalized, 'informes del hotel')
            || str_contains($normalized, 'me das info del hotel');

        if (! $asksLocation && ! $asksTimes && ! $asksAmenities && ! $asksPets && ! $asksGeneralHotelInfo) {
            return null;
        }

        $hotel = $conversation->hotel;
        $chunks = [];

        if ($asksGeneralHotelInfo) {
            $general = [];
            $general[] = 'Hotel: ' . ($hotel->nombre ?? 'Hotel boutique');
            if ($hotel->check_in_time || $hotel->check_out_time) {
                $general[] = 'Horarios: Check-in ' . ($hotel->check_in_time ?? 'N/D') . ' | Check-out ' . ($hotel->check_out_time ?? 'N/D');
            }
            if (! empty($hotel->amenities_text)) {
                $general[] = 'Amenidades: ' . $hotel->amenities_text;
            }

            $chunks[] = implode("\n", $general);
            $asksAmenities = false;
        }

        if ($asksLocation) {
            $address = trim(implode(', ', array_filter([
                $hotel->address_line,
                $hotel->neighborhood,
                $hotel->city,
                $hotel->state,
                $hotel->postal_code ? ('CP ' . $hotel->postal_code) : null,
            ])));

            if ($address !== '') {
                $chunks[] = "📍 Dirección: {$address}";
            }

            if ($hotel->latitude && $hotel->longitude) {
                $chunks[] = 'Google Maps: https://maps.google.com/?q=' . $hotel->latitude . ',' . $hotel->longitude;
            }
        }

        if ($asksTimes) {
            $times = [];
            if ($hotel->check_in_time) {
                $times[] = 'Check-in: ' . $hotel->check_in_time;
            }
            if ($hotel->check_out_time) {
                $times[] = 'Check-out: ' . $hotel->check_out_time;
            }
            if (! empty($times)) {
                $chunks[] = implode(' | ', $times);
            }
        }

        if ($asksAmenities && ! empty($hotel->amenities_text)) {
            $chunks[] = 'Amenidades: ' . $hotel->amenities_text;
        }

        if ($asksPets) {
            $chunks[] = ($hotel->pet_friendly ?? false)
                ? 'Sí somos pet friendly 🐾'
                : 'Por ahora no somos pet friendly.';
        }

        if (empty($chunks)) {
            return 'Con gusto te comparto esa info 🙌 En este momento no la tengo cargada completa, pero te la confirmo enseguida.';
        }

        $hasGreeting = str_contains($normalized, 'hola') || str_contains($normalized, 'buenas') || str_contains($normalized, 'buen dia') || str_contains($normalized, 'buen día') || str_contains($normalized, 'buenas tardes') || str_contains($normalized, 'buenas noches');

        $reply = implode("\n", $chunks);
        return $hasGreeting ? "¡Hola! " . $reply : $reply;
    }

    private function buildPackagesReplyIfAsked(Conversation $conversation, string $message): ?string
    {
        $normalized = $this->normalizeText($message);
        $asksPackages = str_contains($normalized, 'paquete')
            || str_contains($normalized, 'promo')
            || str_contains($normalized, 'promocion')
            || str_contains($normalized, 'experiencia')
            || str_contains($normalized, 'recomiend');

        if (! $asksPackages) {
            return null;
        }

        $packages = HotelPackage::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->orderBy('price')
            ->limit(12)
            ->get();

        if ($packages->isEmpty()) {
            return 'Claro 🙌 Por ahora no tenemos paquetes cargados, pero te puedo armar una opción personalizada según tus fechas y número de huéspedes.';
        }

        $matchingPackage = $this->findMentionedPackage($packages, $normalized);
        if ($matchingPackage) {
            $this->rememberLastPackageMention($conversation, $matchingPackage);

            $description = trim((string) ($matchingPackage->description ?? ''));
            $price = number_format((float) $matchingPackage->price, 2);

            if ($description === '') {
                return "Sí 🙌 El paquete {$matchingPackage->name} está disponible por {$price} MXN por noche. Si quieres te ayudo a agregarlo a tu reservación.";
            }

            return "Sí 🙌 *{$matchingPackage->name}*\n{$description}\nPrecio: {$price} MXN por noche\n\nSi quieres, te ayudo a agregarlo a tu reservación.";
        }

        $wantsRecommendation = str_contains($normalized, 'recomiend') || str_contains($normalized, 'cual') || str_contains($normalized, 'cual me conviene');
        if ($wantsRecommendation) {
            $recommended = $this->recommendPackageByIntent($packages, $normalized);
            if ($recommended) {
                $this->rememberLastPackageMention($conversation, $recommended);
                $price = number_format((float) $recommended->price, 2);
                $description = trim((string) ($recommended->description ?? ''));

                return "Te recomiendo *{$recommended->name}* 🙌 ({$price} MXN). {$description}\n\nSi quieres, te lo agrego a tu reservación.";
            }
        }

        $lines = $packages->take(8)->map(fn ($p) => "• {$p->name} — $" . number_format((float) $p->price, 2) . ' MXN por noche')->implode("\n");

        return "Sí, tenemos estos paquetes disponibles:\n{$lines}\n\nSi quieres, te recomiendo cuál te conviene más para tu viaje.";
    }

    private function findMentionedPackage($packages, string $normalized): ?HotelPackage
    {
        foreach ($packages as $package) {
            if (! $package instanceof HotelPackage) {
                continue;
            }

            $name = $this->normalizeText((string) ($package->name ?? ''));
            if ($name === '') {
                continue;
            }

            if (str_contains($normalized, $name)) {
                return $package;
            }
        }

        return null;
    }

    private function rememberLastPackageMention(Conversation $conversation, HotelPackage $package): void
    {
        ConversationMemory::query()->updateOrCreate(
            ['contact_id' => $conversation->contact_id, 'key_name' => 'last_package_id'],
            ['value_text' => (string) $package->id]
        );
    }

    private function getLastMentionedPackage(Conversation $conversation): ?HotelPackage
    {
        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'last_package_id')
            ->latest('id')
            ->first();

        $packageId = (int) ($memory?->value_text ?? 0);
        if ($packageId <= 0) {
            return null;
        }

        return HotelPackage::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->find($packageId);
    }

    private function recommendPackageByIntent($packages, string $normalized): ?HotelPackage
    {
        if (str_contains($normalized, 'temazcal')) {
            $hit = $packages->first(function ($p) {
                $blob = $this->normalizeText((string) (($p->name ?? '') . ' ' . ($p->description ?? '')));
                return str_contains($blob, 'temazcal');
            });
            if ($hit) {
                return $hit;
            }
        }

        if (str_contains($normalized, 'pareja') || str_contains($normalized, 'enamor')) {
            $hit = $packages->first(function ($p) {
                $blob = $this->normalizeText((string) (($p->name ?? '') . ' ' . ($p->description ?? '')));
                return str_contains($blob, 'pareja') || str_contains($blob, 'enamor');
            });
            if ($hit) {
                return $hit;
            }
        }

        return $packages->sortBy('price')->first();
    }

    private function handlePackageAttachmentIfRequested(Conversation $conversation, ?Reservation $reservation, string $message): ?string
    {
        $normalized = $this->normalizeText($message);

        $packages = HotelPackage::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->get();

        $mentionedPackage = $this->findMentionedPackage($packages, $normalized);
        if ($mentionedPackage) {
            $this->rememberLastPackageMention($conversation, $mentionedPackage);
        }

        $buttonPackageId = null;
        if (preg_match('/package_id:(\d+)/u', $normalized, $m)) {
            $buttonPackageId = (int) ($m[1] ?? 0);
        }

        $lastPackage = $mentionedPackage
            ?? ($buttonPackageId ? HotelPackage::query()->where('hotel_id', $conversation->hotel_id)->where('active', true)->find($buttonPackageId) : null)
            ?? $this->getLastMentionedPackage($conversation);

        $explicitPackageAttach = str_contains($normalized, 'agregarlo')
            || str_contains($normalized, 'agregalo')
            || str_contains($normalized, 'lo quiero')
            || str_contains($normalized, 'si quiero agregar')
            || str_contains($normalized, 'quiero ese paquete')
            || str_contains($normalized, 'quiero el paquete')
            || str_contains($normalized, 'quiero reservar ese paquete')
            || str_contains($normalized, 'reservar ese paquete')
            || str_contains($normalized, 'reservar paquete')
            || str_contains($normalized, 'apartar paquete')
            || str_contains($normalized, 'agregar paquete')
            || str_contains($normalized, 'lo agregamos')
            || (str_contains($normalized, 'agregar') && str_contains($normalized, 'paquete'))
            || ($mentionedPackage && str_contains($normalized, 'paquete') && (
                str_contains($normalized, 'por favor')
                || str_contains($normalized, 'si')
                || str_contains($normalized, 'quiero')
                || str_contains($normalized, 'reserv')
            ));

        $genericReserveIntent = str_contains($normalized, 'quiero reservar')
            || str_contains($normalized, 'si quiero reservar')
            || str_contains($normalized, 'vamos a reservar');

        $roomIntent = str_contains($normalized, 'habitacion')
            || str_contains($normalized, 'habitación')
            || str_contains($normalized, 'cuarto')
            || str_contains($normalized, 'suite');

        $onlyRoomIntent = str_contains($normalized, 'solo habitacion')
            || str_contains($normalized, 'solo habitación')
            || str_contains($normalized, 'sin paquete');

        if ($onlyRoomIntent) {
            ConversationMemory::query()
                ->where('contact_id', $conversation->contact_id)
                ->where('key_name', 'pending_package_id')
                ->delete();

            return 'Perfecto 🙌 Seguimos solo con habitación. Si quieres, te muestro opciones y avanzamos con tu reserva.';
        }

        if ($lastPackage && $genericReserveIntent && ! $explicitPackageAttach && ! $roomIntent) {
            return "Perfecto 🙌 ¿Quieres que agregue el paquete {$lastPackage->name} a tu reservación, o prefieres reservar solo habitación?";
        }

        $wantsAttach = $explicitPackageAttach;

        if (! $wantsAttach) {
            return null;
        }

        Log::info('Package attach intent detected', [
            'conversation_id' => $conversation->id,
            'message' => $message,
            'has_last_package' => (bool) $lastPackage,
            'mentioned_package_id' => $mentionedPackage?->id,
            'button_package_id' => $buttonPackageId,
        ]);

        $package = $lastPackage;
        if (! $package) {
            return 'Con gusto te lo agrego 🙌 ¿Cuál paquete te gustaría incluir?';
        }

        if (! $reservation) {
            $this->rememberPendingPackage($conversation, $package);

            return "¡Claro! 🙌 Ya tengo en cuenta el paquete {$package->name}. Para agregarlo formalmente, compárteme por favor fechas, número de huéspedes y la habitación que prefieres.";
        }

        $this->applyPackageToReservation($reservation, $package);

        $totalWithPackage = $this->resolveReservationTotalWithAddons($reservation);

        return "Perfecto 🙌 Ya agregué el paquete {$package->name} a tu reservación. Nuevo total: " . number_format($totalWithPackage, 2) . " MXN. ¿Prefieres pagar anticipo o liquidar total?";
    }

    private function rememberPendingPackage(Conversation $conversation, HotelPackage $package): void
    {
        ConversationMemory::query()->updateOrCreate(
            ['contact_id' => $conversation->contact_id, 'key_name' => 'pending_package_id'],
            ['value_text' => (string) $package->id]
        );
    }

    private function consumePendingPackage(Conversation $conversation): ?HotelPackage
    {
        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'pending_package_id')
            ->latest('id')
            ->first();

        $packageId = (int) ($memory?->value_text ?? 0);

        if ($memory) {
            $memory->delete();
        }

        if ($packageId <= 0) {
            return null;
        }

        return HotelPackage::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->find($packageId);
    }

    private function applyPackageToReservation(Reservation $reservation, HotelPackage $package): void
    {
        $meta = $reservation->meta_json;
        if (! is_array($meta)) {
            $meta = [];
        }

        $meta['selected_package'] = [
            'id' => (int) $package->id,
            'name' => (string) $package->name,
            'price' => (float) $package->price,
        ];

        $reservation->meta_json = $meta;
        $reservation->save();
    }

    private function attachPendingPackageIfAny(Conversation $conversation, Reservation $reservation): void
    {
        $pending = $this->consumePendingPackage($conversation);
        if (! $pending) {
            return;
        }

        $this->applyPackageToReservation($reservation, $pending);
        $this->rememberLastPackageMention($conversation, $pending);
    }

    private function buildStayPricingClarifyReplyIfNeeded(string $message): ?string
    {
        $normalized = mb_strtolower($message);

        $asksLodgingPrice = str_contains($normalized, 'hospedaje')
            || str_contains($normalized, 'noche')
            || str_contains($normalized, 'cuanto cuesta dormir')
            || str_contains($normalized, 'precio habitacion')
            || str_contains($normalized, 'precio habitación')
            || str_contains($normalized, 'tarifa por noche');

        $isServiceQuestion = str_contains($normalized, 'temazcal')
            || str_contains($normalized, 'cabalgata')
            || str_contains($normalized, 'masaje')
            || str_contains($normalized, 'spa')
            || str_contains($normalized, 'yoga')
            || str_contains($normalized, 'servicio');

        if (! $asksLodgingPrice || $isServiceQuestion) {
            return null;
        }

        $hasDateHint = preg_match('/\b\d{1,2}\s*(?:de\s*)?[a-záéíóú]+\b/iu', $message)
            || preg_match('/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/u', $message)
            || str_contains($normalized, 'del ') || str_contains($normalized, 'al ');

        $hasGuestHint = preg_match('/\b\d{1,2}\s*(?:personas?|huesped(?:es)?|huésped(?:es)?)\b/iu', $message)
            || str_contains($normalized, 'para dos')
            || str_contains($normalized, 'para 2')
            || str_contains($normalized, 'pareja');

        if (! $hasDateHint && ! $hasGuestHint) {
            return 'Con gusto 🙌 Para darte tarifa exacta, compárteme por favor fecha de entrada/salida y para cuántas personas sería.';
        }

        if (! $hasDateHint) {
            return 'Claro 🙌 ¿Qué fechas tienes en mente (entrada y salida)? Con eso te doy tarifa exacta.';
        }

        if (! $hasGuestHint) {
            return 'Perfecto 🙌 Ya con esas fechas, ¿para cuántas personas sería tu estancia?';
        }

        return null;
    }

    private function buildMissingStaySlotsReplyIfNeeded(array $reservationContext, array $quoteContext, string $message): ?string
    {
        $normalized = $this->normalizeText($message);

        $guests = $quoteContext['guests'] ?? null;
        $hasDates = ! empty($reservationContext['check_in']) && ! empty($reservationContext['check_out']);

        $asksLodging = str_contains($normalized, 'hospedaje')
            || str_contains($normalized, 'noche')
            || str_contains($normalized, 'reserv')
            || str_contains($normalized, 'cuanto cuesta')
            || str_contains($normalized, 'cuanto sale')
            || $hasDates;

        if (! $asksLodging) {
            return null;
        }

        if ($hasDates && $guests === null) {
            return 'Perfecto 🙌 Ya con esas fechas, ¿para cuántas personas sería tu estancia?';
        }

        return null;
    }

    private function buildRoomSelectionReplyIfNeeded(Conversation $conversation, array $quoteContext, array $reservationContext): ?string
    {
        $hasDates = ! empty($reservationContext['check_in']) && ! empty($reservationContext['check_out']);
        $guests = (int) ($quoteContext['guests'] ?? 0);
        $preferredRoom = $quoteContext['preferred_room'] ?? null;
        $matchedRoom = $quoteContext['matched_room'] ?? null;

        if (! $hasDates || $guests <= 0 || ! empty($preferredRoom) || ! empty($matchedRoom)) {
            return null;
        }

        $nightsBreakdown = $this->buildNightsBreakdownFromDates(
            (string) ($reservationContext['check_in'] ?? ''),
            (string) ($reservationContext['check_out'] ?? '')
        );

        if (empty($nightsBreakdown)) {
            $nightsBreakdown = $reservationContext['nights_breakdown'] ?? [];
        }

        if (empty($nightsBreakdown)) {
            return null;
        }

        $rooms = $conversation->hotel->rooms()
            ->where('activo', true)
            ->where('capacidad', '>=', $guests)
            ->orderBy('capacidad')
            ->limit(5)
            ->get();

        if ($rooms->isEmpty()) {
            return 'Por ahora no tengo una habitación activa para ese número de huéspedes. Si quieres, te ayudo a revisar otra fecha o configuración.';
        }

        $options = [];
        foreach ($rooms as $room) {
            $availability = app(\App\Services\AvailabilityService::class)->getAvailability(
                $conversation->hotel,
                $room,
                (string) $reservationContext['check_in'],
                (string) $reservationContext['check_out']
            );

            if (! (($availability['is_available'] ?? false) === true)) {
                continue;
            }

            $total = 0.0;
            foreach ($nightsBreakdown as $night) {
                $type = (string) ($night['type'] ?? 'entre_semana');
                $total += $type === 'fin_de_semana'
                    ? (float) ($room->weekend_rate ?? 0)
                    : (float) ($room->weekday_rate ?? 0);
            }

            if ($total <= 0) {
                continue;
            }

            $options[] = "• {$room->nombre} (cap. {$room->capacidad}) — $" . number_format($total, 2) . ' MXN total';

            if (count($options) >= 3) {
                break;
            }
        }

        if (empty($options)) {
            return 'Sí tengo tus fechas 🙌, pero no encontré opción disponible con tarifa completa para ese rango. ¿Quieres que te proponga fechas cercanas?';
        }

        return "¡Perfecto! Para {$guests} persona(s), del {$reservationContext['check_in']} al {$reservationContext['check_out']}, tengo estas opciones:\n"
            . implode("\n", $options)
            . "\n\nSi quieres, dime cuál habitación te interesa y te comparto más detalle para avanzar. También puedo recomendarte paquetes (romántico, wellness, etc.) para complementar tu estancia.";
    }

    private function findMentionedService($services, string $normalized): ?HotelService
    {
        $messageTokens = $this->tokenize($normalized);

        foreach ($services as $service) {
            if (! $service instanceof HotelService) {
                continue;
            }

            $name = $this->normalizeText((string) ($service->name ?? ''));
            if ($name === '' || mb_strlen($name) < 3) {
                continue;
            }

            $nameSingular = rtrim($name, 's');
            if (str_contains($normalized, $name) || ($nameSingular !== '' && str_contains($normalized, $nameSingular))) {
                return $service;
            }

            $nameTokens = $this->tokenize($name);
            foreach ($nameTokens as $nt) {
                if (mb_strlen($nt) < 4) {
                    continue;
                }

                foreach ($messageTokens as $mt) {
                    if ($mt === $nt || levenshtein($mt, $nt) <= 1) {
                        return $service;
                    }
                }
            }
        }

        return null;
    }

    private function findRecentServiceFromMemory(Conversation $conversation, $services, string $normalized): ?HotelService
    {
        $isFollowup = str_contains($normalized, 'costo')
            || str_contains($normalized, 'precio')
            || str_contains($normalized, 'info')
            || str_contains($normalized, 'incluye')
            || str_contains($normalized, 'cuanto')
            || str_contains($normalized, 'cuanto cuesta');

        if (! $isFollowup) {
            return null;
        }

        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'last_service_id')
            ->latest('id')
            ->first();

        $serviceId = (int) ($memory?->value_text ?? 0);
        if ($serviceId <= 0) {
            return null;
        }

        foreach ($services as $service) {
            if ($service instanceof HotelService && (int) $service->id === $serviceId) {
                return $service;
            }
        }

        return null;
    }

    private function rememberLastServiceMention(Conversation $conversation, HotelService $service): void
    {
        ConversationMemory::query()->updateOrCreate(
            ['contact_id' => $conversation->contact_id, 'key_name' => 'last_service_id'],
            ['value_text' => (string) $service->id]
        );
    }

    private function tokenize(string $value): array
    {
        $value = $this->normalizeText($value);
        $parts = preg_split('/[^a-z0-9]+/u', $value) ?: [];
        return array_values(array_filter($parts, fn ($p) => is_string($p) && $p !== ''));
    }

    private function buildRestaurantReplyIfApplicable(Conversation $conversation, string $userMessage): ?string
    {
        if ((string) ($conversation->hotel->business_type ?? 'hotel') !== 'restaurant') {
            return null;
        }

        $normalized = $this->normalizeText($userMessage);
        $asksMenu = $this->isRestaurantMenuIntent($normalized);

        $isOpen = $this->isRestaurantOpenNow((int) $conversation->hotel_id);

        if (! $isOpen && $asksMenu) {
            return $this->buildRestaurantClosedReply((int) $conversation->hotel_id);
        }

        if (! $isOpen) {
            return $this->buildRestaurantClosedReply((int) $conversation->hotel_id);
        }

        $asksMenu = str_contains($normalized, 'menu')
            || str_contains($normalized, 'menú')
            || str_contains($normalized, 'carta')
            || str_contains($normalized, 'que tienen')
            || str_contains($normalized, 'qué tienen')
            || str_contains($normalized, 'que venden')
            || str_contains($normalized, 'quiero ver');

        if ($asksMenu || mb_strlen(trim($normalized)) < 5) {
            return $this->buildRestaurantMenuReply((int) $conversation->hotel_id);
        }

        $orderReply = $this->buildRestaurantOrderQuoteReply((int) $conversation->hotel_id, $normalized);
        if ($orderReply !== null) {
            return $orderReply;
        }

        return "¡Va! 🍽️ Te puedo ayudar con tu pedido. Si quieres, escribe *menú* para mandarte opciones con precio, o dime directamente qué platillo se te antoja.";
    }

    private function isRestaurantOpenNow(int $hotelId): bool
    {
        $day = (int) now('America/Mexico_City')->dayOfWeek;
        $hour = now('America/Mexico_City')->format('H:i:s');

        $row = DB::table('restaurant_business_hours')
            ->where('hotel_id', $hotelId)
            ->where('day_of_week', $day)
            ->first();

        if (! $row || (int) $row->is_open !== 1) {
            return false;
        }

        if (! $row->opens_at || ! $row->closes_at) {
            return false;
        }

        return $hour >= (string) $row->opens_at && $hour <= (string) $row->closes_at;
    }

    private function buildRestaurantClosedReply(int $hotelId): string
    {
        $rows = DB::table('restaurant_business_hours')
            ->where('hotel_id', $hotelId)
            ->where('is_open', 1)
            ->orderBy('day_of_week')
            ->get();

        if ($rows->isEmpty()) {
            return 'Gracias por escribir 🙌 En este momento estamos cerrados. Si gustas, te tomamos pedido cuando abramos.';
        }

        $days = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $schedule = $rows->map(function ($r) use ($days) {
            return ($days[(int) $r->day_of_week] ?? 'día') . ' ' . substr((string) $r->opens_at, 0, 5) . '-' . substr((string) $r->closes_at, 0, 5);
        })->implode(', ');

        return "Gracias por escribir 🙌 Ahorita estamos cerrados. Nuestro horario es: {$schedule}. Si quieres, te comparto el menú para que vayas eligiendo.";
    }

    private function buildRestaurantMenuReply(int $hotelId): string
    {
        $categories = DB::table('restaurant_categories')
            ->where('hotel_id', $hotelId)
            ->where('active', 1)
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        if ($categories->isEmpty()) {
            return 'Ahorita no tengo menú cargado 😅 En unos minutos te lo compartimos completo por aquí.';
        }

        $lines = ["¡Claro! Aquí van algunas opciones del menú 🍽️"]; 

        foreach ($categories as $category) {
            $items = DB::table('restaurant_items')
                ->where('hotel_id', $hotelId)
                ->where('category_id', $category->id)
                ->where('active', 1)
                ->where('stock_status', 'available')
                ->orderBy('name')
                ->limit(4)
                ->get();

            if ($items->isEmpty()) {
                continue;
            }

            $lines[] = "\n*{$category->name}*";
            foreach ($items as $item) {
                $price = number_format((float) $item->price, 0);
                $lines[] = "• {$item->name} - $ {$price}";
            }
        }

        $lines[] = "\nSi quieres, te tomo pedido aquí mismo. Dime platillo + cantidad 🙌";

        return implode("\n", $lines);
    }

    private function buildRestaurantOrderQuoteReply(int $hotelId, string $normalizedMessage): ?string
    {
        $items = DB::table('restaurant_items')
            ->where('hotel_id', $hotelId)
            ->where('active', 1)
            ->where('stock_status', 'available')
            ->get(['id', 'name', 'price']);

        if ($items->isEmpty()) {
            return null;
        }

        $normalizedRawMessage = $this->normalizeText($normalizedMessage);
        $normalizedMessage = $this->stripRestaurantStopwords($normalizedMessage);

        $aliases = [
            'emparedado de jamon con tocino' => 'jamon con tocino',
            'pan frances' => 'pan frances',
            'cafe de olla' => 'cafe de olla',
        ];

        $found = [];
        foreach ($items as $item) {
            $itemName = $this->normalizeText((string) $item->name);
            if ($itemName === '') {
                continue;
            }

            foreach ($aliases as $needle => $aliasToken) {
                if (str_contains($normalizedRawMessage, $needle) && str_contains($itemName, $aliasToken)) {
                    $found[] = [
                        'name' => (string) $item->name,
                        'qty' => 1,
                        'price' => (float) $item->price,
                    ];
                    continue 2;
                }
            }

            if (str_contains($normalizedMessage, $itemName)) {
                $found[] = [
                    'name' => (string) $item->name,
                    'qty' => 1,
                    'price' => (float) $item->price,
                ];
                continue;
            }

            $tokens = array_values(array_filter(explode(' ', $itemName), fn ($t) => mb_strlen($t) >= 4));
            $hits = 0;
            foreach ($tokens as $token) {
                if (str_contains($normalizedMessage, $token)) {
                    $hits++;
                }
            }

            if ($hits >= 2) {
                $found[] = [
                    'name' => (string) $item->name,
                    'qty' => 1,
                    'price' => (float) $item->price,
                ];
            }
        }

        if (empty($found)) {
            return null;
        }

        $lines = ["¡Buenísimo! Ya tengo tu pedido 🙌", ""]; 
        $subtotal = 0.0;
        foreach ($found as $row) {
            $lineTotal = $row['qty'] * $row['price'];
            $subtotal += $lineTotal;
            $lines[] = "• {$row['qty']} x {$row['name']} — $" . number_format($lineTotal, 2);
        }

        $lines[] = "";
        $lines[] = "Subtotal: $" . number_format($subtotal, 2) . " MXN";
        $lines[] = "¿Tu pedido será para *recoger* o para *envío*?";

        return implode("\n", $lines);
    }

    private function stripRestaurantStopwords(string $value): string
    {
        $value = $this->normalizeText($value);
        $stopwords = ['quiero', 'un', 'una', 'unos', 'unas', 'por favor', 'favor', 'de', 'con', 'para', 'el', 'la', 'los', 'las', 'y'];

        foreach ($stopwords as $stop) {
            $value = trim(str_replace($stop, ' ', $value));
        }

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function isRestaurantMenuIntent(string $value): bool
    {
        $normalized = $this->normalizeText($value);

        return str_contains($normalized, 'menu')
            || str_contains($normalized, 'menú')
            || str_contains($normalized, 'carta')
            || str_contains($normalized, 'que tienen')
            || str_contains($normalized, 'qué tienen')
            || str_contains($normalized, 'que venden')
            || str_contains($normalized, 'quiero ver');
    }

    private function getRestaurantMenuImageUrls(int $hotelId): array
    {
        if ($hotelId !== 2) {
            return [];
        }

        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            return [];
        }

        return [
            $base . '/menu/santo-pecado/menu-1.jpg',
            $base . '/menu/santo-pecado/menu-2.jpg',
            $base . '/menu/santo-pecado/menu-3.jpg',
            $base . '/menu/santo-pecado/menu-4.jpg',
        ];
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $value);
        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    private function buildServicesReplyIfAsked(Conversation $conversation, string $message): ?array
    {
        $normalized = $this->normalizeText($message);
        $asksServices = str_contains($normalized, 'servicio')
            || str_contains($normalized, 'masaje')
            || str_contains($normalized, 'temazcal')
            || str_contains($normalized, 'yoga')
            || str_contains($normalized, 'cabalgata')
            || str_contains($normalized, 'spa');

        if (! $asksServices) {
            return null;
        }

        $services = HotelService::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true)
            ->orderBy('price')
            ->limit(20)
            ->get();

        if ($services->isEmpty()) {
            return [
                'text' => 'Claro 🙌 Por ahora no tenemos servicios adicionales cargados, pero te puedo ayudar con tu reservación y te aviso cuando estén disponibles.',
                'images' => [],
            ];
        }

        $specificService = $this->findMentionedService($services, $normalized)
            ?? $this->findRecentServiceFromMemory($conversation, $services, $normalized);

        if ($specificService) {
            $this->rememberLastServiceMention($conversation, $specificService);

            $price = number_format((float) $specificService->price, 2);
            $description = trim((string) ($specificService->description ?? ''));
            $images = $this->pickRandomMediaUrls($conversation->hotel_id, 'service', (int) $specificService->id, 1);

            if ($description === '') {
                return [
                    'text' => "Sí 🙌 El servicio {$specificService->name} tiene costo de {$price} MXN.",
                    'images' => $images,
                ];
            }

            return [
                'text' => "Sí 🙌 {$specificService->name} cuesta {$price} MXN. {$description}",
                'images' => $images,
            ];
        }

        $lines = $services->take(8)->map(fn ($s) => "• {$s->name} — $" . number_format((float) $s->price, 2) . ' MXN')->implode("\n");

        $serviceImages = $this->pickRandomMediaUrls($conversation->hotel_id, 'service', null, 3);

        return [
            'text' => "Sí, contamos con estos servicios:\n{$lines}\n\nSi quieres, te digo cuál te conviene más según tu plan.",
            'images' => $serviceImages,
        ];
    }

    private function schedulePackageFollowUpIfNeeded(Conversation $conversation, Reservation $reservation, array $quoteContext, string $messageText): void
    {
        $hasGuests = ($quoteContext['guests'] ?? null) !== null;
        $hasRoom = ! empty($quoteContext['matched_room']['id'] ?? null);

        if (! $hasGuests || ! $hasRoom) {
            return;
        }

        if ($this->hasRecentlyDeclinedPackage($conversation)) {
            return;
        }

        $package = $this->pickBestPackageForLead($conversation, $quoteContext, $messageText);

        if (! $package) {
            return;
        }

        $scheduled = now()->addHours(2);
        $cutoff = now()->copy()->setTime(21, 0, 0);

        if (now()->gte($cutoff) || $scheduled->gte($cutoff)) {
            $scheduled = now()->copy()->addDay()->setTime(9, 0, 0);
        }

        BotFollowUp::query()
            ->where('conversation_id', $conversation->id)
            ->where('type', 'package_nudge')
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now()]);

        BotFollowUp::query()->create([
            'conversation_id' => $conversation->id,
            'reservation_id' => $reservation->id,
            'type' => 'package_nudge',
            'scheduled_at' => $scheduled,
            'payload_json' => [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'package_price' => (float) $package->price,
            ],
        ]);
    }

    private function scheduleReengagementFollowUpIfNeeded(Conversation $conversation): void
    {
        $alreadyPending = BotFollowUp::query()
            ->where('conversation_id', $conversation->id)
            ->where('type', 'reengagement_nudge')
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->exists();

        if ($alreadyPending) {
            return;
        }

        $scheduled = now()->addHours(3);

        // Quiet hours: 21:00 to 07:59
        if ((int) $scheduled->format('H') >= 21 || (int) $scheduled->format('H') < 8) {
            $scheduled = $scheduled->copy()->addDay()->setTime(9, 0, 0);
        }

        BotFollowUp::query()->create([
            'conversation_id' => $conversation->id,
            'type' => 'reengagement_nudge',
            'scheduled_at' => $scheduled,
            'payload_json' => [
                'message' => 'Hola 🙌 Solo te doy seguimiento: si quieres, te comparto opciones, fotos y cotización para cerrar tu reserva hoy.',
            ],
        ]);
    }

    private function pickBestPackageForLead(Conversation $conversation, array $quoteContext, string $messageText): ?HotelPackage
    {
        $normalized = mb_strtolower($messageText);
        $guests = (int) ($quoteContext['guests'] ?? 0);

        $query = HotelPackage::query()
            ->where('hotel_id', $conversation->hotel_id)
            ->where('active', true);

        if ($guests >= 3 || str_contains($normalized, 'familia')) {
            $candidate = (clone $query)
                ->where(function ($q) {
                    $q->where('name', 'like', '%famil%')
                        ->orWhere('description', 'like', '%famil%');
                })->orderBy('price')->first();
            if ($candidate) {
                return $candidate;
            }
        }

        if (str_contains($normalized, 'pareja') || str_contains($normalized, 'romantic') || str_contains($normalized, 'anivers')) {
            $candidate = (clone $query)
                ->where(function ($q) {
                    $q->where('name', 'like', '%pareja%')
                        ->orWhere('name', 'like', '%rom%')
                        ->orWhere('description', 'like', '%rom%');
                })->orderBy('price')->first();
            if ($candidate) {
                return $candidate;
            }
        }

        if (str_contains($normalized, 'spa') || str_contains($normalized, 'relaj') || str_contains($normalized, 'wellness') || str_contains($normalized, 'temazcal')) {
            $candidate = (clone $query)
                ->where(function ($q) {
                    $q->where('name', 'like', '%wellness%')
                        ->orWhere('description', 'like', '%wellness%')
                        ->orWhere('description', 'like', '%spa%')
                        ->orWhere('description', 'like', '%temazcal%');
                })->orderBy('price')->first();
            if ($candidate) {
                return $candidate;
            }
        }

        return $query->orderBy('price')->first();
    }

    private function capturePackagePreferenceSignals(Conversation $conversation, string $messageText): void
    {
        $normalized = mb_strtolower($messageText);

        $declined = str_contains($normalized, 'sin paquete')
            || str_contains($normalized, 'no paquete')
            || str_contains($normalized, 'no gracias')
            || str_contains($normalized, 'solo habitacion')
            || str_contains($normalized, 'solo habitación');

        if ($declined) {
            ConversationMemory::query()->updateOrCreate(
                ['contact_id' => $conversation->contact_id, 'key_name' => 'package_declined_at'],
                ['value_text' => now()->toIso8601String()]
            );
        }
    }

    private function hasRecentlyDeclinedPackage(Conversation $conversation): bool
    {
        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'package_declined_at')
            ->latest('id')
            ->first();

        if (! $memory || ! is_string($memory->value_text)) {
            return false;
        }

        try {
            return now()->diffInHours($memory->value_text) < 72;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function detectPaymentMethod(string $text): ?string
    {
        $normalized = mb_strtolower($text);

        if (str_contains($normalized, 'tarjeta') || str_contains($normalized, 'stripe') || str_contains($normalized, 'mercado pago')) {
            return 'card';
        }

        if (str_contains($normalized, 'transfer') || str_contains($normalized, 'spei')) {
            return 'transfer';
        }

        if (str_contains($normalized, 'recepcion') || str_contains($normalized, 'efectivo')) {
            return 'cash_reception';
        }

        return null;
    }

    private function detectPaymentPlan(string $text): ?string
    {
        $normalized = mb_strtolower($text);

        if (
            str_contains($normalized, 'anticipo') ||
            str_contains($normalized, 'enganche') ||
            str_contains($normalized, 'apartar') ||
            preg_match('/\b(\d{1,3})\s*%/u', $normalized)
        ) {
            return 'deposit';
        }

        if (
            str_contains($normalized, 'liquidar') ||
            str_contains($normalized, 'total') ||
            str_contains($normalized, 'completo')
        ) {
            return 'full';
        }

        return null;
    }

    private function getDepositPercent(): int
    {
        $value = (int) env('RESERVATION_DEPOSIT_PERCENT', 30);
        if ($value < 1) {
            return 30;
        }

        if ($value > 100) {
            return 100;
        }

        return $value;
    }

    private function getDefaultPaymentProvider(): string
    {
        $configured = strtolower((string) env('PAYMENT_PROVIDER_DEFAULT', 'mercadopago'));
        $mpEnabled = $this->mercadoPagoEnabled();
        $stripeEnabled = $this->stripeEnabled();

        if ($configured === 'mercadopago' && $mpEnabled) {
            return 'mercadopago';
        }

        if ($configured === 'stripe' && $stripeEnabled) {
            return 'stripe';
        }

        if ($mpEnabled) {
            return 'mercadopago';
        }

        if ($stripeEnabled) {
            return 'stripe';
        }

        return 'mercadopago';
    }

    private function mercadoPagoEnabled(): bool
    {
        return trim((string) env('MERCADOPAGO_ACCESS_TOKEN', '')) !== '';
    }

    private function stripeEnabled(): bool
    {
        return trim((string) config('services.stripe.secret_key', '')) !== '';
    }

    private function reservationHasPaymentPlan(Reservation $reservation): bool
    {
        $meta = $reservation->meta_json ?? [];

        return is_array($meta) && isset($meta['payment_plan']);
    }

    private function getReservationPaymentPlan(Reservation $reservation): ?string
    {
        $meta = $reservation->meta_json ?? [];

        if (! is_array($meta)) {
            return null;
        }

        $plan = $meta['payment_plan'] ?? null;

        return in_array($plan, ['deposit', 'full'], true) ? $plan : null;
    }

    private function getConversationPaymentPlan(Conversation $conversation): ?string
    {
        $memory = ConversationMemory::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('key_name', 'payment_plan')
            ->latest('id')
            ->first();

        $value = is_string($memory?->value_text) ? trim($memory->value_text) : null;

        return in_array($value, ['deposit', 'full'], true) ? $value : null;
    }

    private function savePaymentPlan(Reservation $reservation, string $paymentPlan): void
    {
        $meta = $reservation->meta_json ?? [];
        if (! is_array($meta)) {
            $meta = [];
        }

        $meta['payment_plan'] = $paymentPlan;
        $reservation->meta_json = $meta;
        $reservation->save();
    }

    private function saveConversationPaymentPlan(Conversation $conversation, string $paymentPlan): void
    {
        ConversationMemory::query()->updateOrCreate(
            ['contact_id' => $conversation->contact_id, 'key_name' => 'payment_plan'],
            ['value_text' => $paymentPlan]
        );
    }

    private function buildTotalBreakdownReplyIfAsked(?Reservation $reservation, string $message): ?string
    {
        if (! $reservation) {
            return null;
        }

        $normalized = $this->normalizeText($message);
        $asksWhy = str_contains($normalized, 'por que') || str_contains($normalized, 'porque') || str_contains($normalized, 'desglose') || str_contains($normalized, 'como se calcula');

        if (! $asksWhy) {
            return null;
        }

        $meta = $reservation->meta_json;
        if (is_array($meta) && isset($meta['selected_package']['price'])) {
            $packageName = (string) ($meta['selected_package']['name'] ?? 'Paquete');
            $packageNightly = (float) ($meta['selected_package']['price'] ?? 0);
            $nights = max(1, (int) ($reservation->nights ?? 1));
            $total = $this->resolveReservationTotalWithAddons($reservation);

            return "Claro 🙌 El total es {$total} MXN porque el {$packageName} está en {$packageNightly} MXN por noche x {$nights} noche(s).";
        }

        return "Claro 🙌 El total es por la habitación seleccionada y el número de noches de tu estancia.";
    }

    private function buildAskPaymentPlanReply(Reservation $reservation): string
    {
        $depositPercent = $this->getDepositPercent();
        $total = number_format($this->resolveReservationTotalWithAddons($reservation), 2, '.', '');

        return "Excelente 🙌 El total de tu estancia es {$total} MXN. Para asegurar tu reservación pedimos anticipo del {$depositPercent}%. ¿Prefieres pagar anticipo o liquidar total?";
    }

    private function buildAskPaymentMethodReply(Reservation $reservation, string $paymentPlan): string
    {
        $amount = $this->resolveChargeAmount($reservation, $paymentPlan);
        $label = $paymentPlan === 'deposit' ? 'anticipo' : 'total';

        return "Perfecto. Vamos con {$label} de {$amount} MXN 💳 ¿Qué método prefieres: tarjeta, transferencia o pago en recepción?";
    }

    private function resolveReservationTotalWithAddons(Reservation $reservation): float
    {
        $meta = $reservation->meta_json;
        $nights = max(1, (int) ($reservation->nights ?? 1));

        if (is_array($meta) && isset($meta['selected_package']['price'])) {
            $packagePrice = (float) $meta['selected_package']['price'];

            // Package price is handled as nightly and includes lodging by default.
            return round($packagePrice * $nights, 2);
        }

        $stayTotal = $this->resolveReservationStayBaseTotal($reservation);
        return round($stayTotal, 2);
    }

    private function resolveChargeAmount(Reservation $reservation, string $paymentPlan): float
    {
        $total = $this->resolveReservationTotalWithAddons($reservation);

        if ($paymentPlan === 'deposit') {
            $amount = $total * ($this->getDepositPercent() / 100);

            return round($amount, 2);
        }

        return round($total, 2);
    }

    private function resolveReservationStayBaseTotal(Reservation $reservation): float
    {
        $reservation->loadMissing('room');
        $room = $reservation->room;

        if (! $room) {
            return (float) $reservation->total_amount;
        }

        $checkIn = $reservation->check_in;
        $checkOut = $reservation->check_out;

        if (! $checkIn || ! $checkOut || $checkOut->lte($checkIn)) {
            return (float) $reservation->total_amount;
        }

        $total = 0.0;
        $current = $checkIn->copy();

        while ($current->lt($checkOut)) {
            $isWeekend = in_array($current->dayOfWeekIso, [5, 6, 7], true);
            $total += $isWeekend ? (float) ($room->weekend_rate ?? 0) : (float) ($room->weekday_rate ?? 0);
            $current->addDay();
        }

        return $total > 0 ? $total : (float) $reservation->total_amount;
    }

    private function buildNightsBreakdownFromDates(string $checkIn, string $checkOut): array
    {
        if ($checkIn === '' || $checkOut === '') {
            return [];
        }

        try {
            $start = \Carbon\Carbon::parse($checkIn)->startOfDay();
            $end = \Carbon\Carbon::parse($checkOut)->startOfDay();
        } catch (\Throwable $e) {
            return [];
        }

        if ($end->lte($start)) {
            return [];
        }

        $items = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $items[] = [
                'date' => $cursor->toDateString(),
                'type' => in_array($cursor->dayOfWeekIso, [5, 6, 7], true) ? 'fin_de_semana' : 'entre_semana',
            ];
            $cursor->addDay();
        }

        return $items;
    }

    private function createReceptionistAlert(Reservation $reservation, string $type, string $title, string $body, ?int $dueHours = null): void
    {
        ReceptionistAlert::query()->create([
            'hotel_id' => $reservation->hotel_id,
            'reservation_id' => $reservation->id,
            'type' => $type,
            'status' => 'pending',
            'title' => $title,
            'body' => $body,
            'due_at' => $dueHours ? now()->addHours($dueHours) : null,
        ]);
    }

    private function handlePaymentMethodSelection(Reservation $reservation, string $paymentMethod, string $paymentPlan): string
    {
        $reservation->payment_method = $paymentMethod;
        $chargeAmount = $this->resolveChargeAmount($reservation, $paymentPlan);
        $chargeAmountText = number_format($chargeAmount, 2, '.', '');
        $planLabel = $paymentPlan === 'deposit' ? 'anticipo' : 'total';

        $defaultProvider = $this->getDefaultPaymentProvider();

        if (in_array($paymentMethod, ['card', 'transfer'], true)) {
            $providerOrder = $defaultProvider === 'stripe'
                ? ['stripe', 'mercadopago']
                : ['mercadopago', 'stripe'];

            foreach ($providerOrder as $provider) {
                if ($provider === 'mercadopago' && $this->mercadoPagoEnabled()) {
                    $reservation->status = 'awaiting_online_payment';
                    $reservation->hold_expires_at = now()->addMinutes(30);
                    $reservation->save();

                    $preference = app(MercadoPagoPaymentService::class)
                        ->createCheckoutLink($reservation, $chargeAmount, $paymentPlan);

                    if ($preference && ! empty($preference['init_point'])) {
                        $payment = PaymentAttempt::query()->create([
                            'reservation_id' => $reservation->id,
                            'provider' => 'mercadopago',
                            'provider_ref' => (string) ($preference['id'] ?? ''),
                            'status' => 'pending',
                            'amount' => $chargeAmount,
                            'currency' => 'MXN',
                            'payment_url' => (string) $preference['init_point'],
                            'payload_json' => [
                                'payment_plan' => $paymentPlan,
                                'preference' => $preference['raw'] ?? null,
                            ],
                        ]);

                        $methodHint = $paymentMethod === 'transfer' ? ' (puedes usar transferencia/SPEI dentro de Mercado Pago)' : '';

                        return "Excelente 🙌 Te dejo el link de pago para {$planLabel} ({$chargeAmountText} MXN){$methodHint}: {$payment->payment_url} (vigente 30 min). En cuanto se refleje, te confirmamos por aquí.\n\nTambién contamos con servicios (masajes, temazcal, yoga). Puedes pedirlos en cualquier momento.";
                    }
                }

                if ($provider === 'stripe' && $this->stripeEnabled()) {
                    $reservation->status = 'awaiting_card_payment';
                    $reservation->hold_expires_at = now()->addMinutes(30);
                    $reservation->save();

                    $checkout = app(StripePaymentService::class)
                        ->createCheckoutLink($reservation, $chargeAmount, $paymentPlan);

                    if ($checkout && ! empty($checkout['url'])) {
                        $checkoutUrl = trim((string) $checkout['url']);

                        $payment = PaymentAttempt::query()->create([
                            'reservation_id' => $reservation->id,
                            'provider' => 'stripe',
                            'provider_ref' => (string) ($checkout['id'] ?? ''),
                            'status' => 'pending',
                            'amount' => $chargeAmount,
                            'currency' => strtoupper((string) config('services.stripe.currency', 'mxn')),
                            'payment_url' => $checkoutUrl,
                            'payload_json' => [
                                'mode' => 'stripe_checkout',
                                'payment_plan' => $paymentPlan,
                                'checkout_id' => (string) ($checkout['id'] ?? ''),
                            ],
                        ]);

                        return "Excelente 🙌 Te dejo el link seguro para pagar {$planLabel} ({$chargeAmountText} MXN): {$payment->payment_url} (vigente 30 min). En cuanto se refleje, te confirmamos por aquí.\n\nDemo: usa tarjeta de prueba Stripe 4242 4242 4242 4242, fecha futura y CVC de 3 dígitos.";
                    }
                }
            }

            return 'No tengo proveedor de pago configurado en este momento. Si quieres, avanzamos con pago en recepción y te contacta recepción para confirmar.';
        }

        $reservation->status = 'pending_human_confirmation';
        $reservation->hold_expires_at = now()->addHours(12);
        $reservation->save();

        PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => 'cash_reception',
            'status' => 'pending',
            'amount' => $chargeAmount,
            'currency' => 'MXN',
            'payload_json' => ['note' => 'cash_at_reception_followup_required', 'payment_plan' => $paymentPlan],
        ]);

        $this->createReceptionistAlert(
            $reservation,
            'cash_followup',
            'Confirmar pre-reserva de pago en recepción',
            "Reserva #{$reservation->id}: cliente eligió pago en recepción ({$planLabel} {$chargeAmountText} MXN). Dar seguimiento en horario laboral.",
            12
        );

        return "Perfecto. Lo dejamos como pre-reserva para pagar en recepción ({$planLabel} {$chargeAmountText} MXN) ✅ Te contacta recepción en horario laboral para confirmar disponibilidad final y cierre.\n\nTambién contamos con servicios (masajes, temazcal, yoga). Si quieres, te paso opciones.";
    }

    private function sendWhatsAppText(string $token, string $apiVersion, string $phoneNumberId, string $to, string $text)
    {
        return Http::withToken($token)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $text,
                ],
            ]);
    }

    private function sendWhatsAppImage(string $token, string $apiVersion, string $phoneNumberId, string $to, string $imageUrl)
    {
        return Http::withToken($token)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'image',
                'image' => [
                    'link' => $imageUrl,
                ],
            ]);
    }

    private function sendWhatsAppDocument(string $token, string $apiVersion, string $phoneNumberId, string $to, string $documentUrl, string $filename)
    {
        return Http::withToken($token)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'document',
                'document' => [
                    'link' => $documentUrl,
                    'filename' => $filename,
                ],
            ]);
    }

    private function sendWhatsAppButtons(string $token, string $apiVersion, string $phoneNumberId, string $to, string $text, array $buttons): void
    {
        $interactiveButtons = collect($buttons)
            ->filter(fn ($b) => is_array($b) && ! empty($b['id']) && ! empty($b['title']))
            ->take(3)
            ->map(fn ($b) => [
                'type' => 'reply',
                'reply' => [
                    'id' => (string) $b['id'],
                    'title' => (string) $b['title'],
                ],
            ])
            ->values()
            ->all();

        if (empty($interactiveButtons)) {
            return;
        }

        Http::withToken($token)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => $text],
                    'action' => ['buttons' => $interactiveButtons],
                ],
            ]);
    }

    private function enforcePricingLeadCaptureReply(string $botReply, string $userMessage): string
    {
        $normalizedUser = $this->normalizeText($userMessage);
        $asksPrice = str_contains($normalizedUser, 'precio')
            || str_contains($normalizedUser, 'costo')
            || str_contains($normalizedUser, 'tarifa')
            || str_contains($normalizedUser, 'hospedaje')
            || str_contains($normalizedUser, 'noche');

        if (! $asksPrice) {
            return $botReply;
        }

        $normalizedReply = $this->normalizeText($botReply);
        $soundsLikeNoData = str_contains($normalizedReply, 'no tengo informacion')
            || str_contains($normalizedReply, 'no tengo tarifas')
            || str_contains($normalizedReply, 'no cuento con tarifas')
            || str_contains($normalizedReply, 'no tengo disponible la tarifa');

        if (! $soundsLikeNoData) {
            return $botReply;
        }

        return 'Con gusto te cotizo 🙌 Compárteme por favor fecha de entrada, fecha de salida y para cuántas personas sería tu estancia.';
    }

    private function enforcePaymentFlowGuardrail(string $botReply, ?Reservation $reservation, ?string $detectedPaymentPlan, ?string $detectedPaymentMethod): string
    {
        if (! $reservation) {
            return $botReply;
        }

        $status = (string) ($reservation->status ?? '');
        if (in_array($status, ['paid_pending_availability_check', 'confirmed'], true)) {
            return $botReply;
        }

        $normalized = mb_strtolower($botReply);
        $looksLikeFinalConfirmation = str_contains($normalized, 'confirmar tu reserva')
            || str_contains($normalized, 'reserva confirmada')
            || str_contains($normalized, 'te enviaré los detalles finales')
            || str_contains($normalized, 'gracias por elegir');

        if (! $looksLikeFinalConfirmation) {
            return $botReply;
        }

        $resolvedPlan = $detectedPaymentPlan ?? $this->getReservationPaymentPlan($reservation);

        if (! $resolvedPlan) {
            return $this->buildAskPaymentPlanReply($reservation);
        }

        if (! $detectedPaymentMethod && ! in_array((string) $reservation->payment_method, ['card', 'transfer', 'cash_reception'], true)) {
            return $this->buildAskPaymentMethodReply($reservation, $resolvedPlan);
        }

        return $botReply;
    }

    private function buildDeterministicReply(array $quoteContext, array $reservationContext): ?string
    {
        $matchedRoom = $quoteContext['matched_room'] ?? null;
        $preferredRoom = $quoteContext['preferred_room'] ?? null;
        $guests = $quoteContext['guests'] ?? null;
        $nightlyPricing = $quoteContext['nightly_pricing'] ?? [];
        $availability = $quoteContext['availability'] ?? null;

        $hasDates = ! empty($reservationContext['check_in']) && ! empty($reservationContext['check_out']) && ! empty($reservationContext['nights']);
        $hasRoom = is_array($matchedRoom) && ! empty($matchedRoom['nombre']);
        $hasExplicitRoomChoice = (is_array($preferredRoom) && ! empty($preferredRoom['id']))
            || ($hasRoom && ((($availability['is_available'] ?? null) === true) || $availability === null));
        $canCalculateTotal = ($nightlyPricing['can_calculate_total'] ?? false) === true && isset($nightlyPricing['total_estimated']);

        if ($hasDates && $hasRoom && $hasExplicitRoomChoice && $guests !== null && $canCalculateTotal) {
            $total = number_format((float) $nightlyPricing['total_estimated'], 2, '.', '');
            $roomName = (string) $matchedRoom['nombre'];
            $nights = (int) $reservationContext['nights'];
            $checkIn = (string) $reservationContext['check_in'];
            $checkOut = (string) $reservationContext['check_out'];
            $isAvailable = is_array($availability) ? (($availability['is_available'] ?? false) === true) : null;

            if ($isAvailable === false) {
                return "Gracias por los datos 🙌 Para {$roomName}, del {$checkIn} al {$checkOut}, ahorita no tengo cupo preliminar en todas las noches. Si quieres, te propongo otra habitación o fechas cercanas.";
            }

            $depositPercent = $this->getDepositPercent();
            return "Perfecto 🙌 Para {$guests} huésped(es) en {$roomName}, del {$checkIn} al {$checkOut} ({$nights} noche(s)), el total es de {$total} MXN. Para asegurar la reservación pedimos anticipo del {$depositPercent}%. ¿Prefieres pagar anticipo o liquidar total?";
        }

        return null;
    }

    private function enforceNoWeekendWeekdayReask(string $llmReply, array $quoteContext, array $reservationContext): ?string
    {
        $normalized = mb_strtolower($llmReply);

        $asksWeekendWeekday = str_contains($normalized, 'entre semana') || str_contains($normalized, 'fin de semana');

        if (! $asksWeekendWeekday) {
            return null;
        }

        $deterministicReply = $this->buildDeterministicReply($quoteContext, $reservationContext);

        if ($deterministicReply) {
            return $deterministicReply;
        }

        return null;
    }
}
