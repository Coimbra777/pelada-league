<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'amount_per_member' => $this->amount_per_member,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'pix_key' => $this->pix_key,
            'pix_qr_code' => $this->pix_qr_code,
            'members' => $this->whenLoaded('charges', fn () => $this->charges->map(fn ($charge) => [
                'id' => $charge->teamMember?->id,
                'name' => $charge->teamMember?->name,
                'charge_id' => $charge->id,
                'charge_status' => $charge->status,
                'amount' => $charge->amount,
            ])),
        ];
    }
}
