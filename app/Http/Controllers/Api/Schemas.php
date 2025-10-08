<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="Modelo de usuário",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="João Silva"),
 *     @OA\Property(property="email", type="string", format="email", example="joao@exemplo.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatar.jpg"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+55 11 99999-9999"),
 *     @OA\Property(property="position", type="string", nullable=true, example="Desenvolvedor Full Stack"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Desenvolvedor experiente com foco em Laravel e Vue.js"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Project",
 *     type="object",
 *     title="Project",
 *     description="Modelo de projeto",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Projeto Exemplo"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Descrição do projeto"),
 *     @OA\Property(property="status", type="string", enum={"active", "completed", "cancelled"}, example="active"),
 *     @OA\Property(property="start_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="owner_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="owner", ref="#/components/schemas/User"),
 *     @OA\Property(property="teams", type="array", @OA\Items(ref="#/components/schemas/Team")),
 *     @OA\Property(property="tasks", type="array", @OA\Items(ref="#/components/schemas/Task"))
 * )
 * 
 * @OA\Schema(
 *     schema="Task",
 *     type="object",
 *     title="Task",
 *     description="Modelo de tarefa",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Tarefa Exemplo"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Descrição da tarefa"),
 *     @OA\Property(property="status", type="string", enum={"todo", "in_progress", "review", "done", "cancelled"}, example="todo"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="medium"),
 *     @OA\Property(property="due_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="project_id", type="integer", example=1),
 *     @OA\Property(property="created_by", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="project", ref="#/components/schemas/Project"),
 *     @OA\Property(property="creator", ref="#/components/schemas/User"),
 *     @OA\Property(property="users", type="array", @OA\Items(ref="#/components/schemas/User"))
 * )
 * 
 * @OA\Schema(
 *     schema="Team",
 *     type="object",
 *     title="Team",
 *     description="Modelo de equipe",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Equipe Desenvolvimento"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Descrição da equipe"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="owner_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="owner", ref="#/components/schemas/User"),
 *     @OA\Property(property="projects", type="array", @OA\Items(ref="#/components/schemas/Project"))
 * )
 * 
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     title="ApiResponse",
 *     description="Resposta padrão da API",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operação realizada com sucesso"),
 *     @OA\Property(property="data", type="object", description="Dados da resposta")
 * )
 * 
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     title="ValidationError",
 *     description="Erro de validação",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Dados de validação inválidos"),
 *     @OA\Property(property="errors", type="object", description="Detalhes dos erros de validação")
 * )
 */
class Schemas
{
    //
}