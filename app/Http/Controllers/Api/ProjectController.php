<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheableTrait;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    use ApiResponseTrait, CacheableTrait;
    /**
     * @OA\Get(
     *     path="/api/projects",
     *     tags={"Projects"},
     *     summary="Listar projetos",
     *     description="Lista todos os projetos do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de projetos",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/Project")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado"
     *     )
     * )
     * 
     * Lista todos os projetos do usuário autenticado
     */
    public function index(): JsonResponse
    {
        $projects = Project::with(['owner:id,name', 'teams:id,name', 'tasks' => function ($query) {
                $query->select('id', 'project_id', 'title', 'status', 'priority')
                      ->with('creator:id,name');
            }])
            ->where('owner_id', Auth::id())
            ->orWhereHas('teams.owner', function ($query) {
                $query->where('id', Auth::id());
            })
            ->latest()
            ->get();

        return $this->successResponse($projects);
    }

    /**
     * @OA\Post(
     *     path="/api/projects",
     *     tags={"Projects"},
     *     summary="Criar projeto",
     *     description="Cria um novo projeto",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Projeto Exemplo"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Descrição do projeto"),
     *             @OA\Property(property="status", type="string", enum={"active", "completed", "cancelled"}, example="active"),
     *             @OA\Property(property="start_date", type="string", format="date", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Projeto criado com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Project")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     * 
     * Cria um novo projeto
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $validated['owner_id'] = Auth::id();

            $project = Project::create($validated);
            $project->load(['owner', 'teams', 'tasks']);

            // Limpar cache relacionado após criação
            $this->clearUserCache(Auth::id());

            return $this->successResponse($project, 'Projeto criado com sucesso', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/projects/{id}",
     *     tags={"Projects"},
     *     summary="Exibir projeto",
     *     description="Exibe informações de um projeto específico",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do projeto",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projeto encontrado",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Project")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Projeto não encontrado"
     *     )
     * )
     * 
     * Exibe um projeto específico
     */
    public function show(Project $project): JsonResponse
    {
        try {
            // Verifica se o usuário tem acesso ao projeto
            if ($project->owner_id !== Auth::id() &&
                ! $project->teams()->whereHas('owner', function ($query) {
                    $query->where('id', Auth::id());
                })->exists()) {
                return $this->forbiddenResponse();
            }

            $project->load(['owner', 'teams', 'tasks.users', 'tasks.creator']);

            return $this->successResponse($project);
        } catch (\Exception $e) {
            return $this->internalErrorResponse();
        }
    }

    /**
     * @OA\Put(
     *     path="/api/projects/{id}",
     *     tags={"Projects"},
     *     summary="Atualizar projeto",
     *     description="Atualiza informações de um projeto existente",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do projeto",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Projeto Atualizado"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Nova descrição"),
     *             @OA\Property(property="status", type="string", enum={"planning", "active", "on_hold", "completed", "cancelled"}, example="active"),
     *             @OA\Property(property="start_date", type="string", format="date", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projeto atualizado com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Project")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Apenas o dono pode editar o projeto"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     * 
     * Atualiza um projeto
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        // Verifica se o usuário é o dono do projeto
        if ($project->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o dono pode editar o projeto');
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $project->update($validated);
            $project->load(['owner', 'teams', 'tasks']);

            // Limpar cache relacionado após atualização
            $this->clearUserCache($project->owner_id);
            $this->clearProjectCache($project->id);

            return $this->successResponse($project, 'Projeto atualizado com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/projects/{id}",
     *     tags={"Projects"},
     *     summary="Excluir projeto",
     *     description="Remove um projeto do sistema",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do projeto",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projeto excluído com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Apenas o dono pode excluir o projeto"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Não é possível excluir projeto com tarefas associadas"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Projeto não encontrado"
     *     )
     * )
     * 
     * Remove um projeto
     */
    public function destroy(Project $project): JsonResponse
    {
        // Verifica se o usuário é o dono do projeto
        if ($project->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o dono pode excluir o projeto');
        }

        // Verifica se há tarefas associadas
        if ($project->tasks()->count() > 0) {
            return $this->errorResponse('Não é possível excluir um projeto com tarefas associadas', 422);
        }

        try {
            $project->delete();

            // Limpar cache relacionado após exclusão
            $this->clearUserCache($project->owner_id);
            $this->clearProjectCache($project->id);

            return $this->successResponse(null, 'Projeto excluído com sucesso');
        } catch (\Exception $e) {
            return $this->internalErrorResponse('Erro interno do servidor');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/projects/status/{status}",
     *     tags={"Projects"},
     *     summary="Listar projetos por status",
     *     description="Lista todos os projetos do usuário filtrados por status",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status dos projetos",
     *         required=true,
     *         @OA\Schema(type="string", enum={"planning", "active", "on_hold", "completed", "cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de projetos por status",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Project")),
     *                         @OA\Property(property="current_page", type="integer"),
     *                         @OA\Property(property="last_page", type="integer"),
     *                         @OA\Property(property="per_page", type="integer"),
     *                         @OA\Property(property="total", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Status inválido"
     *     )
     * )
     * 
     * Lista projetos por status
     */
    public function byStatus(string $status): JsonResponse
    {
        $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

        if (! in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Status inválido',
            ], 422);
        }

        $projects = Project::with(['owner', 'teams', 'tasks'])
            ->where('owner_id', Auth::id())
            ->where('status', $status)
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/projects/{id}/statistics",
     *     tags={"Projects"},
     *     summary="Estatísticas do projeto",
     *     description="Retorna estatísticas detalhadas de um projeto específico",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do projeto",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estatísticas do projeto",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="tasks_total", type="integer", example=25),
     *                         @OA\Property(property="tasks_completed", type="integer", example=15),
     *                         @OA\Property(property="tasks_in_progress", type="integer", example=8),
     *                         @OA\Property(property="tasks_pending", type="integer", example=2),
     *                         @OA\Property(property="completion_percentage", type="number", format="float", example=60.0),
     *                         @OA\Property(property="teams_count", type="integer", example=3),
     *                         @OA\Property(property="members_count", type="integer", example=12)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Projeto não encontrado"
     *     )
     * )
     * 
     * Estatísticas do projeto
     */
    public function statistics(Project $project): JsonResponse
    {
        // Verifica se o usuário tem acesso ao projeto
        if ($project->owner_id !== Auth::id() &&
            ! $project->teams()->where('owner_id', Auth::id())->exists()) {
            return $this->forbiddenResponse('Acesso negado');
        }

        // Usar cache para estatísticas do projeto
        $statistics = $this->getCachedProjectStatistics($project->id);

        return $this->successResponse($statistics, 'Estatísticas do projeto');
    }
}
