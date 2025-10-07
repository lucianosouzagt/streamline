<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('teams.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        // Usuário pode ver se é o dono do time ou tem permissão geral
        return $team->owner_id === $user->id || 
               $user->hasPermission('teams.view') ||
               $team->projects()->whereHas('owner', function ($query) use ($user) {
                   $query->where('id', $user->id);
               })->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('teams.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        // Apenas o dono pode editar ou usuário com permissão admin
        return $team->owner_id === $user->id || 
               $user->hasPermission('teams.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        // Apenas o dono pode excluir ou usuário com permissão admin
        return $team->owner_id === $user->id || 
               $user->hasPermission('teams.delete');
    }

    /**
     * Determine whether the user can manage projects in the team.
     */
    public function manageProjects(User $user, Team $team): bool
    {
        return $team->owner_id === $user->id || 
               $user->hasPermission('teams.manage_projects');
    }
}