<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

class TeamMemberService
{
    public function createMember(Team $team, array $data): TeamMember
    {
        $user = null;

        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
        }

        return TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user?->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'] ?? 'member',
        ]);
    }
}
