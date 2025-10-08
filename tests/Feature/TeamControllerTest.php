<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

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
            'users.create',
            'users.edit',
            'users.delete',
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
        
        $this->user = User::factory()->create();
        $this->user->roles()->attach($adminRole);
    }

    public function test_can_list_user_teams()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'owner_id',
                            'is_active',
                            'created_at',
                            'updated_at',
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_create_team_with_valid_data()
    {
        $teamData = [
            'name' => 'Novo Time',
            'description' => 'Descrição do time',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'owner_id',
                    'is_active',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Time criado com sucesso',
                'data' => [
                    'name' => $teamData['name'],
                    'description' => $teamData['description'],
                    'owner_id' => $this->user->id,
                ]
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => $teamData['name'],
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_cannot_create_team_without_name()
    {
        $teamData = [
            'description' => 'Descrição do time',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/teams', $teamData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_show_own_team()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/teams/{$team->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'owner',
                    'projects',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ]
            ]);
    }

    public function test_cannot_show_team_without_access()
    {
        $otherUser = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/teams/{$team->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_update_own_team()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        $updateData = [
            'name' => 'Nome Atualizado',
            'description' => 'Nova descrição',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/teams/{$team->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Time atualizado com sucesso',
                'data' => [
                    'name' => $updateData['name'],
                    'description' => $updateData['description'],
                ]
            ]);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => $updateData['name'],
            'description' => $updateData['description'],
        ]);
    }

    public function test_cannot_update_team_not_owned()
    {
        $otherUser = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $otherUser->id]);

        $updateData = [
            'name' => 'Nome Atualizado',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/teams/{$team->id}", $updateData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_delete_own_team_without_projects()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/teams/{$team->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Time excluído com sucesso',
            ]);

        $this->assertDatabaseMissing('teams', [
            'id' => $team->id,
        ]);
    }

    public function test_cannot_delete_team_with_projects()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        $team->projects()->attach($project->id);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/teams/{$team->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível excluir um time com projetos associados',
            ]);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
        ]);
    }

    public function test_can_add_project_to_team()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/projects", [
                'project_id' => $project->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Projeto adicionado ao time com sucesso',
            ]);

        $this->assertDatabaseHas('project_team', [
            'team_id' => $team->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_cannot_add_project_not_owned()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/projects", [
                'project_id' => $project->id,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_add_duplicate_project_to_team()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        $team->projects()->attach($project->id);

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/projects", [
                'project_id' => $project->id,
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Projeto já está associado a este time',
            ]);
    }

    public function test_can_remove_project_from_team()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        $team->projects()->attach($project->id);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/teams/{$team->id}/projects", [
                'project_id' => $project->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Projeto removido do time com sucesso',
            ]);

        $this->assertDatabaseMissing('project_team', [
            'team_id' => $team->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_cannot_remove_project_not_in_team()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/teams/{$team->id}/projects", [
                'project_id' => $project->id,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Projeto não está associado a este time',
            ]);
    }

    public function test_can_create_user_for_team()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
            'role' => 'member',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/users", $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'roles',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Usuário criado com sucesso para a equipe',
                'data' => [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => $userData['name'],
            'email' => $userData['email'],
        ]);
    }

    public function test_cannot_create_user_without_permission()
    {
        // Criar usuário sem permissão users.create
        $userWithoutPermission = User::factory()->create();
        $memberRole = Role::create([
            'name' => 'member',
            'display_name' => 'Member',
            'description' => 'Basic member access',
        ]);
        $userWithoutPermission->roles()->attach($memberRole);

        $team = Team::factory()->create(['owner_id' => $userWithoutPermission->id]);

        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ];

        $response = $this->actingAs($userWithoutPermission)
            ->postJson("/api/teams/{$team->id}/users", $userData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_create_user_for_team_not_owned()
    {
        $otherUser = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $otherUser->id]);

        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/users", $userData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Apenas o dono da equipe pode adicionar usuários',
            ]);
    }

    public function test_cannot_create_user_with_invalid_data()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        $invalidData = [
            'name' => '', // Nome vazio
            'email' => 'invalid-email', // Email inválido
            'password' => '123', // Senha muito curta
            'password_confirmation' => '456', // Confirmação não confere
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/users", $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_create_user_with_duplicate_email()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        $existingUser = User::factory()->create(['email' => 'joao@exemplo.com']);

        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com', // Email já existe
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/users", $userData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_creates_user_with_default_member_role()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        // Criar role member se não existir
        $memberRole = Role::firstOrCreate([
            'name' => 'member'
        ], [
            'display_name' => 'Member',
            'description' => 'Basic member access',
        ]);

        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
            // Sem especificar role - deve usar 'member' como padrão
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/teams/{$team->id}/users", $userData);

        $response->assertStatus(201);

        $createdUser = User::where('email', $userData['email'])->first();
        $this->assertTrue($createdUser->hasRole('member'));
    }
}