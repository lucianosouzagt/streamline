<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TeamController extends Controller
{
    use AuthorizesRequests;
    /**
     * Lista todos os times do usuário autenticado
     */
    public function index(): JsonResponse
    {
        $teams = Team::with(['owner', 'projects'])
            ->where('owner_id', Auth::id())
            ->orWhereHas('projects.owner', function ($query) {
                $query->where('id', Auth::id());
            })
            ->active()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }

    /**
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

            $team->load(['owner', 'projects']);

            return response()->json([
                'success' => true,
                'message' => 'Time criado com sucesso',
                'data' => $team,
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
     * Exibe um time específico
     */
    public function show(Team $team): JsonResponse
    {
        // Verifica se o usuário tem acesso ao time
        if ($team->owner_id !== Auth::id() && 
            !$team->projects()->whereHas('owner', function ($query) {
                $query->where('id', Auth::id());
            })->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado',
            ], 403);
        }

        $team->load(['owner', 'projects.tasks']);

        return response()->json([
            'success' => true,
            'data' => $team,
        ]);
    }

    /**
     * Atualiza um time
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        // Verifica se o usuário é o dono do time
        if ($team->owner_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas o dono pode editar o time',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean',
            ]);

            $team->update($validated);
            $team->load(['owner', 'projects']);

            return response()->json([
                'success' => true,
                'message' => 'Time atualizado com sucesso',
                'data' => $team,
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
     * Remove um time
     */
    public function destroy(Team $team): JsonResponse
    {
        // Verifica se o usuário é o dono do time
        if ($team->owner_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas o dono pode excluir o time',
            ], 403);
        }

        // Verifica se há projetos associados
        if ($team->projects()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível excluir um time com projetos associados',
            ], 422);
        }

        $team->delete();

        return response()->json([
            'success' => true,
            'message' => 'Time excluído com sucesso',
        ]);
    }

    /**
     * Adiciona um projeto ao time
     */
    public function addProject(Request $request, Team $team): JsonResponse
    {
        if ($team->owner_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas o dono pode gerenciar projetos do time',
            ], 403);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $team->projects()->syncWithoutDetaching([$validated['project_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Projeto adicionado ao time com sucesso',
        ]);
    }

    /**
     * Remove um projeto do time
     */
    public function removeProject(Request $request, Team $team): JsonResponse
    {
        if ($team->owner_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas o dono pode gerenciar projetos do time',
            ], 403);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $team->projects()->detach($validated['project_id']);

        return response()->json([
            'success' => true,
            'message' => 'Projeto removido do time com sucesso',
        ]);
    }
}