<?php

use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:whatsapp-webhook');

Route::post('/payments/mercadopago/webhook', [MercadoPagoWebhookController::class, 'receive'])
    ->middleware('throttle:mercadopago-webhook');

Route::post('/payments/stripe/webhook', [StripeWebhookController::class, 'receive']);
