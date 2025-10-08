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
     * Exibe um usuário específico
     */
    public function show(User $user): JsonResponse
    {
        return $this->successResponse($user->only(['id', 'name', 'email', 'created_at', 'updated_at']));
    }

    /**
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
