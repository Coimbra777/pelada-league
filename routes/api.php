<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ChargeValidationController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\PublicExpenseController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::post('/public/expenses', [PublicExpenseController::class, 'store']);

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::prefix('public')->group(function () {
        Route::get('/expenses/{hash}', [PublicExpenseController::class, 'show']);
        Route::patch('/expenses/{hash}/close', [PublicExpenseController::class, 'closeExpense']);
        Route::patch('/expenses/{hash}', [PublicExpenseController::class, 'updateExpense']);
        Route::post('/expenses/{hash}/participants', [PublicExpenseController::class, 'addParticipants']);
        Route::post('/expenses/{hash}/validate-participant', [PublicExpenseController::class, 'validateParticipantPublic']);
        Route::post('/expenses/{hash}/submit-proof', [PublicExpenseController::class, 'submitProofPublic']);
        Route::patch('/charges/{charge}/validate', [PublicExpenseController::class, 'validateCharge']);
        Route::patch('/charges/{charge}/reject', [PublicExpenseController::class, 'rejectCharge']);
        Route::get('/charges/{charge}/proof', [PublicExpenseController::class, 'downloadProof']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/expenses/{expense}', [ExpenseController::class, 'showDirect']);
        Route::get('/expenses/{expense}/members', [ExpenseController::class, 'members']);

        Route::patch('/charges/{charge}/validate', [ChargeValidationController::class, 'validateCharge']);
        Route::patch('/charges/{charge}/reject', [ChargeValidationController::class, 'reject']);
        Route::get('/charges/{charge}/proof', [ChargeValidationController::class, 'downloadProof']);

        Route::prefix('teams')->group(function () {
            Route::post('/', [TeamController::class, 'store']);
            Route::get('/', [TeamController::class, 'index']);
            Route::get('/{team}', [TeamController::class, 'show']);
            Route::get('/{team}/dashboard', [TeamController::class, 'dashboard']);

            Route::post('/{team}/members', [TeamMemberController::class, 'store']);
            Route::delete('/{team}/members/{member}', [TeamMemberController::class, 'destroy']);

            Route::post('/{team}/expenses', [ExpenseController::class, 'store']);
            Route::get('/{team}/expenses', [ExpenseController::class, 'index']);
            Route::get('/{team}/expenses/{expense}', [ExpenseController::class, 'show']);
            Route::patch('/{team}/expenses/{expense}', [ExpenseController::class, 'update']);
            Route::post('/{team}/expenses/{expense}/participants', [ExpenseController::class, 'addParticipants']);
        });
    });

});
