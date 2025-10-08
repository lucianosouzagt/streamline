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
        // Tratamento específico para ModelNotFoundException em requisições JSON/API
        if ($exception instanceof ModelNotFoundException && $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Recurso não encontrado',
                'error' => 'O recurso solicitado não existe no sistema'
            ], 404);
        }

        return parent::render($request, $exception);
    }
}