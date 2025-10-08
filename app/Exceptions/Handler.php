<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Forçar resposta JSON para todas as rotas da API
        if ($request->is('api/*')) {
            // Tratamento específico para ModelNotFoundException
            if ($exception instanceof ModelNotFoundException) {
                $model = $exception->getModel();
                $modelName = class_basename($model);
                
                // Mensagens específicas por modelo
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

            // Tratamento para NotFoundHttpException (que pode vir de ModelNotFoundException)
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                // Verificar se a mensagem contém informações sobre modelo
                $message = $exception->getMessage();
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

            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $exception->errors()
                ], 422);
            }

            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não autenticado'
                ], 401);
            }

            if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
            }

            // Para outras exceções, retornar erro genérico
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => config('app.debug') ? $exception->getMessage() : 'Ocorreu um erro inesperado'
            ], 500);
        }

        return parent::render($request, $exception);
    }
}