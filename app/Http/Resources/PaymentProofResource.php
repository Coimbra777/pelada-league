<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'charge_id' => $this->charge_id,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
