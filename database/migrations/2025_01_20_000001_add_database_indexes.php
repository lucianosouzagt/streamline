<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar se as tabelas existem antes de adicionar índices
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->index(['owner_id', 'created_at'], 'projects_owner_created_idx');
                $table->index(['status', 'created_at'], 'projects_status_created_idx');
                $table->index('created_at', 'projects_created_at_idx');
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['created_by', 'created_at'], 'tasks_creator_created_idx');
                $table->index(['project_id', 'status'], 'tasks_project_status_idx');
                $table->index(['status', 'created_at'], 'tasks_status_created_idx');
                $table->index(['due_date', 'status'], 'tasks_due_status_idx');
                $table->index('created_at', 'tasks_created_at_idx');
            });
        }

        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->index(['owner_id', 'is_active'], 'teams_owner_active_idx');
                $table->index(['is_active', 'created_at'], 'teams_active_created_idx');
                $table->index('created_at', 'teams_created_at_idx');
            });
        }

        if (Schema::hasTable('task_user')) {
            Schema::table('task_user', function (Blueprint $table) {
                $table->index(['user_id', 'created_at'], 'task_user_user_created_idx');
                $table->index('task_id', 'task_user_task_idx');
            });
        }

        if (Schema::hasTable('project_team')) {
            Schema::table('project_team', function (Blueprint $table) {
                $table->index('team_id', 'project_team_team_idx');
                $table->index('project_id', 'project_team_project_idx');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('email', 'users_email_idx');
                $table->index('created_at', 'users_created_at_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover índices da tabela projects
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_owner_created_idx');
            $table->dropIndex('projects_status_created_idx');
            $table->dropIndex('projects_created_at_idx');
        });

        // Remover índices da tabela tasks
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_creator_created_idx');
            $table->dropIndex('tasks_project_status_idx');
            $table->dropIndex('tasks_status_created_idx');
            $table->dropIndex('tasks_due_status_idx');
            $table->dropIndex('tasks_created_at_idx');
        });

        // Remover índices da tabela teams
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('teams_owner_active_idx');
            $table->dropIndex('teams_active_created_idx');
            $table->dropIndex('teams_created_at_idx');
        });

        // Remover índices da tabela task_user
        Schema::table('task_user', function (Blueprint $table) {
            $table->dropIndex('task_user_user_created_idx');
            $table->dropIndex('task_user_task_idx');
        });

        // Remover índices da tabela project_team
        Schema::table('project_team', function (Blueprint $table) {
            $table->dropIndex('project_team_team_idx');
            $table->dropIndex('project_team_project_idx');
        });

        // Remover índices da tabela users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_idx');
            $table->dropIndex('users_created_at_idx');
        });
    }
};