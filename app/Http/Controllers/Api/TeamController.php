<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheableTrait;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    use AuthorizesRequests, ApiResponseTrait, CacheableTrait;

    /**
     * @OA\Get(
     *     path="/api/teams",
     *     tags={"Teams"},
     *     summary="Listar times",
     *     description="Lista todos os times do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número da página",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de times paginada",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(
     *                             property="data",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/Team")
     *                         ),
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
     *         response=401,
     *         description="Não autenticado"
     *     )
     * )
     * 
     * Lista todos os times do usuário autenticado
     */
    public function index(): JsonResponse
    {
        $teams = Team::with(['owner:id,name', 'projects' => function ($query) {
                $query->select('projects.id', 'projects.name', 'projects.status', 'projects.owner_id')
                      ->with('owner:id,name');
            }])
            ->where('owner_id', Auth::id())
            ->orWhereHas('projects.owner', function ($query) {
                $query->where('users.id', Auth::id());
            })
            ->active()
            ->latest()
            ->paginate(15);

        return $this->successResponse($teams);
    }

    /**
     * @OA\Post(
     *     path="/api/teams",
     *     tags={"Teams"},
     *     summary="Criar time",
     *     description="Cria um novo time",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Time de Desenvolvimento"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Time responsável pelo desenvolvimento do produto"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Time criado com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Team")
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
     * Cria um novo time
     */
    public function store(Request $request): JsonResponse
    {
        // Verificar autorização
        $this->authorize('create', Team::class);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean',
            ]);

            $team = Team::create([
                ...$validated,
                'owner_id' => Auth::id(),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Limpar cache relacionado após criação
            $this->clearUserCache(Auth::id());

            $team->load(['owner', 'projects']);

            return $this->successResponse($team, 'Time criado com sucesso', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/teams/{id}",
     *     tags={"Teams"},
     *     summary="Exibir time",
     *     description="Exibe informações de um time específico",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do time",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Time encontrado",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Team")
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
     *         description="Time não encontrado"
     *     )
     * )
     * 
     * Exibe um time específico
     */
    public function show(Team $team): JsonResponse
    {
        try {
            // Verifica se o usuário tem acesso ao time
            if ($team->owner_id !== Auth::id() &&
                ! $team->projects()->whereHas('owner', function ($query) {
                    $query->where('id', Auth::id());
                })->exists()) {
                return $this->forbiddenResponse();
            }

            $team->load(['owner', 'projects.tasks']);

            return $this->successResponse($team);
        } catch (\Exception $e) {
            return $this->internalErrorResponse();
        }
    }

    /**
     * @OA\Put(
     *     path="/api/teams/{id}",
     *     tags={"Teams"},
     *     summary="Atualizar time",
     *     description="Atualiza informações de um time existente",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do time",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Time de Desenvolvimento Atualizado"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Nova descrição do time"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Time atualizado com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Team")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Apenas o dono pode editar o time"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     * 
     * Atualiza um time
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        // Verifica se o usuário é o dono do time
        if ($team->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o dono pode editar o time');
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean',
            ]);

            $team->update($validated);

            // Limpar cache relacionado após atualização
            $this->clearUserCache($team->owner_id);

            $team->load(['owner', 'projects']);

            return $this->successResponse($team, 'Time atualizado com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/teams/{id}",
     *     tags={"Teams"},
     *     summary="Excluir time",
     *     description="Remove um time do sistema",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do time",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Time excluído com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Apenas o dono pode excluir o time"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Não é possível excluir um time com projetos associados"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Time não encontrado"
     *     )
     * )
     * 
     * Remove um time
     */
    public function destroy(Team $team): JsonResponse
    {
        // Verifica se o usuário é o dono do time
        if ($team->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o dono pode excluir o time');
        }

        // Verifica se há projetos associados
        if ($team->projects()->count() > 0) {
            return $this->errorResponse('Não é possível excluir um time com projetos associados', 422);
        }

        // Limpar cache relacionado antes da exclusão
        $this->clearUserCache($team->owner_id);

        $team->delete();

        return $this->successResponse(null, 'Time excluído com sucesso');
    }

    /**
     * @OA\Post(
     *     path="/api/teams/{id}/projects",
     *     tags={"Teams"},
     *     summary="Adicionar projeto ao time",
     *     description="Adiciona um projeto existente ao time",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do time",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="project_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projeto adicionado ao time com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Apenas o dono pode gerenciar projetos do time"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Projeto não encontrado"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Projeto já está associado a este time"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     * 
     * Adiciona um projeto ao time
     */
    public function addProject(Request $request, Team $team): JsonResponse
    {
        if ($team->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o dono pode gerenciar projetos do time');
        }

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
            ]);

            // Verificação adicional se o projeto existe e se o usuário tem acesso
            $project = Project::find($validated['project_id']);
            if (!$project) {
                return $this->notFoundResponse('Projeto não encontrado');
            }

            // Verifica se o usuário tem acesso ao projeto
            if ($project->owner_id !== Auth::id()) {
                return $this->forbiddenResponse('Você não tem permissão para adicionar este projeto ao time');
            }

            // Verifica se o projeto já está no time
            if ($team->projects()->where('project_id', $validated['project_id'])->exists()) {
                return $this->errorResponse('Projeto já está associado a este time', 409);
            }

            $team->projects()->syncWithoutDetaching([$validated['project_id']]);

            // Limpar cache relacionado após adicionar projeto
            $this->clearUserCache($team->owner_id);
            $this->clearProjectCache($validated['project_id']);

            return $this->successResponse(null, 'Projeto adicionado ao time com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/teams/{id}/projects",
     *     tags={"Teams"},
     *     summary="Remover projeto do time",
     *     description="Remove um projeto do time",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do time",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="project_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projeto removido do time com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Apenas o dono pode gerenciar projetos do time"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Projeto não está associado a este time"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     * 
     * Remove um projeto do time
     */
    public function removeProject(Request $request, Team $team): JsonResponse
    {
        if ($team->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o dono pode gerenciar projetos do time');
        }

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
            ]);

            // Verifica se o projeto está realmente associado ao time
            if (!$team->projects()->where('project_id', $validated['project_id'])->exists()) {
                return $this->notFoundResponse('Projeto não está associado a este time');
            }

            $team->projects()->detach($validated['project_id']);

            // Limpar cache relacionado após remover projeto
            $this->clearUserCache($team->owner_id);
            $this->clearProjectCache($validated['project_id']);

            return $this->successResponse(null, 'Projeto removido do time com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }
}
