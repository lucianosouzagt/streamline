<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        // Usuário pode ver se é o dono do projeto ou membro de um time associado
        return $project->owner_id === $user->id || 
               $user->hasPermission('projects.view') ||
               $project->teams()->where('owner_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        // Apenas o dono pode editar ou usuário com permissão admin
        return $project->owner_id === $user->id || 
               $user->hasPermission('projects.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        // Apenas o dono pode excluir ou usuário com permissão admin
        return $project->owner_id === $user->id || 
               $user->hasPermission('projects.delete');
    }

    /**
     * Determine whether the user can manage tasks in the project.
     */
    public function manageTasks(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id || 
               $project->teams()->where('owner_id', $user->id)->exists() ||
               $user->hasPermission('projects.manage_tasks');
    }

    /**
     * Determine whether the user can view project statistics.
     */
    public function viewStatistics(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }
}