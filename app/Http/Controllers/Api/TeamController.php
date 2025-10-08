<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheableTrait;
use App\Models\Project;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    /**
     * @OA\Post(
     *     path="/api/teams/{id}/users",
     *     tags={"Teams"},
     *     summary="Criar usuário para equipe",
     *     description="Cria um novo usuário e o adiciona à equipe (requer permissão users.create)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID da equipe",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="João Silva"),
     *             @OA\Property(property="email", type="string", format="email", example="joao@exemplo.com"),
     *             @OA\Property(property="password", type="string", example="senha123"),
     *             @OA\Property(property="password_confirmation", type="string", example="senha123"),
     *             @OA\Property(property="role", type="string", nullable=true, example="member", description="Papel a ser atribuído (admin, manager, member)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuário criado com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="roles", type="array", @OA\Items(type="object"))
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado - sem permissão ou não é dono da equipe"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     * 
     * Cria um novo usuário para a equipe
     */
    public function createUser(CreateUserRequest $request, Team $team): JsonResponse
    {
        // Verificar se é o dono da equipe ou tem permissão de gerenciar equipes
        if ($team->owner_id !== Auth::id() && !Auth::user()->hasPermission('teams.edit')) {
            return $this->forbiddenResponse('Apenas o dono da equipe pode adicionar usuários');
        }

        try {
            $validated = $request->validated();

            // Criar o usuário
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(), // Auto-verificar email para usuários criados por administradores
            ]);

            // Atribuir role se especificada
            $roleName = $validated['role'] ?? 'member';
            $role = Role::where('name', $roleName)->first();
            
            if ($role) {
                $user->roles()->attach($role->id);
            }

            // Carregar relacionamentos para resposta
            $user->load('roles');

            // Limpar cache relacionado
            $this->clearUserCache(Auth::id());

            return $this->successResponse($user, 'Usuário criado com sucesso para a equipe', 201);
        } catch (\Exception $e) {
            return $this->internalErrorResponse('Erro interno ao criar usuário');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/teams/{id}/members",
     *     tags={"Teams"},
     *     summary="Adicionar membro à equipe",
     *     description="Adiciona um usuário existente como membro da equipe (requer ser dono da equipe ou permissão teams.manage_members)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID da equipe",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", example=1, description="ID do usuário a ser adicionado"),
     *             @OA\Property(property="role", type="string", example="member", description="Role do usuário na equipe (opcional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Membro adicionado com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sem permissão para gerenciar membros da equipe"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuário não encontrado"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Usuário já é membro da equipe"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     * 
     * Adiciona um membro à equipe
     */
    public function addMember(Request $request, Team $team): JsonResponse
    {
        // Verificar permissões
        if ($team->owner_id !== Auth::id() && !Auth::user()->hasPermission('teams.manage_members')) {
            return $this->forbiddenResponse('Você não tem permissão para gerenciar membros desta equipe');
        }

        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'role' => 'nullable|string|in:member,admin,manager',
            ]);

            $user = User::find($validated['user_id']);
            if (!$user) {
                return $this->notFoundResponse('Usuário não encontrado');
            }

            // Verificar se o usuário já é membro da equipe
            if ($team->users()->where('user_id', $validated['user_id'])->exists()) {
                return $this->errorResponse('Usuário já é membro desta equipe', 409);
            }

            // Adicionar o usuário à equipe
            $role = $validated['role'] ?? 'member';
            $team->users()->attach($validated['user_id'], ['role' => $role]);

            // Limpar cache relacionado
            $this->clearUserCache(Auth::id());
            $this->clearUserCache($validated['user_id']);

            return $this->successResponse(null, 'Membro adicionado à equipe com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->internalErrorResponse('Erro interno ao adicionar membro à equipe');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/teams/{id}/members/{user}",
     *     tags={"Teams"},
     *     summary="Remover membro da equipe",
     *     description="Remove um usuário da equipe (requer ser dono da equipe ou permissão teams.manage_members)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID da equipe",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="ID do usuário a ser removido",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Membro removido com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sem permissão para gerenciar membros da equipe"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuário não é membro da equipe"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Não é possível remover o dono da equipe"
     *     )
     * )
     * 
     * Remove um membro da equipe
     */
    public function removeMember(Team $team, User $user): JsonResponse
    {
        // Verificar permissões
        if ($team->owner_id !== Auth::id() && !Auth::user()->hasPermission('teams.manage_members')) {
            return $this->forbiddenResponse('Você não tem permissão para gerenciar membros desta equipe');
        }

        // Não permitir remover o dono da equipe
        if ($team->owner_id === $user->id) {
            return $this->errorResponse('Não é possível remover o dono da equipe', 422);
        }

        // Verificar se o usuário é membro da equipe
        if (!$team->users()->where('user_id', $user->id)->exists()) {
            return $this->notFoundResponse('Usuário não é membro desta equipe');
        }

        try {
            // Remover o usuário da equipe
            $team->users()->detach($user->id);

            // Limpar cache relacionado
            $this->clearUserCache(Auth::id());
            $this->clearUserCache($user->id);

            return $this->successResponse(null, 'Membro removido da equipe com sucesso');
        } catch (\Exception $e) {
            return $this->internalErrorResponse('Erro interno ao remover membro da equipe');
        }
    }
}
