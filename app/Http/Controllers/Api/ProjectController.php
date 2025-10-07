<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    /**
     * Lista todos os projetos do usuário autenticado
     */
    public function index(): JsonResponse
    {
        $projects = Project::with(['owner', 'teams', 'tasks'])
            ->where('owner_id', Auth::id())
            ->orWhereHas('teams', function ($query) {
                $query->where('owner_id', Auth::id());
            })
            ->active()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Cria um novo projeto
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'required|in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date|after_or_equal:today',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            $project = Project::create([
                ...$validated,
                'owner_id' => Auth::id(),
            ]);

            $project->load(['owner', 'teams', 'tasks']);

            return response()->json([
                'success' => true,
                'message' => 'Projeto criado com sucesso',
                'data' => $project,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Exibe um projeto específico
     */
    public function show(Project $project): JsonResponse
    {
        // Verifica se o usuário tem acesso ao projeto
        if ($project->owner_id !== Auth::id() && 
            !$project->teams()->where('owner_id', Auth::id())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado',
            ], 403);
        }

        $project->load(['owner', 'teams', 'tasks.users', 'tasks.creator']);

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Atualiza um projeto
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        // Verifica se o usuário é o dono do projeto
        if ($project->owner_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas o dono pode editar o projeto',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'sometimes|required|in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            $project->update($validated);
            $project->load(['owner', 'teams', 'tasks']);

            return response()->json([
                'success' => true,
                'message' => 'Projeto atualizado com sucesso',
                'data' => $project,
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
     * Remove um projeto
     */
    public function destroy(Project $project): JsonResponse
    {
        // Verifica se o usuário é o dono do projeto
        if ($project->owner_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas o dono pode excluir o projeto',
            ], 403);
        }

        // Verifica se há tarefas associadas
        if ($project->tasks()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível excluir um projeto com tarefas. Exclua as tarefas primeiro.',
            ], 422);
        }

        // Remove associações com times
        $project->teams()->detach();
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Projeto excluído com sucesso',
        ]);
    }

    /**
     * Lista projetos por status
     */
    public function byStatus(string $status): JsonResponse
    {
        $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
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
     * Estatísticas do projeto
     */
    public function statistics(Project $project): JsonResponse
    {
        // Verifica se o usuário tem acesso ao projeto
        if ($project->owner_id !== Auth::id() && 
            !$project->teams()->where('owner_id', Auth::id())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado',
            ], 403);
        }

        $totalTasks = $project->tasks()->count();
        $completedTasks = $project->tasks()->where('status', 'completed')->count();
        $pendingTasks = $project->tasks()->where('status', 'pending')->count();
        $inProgressTasks = $project->tasks()->where('status', 'in_progress')->count();
        
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        $statistics = [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $pendingTasks,
            'in_progress_tasks' => $inProgressTasks,
            'progress_percentage' => $progress,
            'teams_count' => $project->teams()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
}