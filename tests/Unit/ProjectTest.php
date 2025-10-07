<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_belongs_to_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);

        $this->assertInstanceOf(User::class, $project->owner);
        $this->assertEquals($user->id, $project->owner->id);
    }

    public function test_project_belongs_to_many_teams(): void
    {
        $project = Project::factory()->create();
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();

        $project->teams()->attach([$team1->id, $team2->id]);

        $this->assertEquals(2, $project->teams->count());
        $this->assertTrue($project->teams->contains($team1));
        $this->assertTrue($project->teams->contains($team2));
    }

    public function test_project_has_many_tasks(): void
    {
        $project = Project::factory()->create();
        $task1 = Task::factory()->create(['project_id' => $project->id]);
        $task2 = Task::factory()->create(['project_id' => $project->id]);

        $this->assertEquals(2, $project->tasks->count());
        $this->assertTrue($project->tasks->contains($task1));
        $this->assertTrue($project->tasks->contains($task2));
    }

    public function test_project_name_is_required(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        Project::factory()->create(['name' => null]);
    }

    public function test_project_owner_id_is_required(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        Project::factory()->create(['owner_id' => null]);
    }

    public function test_project_status_defaults_to_planning(): void
    {
        $project = Project::factory()->create();

        $this->assertEquals('planning', $project->status);
    }

    public function test_project_valid_status_values(): void
    {
        $validStatuses = ['planning', 'active', 'completed', 'on_hold', 'cancelled'];

        foreach ($validStatuses as $status) {
            $project = Project::factory()->create(['status' => $status]);
            $this->assertEquals($status, $project->status);
        }
    }

    public function test_project_description_can_be_null(): void
    {
        $project = Project::factory()->create(['description' => null]);

        $this->assertNull($project->description);
    }

    public function test_project_dates_can_be_null(): void
    {
        $project = Project::factory()->create([
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->assertNull($project->start_date);
        $this->assertNull($project->end_date);
    }

    public function test_project_has_timestamps(): void
    {
        $project = Project::factory()->create();

        $this->assertNotNull($project->created_at);
        $this->assertNotNull($project->updated_at);
    }

    public function test_project_can_calculate_progress(): void
    {
        $project = Project::factory()->create();

        // Criar tarefas com diferentes status
        Task::factory()->create(['project_id' => $project->id, 'status' => 'done']);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'done']);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress']);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

        $project->load('tasks');

        $completedTasks = $project->tasks->where('status', 'done')->count();
        $totalTasks = $project->tasks->count();
        $expectedProgress = ($completedTasks / $totalTasks) * 100;

        $this->assertEquals(4, $totalTasks);
        $this->assertEquals(2, $completedTasks);
        $this->assertEquals(50, $expectedProgress);
    }

    public function test_project_teams_relationship_is_many_to_many(): void
    {
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $team = Team::factory()->create();

        $project1->teams()->attach($team);
        $project2->teams()->attach($team);

        $this->assertTrue($project1->teams->contains($team));
        $this->assertTrue($project2->teams->contains($team));
        $this->assertEquals(1, $project1->teams->count());
        $this->assertEquals(1, $project2->teams->count());
    }

    public function test_project_tasks_are_deleted_when_project_is_deleted(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        $project->delete();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
