<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    private const EVENT_STATUS_MAP = [
        'PAYMENT_RECEIVED' => 'RECEIVED',
        'PAYMENT_CONFIRMED' => 'CONFIRMED',
        'PAYMENT_RECEIVED_IN_CASH' => 'RECEIVED_IN_CASH',
        'PAYMENT_OVERDUE' => 'OVERDUE',
    ];

    private const PAID_STATUSES = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    public function asaas(Request $request): JsonResponse
    {
        $token = $request->header('asaas-access-token');

        if ($token !== config('services.asaas.webhook_token')) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $event = $request->input('event');
        $paymentId = $request->input('payment.id');

        if (!isset(self::EVENT_STATUS_MAP[$event])) {
            return response()->json(['message' => 'Event ignored.']);
        }

        $charge = Charge::where('asaas_charge_id', $paymentId)->first();

        if (!$charge) {
            return response()->json(['message' => 'Charge not found.'], 404);
        }

        $targetStatus = self::EVENT_STATUS_MAP[$event];

        if (in_array($charge->status, self::PAID_STATUSES) || $charge->status === $targetStatus) {
            return response()->json(['message' => 'Already processed.']);
        }

        $isPaid = in_array($targetStatus, self::PAID_STATUSES);

        $charge->update([
            'status' => $targetStatus,
            'paid_at' => $isPaid ? ($charge->paid_at ?? now()) : $charge->paid_at,
        ]);

        if ($charge->expense_id) {
            $charge->expense->recalculateStatus();
        }

        return response()->json(['message' => 'Webhook processed.']);
    }
}
