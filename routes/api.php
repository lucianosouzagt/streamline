<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rotas de autenticação
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Rotas protegidas por autenticação
Route::middleware('auth:sanctum')->group(function () {
    // Rota para listar todas as roles
    Route::get('/roles', [UserController::class, 'listRoles']);

    // Rotas de usuários
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::put('/password', [UserController::class, 'updatePassword']);
        Route::get('/dashboard', [UserController::class, 'dashboard']);
        Route::get('/projects', [UserController::class, 'myProjects']);
        Route::get('/teams', [UserController::class, 'myTeams']);
        Route::get('/tasks', [UserController::class, 'myTasks']);
        Route::delete('/account', [UserController::class, 'deleteAccount']);
        Route::get('/{user}', [UserController::class, 'show']);

        // Gerenciamento de roles
        Route::post('/roles/list', [UserController::class, 'getRoles']);
        Route::post('/roles/assign', [UserController::class, 'assignRole']);
        Route::post('/roles/remove', [UserController::class, 'removeRole']);
    });

    // Rotas de times
    Route::prefix('teams')->group(function () {
        Route::get('/', [TeamController::class, 'index']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{team}', [TeamController::class, 'show']);
        Route::put('/{team}', [TeamController::class, 'update']);
        Route::delete('/{team}', [TeamController::class, 'destroy']);

        // Gerenciamento de projetos no time
        Route::post('/{team}/projects', [TeamController::class, 'addProject']);
        Route::delete('/{team}/projects', [TeamController::class, 'removeProject']);
    });

    // Rotas de projetos
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/status/{status}', [ProjectController::class, 'byStatus']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);
        Route::get('/{project}/statistics', [ProjectController::class, 'statistics']);
    });

    // Rotas de tarefas
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/', [TaskController::class, 'store']);
        Route::get('/status/{status}', [TaskController::class, 'byStatus']);
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::put('/{task}', [TaskController::class, 'update']);
        Route::delete('/{task}', [TaskController::class, 'destroy']);

        // Gerenciamento de usuários na tarefa
        Route::post('/{task}/assign', [TaskController::class, 'assignUser']);
        Route::delete('/{task}/unassign', [TaskController::class, 'unassignUser']);
    });
});
