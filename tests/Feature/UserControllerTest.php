<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Team;
use App\Models\Task;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Criar permissões básicas
        $permissions = [
            'users.view',
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
        
        $this->user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $this->user->roles()->attach($adminRole);
    }

    public function test_can_list_users()
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_get_user_profile()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/users/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ]
            ]);
    }

    public function test_can_update_profile()
    {
        $updateData = [
            'name' => 'Nome Atualizado',
            'email' => 'novo@email.com',
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => [
                    'name' => $updateData['name'],
                    'email' => $updateData['email'],
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => $updateData['name'],
            'email' => $updateData['email'],
        ]);
    }

    public function test_cannot_update_profile_with_existing_email()
    {
        $existingUser = User::factory()->create();

        $updateData = [
            'name' => 'Nome Atualizado',
            'email' => $existingUser->email,
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/profile', $updateData);

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

    public function test_can_update_password()
    {
        $updateData = [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/password', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Senha atualizada com sucesso',
            ]);

        // Verificar se a nova senha funciona
        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    public function test_cannot_update_password_with_wrong_current_password()
    {
        $updateData = [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/password', $updateData);

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

    public function test_cannot_update_password_with_mismatched_confirmation()
    {
        $updateData = [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/password', $updateData);

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

    public function test_can_show_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ]
            ]);
    }

    public function test_can_create_user()
    {
        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'data' => [
                    'name' => 'João Silva',
                    'email' => 'joao@exemplo.com',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
        ]);
    }

    public function test_cannot_create_user_with_existing_email()
    {
        $existingUser = User::factory()->create(['email' => 'joao@exemplo.com']);

        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/users', $userData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'email'
                ]
            ]);
    }

    public function test_cannot_create_user_with_invalid_data()
    {
        $userData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/users', $userData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'name',
                    'email',
                    'password',
                ]
            ]);
    }

    public function test_can_delete_user()
    {
        $userToDelete = User::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/users/{$userToDelete->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Usuário excluído com sucesso',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $userToDelete->id,
        ]);
    }

    public function test_cannot_delete_nonexistent_user()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/users/99999');

        $response->assertStatus(404);
    }

    public function test_cannot_delete_own_user()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/users/{$this->user->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Você não pode excluir sua própria conta',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
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

        $userData = [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ];

        $response = $this->actingAs($userWithoutPermission)
            ->postJson('/api/users', $userData);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_user_without_permission()
    {
        // Criar usuário sem permissão users.delete
        $userWithoutPermission = User::factory()->create();
        $memberRole = Role::create([
            'name' => 'member',
            'display_name' => 'Member',
            'description' => 'Basic member access',
        ]);
        $userWithoutPermission->roles()->attach($memberRole);

        $userToDelete = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->deleteJson("/api/users/{$userToDelete->id}");

        $response->assertStatus(403);
    }

    public function test_can_get_dashboard_data()
    {
        // Criar dados para o dashboard
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        $team = Team::factory()->create(['owner_id' => $this->user->id]);
        Task::factory()->count(3)->create([
            'created_by' => $this->user->id,
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/users/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'projects_count',
                    'teams_count',
                    'tasks_count',
                    'recent_projects',
                    'recent_teams',
                    'recent_tasks',
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_get_my_projects()
    {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/users/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'owner_id',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_get_my_teams()
    {
        $team = Team::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/users/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'owner_id',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_profile_validation_rules()
    {
        $invalidData = [
            'name' => '', // Nome vazio
            'email' => 'invalid-email', // Email inválido
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/profile', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'name',
                    'email',
                ]
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_password_validation_rules()
    {
        $invalidData = [
            'current_password' => 'password123',
            'password' => '123', // Senha muito curta
            'password_confirmation' => '123',
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/password', $invalidData);

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

    public function test_unauthenticated_requests_are_rejected()
    {
        $response = $this->getJson('/api/users/profile');
        $response->assertStatus(401);

        $response = $this->getJson('/api/users/dashboard');
        $response->assertStatus(401);

        $response = $this->getJson('/api/users/projects');
        $response->assertStatus(401);

        $response = $this->getJson('/api/users/teams');
        $response->assertStatus(401);
    }
}