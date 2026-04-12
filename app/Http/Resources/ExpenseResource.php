<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'due_date' => $this->due_date,
            'amount_per_member' => $this->amount_per_member,
            'pix_key' => $this->pix_key,
            'status' => $this->status,
            'public_hash' => $this->public_hash,
            'public_url' => $this->public_hash ? $this->getPublicUrl() : null,
            'charges' => ChargeResource::collection($this->whenLoaded('charges')),
            'created_at' => $this->created_at,
        ];
    }
}
