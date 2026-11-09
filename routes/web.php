<?php

use App\Application\RefundCallback;
use App\Application\SupplierCallback;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TicketController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index']);
Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [CatalogController::class, 'show'])->name('products.show');
Route::view('/login', 'auth.login')->middleware('guest')->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/webhooks/mock', [OrderController::class, 'webhook'])->middleware('throttle:120,1');
Route::post('/webhooks/mock-suppliers/{supplier}', function (int $supplier, Request $request, SupplierCallback $callback): JsonResponse {
    abort_unless(app()->environment('testing'), 404);

    return response()->json([
        'outcome' => $callback->receive($supplier, $request->getContent(), (string) $request->header('X-TravelOps-Signature')),
    ]);
})->whereNumber('supplier')->middleware('throttle:120,1');
Route::post('/webhooks/mock-refunds', function (Request $request, RefundCallback $callback): JsonResponse {
    abort_unless(app()->environment('testing'), 404);

    return response()->json([
        'outcome' => $callback->receive($request->getContent(), (string) $request->header('X-TravelOps-Signature')),
    ]);
})->middleware('throttle:120,1');

Route::middleware('auth')->group(function (): void {
    Route::get('/my/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/my/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::post('/my/notifications/{message}/read', [NotificationController::class, 'read'])->whereNumber('message')->name('notifications.read');
    Route::get('/ops/reports.csv', [ReportController::class, 'csv'])->middleware('throttle:10,1')->name('reports.csv');
    Route::get('/my/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:30,1')->name('orders.store');
    Route::get('/my/orders/{order:reference}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/my/orders/{order:reference}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/my/orders/{order:reference}/refund', [OrderController::class, 'refund'])->middleware('throttle:20,1')->name('orders.refund');
    Route::post('/my/orders/{order:reference}/simulate', [OrderController::class, 'simulate'])->middleware('throttle:20,1')->name('orders.simulate');
    Route::get('/my/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/my/tickets/{ticket}', [TicketController::class, 'show'])->middleware('cache.headers:no_store;private')->name('tickets.show');
    Route::get('/merchant/redemptions', [MerchantController::class, 'index'])->name('merchant.index');
    Route::post('/merchant/redemptions', [MerchantController::class, 'store'])->middleware('throttle:40,1')->name('merchant.store');
    Route::get('/pos', [PosController::class, 'stores'])->name('pos.stores');
    Route::get('/pos/{store}', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/{store}/sales', [PosController::class, 'sell'])->middleware('throttle:40,1')->name('pos.sell');
    Route::get('/pos/{store}/sales/{reference}', [PosController::class, 'show'])
        ->whereUuid('reference')->middleware('cache.headers:no_store;private')->name('pos.show');
    Route::post('/pos/{store}/sales/{reference}/refund', [PosController::class, 'refund'])
        ->whereUuid('reference')->middleware('throttle:20,1')->name('pos.refund');
    Route::get('/pos/{store}/closing', [PosController::class, 'closing'])->name('pos.closing');
});
