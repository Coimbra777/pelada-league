<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreChargeRequest;
use App\Http\Resources\ChargeResource;
use App\Models\Charge;
use App\Services\ChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChargeController extends Controller
{
    public function store(StoreChargeRequest $request, ChargeService $chargeService): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $charge = $chargeService->createCharge($user, $request->validated());

            return response()->json([
                'charge' => new ChargeResource($charge),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function sync(Charge $charge, ChargeService $chargeService): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($charge->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $charge = $chargeService->syncCharge($charge);

        return response()->json([
            'charge' => new ChargeResource($charge),
        ]);
    }
}
