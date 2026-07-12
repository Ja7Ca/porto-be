<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RealTimePlaygroundController;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);

Route::get('/products/{id}', [ProductController::class, 'show']);

Route::get('/products/{id}/flaky', [ProductController::class, 'flaky']);

Route::post('/orders', [OrderController::class, 'store'])->middleware(IdempotencyMiddleware::class);

Route::patch('/admin/products/{id}/price', [ProductController::class, 'updatePrice']);

Route::patch('/admin/products/{id}/reset-stock', [ProductController::class, 'resetStock']);

Route::post('/orders/secure', [OrderController::class, 'storeSecure']);

Route::get('/playground/messages', [RealTimePlaygroundController::class, 'getMessages']);
Route::post('/playground/messages', [RealTimePlaygroundController::class, 'sendMessage']);
Route::post('/playground/sync-stock', [RealTimePlaygroundController::class, 'triggerManualSync']);



