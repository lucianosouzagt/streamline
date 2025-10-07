<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Lista todas as tarefas do usuário autenticado
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::with(['project', 'creator', 'users'])
            ->whereHas('project', function ($q) {
                $q->where('owner_id', Auth::id())
                  ->orWhereHas('teams', function ($teamQuery) {
                      $teamQuery->where('owner_id', Auth::id());
                  });
            })
            ->orWhere('created_by', Auth::id())
            ->orWhereHas('users', function ($q) {
                $q->where('user_id', Auth::id());
            });

        // Filtros opcionais
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $tasks = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Cria uma nova tarefa
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:2000',
                'project_id' => 'required|exists:projects,id',
                'status' => 'required|in:pending,in_progress,completed,cancelled',
                'priority' => 'required|in:low,medium,high,urgent',
                'due_date' => 'nullable|date|after_or_equal:today',
                'assigned_users' => 'nullable|array',
                'assigned_users.*' => 'exists:users,id',
            ]);

            // Verifica se o usuário tem acesso ao projeto
            $project = Project::findOrFail($validated['project_id']);
            if ($project->owner_id !== Auth::id() && 
                !$project->teams()->where('owner_id', Auth::id())->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar tarefas neste projeto',
                ], 403);
            }

            $task = Task::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'project_id' => $validated['project_id'],
                'created_by' => Auth::id(),
                'status' => $validated['status'],
                'priority' => $validated['priority'],
                'due_date' => $validated['due_date'],
            ]);

            // Atribui usuários à tarefa se especificado
            if (!empty($validated['assigned_users'])) {
                $task->users()->attach($validated['assigned_users']);
            }

            $task->load(['project', 'creator', 'users']);

            return response()->json([
                'success' => true,
                'message' => 'Tarefa criada com sucesso',
                'data' => $task,
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
     * Exibe uma tarefa específica
     */
    public function show(Task $task): JsonResponse
    {
        // Verifica se o usuário tem acesso à tarefa
        if (!$this->userCanAccessTask($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado',
            ], 403);
        }

        $task->load(['project', 'creator', 'users']);

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    /**
     * Atualiza uma tarefa
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        // Verifica se o usuário pode editar a tarefa
        if (!$this->userCanEditTask($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para editar esta tarefa',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:2000',
                'status' => 'sometimes|required|in:pending,in_progress,completed,cancelled',
                'priority' => 'sometimes|required|in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
                'assigned_users' => 'nullable|array',
                'assigned_users.*' => 'exists:users,id',
            ]);

            $task->update(collect($validated)->except('assigned_users')->toArray());

            // Atualiza usuários atribuídos se especificado
            if (array_key_exists('assigned_users', $validated)) {
                $task->users()->sync($validated['assigned_users'] ?? []);
            }

            $task->load(['project', 'creator', 'users']);

            return response()->json([
                'success' => true,
                'message' => 'Tarefa atualizada com sucesso',
                'data' => $task,
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
     * Remove uma tarefa
     */
    public function destroy(Task $task): JsonResponse
    {
        // Verifica se o usuário pode excluir a tarefa
        if (!$this->userCanEditTask($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para excluir esta tarefa',
            ], 403);
        }

        // Remove associações com usuários
        $task->users()->detach();
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tarefa excluída com sucesso',
        ]);
    }

    /**
     * Atribui um usuário à tarefa
     */
    public function assignUser(Request $request, Task $task): JsonResponse
    {
        if (!$this->userCanEditTask($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para atribuir usuários a esta tarefa',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|max:50',
        ]);

        $task->users()->syncWithoutDetaching([
            $validated['user_id'] => ['role' => $validated['role'] ?? 'assignee']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuário atribuído à tarefa com sucesso',
        ]);
    }

    /**
     * Remove um usuário da tarefa
     */
    public function unassignUser(Request $request, Task $task): JsonResponse
    {
        if (!$this->userCanEditTask($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para remover usuários desta tarefa',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $task->users()->detach($validated['user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Usuário removido da tarefa com sucesso',
        ]);
    }

    /**
     * Lista tarefas por status
     */
    public function byStatus(string $status): JsonResponse
    {
        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Status inválido',
            ], 422);
        }

        $tasks = Task::with(['project', 'creator', 'users'])
            ->whereHas('project', function ($q) {
                $q->where('owner_id', Auth::id())
                  ->orWhereHas('teams', function ($teamQuery) {
                      $teamQuery->where('owner_id', Auth::id());
                  });
            })
            ->where('status', $status)
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Verifica se o usuário pode acessar a tarefa
     */
    private function userCanAccessTask(Task $task): bool
    {
        return $task->created_by === Auth::id() ||
               $task->project->owner_id === Auth::id() ||
               $task->project->teams()->where('owner_id', Auth::id())->exists() ||
               $task->users()->where('user_id', Auth::id())->exists();
    }

    /**
     * Verifica se o usuário pode editar a tarefa
     */
    private function userCanEditTask(Task $task): bool
    {
        return $task->created_by === Auth::id() ||
               $task->project->owner_id === Auth::id() ||
               $task->project->teams()->where('owner_id', Auth::id())->exists();
    }
}