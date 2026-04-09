<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TeamMemberController extends Controller
{
    public function store(AddTeamMemberRequest $request, Team $team): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $membership = $team->members()->where('user_id', $authUser->id)->first();
        if (!$membership || $membership->pivot->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $userId = $request->validated()['user_id'];

        if ($team->members()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'User is already a member.'], 422);
        }

        $team->members()->attach($userId, ['role' => 'member']);

        return response()->json(['message' => 'Member added.'], 201);
    }

    public function destroy(Team $team, User $user): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $membership = $team->members()->where('user_id', $authUser->id)->first();
        if (!$membership || $membership->pivot->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->id === $team->owner_id) {
            return response()->json(['message' => 'Cannot remove the team owner.'], 422);
        }

        $team->members()->detach($user->id);

        return response()->json(['message' => 'Member removed.']);
    }
}
