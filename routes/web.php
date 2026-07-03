<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\HotelProfileController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MediaAssetController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\ReceptionistAlertController;
use App\Http\Controllers\Admin\ReservationDecisionController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $demoWhatsApp = preg_replace('/\D+/', '', (string) env('DEMO_WHATSAPP', '5215500000000'));
    $salesWhatsApp = preg_replace('/\D+/', '', (string) env('SALES_WHATSAPP', '5215500000000'));

    return view('landing', [
        'demoWhatsApp' => $demoWhatsApp,
        'salesWhatsApp' => $salesWhatsApp,
    ]);
});

Route::get('/landing', function () {
    $demoWhatsApp = preg_replace('/\D+/', '', (string) env('DEMO_WHATSAPP', '5215500000000'));
    $salesWhatsApp = preg_replace('/\D+/', '', (string) env('SALES_WHATSAPP', '5215500000000'));

    return view('landing', [
        'demoWhatsApp' => $demoWhatsApp,
        'salesWhatsApp' => $salesWhatsApp,
    ]);
});

Route::get('/video-ia', function () {
    $salesWhatsApp = preg_replace('/\D+/', '', (string) env('SALES_WHATSAPP', '5215500000000'));

    return view('video-ia', [
        'salesWhatsApp' => $salesWhatsApp,
    ]);
});

Route::view('/payment/success', 'payment.success');
Route::view('/payment/failure', 'payment.failure');
Route::view('/payment/pending', 'payment.pending');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/rooms/{room}', [InventoryController::class, 'updateRoom']);
    Route::post('/inventory/block', [InventoryController::class, 'blockRange']);

    Route::post('/room-types', [RoomTypeController::class, 'store']);
    Route::post('/room-types/{roomType}', [RoomTypeController::class, 'update']);
    Route::post('/room-types/{roomType}/delete', [RoomTypeController::class, 'destroy']);

    Route::post('/rooms', [RoomController::class, 'store']);
    Route::post('/rooms/{room}', [RoomController::class, 'update']);
    Route::post('/rooms/{room}/delete', [RoomController::class, 'destroy']);

    Route::post('/services', [ServiceController::class, 'store']);
    Route::post('/services/{service}', [ServiceController::class, 'update']);
    Route::post('/services/{service}/delete', [ServiceController::class, 'destroy']);

    Route::post('/packages', [PackageController::class, 'store']);
    Route::post('/packages/{package}', [PackageController::class, 'update']);
    Route::post('/packages/{package}/delete', [PackageController::class, 'destroy']);

    Route::post('/media-assets', [MediaAssetController::class, 'store']);
    Route::post('/media-assets/{mediaAsset}/delete', [MediaAssetController::class, 'destroy']);

    Route::get('/calendar', [CalendarController::class, 'index']);
    Route::get('/calendar/{date}', [CalendarController::class, 'day']);
    Route::post('/calendar/day-status', [CalendarController::class, 'updateDayStatus']);

    Route::get('/alerts', [ReceptionistAlertController::class, 'index']);
    Route::post('/alerts/{alert}/resolve', [ReceptionistAlertController::class, 'resolve']);

    Route::get('/operations', [OperationsController::class, 'index']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/analytics/export.csv', [AnalyticsController::class, 'exportCsv']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}/export.json', [ConversationController::class, 'exportJson']);
    Route::post('/reservations/{reservation}/confirm', [ReservationDecisionController::class, 'confirm']);
    Route::post('/reservations/{reservation}/reject', [ReservationDecisionController::class, 'reject']);

    Route::get('/hotel-profile', [HotelProfileController::class, 'edit']);
    Route::post('/hotel-profile', [HotelProfileController::class, 'update']);
});
