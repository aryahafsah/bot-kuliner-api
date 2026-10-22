<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->middleware('throttle:telegram-webhook');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/2fa/verify', [AuthController::class, 'verify2FA'])->middleware('throttle:two-factor');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/2fa/enable', [AuthController::class, 'enable2FA'])->middleware('auth:sanctum');
    Route::post('/2fa/disable', [AuthController::class, 'disable2FA'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('restaurants')->group(function () {
        Route::get('/', [RestaurantController::class, 'index'])->middleware('throttle:search');
        Route::get('/{id}', [RestaurantController::class, 'show']);
        Route::get('/{id}/menu', [RestaurantController::class, 'menu']);
        Route::get('/{id}/reviews', [RestaurantController::class, 'reviews']);
    });

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });
});
