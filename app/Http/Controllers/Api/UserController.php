<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Lista usuários (apenas para busca/atribuição)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::select(['id', 'name', 'email', 'created_at']);

        // Busca por nome ou email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Exibe o perfil do usuário autenticado
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        $user->load(['roles.permissions']);

        // Estatísticas do usuário
        $stats = [
            'teams_owned' => $user->ownedTeams()->count(),
            'projects_owned' => $user->ownedProjects()->count(),
            'tasks_created' => $user->createdTasks()->count(),
            'tasks_assigned' => $user->tasks()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'statistics' => $stats,
            ],
        ]);
    }

    /**
     * Atualiza o perfil do usuário autenticado
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . Auth::id(),
            ]);

            $user = Auth::user();
            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => $user,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Atualiza a senha do usuário autenticado
     */
    public function updatePassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            $user = Auth::user();

            // Verifica a senha atual
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Senha atual incorreta',
                ], 422);
            }

            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Senha atualizada com sucesso',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Exibe um usuário específico (informações públicas)
     */
    public function show(User $user): JsonResponse
    {
        $userData = $user->only(['id', 'name', 'email', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $userData,
        ]);
    }

    /**
     * Lista projetos do usuário autenticado
     */
    public function myProjects(): JsonResponse
    {
        $user = Auth::user();
        
        $projects = $user->ownedProjects()
            ->with(['teams', 'tasks'])
            ->withCount(['tasks', 'teams'])
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Lista times do usuário autenticado
     */
    public function myTeams(): JsonResponse
    {
        $user = Auth::user();
        
        $teams = $user->ownedTeams()
            ->with(['projects'])
            ->withCount(['projects'])
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }

    /**
     * Lista tarefas atribuídas ao usuário autenticado
     */
    public function myTasks(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = $user->tasks()->with(['project', 'creator']);

        // Filtros opcionais
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $tasks = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Dashboard com estatísticas do usuário
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        $stats = [
            'teams' => [
                'owned' => $user->ownedTeams()->count(),
                'active' => $user->ownedTeams()->active()->count(),
            ],
            'projects' => [
                'owned' => $user->ownedProjects()->count(),
                'active' => $user->ownedProjects()->active()->count(),
                'completed' => $user->ownedProjects()->where('status', 'completed')->count(),
            ],
            'tasks' => [
                'created' => $user->createdTasks()->count(),
                'assigned' => $user->tasks()->count(),
                'completed' => $user->tasks()->where('status', 'completed')->count(),
                'pending' => $user->tasks()->where('status', 'pending')->count(),
                'in_progress' => $user->tasks()->where('status', 'in_progress')->count(),
            ],
        ];

        // Tarefas recentes
        $recentTasks = $user->tasks()
            ->with(['project', 'creator'])
            ->latest()
            ->limit(5)
            ->get();

        // Projetos recentes
        $recentProjects = $user->ownedProjects()
            ->with(['teams'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'recent_tasks' => $recentTasks,
                'recent_projects' => $recentProjects,
            ],
        ]);
    }

    /**
     * Deleta a conta do usuário autenticado
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        // Verifica a senha
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha incorreta',
            ], 422);
        }

        // Verifica se há projetos ou times ativos
        if ($user->ownedProjects()->count() > 0 || $user->ownedTeams()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível excluir a conta. Você possui projetos ou times ativos. Transfira a propriedade ou exclua-os primeiro.',
            ], 422);
        }

        // Remove tokens de acesso
        $user->tokens()->delete();
        
        // Remove associações com tarefas
        $user->tasks()->detach();
        
        // Deleta o usuário
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conta excluída com sucesso',
        ]);
    }
}