<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheableTrait;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    use ApiResponseTrait, CacheableTrait;
    /**
     * @OA\Get(
     *     path="/api/tasks",
     *     tags={"Tasks"},
     *     summary="Listar tarefas",
     *     description="Lista todas as tarefas do usuário autenticado",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tarefas",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/Task")
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
     * Lista todas as tarefas do usuário autenticado
     */
    public function index(): JsonResponse
    {
        $tasks = Task::with(['project:id,name,status', 'creator:id,name', 'users:id,name'])
            ->where('created_by', Auth::id())
            ->orWhereHas('users', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->latest()
            ->get();

        return $this->successResponse($tasks);
    }

    /**
     * @OA\Post(
     *     path="/api/tasks",
     *     tags={"Tasks"},
     *     summary="Criar tarefa",
     *     description="Cria uma nova tarefa",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "project_id"},
     *             @OA\Property(property="title", type="string", example="Implementar autenticação"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Implementar sistema de login e registro"),
     *             @OA\Property(property="project_id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, example="pending"),
     *             @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="medium"),
     *             @OA\Property(property="due_date", type="string", format="date", nullable=true),
     *             @OA\Property(
     *                 property="assigned_users",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tarefa criada com sucesso",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Task")
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
     * Cria uma nova tarefa
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'in:todo,in_progress,review,done,cancelled',
                'priority' => 'in:low,medium,high,urgent',
                'project_id' => 'required|exists:projects,id',
                'due_date' => 'nullable|date|after_or_equal:today',
                'assigned_users' => 'array',
                'assigned_users.*' => 'exists:users,id',
            ]);

            // Verifica se o usuário tem acesso ao projeto
            $project = Project::find($validated['project_id']);
            if ($project->owner_id !== Auth::id() &&
                ! $project->teams()->whereHas('owner', function ($query) {
                    $query->where('id', Auth::id());
                })->exists()) {
                return $this->forbiddenResponse('Você não tem permissão para criar tarefas neste projeto');
            }

            $validated['created_by'] = Auth::id();
            $assignedUsers = $validated['assigned_users'] ?? [];
            unset($validated['assigned_users']);

            $task = Task::create($validated);

            if (!empty($assignedUsers)) {
                $task->users()->sync($assignedUsers);
            }

            // Limpar cache relacionado após criação
            $this->clearUserCache(Auth::id());
            $this->clearProjectCache($task->project_id);

            $task->load(['project', 'creator', 'users']);

            return $this->successResponse($task, 'Tarefa criada com sucesso', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * Exibe uma tarefa específica
     */
    public function show(Task $task): JsonResponse
    {
        try {
            // Verifica se o usuário tem acesso à tarefa
            if ($task->created_by !== Auth::id() &&
                ! $task->users()->where('user_id', Auth::id())->exists() &&
                $task->project->owner_id !== Auth::id()) {
                return $this->forbiddenResponse();
            }

            $task->load(['project', 'creator', 'users']);

            return $this->successResponse($task);
        } catch (\Exception $e) {
            return $this->internalErrorResponse();
        }
    }

    /**
     * Atualiza uma tarefa
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        // Verifica se o usuário pode editar a tarefa
        if ($task->created_by !== Auth::id() && $task->project->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o criador da tarefa ou dono do projeto pode editá-la');
        }

        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'in:todo,in_progress,review,done,cancelled',
                'priority' => 'in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
                'assigned_users' => 'array',
                'assigned_users.*' => 'exists:users,id',
            ]);

            $assignedUsers = $validated['assigned_users'] ?? null;
            unset($validated['assigned_users']);

            $task->update($validated);

            if ($assignedUsers !== null) {
                $task->users()->sync($assignedUsers);
            }

            // Limpar cache relacionado após atualização
            $this->clearUserCache($task->created_by);
            $this->clearProjectCache($task->project_id);

            $task->load(['project', 'creator', 'users']);

            return $this->successResponse($task, 'Tarefa atualizada com sucesso');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * Remove uma tarefa
     */
    public function destroy(Task $task): JsonResponse
    {
        // Verifica se o usuário pode excluir a tarefa
        if ($task->created_by !== Auth::id() && $task->project->owner_id !== Auth::id()) {
            return $this->forbiddenResponse('Apenas o criador da tarefa ou dono do projeto pode excluí-la');
        }

        // Limpar cache relacionado antes da exclusão
        $this->clearUserCache($task->created_by);
        $this->clearProjectCache($task->project_id);

        $task->delete();

        return $this->successResponse(null, 'Tarefa excluída com sucesso');
    }



    /**
     * Lista tarefas por status
     */
    public function byStatus(string $status): JsonResponse
    {
        $validStatuses = ['todo', 'in_progress', 'review', 'done', 'cancelled'];

        if (!in_array($status, $validStatuses)) {
            return $this->errorResponse('Status inválido', 400);
        }

        $tasks = Task::with(['project:id,name,status', 'creator:id,name', 'users:id,name'])
            ->where('status', $status)
            ->where(function ($query) {
                $query->where('created_by', Auth::id())
                    ->orWhereHas('users', function ($q) {
                        $q->where('user_id', Auth::id());
                    });
            })
            ->latest()
            ->get();

        return $this->successResponse($tasks);
    }


}
