<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        // Usuário pode ver se criou a tarefa, é dono do projeto, membro do time ou está atribuído
        return $task->created_by === $user->id ||
               $task->project->owner_id === $user->id ||
               $task->project->teams()->where('owner_id', $user->id)->exists() ||
               $task->users()->where('user_id', $user->id)->exists() ||
               $user->hasPermission('tasks.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task): bool
    {
        // Criador, dono do projeto, dono do time ou usuário atribuído podem editar
        return $task->created_by === $user->id ||
               $task->project->owner_id === $user->id ||
               $task->project->teams()->where('owner_id', $user->id)->exists() ||
               $task->users()->where('user_id', $user->id)->exists() ||
               $user->hasPermission('tasks.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        // Apenas criador, dono do projeto ou dono do time podem excluir
        return $task->created_by === $user->id ||
               $task->project->owner_id === $user->id ||
               $task->project->teams()->where('owner_id', $user->id)->exists() ||
               $user->hasPermission('tasks.delete');
    }

    /**
     * Determine whether the user can assign users to the task.
     */
    public function assignUsers(User $user, Task $task): bool
    {
        return $task->created_by === $user->id ||
               $task->project->owner_id === $user->id ||
               $task->project->teams()->where('owner_id', $user->id)->exists() ||
               $user->hasPermission('tasks.assign_users');
    }

    /**
     * Determine whether the user can update task status.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        // Qualquer pessoa com acesso à tarefa pode atualizar o status
        return $this->update($user, $task);
    }
}
