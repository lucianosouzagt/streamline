<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

trait CacheableTrait
{
    /**
     * Cache para estatísticas do usuário
     */
    protected function getCachedUserStatistics(int $userId): array
    {
        $cacheKey = "user_stats_{$userId}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($userId) {
            $user = \App\Models\User::find($userId);
            
            return [
                'projects_count' => $user->projects()->count(),
                'teams_count' => $user->teams()->count(),
                'tasks_created' => $user->createdTasks()->count(),
                'tasks_assigned' => $user->assignedTasks()->count(),
                'tasks_pending' => $user->assignedTasks()->where('status', 'todo')->count(),
                'tasks_in_progress' => $user->assignedTasks()->where('status', 'in_progress')->count(),
            ];
        });
    }

    /**
     * Cache para projetos recentes do usuário
     */
    protected function getCachedRecentProjects(int $userId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "user_recent_projects_{$userId}_{$limit}";
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userId, $limit) {
            $user = \App\Models\User::find($userId);
            
            return $user->projects()
                ->with(['teams:id,name', 'tasks:id,project_id,status'])
                ->latest()
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Cache para times recentes do usuário
     */
    protected function getCachedRecentTeams(int $userId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "user_recent_teams_{$userId}_{$limit}";
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userId, $limit) {
            $user = \App\Models\User::find($userId);
            
            return $user->teams()
                ->with(['projects:id,name,status', 'owner:id,name'])
                ->latest()
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Cache para tarefas recentes do usuário
     */
    protected function getCachedRecentTasks(int $userId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "user_recent_tasks_{$userId}_{$limit}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($userId, $limit) {
            $user = \App\Models\User::find($userId);
            
            return $user->assignedTasks()
                ->with(['project:id,name', 'creator:id,name'])
                ->latest()
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Cache para lista de usuários (busca)
     */
    protected function getCachedUsers(string $search = null, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = $search ? "users_search_{$search}_{$limit}" : "users_list_{$limit}";
        
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($search, $limit) {
            $query = \App\Models\User::select(['id', 'name', 'email', 'created_at', 'updated_at']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            return $query->limit($limit)->get();
        });
    }

    /**
     * Cache para estatísticas de projeto
     */
    protected function getCachedProjectStatistics(int $projectId): array
    {
        $cacheKey = "project_stats_{$projectId}";
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($projectId) {
            $project = \App\Models\Project::find($projectId);
            
            $totalTasks = $project->tasks()->count();
            $completedTasks = $project->tasks()->where('status', 'done')->count();
            $pendingTasks = $project->tasks()->where('status', 'todo')->count();
            $inProgressTasks = $project->tasks()->where('status', 'in_progress')->count();
            $reviewTasks = $project->tasks()->where('status', 'review')->count();

            $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

            return [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $pendingTasks,
                'in_progress_tasks' => $inProgressTasks,
                'review_tasks' => $reviewTasks,
                'progress_percentage' => $progress,
                'teams_count' => $project->teams()->count(),
            ];
        });
    }

    /**
     * Limpa cache relacionado ao usuário
     */
    protected function clearUserCache(int $userId): void
    {
        $patterns = [
            "user_stats_{$userId}",
            "user_recent_projects_{$userId}_*",
            "user_recent_teams_{$userId}_*",
            "user_recent_tasks_{$userId}_*",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // Para padrões com wildcard, precisaríamos de uma implementação mais complexa
                // Por simplicidade, vamos limpar as chaves mais comuns
                Cache::forget(str_replace('*', '5', $pattern));
                Cache::forget(str_replace('*', '10', $pattern));
                Cache::forget(str_replace('*', '20', $pattern));
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Limpa cache relacionado ao projeto
     */
    protected function clearProjectCache(int $projectId): void
    {
        Cache::forget("project_stats_{$projectId}");
    }

    /**
     * Limpa cache de busca de usuários
     */
    protected function clearUsersCache(): void
    {
        // Limpar cache básico de usuários
        Cache::forget('users_list_20');
        
        // Para buscas específicas, seria necessário um sistema mais sofisticado
        // Por simplicidade, vamos usar tags de cache se disponível
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['users'])->flush();
        }
    }
}