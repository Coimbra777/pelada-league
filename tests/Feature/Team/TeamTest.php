<?php

namespace Tests\Feature\Team;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_team(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/teams', ['name' => 'My Team']);

        $response->assertStatus(201)
            ->assertJsonPath('team.name', 'My Team');

        $this->assertDatabaseHas('teams', [
            'name' => 'My Team',
            'owner_id' => $user->id,
        ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $response->json('team.id'),
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_list_teams_shows_only_user_teams(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myTeam = Team::factory()->create(['owner_id' => $user->id]);
        $myTeam->members()->attach($user->id, ['role' => 'admin']);

        $otherTeam = Team::factory()->create(['owner_id' => $otherUser->id]);
        $otherTeam->members()->attach($otherUser->id, ['role' => 'admin']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/teams');

        $response->assertOk();
        $this->assertCount(1, $response->json('teams'));
        $this->assertEquals($myTeam->id, $response->json('teams.0.id'));
    }

    public function test_admin_can_add_member_to_team(): void
    {
        $admin = User::factory()->create();
        $newMember = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/members", [
                'user_id' => $newMember->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Member added.']);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);
    }

    public function test_non_admin_cannot_add_member(): void
    {
        $admin = User::factory()->create();
        $regularMember = User::factory()->create();
        $newUser = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);
        $team->members()->attach($regularMember->id, ['role' => 'member']);

        $response = $this->actingAs($regularMember, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_remove_member(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Member removed.']);

        $this->assertDatabaseMissing('team_members', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_cannot_remove_team_owner(): void
    {
        $admin = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/teams/{$team->id}/members/{$admin->id}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot remove the team owner.']);
    }

    public function test_non_member_cannot_see_team(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'admin']);

        $response = $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/teams/{$team->id}");

        $response->assertStatus(403);
    }
}
