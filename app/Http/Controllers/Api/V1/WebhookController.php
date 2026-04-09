<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function asaas(Request $request): JsonResponse
    {
        $token = $request->header('asaas-access-token');

        if ($token !== config('services.asaas.webhook_token')) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $event = $request->input('event');
        $paymentId = $request->input('payment.id');

        if (!in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'])) {
            return response()->json(['message' => 'Event ignored.']);
        }

        $charge = Charge::where('asaas_charge_id', $paymentId)->first();

        if (!$charge) {
            return response()->json(['message' => 'Charge not found.'], 404);
        }

        $targetStatus = $event === 'PAYMENT_RECEIVED' ? 'RECEIVED' : 'CONFIRMED';

        if ($charge->status === $targetStatus) {
            return response()->json(['message' => 'Already processed.']);
        }

        $charge->update([
            'status' => $targetStatus,
            'paid_at' => $charge->paid_at ?? now(),
        ]);

        return response()->json(['message' => 'Webhook processed.']);
    }
}
