<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar permissões básicas
        $permissions = [
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.delete',
            'teams.manage_projects',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'display_name' => ucfirst(str_replace('.', ' ', $permission)),
                'description' => 'Permission to '.str_replace('.', ' ', $permission),
                'resource' => explode('.', $permission)[0],
                'action' => explode('.', $permission)[1] ?? 'manage',
            ]);
        }

        // Criar role admin com todas as permissões
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Full system access',
        ]);
        $adminRole->permissions()->attach(Permission::all());
    }

    public function test_authenticated_user_can_list_teams(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'is_active',
                            'created_at',
                            'updated_at',
                            'owner',
                        ],
                    ],
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ],
            ]);
    }

    public function test_user_with_permission_can_create_team(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $user->roles()->attach($adminRole);

        $teamData = [
            'name' => $this->faker->company,
            'description' => $this->faker->text(200),
            'is_active' => true,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                    'owner',
                ],
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => $teamData['name'],
            'owner_id' => $user->id,
        ]);
    }

    public function test_user_without_permission_cannot_create_team(): void
    {
        $user = User::factory()->create();

        $teamData = [
            'name' => $this->faker->company,
            'description' => $this->faker->text(200),
            'is_active' => true,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/teams', $teamData);

        $response->assertStatus(403);
    }

    public function test_team_owner_can_view_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/teams/{$team->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                    'owner',
                    'projects',
                ],
            ]);
    }

    public function test_team_owner_can_update_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);

        $updateData = [
            'name' => 'Updated Team Name',
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/teams/{$team->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => $updateData['name'],
            'is_active' => false,
        ]);
    }

    public function test_non_owner_cannot_update_team(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $updateData = [
            'name' => 'Updated Team Name',
        ];

        $response = $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/teams/{$team->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_team_owner_can_delete_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/teams/{$team->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('teams', [
            'id' => $team->id,
        ]);
    }

    public function test_non_owner_cannot_delete_team(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/teams/{$team->id}");

        $response->assertStatus(403);
    }

    public function test_create_team_validation_errors(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $user->roles()->attach($adminRole);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/teams', [
                'name' => '', // Nome obrigatório
                'description' => str_repeat('a', 1001), // Muito longo
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description']);
    }
}
