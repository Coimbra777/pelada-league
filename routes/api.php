<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/charges', [ChargeController::class, 'store']);
        Route::post('/charges/{charge}/sync', [ChargeController::class, 'sync']);

        Route::prefix('teams')->group(function () {
            Route::post('/', [TeamController::class, 'store']);
            Route::get('/', [TeamController::class, 'index']);
            Route::get('/{team}', [TeamController::class, 'show']);
            Route::get('/{team}/dashboard', [TeamController::class, 'dashboard']);

            Route::post('/{team}/members', [TeamMemberController::class, 'store']);
            Route::delete('/{team}/members/{user}', [TeamMemberController::class, 'destroy']);

            Route::post('/{team}/expenses', [ExpenseController::class, 'store']);
            Route::get('/{team}/expenses', [ExpenseController::class, 'index']);
            Route::get('/{team}/expenses/{expense}', [ExpenseController::class, 'show']);
        });
    });

    Route::prefix('webhooks')->group(function () {
        Route::post('/asaas', [WebhookController::class, 'asaas']);
    });

});
