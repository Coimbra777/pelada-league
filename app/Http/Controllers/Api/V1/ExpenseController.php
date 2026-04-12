<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddTeamExpenseParticipantsRequest;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Requests\Api\V1\UpdateExpenseRequest;
use App\Http\Resources\ChargeResource;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Team;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request, Team $team, ExpenseService $expenseService): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $membership = $team->members()->where('user_id', $user->id)->first();
        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $expense = $expenseService->createExpenseAndSplit($team, $user, $request->validated());

            return response()->json([
                'expense' => new ExpenseResource($expense),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function index(Team $team): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$team->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $expenses = $team->expenses()->latest()->get();

        return response()->json([
            'expenses' => ExpenseResource::collection($expenses),
        ]);
    }

    public function show(Team $team, Expense $expense): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$team->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($expense->team_id !== $team->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $expense->load('charges.teamMember', 'charges.paymentProofs');

        return response()->json([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Team $team, Expense $expense, ExpenseService $expenseService): JsonResponse
    {
        try {
            $expense = $expenseService->updateExpense($expense, $request->validated());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    public function addParticipants(AddTeamExpenseParticipantsRequest $request, Team $team, Expense $expense, ExpenseService $expenseService): JsonResponse
    {
        try {
            $expense = $expenseService->addParticipantsToExpense(
                $team,
                $expense,
                $request->input('participants', [])
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    public function showDirect(Expense $expense): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$expense->team->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $expense->load('charges.teamMember', 'charges.paymentProofs');

        return response()->json([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    public function members(Expense $expense): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $membership = $expense->team->members()->where('user_id', $user->id)->first();
        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $expense->load('charges.teamMember', 'charges.paymentProofs');

        return response()->json([
            'charges' => ChargeResource::collection($expense->charges),
        ]);
    }
}
