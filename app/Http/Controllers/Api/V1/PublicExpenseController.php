<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IdentifyMemberRequest;
use App\Http\Requests\Api\V1\UploadProofRequest;
use App\Http\Resources\PaymentProofResource;
use App\Http\Resources\PublicExpenseResource;
use App\Models\Charge;
use App\Models\Expense;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;

class PublicExpenseController extends Controller
{
    public function show(string $hash): JsonResponse
    {
        $expense = Expense::byHash($hash)->first();

        if (!$expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $expense->load('charges.teamMember');

        return response()->json([
            'expense' => new PublicExpenseResource($expense),
        ]);
    }

    public function identify(IdentifyMemberRequest $request, string $hash): JsonResponse
    {
        $expense = Expense::byHash($hash)->first();

        if (!$expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $name = $request->validated()['name'];

        $charges = $expense->charges()
            ->whereHas('teamMember', function ($q) use ($name) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%']);
            })
            ->with('teamMember')
            ->get();

        if ($charges->isEmpty()) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        return response()->json([
            'members' => $charges->map(fn ($charge) => [
                'member_id' => $charge->teamMember->id,
                'name' => $charge->teamMember->name,
                'charge_id' => $charge->id,
                'amount' => $charge->amount,
                'status' => $charge->status,
            ]),
        ]);
    }

    public function uploadProof(UploadProofRequest $request, Charge $charge, PaymentProofService $service): JsonResponse
    {
        if (!$charge->expense?->public_hash) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        try {
            $proof = $service->uploadProof($charge, $request->file('file'));

            return response()->json([
                'proof' => new PaymentProofResource($proof),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function markAsPaid(Charge $charge): JsonResponse
    {
        if (!$charge->expense?->public_hash) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (!$charge->paymentProofs()->exists()) {
            return response()->json(['message' => 'Upload a proof before marking as paid.'], 422);
        }

        if (!in_array($charge->status, ['pending', 'rejected'])) {
            return response()->json(['message' => 'Charge already processed.'], 422);
        }

        $charge->update(['status' => 'proof_sent']);

        return response()->json([
            'message' => 'Marked as paid. Waiting for admin validation.',
            'status' => 'proof_sent',
        ]);
    }
}
