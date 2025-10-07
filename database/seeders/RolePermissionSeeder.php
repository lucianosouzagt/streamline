<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar permissions
        $permissions = [
            // Users
            ['name' => 'users.view', 'display_name' => 'Visualizar Usuários', 'description' => 'Pode visualizar usuários', 'resource' => 'users', 'action' => 'view'],
            ['name' => 'users.create', 'display_name' => 'Criar Usuários', 'description' => 'Pode criar usuários', 'resource' => 'users', 'action' => 'create'],
            ['name' => 'users.edit', 'display_name' => 'Editar Usuários', 'description' => 'Pode editar usuários', 'resource' => 'users', 'action' => 'edit'],
            ['name' => 'users.delete', 'display_name' => 'Deletar Usuários', 'description' => 'Pode deletar usuários', 'resource' => 'users', 'action' => 'delete'],

            // Teams
            ['name' => 'teams.view', 'display_name' => 'Visualizar Times', 'description' => 'Pode visualizar times', 'resource' => 'teams', 'action' => 'view'],
            ['name' => 'teams.create', 'display_name' => 'Criar Times', 'description' => 'Pode criar times', 'resource' => 'teams', 'action' => 'create'],
            ['name' => 'teams.edit', 'display_name' => 'Editar Times', 'description' => 'Pode editar times', 'resource' => 'teams', 'action' => 'edit'],
            ['name' => 'teams.delete', 'display_name' => 'Deletar Times', 'description' => 'Pode deletar times', 'resource' => 'teams', 'action' => 'delete'],

            // Projects
            ['name' => 'projects.view', 'display_name' => 'Visualizar Projetos', 'description' => 'Pode visualizar projetos', 'resource' => 'projects', 'action' => 'view'],
            ['name' => 'projects.create', 'display_name' => 'Criar Projetos', 'description' => 'Pode criar projetos', 'resource' => 'projects', 'action' => 'create'],
            ['name' => 'projects.edit', 'display_name' => 'Editar Projetos', 'description' => 'Pode editar projetos', 'resource' => 'projects', 'action' => 'edit'],
            ['name' => 'projects.delete', 'display_name' => 'Deletar Projetos', 'description' => 'Pode deletar projetos', 'resource' => 'projects', 'action' => 'delete'],

            // Tasks
            ['name' => 'tasks.view', 'display_name' => 'Visualizar Tarefas', 'description' => 'Pode visualizar tarefas', 'resource' => 'tasks', 'action' => 'view'],
            ['name' => 'tasks.create', 'display_name' => 'Criar Tarefas', 'description' => 'Pode criar tarefas', 'resource' => 'tasks', 'action' => 'create'],
            ['name' => 'tasks.edit', 'display_name' => 'Editar Tarefas', 'description' => 'Pode editar tarefas', 'resource' => 'tasks', 'action' => 'edit'],
            ['name' => 'tasks.delete', 'display_name' => 'Deletar Tarefas', 'description' => 'Pode deletar tarefas', 'resource' => 'tasks', 'action' => 'delete'],

            // Roles & Permissions
            ['name' => 'roles.view', 'display_name' => 'Visualizar Roles', 'description' => 'Pode visualizar roles', 'resource' => 'roles', 'action' => 'view'],
            ['name' => 'roles.create', 'display_name' => 'Criar Roles', 'description' => 'Pode criar roles', 'resource' => 'roles', 'action' => 'create'],
            ['name' => 'roles.edit', 'display_name' => 'Editar Roles', 'description' => 'Pode editar roles', 'resource' => 'roles', 'action' => 'edit'],
            ['name' => 'roles.delete', 'display_name' => 'Deletar Roles', 'description' => 'Pode deletar roles', 'resource' => 'roles', 'action' => 'delete'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // Criar roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrador',
                'description' => 'Acesso total ao sistema',
                'is_system' => true,
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Gerente',
                'description' => 'Pode gerenciar projetos e times',
                'is_system' => false,
            ]
        );

        $memberRole = Role::firstOrCreate(
            ['name' => 'member'],
            [
                'display_name' => 'Membro',
                'description' => 'Acesso básico ao sistema',
                'is_system' => false,
            ]
        );

        // Atribuir todas as permissions ao admin
        $allPermissions = Permission::all();
        $adminRole->permissions()->sync($allPermissions->pluck('id'));

        // Atribuir permissions específicas ao manager
        $managerPermissions = Permission::whereIn('resource', ['teams', 'projects', 'tasks'])
            ->whereIn('action', ['view', 'create', 'edit'])
            ->get();
        $managerRole->permissions()->sync($managerPermissions->pluck('id'));

        // Atribuir permissions básicas ao member
        $memberPermissions = Permission::whereIn('resource', ['teams', 'projects', 'tasks'])
            ->where('action', 'view')
            ->get();
        $memberRole->permissions()->sync($memberPermissions->pluck('id'));
    }
}
