<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait AuthorizesPublicExpense
{
    protected function authorizeManage(Request $request, ?Expense $expense): Expense|JsonResponse
    {
        if (! $expense || ! $expense->public_hash) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return $this->authorizeManageToken($request, $expense);
    }

    protected function authorizeManageToken(Request $request, Expense $expense): Expense|JsonResponse
    {
        $token = $this->resolveManageToken($request);
        if (! $token || ! hash_equals((string) $expense->manage_token, (string) $token)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $expense;
    }

    protected function rejectIfClosed(Expense $expense): ?JsonResponse
    {
        if ($expense->status === 'closed') {
            return response()->json([
                'message' => 'Esta despesa foi finalizada e nao aceita mais alteracoes.',
            ], 422);
        }

        return null;
    }

    protected function resolveManageToken(Request $request): ?string
    {
        $t = $request->input('manage_token')
            ?? $request->query('manage_token')
            ?? $request->query('manage')
            ?? $request->header('X-Manage-Token');

        return $t !== null && $t !== '' ? (string) $t : null;
    }
}
