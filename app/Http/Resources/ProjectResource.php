<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'description' => $this->description,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relacionamentos
            'owner' => new UserResource($this->whenLoaded('owner')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            
            // Contadores e estatísticas
            'teams_count' => $this->when(
                $this->relationLoaded('teams'),
                fn() => $this->teams->count()
            ),
            'tasks_count' => $this->when(
                $this->relationLoaded('tasks'),
                fn() => $this->tasks->count()
            ),
            'completed_tasks_count' => $this->when(
                $this->relationLoaded('tasks'),
                fn() => $this->tasks->where('status', 'completed')->count()
            ),
            'progress_percentage' => $this->when(
                $this->relationLoaded('tasks'),
                function () {
                    $total = $this->tasks->count();
                    if ($total === 0) return 0;
                    $completed = $this->tasks->where('status', 'completed')->count();
                    return round(($completed / $total) * 100, 2);
                }
            ),
        ];
    }
}