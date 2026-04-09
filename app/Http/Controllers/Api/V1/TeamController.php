<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTeamRequest;
use App\Http\Resources\TeamMemberResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function store(StoreTeamRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $team = Team::create([
            'name' => $request->validated()['name'],
            'owner_id' => $user->id,
        ]);

        $team->members()->attach($user->id, ['role' => 'admin']);

        return response()->json([
            'team' => new TeamResource($team->loadCount('members')),
        ], 201);
    }

    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $teams = $user->teams()->withCount('members')->get();

        return response()->json([
            'teams' => TeamResource::collection($teams),
        ]);
    }

    public function show(Team $team): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$team->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $team->load('owner', 'members');

        return response()->json([
            'team' => new TeamResource($team),
            'members' => TeamMemberResource::collection($team->members),
        ]);
    }
}
