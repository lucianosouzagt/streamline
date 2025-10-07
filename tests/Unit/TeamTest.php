<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_belongs_to_owner(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);

        $this->assertInstanceOf(User::class, $team->owner);
        $this->assertEquals($user->id, $team->owner->id);
    }

    public function test_team_has_many_projects(): void
    {
        $team = Team::factory()->create();
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();

        $team->projects()->attach([$project1->id, $project2->id]);

        $this->assertEquals(2, $team->projects->count());
        $this->assertTrue($team->projects->contains($project1));
        $this->assertTrue($team->projects->contains($project2));
    }

    public function test_team_name_is_required(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        Team::factory()->create(['name' => null]);
    }

    public function test_team_owner_id_is_required(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        Team::factory()->create(['owner_id' => null]);
    }

    public function test_team_is_active_defaults_to_true(): void
    {
        $team = Team::factory()->create();

        $this->assertTrue($team->is_active);
    }

    public function test_team_can_be_inactive(): void
    {
        $team = Team::factory()->create(['is_active' => false]);

        $this->assertFalse($team->is_active);
    }

    public function test_team_description_can_be_null(): void
    {
        $team = Team::factory()->create(['description' => null]);

        $this->assertNull($team->description);
    }

    public function test_team_has_timestamps(): void
    {
        $team = Team::factory()->create();

        $this->assertNotNull($team->created_at);
        $this->assertNotNull($team->updated_at);
    }

    public function test_team_can_have_multiple_projects(): void
    {
        $team = Team::factory()->create();
        $projects = Project::factory()->count(3)->create();

        $team->projects()->attach($projects->pluck('id'));

        $this->assertEquals(3, $team->projects->count());

        foreach ($projects as $project) {
            $this->assertTrue($team->projects->contains($project));
        }
    }

    public function test_team_projects_relationship_is_many_to_many(): void
    {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        $project = Project::factory()->create();

        $team1->projects()->attach($project);
        $team2->projects()->attach($project);

        $this->assertTrue($team1->projects->contains($project));
        $this->assertTrue($team2->projects->contains($project));
        $this->assertEquals(1, $team1->projects->count());
        $this->assertEquals(1, $team2->projects->count());
    }
}
