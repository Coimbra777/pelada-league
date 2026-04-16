<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChargeResource;
use App\Models\Charge;
use App\Support\ChargeStatusTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChargeValidationController extends Controller
{
    public function validateCharge(Charge $charge): JsonResponse
    {
        $authCheck = $this->authorizeAdmin($charge);
        if ($authCheck) {
            return $authCheck;
        }

        if ($charge->status !== 'proof_sent') {
            return response()->json(['message' => 'Charge must have proof_sent status.'], 422);
        }

        ChargeStatusTransition::assertTransition($charge->status, 'validated');

        $charge->update([
            'status' => 'validated',
            'paid_at' => now(),
            'rejection_reason' => null,
        ]);

        $charge->expense?->syncClosedStateFromCharges();

        return response()->json([
            'charge' => new ChargeResource($charge->load('teamMember')),
        ]);
    }

    public function reject(Request $request, Charge $charge): JsonResponse
    {
        $authCheck = $this->authorizeAdmin($charge);
        if ($authCheck) {
            return $authCheck;
        }

        if ($charge->status !== 'proof_sent') {
            return response()->json(['message' => 'Charge must have proof_sent status.'], 422);
        }

        ChargeStatusTransition::assertTransition($charge->status, 'rejected');

        $reasonRaw = $request->input('reason');
        $reason = is_string($reasonRaw) && trim($reasonRaw) !== ''
            ? Str::limit(trim($reasonRaw), 2000)
            : null;

        $charge->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $latestProof = $charge->latestProof();
        if ($latestProof) {
            $latestProof->update(['status' => 'rejected']);
        }

        return response()->json([
            'charge' => new ChargeResource($charge->load('teamMember')),
        ]);
    }

    public function downloadProof(Charge $charge): StreamedResponse|JsonResponse
    {
        $authCheck = $this->authorizeAdmin($charge);
        if ($authCheck) {
            return $authCheck;
        }

        $proof = $charge->latestProof();
        if (! $proof) {
            return response()->json(['message' => 'No proof found.'], 404);
        }

        return Storage::disk('local')->download($proof->file_path, $proof->original_filename);
    }

    private function authorizeAdmin(Charge $charge): ?JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $expense = $charge->expense;
        if (! $expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $membership = $expense->team->members()->where('user_id', $user->id)->first();
        if (! $membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }
}
