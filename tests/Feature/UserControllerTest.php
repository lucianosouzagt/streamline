<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Team;
use App\Models\Task;
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
        
        $this->user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
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
        $targetUser = User::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$targetUser->id}");

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
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                ]
            ]);
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

        $response = $this->getJson('/api/users/my-projects');
        $response->assertStatus(401);

        $response = $this->getJson('/api/users/my-teams');
        $response->assertStatus(401);
    }
}