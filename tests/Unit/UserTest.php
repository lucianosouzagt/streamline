<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_roles_relationship(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        
        $user->roles()->attach($role);
        
        $this->assertTrue($user->roles->contains($role));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->roles);
    }

    public function test_user_has_owned_teams_relationship(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        
        $this->assertTrue($user->ownedTeams->contains($team));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->ownedTeams);
    }

    public function test_user_has_owned_projects_relationship(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        
        $this->assertTrue($user->ownedProjects->contains($project));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->ownedProjects);
    }

    public function test_user_has_created_tasks_relationship(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $user->id
        ]);
        
        $this->assertTrue($user->createdTasks->contains($task));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->createdTasks);
    }

    public function test_user_has_assigned_tasks_relationship(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        
        // Atribuir a tarefa ao usuário
        $task->users()->attach($user, ['role' => 'assignee']);
        
        $this->assertTrue($user->assignedTasks->contains($task));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->assignedTasks);
    }

    public function test_user_has_role_method(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'admin']);
        
        $user->roles()->attach($role);
        
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_user_has_permission_method(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create([
            'name' => 'users.create',
            'display_name' => 'Users Create',
            'description' => 'Permission to create users',
            'resource' => 'users',
            'action' => 'create',
        ]);
        
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        
        $this->assertTrue($user->hasPermission('users.create'));
        $this->assertFalse($user->hasPermission('users.delete'));
    }

    public function test_user_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'password123'
        ]);
        
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }

    public function test_user_email_is_unique(): void
    {
        $email = 'test@example.com';
        
        User::factory()->create(['email' => $email]);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => $email]);
    }

    public function test_user_name_is_required(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['name' => null]);
    }

    public function test_user_email_is_required(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => null]);
    }

    public function test_user_can_have_multiple_roles(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $userRole = Role::factory()->create(['name' => 'user']);
        
        $user->roles()->attach([$adminRole->id, $userRole->id]);
        
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('user'));
        $this->assertEquals(2, $user->roles->count());
    }

    public function test_user_permissions_through_multiple_roles(): void
    {
        $user = User::factory()->create();
        
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $userRole = Role::factory()->create(['name' => 'user']);
        
        $adminPermission = Permission::factory()->create([
            'name' => 'admin.access',
            'display_name' => 'Admin Access',
            'description' => 'Permission to access admin',
            'resource' => 'admin',
            'action' => 'access',
        ]);
        $userPermission = Permission::factory()->create([
            'name' => 'user.access',
            'display_name' => 'User Access',
            'description' => 'Permission to access user',
            'resource' => 'user',
            'action' => 'access',
        ]);
        
        $adminRole->permissions()->attach($adminPermission);
        $userRole->permissions()->attach($userPermission);
        
        $user->roles()->attach([$adminRole->id, $userRole->id]);
        
        $this->assertTrue($user->hasPermission('admin.access'));
        $this->assertTrue($user->hasPermission('user.access'));
        $this->assertFalse($user->hasPermission('nonexistent.permission'));
    }
}