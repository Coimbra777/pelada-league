<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\ChargeValidationController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\PublicExpenseController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Controllers\Api\V1\WebhookController;
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
        Route::get('/expenses/{hash}/participants/{participantHash}', [PublicExpenseController::class, 'showParticipant']);
        Route::post('/expenses/{hash}/identify', [PublicExpenseController::class, 'identify']);
        Route::post('/expenses/{hash}/participate', [PublicExpenseController::class, 'participate']);
        Route::post('/charges/{charge}/upload-proof', [PublicExpenseController::class, 'uploadProof']);
        Route::post('/charges/{charge}/mark-as-paid', [PublicExpenseController::class, 'markAsPaid']);
        Route::patch('/charges/{charge}/validate', [PublicExpenseController::class, 'validateCharge']);
        Route::patch('/charges/{charge}/reject', [PublicExpenseController::class, 'rejectCharge']);
        Route::get('/charges/{charge}/proof', [PublicExpenseController::class, 'downloadProof']);
        Route::post('/expenses/{hash}/participants/{member}/resend-link', [PublicExpenseController::class, 'resendParticipantLink']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/charges', [ChargeController::class, 'store']);
        Route::post('/charges/{charge}/sync', [ChargeController::class, 'sync']);

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

    Route::prefix('webhooks')->middleware('throttle:webhook')->group(function () {
        Route::post('/asaas', [WebhookController::class, 'asaas']);
    });

});
