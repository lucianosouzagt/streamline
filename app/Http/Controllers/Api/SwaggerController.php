<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Streamline API",
 *     version="1.0.0",
 *     description="API para gerenciamento de projetos, tarefas e equipes",
 *     @OA\Contact(
 *         email="admin@streamline.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Servidor de desenvolvimento"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token de autenticação Sanctum"
 * )
 * 
 * @OA\Tag(
 *     name="Auth",
 *     description="Endpoints de autenticação"
 * )
 * 
 * @OA\Tag(
 *     name="Users",
 *     description="Gerenciamento de usuários"
 * )
 * 
 * @OA\Tag(
 *     name="Projects",
 *     description="Gerenciamento de projetos"
 * )
 * 
 * @OA\Tag(
 *     name="Tasks",
 *     description="Gerenciamento de tarefas"
 * )
 * 
 * @OA\Tag(
 *     name="Teams",
 *     description="Gerenciamento de equipes"
 * )
 */
class SwaggerController extends Controller
{
    //
}