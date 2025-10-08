<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheableTrait;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use ApiResponseTrait, CacheableTrait;
    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Listar usuários",
     *     description="Lista todos os usuários com paginação e busca opcional",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Termo de busca para filtrar usuários",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de usuários",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
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
     * Lista usuários (apenas para busca/atribuição)
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $users = $this->getCachedUsers($search, 20);

        return $this->successResponse($users);
    }

    /**
     * @OA\Get(
     *     path="/api/user/profile",
     *     tags={"Users"},
     *     summary="Obter perfil do usuário",
     *     description="Retorna o perfil do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Perfil do usuário",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
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
     * Exibe o perfil do usuário autenticado
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        return $this->successResponse($user);
    }

    /**
     * @OA\Put(
     *     path="/api/user/profile",
     *     tags={"Users"},
     *     summary="Atualizar perfil do usuário",
     *     description="Atualiza as informações do perfil do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=255, example="João Silva"),
     *             @OA\Property(property="email", type="string", format="email", example="joao@exemplo.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Perfil atualizado com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados inválidos",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     * 
     * Atualiza o perfil do usuário
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . Auth::id(),
            ]);

            $user = Auth::user();
            $user->update($validated);

            // Limpar cache do usuário após atualização
            $this->clearUserCache($user->id);

            return $this->successResponse($user, 'Perfil atualizado com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * @OA\Put(
     *     path="/api/user/password",
     *     tags={"Users"},
     *     summary="Atualizar senha do usuário",
     *     description="Atualiza a senha do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="senhaAtual123"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="novaSenha123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="novaSenha123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Senha atualizada com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="null")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados inválidos ou senha atual incorreta",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     * 
     * Atualiza a senha do usuário
     */
    public function updatePassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = Auth::user();

            if (!Hash::check($validated['current_password'], $user->password)) {
                return $this->errorResponse('Senha atual incorreta', 422, ['current_password' => ['Senha atual incorreta']]);
            }

            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            return $this->successResponse(null, 'Senha atualizada com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Criar usuário",
     *     description="Cria um novo usuário no sistema (requer permissão users.create)",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="João Silva", description="Nome do usuário"),
     *             @OA\Property(property="email", type="string", format="email", example="joao@exemplo.com", description="Email do usuário"),
     *             @OA\Property(property="password", type="string", example="senha123", description="Senha do usuário"),
     *             @OA\Property(property="password_confirmation", type="string", example="senha123", description="Confirmação da senha"),
     *             @OA\Property(property="role", type="string", example="member", description="Role inicial do usuário (opcional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuário criado com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sem permissão para criar usuários"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos"
     *     )
     * )
     * 
     * Cria um novo usuário
     */
    public function store(Request $request): JsonResponse
    {
        // Verificar permissão
        if (!Auth::user()->hasPermission('users.create')) {
            return $this->forbiddenResponse('Você não tem permissão para criar usuários');
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'nullable|string|exists:roles,name',
            ]);

            // Criar o usuário
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(), // Auto-verificar email para usuários criados por administradores
            ]);

            // Atribuir role se especificada
            if (isset($validated['role'])) {
                $role = \App\Models\Role::where('name', $validated['role'])->first();
                if ($role) {
                    $user->roles()->attach($role->id);
                }
            }

            // Carregar relacionamentos para resposta
            $user->load('roles');

            // Limpar cache relacionado
            $this->clearUserCache(Auth::id());

            return $this->successResponse($user, 'Usuário criado com sucesso', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->internalErrorResponse('Erro interno ao criar usuário');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Exibir usuário",
     *     description="Exibe informações de um usuário específico",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID do usuário",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuário encontrado",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuário não encontrado"
     *     )
     * )
     * 
     * Exibe um usuário específico
     */
    public function show(User $user): JsonResponse
    {
        return $this->successResponse($user->only(['id', 'name', 'email', 'created_at', 'updated_at']), 'Usuário encontrado com sucesso');
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{user}",
     *     tags={"Users"},
     *     summary="Excluir usuário",
     *     description="Remove um usuário do sistema (requer permissão users.delete)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="ID do usuário",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuário excluído com sucesso",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sem permissão para excluir usuários"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuário não encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Não é possível excluir usuário com dados associados"
     *     )
     * )
     * 
     * Exclui um usuário
     */
    public function destroy(User $user): JsonResponse
    {
        // Não permitir excluir o próprio usuário (verificar primeiro)
        if ($user->id === Auth::id()) {
            return $this->errorResponse('Você não pode excluir sua própria conta', 403);
        }

        // Verificar permissão
        if (!Auth::user()->hasPermission('users.delete')) {
            return $this->forbiddenResponse('Você não tem permissão para excluir usuários');
        }

        // Verificar se o usuário possui equipes como dono
        if ($user->ownedTeams()->count() > 0) {
            return $this->errorResponse('Não é possível excluir usuário que possui equipes como dono', 422);
        }

        // Verificar se o usuário possui projetos como dono
        if ($user->ownedProjects()->count() > 0) {
            return $this->errorResponse('Não é possível excluir usuário que possui projetos como dono', 422);
        }

        try {
            // Remover relacionamentos antes de excluir
            $user->teams()->detach();
            $user->projects()->detach();
            $user->tasks()->detach();
            $user->roles()->detach();

            // Limpar cache relacionado antes da exclusão
            $this->clearUserCache($user->id);
            $this->clearUserCache(Auth::id());

            $user->delete();

            return $this->successResponse(null, 'Usuário excluído com sucesso');
        } catch (\Exception $e) {
            return $this->internalErrorResponse('Erro interno ao excluir usuário');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user/dashboard",
     *     tags={"Users"},
     *     summary="Dashboard do usuário",
     *     description="Retorna estatísticas e dados recentes do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard do usuário",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="projects_count", type="integer", example=5),
     *                         @OA\Property(property="teams_count", type="integer", example=3),
     *                         @OA\Property(property="tasks_count", type="integer", example=12),
     *                         @OA\Property(
     *                             property="recent_projects",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/Project")
     *                         ),
     *                         @OA\Property(
     *                             property="recent_teams",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/Team")
     *                         ),
     *                         @OA\Property(
     *                             property="recent_tasks",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/Task")
     *                         )
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
     * Dashboard do usuário com estatísticas
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();
        $userId = $user->id;
        
        // Usar cache para estatísticas e dados recentes
        $stats = $this->getCachedUserStatistics($userId);
        $recentProjects = $this->getCachedRecentProjects($userId, 5);
        $recentTeams = $this->getCachedRecentTeams($userId, 5);
        $recentTasks = $this->getCachedRecentTasks($userId, 10);

        return $this->successResponse([
            'projects_count' => $stats['projects_count'],
            'teams_count' => $stats['teams_count'],
            'tasks_count' => $stats['tasks_created'] + $stats['tasks_assigned'],
            'recent_projects' => $recentProjects,
            'recent_teams' => $recentTeams,
            'recent_tasks' => $recentTasks,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user/projects",
     *     tags={"Users"},
     *     summary="Meus projetos",
     *     description="Lista todos os projetos do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de projetos do usuário",
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
     * Lista projetos do usuário
     */
    public function myProjects(): JsonResponse
    {
        $projects = Auth::user()->ownedProjects()
            ->with(['teams', 'tasks'])
            ->get();

        return $this->successResponse($projects);
    }

    /**
     * @OA\Get(
     *     path="/api/user/teams",
     *     tags={"Users"},
     *     summary="Meus times",
     *     description="Lista todos os times do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de times do usuário",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/Team")
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
     * Lista times do usuário
     */
    public function myTeams(): JsonResponse
    {
        $teams = Auth::user()->ownedTeams()
            ->with(['projects', 'owner'])
            ->get();

        return $this->successResponse($teams);
    }


}
