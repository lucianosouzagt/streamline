<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Para rotas web, redireciona para login
        $middleware->redirectGuestsTo('/login');

        // Registrar middleware customizado de CSRF
        $middleware->alias([
            'csrf' => \App\Http\Middleware\VerifyCsrfToken::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);

        // Para APIs, configuramos o middleware de autenticação
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Configurar resposta JSON para APIs quando não autenticado
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        // Tratamento para ModelNotFoundException em rotas API
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                $model = $e->getModel();
                $modelName = class_basename($model);
                
                $messages = [
                    'Team' => 'Time não encontrado',
                    'Project' => 'Projeto não encontrado',
                    'Task' => 'Tarefa não encontrada',
                    'User' => 'Usuário não encontrado',
                ];
                
                $message = $messages[$modelName] ?? 'Recurso não encontrado';
                
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error' => 'O recurso solicitado não existe no sistema',
                    'resource' => strtolower($modelName)
                ], 404);
            }
        });

        // Tratamento para NotFoundHttpException em rotas API
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                // Verificar se a mensagem contém informações sobre modelo
                $message = $e->getMessage();
                if (preg_match('/No query results for model \[App\\\\Models\\\\(\w+)\]/', $message, $matches)) {
                    $modelName = $matches[1];
                    $messages = [
                        'Team' => 'Time não encontrado',
                        'Project' => 'Projeto não encontrado',
                        'Task' => 'Tarefa não encontrada',
                        'User' => 'Usuário não encontrado',
                    ];
                    
                    $customMessage = $messages[$modelName] ?? 'Recurso não encontrado';
                    
                    return response()->json([
                        'success' => false,
                        'message' => $customMessage,
                        'error' => 'O recurso solicitado não existe no sistema',
                        'resource' => strtolower($modelName)
                    ], 404);
                }
                
                // Para outras exceções 404
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso não encontrado',
                    'error' => 'A rota ou recurso solicitado não existe'
                ], 404);
            }
        });

        // Tratamento para ValidationException em rotas API
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $e->errors()
                ], 422);
            }
        });

        // Tratamento para AuthorizationException em rotas API
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
            }
        });
    })->create();
