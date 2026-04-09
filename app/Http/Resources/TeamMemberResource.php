<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'name' => $this->name,
                'email' => $this->email,
            ],
            'role' => $this->pivot->role,
            'joined_at' => $this->pivot->created_at,
        ];
    }
}
