<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relacionamentos
            'project' => new ProjectResource($this->whenLoaded('project')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'assigned_users' => UserResource::collection($this->whenLoaded('users')),

            // Informações adicionais
            'is_overdue' => $this->when(
                $this->due_date,
                fn () => $this->due_date->isPast() && $this->status !== 'completed'
            ),
            'days_until_due' => $this->when(
                $this->due_date && $this->status !== 'completed',
                fn () => now()->diffInDays($this->due_date, false)
            ),
            'assigned_users_count' => $this->when(
                $this->relationLoaded('users'),
                fn () => $this->users->count()
            ),
        ];
    }
}
