<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerServiceController;
use App\Http\Controllers\Api\V1\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Everything lives under /api/v1. Only the health check and the login
| endpoint are reachable without credentials; every resource endpoint sits
| behind the auth.api middleware, which accepts HTTP Basic as the brief
| requires and a bearer JWT as the bonus.
|
*/

Route::prefix('v1')->group(function (): void {

    // Open: container orchestration probes this without credentials.
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->name('health');

    // Open: exchanging credentials for a token is how a client obtains one.
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth.api')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::apiResource('customers', CustomerController::class);

        // A service is always created under the customer that owns it, so the
        // foreign key comes from the URL rather than the request body.
        Route::apiResource('customers.services', CustomerServiceController::class)
            ->only(['index', 'store'])
            ->shallow();

        Route::apiResource('services', ServiceController::class)
            ->except(['store']);
    });
});
