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
        return $this->successResponse($user->only(['id', 'name', 'email', 'created_at', 'updated_at']));
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
