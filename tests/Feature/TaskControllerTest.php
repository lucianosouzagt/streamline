<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
    }

    public function test_can_list_user_tasks()
    {
        $task = Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks');

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
    }

    public function test_can_create_task_with_valid_data()
    {
        $taskData = [
            'title' => 'Nova Tarefa',
            'description' => 'Descrição da tarefa',
            'status' => 'todo',
            'priority' => 'medium',
            'project_id' => $this->project->id,
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'priority',
                    'project_id',
                    'created_by',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Tarefa criada com sucesso',
                'data' => [
                    'title' => $taskData['title'],
                    'status' => $taskData['status'],
                    'priority' => $taskData['priority'],
                    'created_by' => $this->user->id,
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => $taskData['title'],
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
        ]);
    }

    public function test_cannot_create_task_with_invalid_status()
    {
        $taskData = [
            'title' => 'Nova Tarefa',
            'status' => 'invalid_status',
            'priority' => 'medium',
            'project_id' => $this->project->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', $taskData);

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

    public function test_can_show_task_with_access()
    {
        $task = Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'priority',
                    'project',
                    'creator',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                ]
            ]);
    }

    public function test_cannot_show_task_without_access()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_id' => $otherUser->id]);
        $task = Task::factory()->create([
            'created_by' => $otherUser->id,
            'project_id' => $otherProject->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_update_own_task()
    {
        $task = Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
            'status' => 'todo',
        ]);

        $updateData = [
            'title' => 'Título Atualizado',
            'status' => 'in_progress',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/tasks/{$task->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tarefa atualizada com sucesso',
                'data' => [
                    'title' => $updateData['title'],
                    'status' => $updateData['status'],
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => $updateData['title'],
            'status' => $updateData['status'],
        ]);
    }

    public function test_can_delete_own_task()
    {
        $task = Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tarefa excluída com sucesso',
            ]);

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_can_filter_tasks_by_status()
    {
        Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
            'status' => 'todo',
        ]);

        Task::factory()->create([
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks/status/todo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $tasks = $response->json('data');
        $this->assertCount(1, $tasks);
        $this->assertEquals('todo', $tasks[0]['status']);
    }

    public function test_cannot_filter_tasks_by_invalid_status()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks/status/invalid');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Status inválido',
            ]);
    }
}