<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerServiceController;
use App\Http\Controllers\Api\V1\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every endpoint lives under /api/v1. Authentication is applied to the whole
| group in phase 4; the health check stays open so container orchestration
| can probe it without credentials.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->name('health');

    Route::apiResource('customers', CustomerController::class);

    // A service is always created under the customer that owns it, so the
    // foreign key comes from the URL rather than the request body.
    Route::apiResource('customers.services', CustomerServiceController::class)
        ->only(['index', 'store'])
        ->shallow();

    Route::apiResource('services', ServiceController::class)
        ->except(['store']);
});
