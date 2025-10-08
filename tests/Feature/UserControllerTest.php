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

    public function test_can_update_profile_with_new_fields()
    {
        $updateData = [
            'name' => 'Nome Atualizado',
            'email' => 'novo@email.com',
            'phone' => '+55 11 98765-4321',
            'position' => 'Senior Developer',
            'description' => 'Desenvolvedor experiente em Laravel e Vue.js com mais de 5 anos de experiência.',
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
                    'phone' => $updateData['phone'],
                    'position' => $updateData['position'],
                    'description' => $updateData['description'],
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => $updateData['name'],
            'email' => $updateData['email'],
            'phone' => $updateData['phone'],
            'position' => $updateData['position'],
            'description' => $updateData['description'],
        ]);
    }

    public function test_can_update_profile_with_partial_new_fields()
    {
        $updateData = [
            'position' => 'Tech Lead',
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/users/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => [
                    'position' => $updateData['position'],
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'position' => $updateData['position'],
        ]);
    }

    public function test_profile_includes_new_fields_in_response()
    {
        // Atualizar o usuário com os novos campos
        $this->user->update([
            'avatar' => 'https://example.com/test-avatar.jpg',
            'phone' => '+55 11 99999-8888',
            'position' => 'Full Stack Developer',
            'description' => 'Desenvolvedor apaixonado por tecnologia.',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/users/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'avatar',
                    'phone',
                    'position',
                    'description',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'avatar' => 'https://example.com/test-avatar.jpg',
                    'phone' => '+55 11 99999-8888',
                    'position' => 'Full Stack Developer',
                    'description' => 'Desenvolvedor apaixonado por tecnologia.',
                ]
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

    public function test_can_update_avatar()
    {
        \Storage::fake('public');
        
        // Criar um arquivo fake que simula uma imagem
        $file = \Illuminate\Http\UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->user)
            ->post('/api/users/avatar', ['avatar' => $file]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Avatar atualizado com sucesso',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'avatar'
                ]
            ]);

        // Verifica se o arquivo foi armazenado
        \Storage::disk('public')->assertExists('avatars/' . $file->hashName());
        
        // Verifica se o banco foi atualizado
        $this->user->refresh();
        $this->assertStringContainsString('avatars/', $this->user->avatar);
    }

    public function test_cannot_update_avatar_with_invalid_file()
    {
        \Storage::fake('public');
        
        $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post('/api/users/avatar', ['avatar' => $file]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'avatar'
                ]
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_update_avatar_with_large_file()
    {
        \Storage::fake('public');
        
        // Arquivo maior que 2MB (2048KB)
        $file = \Illuminate\Http\UploadedFile::fake()->create('large-avatar.jpg', 3000, 'image/jpeg');

        $response = $this->actingAs($this->user)
            ->post('/api/users/avatar', ['avatar' => $file]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'avatar'
                ]
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_update_avatar_without_authentication()
    {
        \Storage::fake('public');
        
        $file = \Illuminate\Http\UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->post('/api/users/avatar', ['avatar' => $file]);

        $response->assertStatus(401);
    }

    public function test_cannot_update_avatar_without_avatar_field()
    {
        $response = $this->actingAs($this->user)
            ->post('/api/users/avatar', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'avatar'
                ]
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_update_avatar_removes_old_file()
    {
        \Storage::fake('public');
        
        // Criar um avatar inicial
        $oldFile = \Illuminate\Http\UploadedFile::fake()->create('old-avatar.jpg', 100, 'image/jpeg');
        $oldPath = $oldFile->store('avatars', 'public');
        $this->user->update(['avatar' => $oldPath]);
        
        // Verificar que o arquivo antigo existe
        \Storage::disk('public')->assertExists($oldPath);
        
        // Fazer upload de um novo avatar
        $newFile = \Illuminate\Http\UploadedFile::fake()->create('new-avatar.jpg', 100, 'image/jpeg');
        
        $response = $this->actingAs($this->user)
            ->post('/api/users/avatar', ['avatar' => $newFile]);

        $response->assertStatus(200);
        
        // Verificar que o arquivo antigo foi removido
        \Storage::disk('public')->assertMissing($oldPath);
        
        // Verificar que o novo arquivo existe
        \Storage::disk('public')->assertExists('avatars/' . $newFile->hashName());
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

    public function test_can_get_my_tasks()
    {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        
        // Criar tarefa criada pelo usuário
        $createdTask = Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $project->id,
        ]);
        
        // Criar tarefa atribuída ao usuário
        $assignedTask = Task::factory()->create(['project_id' => $project->id]);
        $assignedTask->users()->attach($this->user, ['role' => 'assignee']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/my/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'priority',
                        'project_id',
                        'created_by',
                        'due_date',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verificar se ambas as tarefas estão na resposta
        $taskIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($createdTask->id, $taskIds);
        $this->assertContains($assignedTask->id, $taskIds);
    }

    public function test_can_filter_my_tasks_by_status()
    {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        
        Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $project->id,
            'status' => 'todo',
        ]);
        
        Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/my/tasks?status=todo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $tasks = $response->json('data');
        $this->assertCount(1, $tasks);
        $this->assertEquals('todo', $tasks[0]['status']);
    }

    public function test_can_get_my_projects_with_owned_and_member()
    {
        // Projeto próprio
        $ownedProject = Project::factory()->create(['owner_id' => $this->user->id]);
        
        // Projeto onde é membro
        $memberProject = Project::factory()->create();
        $this->user->projects()->attach($memberProject);

        $response = $this->actingAs($this->user)
            ->getJson('/api/my/projects');

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
                        'created_at',
                        'updated_at',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verificar se ambos os projetos estão na resposta
        $projectIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($ownedProject->id, $projectIds);
        $this->assertContains($memberProject->id, $projectIds);
    }

    public function test_can_filter_my_projects_by_status()
    {
        Project::factory()->create([
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);
        
        Project::factory()->create([
            'owner_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/my/projects?status=active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $projects = $response->json('data');
        $this->assertCount(1, $projects);
        $this->assertEquals('active', $projects[0]['status']);
    }

    public function test_can_get_my_teams_with_owned_and_member()
    {
        // Equipe própria
        $ownedTeam = Team::factory()->create(['owner_id' => $this->user->id]);
        
        // Equipe onde é membro
        $memberTeam = Team::factory()->create();
        $this->user->teams()->attach($memberTeam);

        $response = $this->actingAs($this->user)
            ->getJson('/api/my/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                        'owner_id',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verificar se ambas as equipes estão na resposta
        $teamIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($ownedTeam->id, $teamIds);
        $this->assertContains($memberTeam->id, $teamIds);
    }

    public function test_can_filter_my_teams_by_active_status()
    {
        Team::factory()->create([
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);
        
        Team::factory()->create([
            'owner_id' => $this->user->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/my/teams?is_active=1');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $teams = $response->json('data');
        $this->assertCount(1, $teams);
        $this->assertTrue($teams[0]['is_active']);
    }

    public function test_my_endpoints_require_authentication()
    {
        $this->getJson('/api/my/tasks')->assertStatus(401);
        $this->getJson('/api/my/projects')->assertStatus(401);
        $this->getJson('/api/my/teams')->assertStatus(401);
    }

    // Testes para endpoints de usuários específicos
    public function test_can_get_user_tasks()
    {
        $targetUser = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $targetUser->id]);
        
        // Criar tarefa criada pelo usuário alvo
        $createdTask = Task::factory()->create([
            'created_by' => $targetUser->id,
            'project_id' => $project->id,
        ]);
        
        // Criar tarefa atribuída ao usuário alvo
        $assignedTask = Task::factory()->create([
            'project_id' => $project->id,
        ]);
        $assignedTask->users()->attach($targetUser->id);

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}/tasks");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'priority',
                        'project_id',
                        'created_by',
                        'due_date',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verificar se ambas as tarefas estão na resposta
        $taskIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($createdTask->id, $taskIds);
        $this->assertContains($assignedTask->id, $taskIds);
    }

    public function test_can_filter_user_tasks_by_status()
    {
        $targetUser = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $targetUser->id]);
        
        Task::factory()->create([
            'created_by' => $targetUser->id,
            'project_id' => $project->id,
            'status' => 'todo',
        ]);
        
        Task::factory()->create([
            'created_by' => $targetUser->id,
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}/tasks?status=todo");

        $response->assertStatus(200);
        
        $tasks = $response->json('data');
        $this->assertCount(1, $tasks);
        $this->assertEquals('todo', $tasks[0]['status']);
    }

    public function test_can_filter_user_tasks_by_priority()
    {
        $targetUser = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $targetUser->id]);
        
        Task::factory()->create([
            'created_by' => $targetUser->id,
            'project_id' => $project->id,
            'priority' => 'high',
        ]);
        
        Task::factory()->create([
            'created_by' => $targetUser->id,
            'project_id' => $project->id,
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}/tasks?priority=high");

        $response->assertStatus(200);
        
        $tasks = $response->json('data');
        $this->assertCount(1, $tasks);
        $this->assertEquals('high', $tasks[0]['priority']);
    }

    public function test_can_get_user_projects()
    {
        $targetUser = User::factory()->create();
        
        // Criar projeto próprio do usuário alvo
        $ownedProject = Project::factory()->create(['owner_id' => $targetUser->id]);
        
        // Criar projeto onde o usuário alvo é membro
        $memberProject = Project::factory()->create();
        $memberProject->users()->attach($targetUser->id);

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}/projects");

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
                        'start_date',
                        'end_date',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verificar se ambos os projetos estão na resposta
        $projectIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($ownedProject->id, $projectIds);
        $this->assertContains($memberProject->id, $projectIds);
    }

    public function test_can_filter_user_projects_by_status()
    {
        $targetUser = User::factory()->create();
        
        Project::factory()->create([
            'owner_id' => $targetUser->id,
            'status' => 'active',
        ]);
        
        Project::factory()->create([
            'owner_id' => $targetUser->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}/projects?status=active");

        $response->assertStatus(200);
        
        $projects = $response->json('data');
        $this->assertCount(1, $projects);
        $this->assertEquals('active', $projects[0]['status']);
    }

    public function test_can_get_user_teams()
    {
        $targetUser = User::factory()->create();
        
        // Criar equipe própria do usuário alvo
        $ownedTeam = Team::factory()->create(['owner_id' => $targetUser->id]);
        
        // Criar equipe onde o usuário alvo é membro
        $memberTeam = Team::factory()->create();
        $memberTeam->users()->attach($targetUser->id);

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}/teams");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
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
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verificar se ambas as equipes estão na resposta
        $teamIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($ownedTeam->id, $teamIds);
        $this->assertContains($memberTeam->id, $teamIds);
    }

    public function test_can_filter_user_teams_by_active_status()
    {
        $targetUser = User::factory()->create();
        
        Team::factory()->create([
            'owner_id' => $targetUser->id,
            'is_active' => true,
        ]);
        
        Team::factory()->create([
            'owner_id' => $targetUser->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}/teams?is_active=1");

        $response->assertStatus(200);
        
        $teams = $response->json('data');
        $this->assertCount(1, $teams);
        $this->assertTrue($teams[0]['is_active']);
    }

    public function test_user_endpoints_require_authentication()
    {
        $targetUser = User::factory()->create();
        
        $this->getJson("/api/users/{$targetUser->id}/tasks")->assertStatus(401);
        $this->getJson("/api/users/{$targetUser->id}/projects")->assertStatus(401);
        $this->getJson("/api/users/{$targetUser->id}/teams")->assertStatus(401);
    }

    public function test_user_endpoints_return_404_for_nonexistent_user()
    {
        $nonExistentUserId = 99999;
        
        $this->actingAs($this->user)
            ->getJson("/api/users/{$nonExistentUserId}/tasks")
            ->assertStatus(404);
            
        $this->actingAs($this->user)
            ->getJson("/api/users/{$nonExistentUserId}/projects")
            ->assertStatus(404);
            
        $this->actingAs($this->user)
            ->getJson("/api/users/{$nonExistentUserId}/teams")
            ->assertStatus(404);
    }
}