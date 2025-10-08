<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_can_list_user_projects()
    {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/projects');

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
    }

    public function test_can_create_project_with_valid_data()
    {
        $projectData = [
            'name' => 'Novo Projeto',
            'description' => 'Descrição do projeto',
            'status' => 'planning',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(3)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/projects', $projectData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'owner_id',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Projeto criado com sucesso',
                'data' => [
                    'name' => $projectData['name'],
                    'status' => $projectData['status'],
                    'owner_id' => $this->user->id,
                ]
            ]);

        $this->assertDatabaseHas('projects', [
            'name' => $projectData['name'],
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_cannot_create_project_with_invalid_status()
    {
        $projectData = [
            'name' => 'Novo Projeto',
            'status' => 'invalid_status',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/projects', $projectData);

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

    public function test_can_show_own_project()
    {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'owner',
                    'teams',
                    'tasks',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'name' => $project->name,
                ]
            ]);
    }

    public function test_cannot_show_project_without_access()
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_update_own_project()
    {
        $project = Project::factory()->create([
            'owner_id' => $this->user->id,
            'status' => 'planning',
        ]);

        $updateData = [
            'name' => 'Nome Atualizado',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/projects/{$project->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Projeto atualizado com sucesso',
                'data' => [
                    'name' => $updateData['name'],
                    'status' => $updateData['status'],
                ]
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => $updateData['name'],
            'status' => $updateData['status'],
        ]);
    }

    public function test_cannot_update_project_not_owned()
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $otherUser->id]);

        $updateData = [
            'name' => 'Nome Atualizado',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/projects/{$project->id}", $updateData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_delete_own_project_without_tasks()
    {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Projeto excluído com sucesso',
            ]);

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_cannot_delete_project_with_tasks()
    {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível excluir um projeto com tarefas associadas',
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_cannot_delete_project_not_owned()
    {
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_project_status_validation()
    {
        $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

        foreach ($validStatuses as $status) {
            $projectData = [
                'name' => "Projeto {$status}",
                'status' => $status,
            ];

            $response = $this->actingAs($this->user)
                ->postJson('/api/projects', $projectData);

            $response->assertStatus(201);
        }
    }

    public function test_project_date_validation()
    {
        $projectData = [
            'name' => 'Projeto com Datas',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'), // Data final antes da inicial
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/projects', $projectData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }
}