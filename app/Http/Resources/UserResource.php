<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relacionamentos (apenas quando carregados)
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'owned_teams' => TeamResource::collection($this->whenLoaded('ownedTeams')),
            'owned_projects' => ProjectResource::collection($this->whenLoaded('ownedProjects')),
            'created_tasks' => TaskResource::collection($this->whenLoaded('createdTasks')),
            'assigned_tasks' => TaskResource::collection($this->whenLoaded('tasks')),

            // Contadores
            'teams_count' => $this->when(
                $this->relationLoaded('ownedTeams'),
                fn () => $this->ownedTeams->count()
            ),
            'projects_count' => $this->when(
                $this->relationLoaded('ownedProjects'),
                fn () => $this->ownedProjects->count()
            ),
            'created_tasks_count' => $this->when(
                $this->relationLoaded('createdTasks'),
                fn () => $this->createdTasks->count()
            ),
            'assigned_tasks_count' => $this->when(
                $this->relationLoaded('tasks'),
                fn () => $this->tasks->count()
            ),
        ];
    }
}
