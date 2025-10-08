<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Retorna uma resposta de sucesso padronizada
     */
    protected function successResponse($data = null, string $message = null, int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Retorna uma resposta de erro padronizada
     */
    protected function errorResponse(string $message, int $status = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Retorna uma resposta de validação padronizada
     */
    protected function validationErrorResponse($errors, string $message = 'Dados inválidos'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Retorna uma resposta de acesso negado padronizada
     */
    protected function forbiddenResponse(string $message = 'Acesso negado'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Retorna uma resposta de não encontrado padronizada
     */
    protected function notFoundResponse(string $message = 'Recurso não encontrado'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Retorna uma resposta de erro interno padronizada
     */
    protected function internalErrorResponse(string $message = 'Erro interno do servidor'): JsonResponse
    {
        return $this->errorResponse($message, 500);
    }
}