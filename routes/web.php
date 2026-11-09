<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index']);
Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [CatalogController::class, 'show'])->name('products.show');
Route::view('/login', 'auth.login')->middleware('guest')->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/webhooks/mock', [OrderController::class, 'webhook'])->middleware('throttle:120,1');

Route::middleware('auth')->group(function (): void {
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
});
