<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'pix_qr_code' => $this->pix_qr_code,
            'pix_copy_paste' => $this->pix_copy_paste,
            'payment_link' => $this->payment_link,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'member' => new TeamMemberResource($this->whenLoaded('teamMember')),
        ];
    }
}
