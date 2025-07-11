<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfflineController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Offline API Routes
Route::prefix('offline')->group(function () {
    Route::get('/health-check', [OfflineController::class, 'healthCheck']);
    Route::post('/store-order', [OfflineController::class, 'storeOfflineOrder']);
    Route::post('/sync-orders', [OfflineController::class, 'syncOfflineOrders']);
    Route::get('/orders-count', [OfflineController::class, 'getOfflineOrdersCount']);
});
