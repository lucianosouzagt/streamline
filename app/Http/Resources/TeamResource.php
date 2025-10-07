<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
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
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relacionamentos
            'owner' => new UserResource($this->whenLoaded('owner')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),

            // Contadores
            'projects_count' => $this->when(
                $this->relationLoaded('projects'),
                fn () => $this->projects->count()
            ),
        ];
    }
}
