<?php

namespace Tests\Feature\Team;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_team(): void
    {
        $user = User::factory()->create(['phone' => '11999999999']);

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
            'name' => $user->name,
            'role' => 'admin',
        ]);
    }

    public function test_list_teams_shows_only_user_teams(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myTeam = Team::factory()->create(['owner_id' => $user->id]);
        TeamMember::create([
            'team_id' => $myTeam->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone ?? '11000000001',
            'email' => $user->email,
            'role' => 'admin',
        ]);

        $otherTeam = Team::factory()->create(['owner_id' => $otherUser->id]);
        TeamMember::create([
            'team_id' => $otherTeam->id,
            'user_id' => $otherUser->id,
            'name' => $otherUser->name,
            'phone' => $otherUser->phone ?? '11000000002',
            'email' => $otherUser->email,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/teams');

        $response->assertOk();
        $this->assertCount(1, $response->json('teams'));
        $this->assertEquals($myTeam->id, $response->json('teams.0.id'));
    }

    public function test_admin_can_add_member_by_name_phone_email(): void
    {
        $admin = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/members", [
                'name' => 'Joao Silva',
                'phone' => '11988887777',
                'email' => 'joao@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('member.name', 'Joao Silva')
            ->assertJsonPath('member.phone', '11988887777');

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'name' => 'Joao Silva',
            'phone' => '11988887777',
            'email' => 'joao@example.com',
            'role' => 'member',
        ]);
    }

    public function test_add_member_auto_links_user_by_email(): void
    {
        $admin = User::factory()->create();
        $existingUser = User::factory()->create(['email' => 'maria@example.com']);

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/members", [
                'name' => 'Maria Souza',
                'phone' => '11977776666',
                'email' => 'maria@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('member.has_account', true)
            ->assertJsonPath('member.user_id', $existingUser->id);
    }

    public function test_non_admin_cannot_add_member(): void
    {
        $admin = User::factory()->create();
        $regularUser = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $regularUser->id,
            'name' => $regularUser->name,
            'phone' => '11000000002',
            'email' => $regularUser->email,
            'role' => 'member',
        ]);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/members", [
                'name' => 'New Person',
                'phone' => '11966665555',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_remove_member(): void
    {
        $admin = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);
        $member = TeamMember::create([
            'team_id' => $team->id,
            'name' => 'Remove Me',
            'phone' => '11000000002',
            'role' => 'member',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Member removed.']);

        $this->assertDatabaseMissing('team_members', [
            'id' => $member->id,
        ]);
    }

    public function test_cannot_remove_team_owner(): void
    {
        $admin = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $adminMember = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/teams/{$team->id}/members/{$adminMember->id}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot remove the team owner.']);
    }

    public function test_non_member_cannot_see_team(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $owner->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'name' => $owner->name,
            'phone' => '11000000001',
            'email' => $owner->email,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/teams/{$team->id}");

        $response->assertStatus(403);
    }
}
