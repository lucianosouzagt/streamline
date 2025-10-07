# API Documentation - Streamline

## Visão Geral

O **Streamline** é uma API REST robusta para gerenciamento de projetos e tarefas, desenvolvida em Laravel 11 com PostgreSQL. A API oferece autenticação via Sanctum, sistema de permissões baseado em roles, e recursos completos para gerenciar usuários, equipes, projetos e tarefas.

## Autenticação

A API utiliza Laravel Sanctum para autenticação baseada em tokens.

### Endpoints de Autenticação

#### POST /api/auth/register
Registra um novo usuário no sistema.

**Request Body:**
```json
{
    "name": "João Silva",
    "email": "joao@exemplo.com",
    "password": "senha123",
    "password_confirmation": "senha123"
}
```

**Response (201):**
```json
{
    "user": {
        "id": 1,
        "name": "João Silva",
        "email": "joao@exemplo.com",
        "email_verified_at": null,
        "created_at": "2025-10-07T19:00:00.000000Z",
        "updated_at": "2025-10-07T19:00:00.000000Z"
    },
    "token": "1|abc123..."
}
```

#### POST /api/auth/login
Autentica um usuário existente.

**Request Body:**
```json
{
    "email": "joao@exemplo.com",
    "password": "senha123"
}
```

**Response (200):**
```json
{
    "user": {
        "id": 1,
        "name": "João Silva",
        "email": "joao@exemplo.com"
    },
    "token": "1|abc123..."
}
```

#### POST /api/auth/logout
Revoga o token atual do usuário.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "message": "Logout realizado com sucesso"
}
```

#### POST /api/auth/me
Retorna informações do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "João Silva",
        "email": "joao@exemplo.com",
        "email_verified_at": null,
        "created_at": "2025-10-07T19:00:00.000000Z",
        "updated_at": "2025-10-07T19:00:00.000000Z"
    }
}
```

## Recursos da API

### Usuários

#### GET /api/users
Lista todos os usuários (requer permissão `users.index`).

**Query Parameters:**
- `search` (opcional): Busca por nome ou email
- `page` (opcional): Número da página para paginação

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "name": "João Silva",
                "email": "joao@exemplo.com",
                "created_at": "2025-10-07T19:00:00.000000Z"
            }
        ],
        "links": {...},
        "meta": {...}
    }
}
```

#### GET /api/users/{id}
Exibe detalhes de um usuário específico.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "João Silva",
        "email": "joao@exemplo.com",
        "created_at": "2025-10-07T19:00:00.000000Z",
        "updated_at": "2025-10-07T19:00:00.000000Z"
    }
}
```

#### GET /api/users/profile
Retorna o perfil do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "João Silva",
        "email": "joao@exemplo.com",
        "email_verified_at": null,
        "created_at": "2025-10-07T19:00:00.000000Z",
        "updated_at": "2025-10-07T19:00:00.000000Z"
    }
}
```

#### PUT /api/users/profile
Atualiza o perfil do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "name": "João Silva Santos",
    "email": "joao.santos@exemplo.com"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Perfil atualizado com sucesso",
    "data": {
        "id": 1,
        "name": "João Silva Santos",
        "email": "joao.santos@exemplo.com",
        "updated_at": "2025-10-07T20:00:00.000000Z"
    }
}
```

#### PUT /api/users/password
Atualiza a senha do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "current_password": "senhaAtual123",
    "password": "novaSenha456",
    "password_confirmation": "novaSenha456"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Senha atualizada com sucesso"
}
```

#### GET /api/users/dashboard
Retorna estatísticas do dashboard do usuário.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "statistics": {
            "teams": {
                "owned": 3,
                "active": 2
            },
            "projects": {
                "owned": 5,
                "active": 3,
                "completed": 2
            },
            "tasks": {
                "created": 15,
                "assigned": 8,
                "completed": 12,
                "pending": 2,
                "in_progress": 1
            }
        },
        "recent_tasks": [...],
        "recent_projects": [...]
    }
}
```

#### GET /api/users/projects
Lista projetos do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Sistema de Vendas",
                "description": "Sistema completo para gestão de vendas",
                "status": "active",
                "teams_count": 2,
                "tasks_count": 8
            }
        ]
    }
}
```

#### GET /api/users/teams
Lista equipes do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Equipe de Desenvolvimento",
                "description": "Equipe responsável pelo desenvolvimento",
                "is_active": true,
                "projects_count": 3
            }
        ]
    }
}
```

#### GET /api/users/tasks
Lista tarefas atribuídas ao usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `status` (opcional): Filtrar por status
- `priority` (opcional): Filtrar por prioridade

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "title": "Implementar autenticação",
                "description": "Desenvolver sistema de login",
                "status": "in_progress",
                "priority": "high",
                "project": {...},
                "creator": {...}
            }
        ]
    }
}
```

#### DELETE /api/users/account
Exclui a conta do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "message": "Conta excluída com sucesso"
}
```

#### GET /api/roles
Lista todas as roles disponíveis no sistema.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "message": "Roles listadas com sucesso",
    "data": [
        {
            "id": 1,
            "name": "admin",
            "display_name": "Administrador",
            "description": "Acesso total ao sistema",
            "is_system": true,
            "permissions": [...]
        }
    ]
}
```

#### POST /api/users/roles/list
Lista roles de um usuário específico.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "user_id": 1
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Roles do usuário listadas com sucesso",
    "data": [
        {
            "id": 2,
            "name": "manager",
            "display_name": "Gerente",
            "description": "Gerenciamento de projetos e equipes"
        }
    ]
}
```

#### POST /api/users/roles/assign
Atribui uma role a um usuário.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "user_id": 1,
    "role_id": 2
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Role atribuída com sucesso"
}
```

#### POST /api/users/roles/remove
Remove uma role de um usuário.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "user_id": 1,
    "role_id": 2
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Role removida com sucesso"
}
```

### Equipes (Teams)

#### GET /api/teams
Lista todas as equipes do usuário.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Equipe de Desenvolvimento",
                "description": "Equipe responsável pelo desenvolvimento do produto",
                "is_active": true,
                "owner": {...},
                "projects": [...]
            }
        ]
    }
}
```

#### POST /api/teams
Cria uma nova equipe (requer permissão `teams.create`).

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "name": "Equipe de Desenvolvimento",
    "description": "Equipe responsável pelo desenvolvimento do produto",
    "is_active": true
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Equipe criada com sucesso",
    "data": {
        "id": 1,
        "name": "Equipe de Desenvolvimento",
        "description": "Equipe responsável pelo desenvolvimento do produto",
        "is_active": true,
        "owner_id": 1,
        "created_at": "2025-10-07T19:00:00.000000Z",
        "updated_at": "2025-10-07T19:00:00.000000Z"
    }
}
```

#### GET /api/teams/{id}
Exibe detalhes de uma equipe específica.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Equipe de Desenvolvimento",
        "description": "Equipe responsável pelo desenvolvimento do produto",
        "is_active": true,
        "owner": {
            "id": 1,
            "name": "João Silva",
            "email": "joao@exemplo.com"
        },
        "projects": [
            {
                "id": 1,
                "name": "Sistema de Vendas",
                "status": "active",
                "tasks": [...]
            }
        ]
    }
}
```

#### PUT /api/teams/{id}
Atualiza uma equipe (requer permissão `teams.update`).

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "name": "Equipe de Desenvolvimento Frontend",
    "description": "Equipe especializada em desenvolvimento frontend",
    "is_active": true
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Equipe atualizada com sucesso",
    "data": {
        "id": 1,
        "name": "Equipe de Desenvolvimento Frontend",
        "description": "Equipe especializada em desenvolvimento frontend",
        "is_active": true,
        "updated_at": "2025-10-07T20:00:00.000000Z"
    }
}
```

#### DELETE /api/teams/{id}
Exclui uma equipe (requer permissão `teams.delete`).

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "message": "Equipe excluída com sucesso"
}
```

#### POST /api/teams/{id}/projects
Adiciona um projeto à equipe.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "project_id": 1
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Projeto adicionado ao time com sucesso"
}
```

#### DELETE /api/teams/{id}/projects
Remove um projeto da equipe.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "project_id": 1
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Projeto removido do time com sucesso"
}
```

### Projetos

#### GET /api/projects
Lista todos os projetos do usuário.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Sistema de Vendas",
                "description": "Sistema completo para gestão de vendas",
                "status": "active",
                "start_date": "2025-01-01",
                "end_date": "2025-06-30",
                "owner": {...},
                "teams": [...],
                "tasks": [...]
            }
        ]
    }
}
```

#### POST /api/projects
Cria um novo projeto (requer permissão `projects.create`).

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "name": "Sistema de Vendas",
    "description": "Sistema completo para gestão de vendas",
    "status": "planning",
    "start_date": "2025-01-01",
    "end_date": "2025-06-30"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Projeto criado com sucesso",
    "data": {
        "id": 1,
        "name": "Sistema de Vendas",
        "description": "Sistema completo para gestão de vendas",
        "status": "planning",
        "start_date": "2025-01-01",
        "end_date": "2025-06-30",
        "owner_id": 1,
        "created_at": "2025-10-07T19:00:00.000000Z",
        "updated_at": "2025-10-07T19:00:00.000000Z"
    }
}
```

#### GET /api/projects/status/{status}
Lista projetos por status (`planning`, `active`, `on_hold`, `completed`, `cancelled`).

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Sistema de Vendas",
                "description": "Sistema completo para gestão de vendas",
                "status": "active",
                "owner": {...},
                "teams": [...],
                "tasks": [...]
            }
        ]
    }
}
```

#### GET /api/projects/{id}
Exibe detalhes de um projeto específico.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Sistema de Vendas",
        "description": "Sistema completo para gestão de vendas",
        "status": "active",
        "start_date": "2025-01-01",
        "end_date": "2025-06-30",
        "owner": {
            "id": 1,
            "name": "João Silva",
            "email": "joao@exemplo.com"
        },
        "teams": [...],
        "tasks": [
            {
                "id": 1,
                "title": "Implementar autenticação",
                "status": "in_progress",
                "users": [...],
                "creator": {...}
            }
        ]
    }
}
```

#### PUT /api/projects/{id}
Atualiza um projeto (requer permissão `projects.update`).

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "name": "Sistema de Vendas Online",
    "description": "Sistema completo para gestão de vendas online e presencial",
    "status": "active",
    "start_date": "2025-01-01",
    "end_date": "2025-08-30"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Projeto atualizado com sucesso",
    "data": {
        "id": 1,
        "name": "Sistema de Vendas Online",
        "description": "Sistema completo para gestão de vendas online e presencial",
        "status": "active",
        "start_date": "2025-01-01",
        "end_date": "2025-08-30",
        "updated_at": "2025-10-07T20:00:00.000000Z"
    }
}
```

#### DELETE /api/projects/{id}
Exclui um projeto (requer permissão `projects.delete`).

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "message": "Projeto excluído com sucesso"
}
```

#### GET /api/projects/{id}/statistics
Retorna estatísticas detalhadas do projeto.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "total_tasks": 15,
        "completed_tasks": 8,
        "pending_tasks": 4,
        "in_progress_tasks": 3,
        "progress_percentage": 53.33,
        "teams_count": 2
    }
}
```

### Tarefas

#### GET /api/tasks
Lista todas as tarefas do usuário.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `status` (opcional): Filtrar por status
- `priority` (opcional): Filtrar por prioridade  
- `project_id` (opcional): Filtrar por projeto

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "title": "Implementar autenticação",
                "description": "Desenvolver sistema de login e registro",
                "project_id": 1,
                "status": "in_progress",
                "priority": "high",
                "due_date": "2025-01-15",
                "project": {...},
                "creator": {...},
                "users": [...]
            }
        ]
    }
}
```

#### POST /api/tasks
Cria uma nova tarefa (requer permissão `tasks.create`).

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "title": "Implementar autenticação",
    "description": "Desenvolver sistema de login e registro",
    "project_id": 1,
    "status": "todo",
    "priority": "high",
    "due_date": "2025-01-15",
    "assigned_users": [2, 3]
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Tarefa criada com sucesso",
    "data": {
        "id": 1,
        "title": "Implementar autenticação",
        "description": "Desenvolver sistema de login e registro",
        "project_id": 1,
        "status": "todo",
        "priority": "high",
        "due_date": "2025-01-15",
        "created_by": 1,
        "created_at": "2025-10-07T19:00:00.000000Z",
        "updated_at": "2025-10-07T19:00:00.000000Z"
    }
}
```

#### GET /api/tasks/status/{status}
Lista tarefas por status (`todo`, `in_progress`, `review`, `done`, `cancelled`).

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "title": "Implementar autenticação",
                "status": "in_progress",
                "priority": "high",
                "project": {...},
                "creator": {...},
                "users": [...]
            }
        ]
    }
}
```

#### GET /api/tasks/{id}
Exibe detalhes de uma tarefa específica.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Implementar autenticação",
        "description": "Desenvolver sistema de login e registro",
        "project_id": 1,
        "status": "in_progress",
        "priority": "high",
        "due_date": "2025-01-15",
        "created_by": 1,
        "project": {
            "id": 1,
            "name": "Sistema de Vendas",
            "status": "active"
        },
        "creator": {
            "id": 1,
            "name": "João Silva",
            "email": "joao@exemplo.com"
        },
        "users": [
            {
                "id": 2,
                "name": "Maria Santos",
                "email": "maria@exemplo.com"
            }
        ]
    }
}
```

#### PUT /api/tasks/{id}
Atualiza uma tarefa (requer permissão `tasks.update`).

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "title": "Implementar autenticação completa",
    "description": "Desenvolver sistema de login, registro e recuperação de senha",
    "status": "in_progress",
    "priority": "high",
    "due_date": "2025-01-20"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Tarefa atualizada com sucesso",
    "data": {
        "id": 1,
        "title": "Implementar autenticação completa",
        "description": "Desenvolver sistema de login, registro e recuperação de senha",
        "status": "in_progress",
        "priority": "high",
        "due_date": "2025-01-20",
        "updated_at": "2025-10-07T20:00:00.000000Z"
    }
}
```

#### DELETE /api/tasks/{id}
Exclui uma tarefa (requer permissão `tasks.delete`).

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "message": "Tarefa excluída com sucesso"
}
```

#### POST /api/tasks/{id}/assign
Atribui um usuário à tarefa.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "user_id": 2
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Usuário atribuído à tarefa com sucesso"
}
```

#### DELETE /api/tasks/{id}/unassign
Remove a atribuição de um usuário da tarefa.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
    "user_id": 2
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Usuário removido da tarefa com sucesso"
}
```

## Sistema de Permissões

O sistema utiliza roles e permissões para controle de acesso:

### Roles Padrão
- **Admin**: Acesso total ao sistema
- **Manager**: Gerenciamento de projetos e equipes
- **Developer**: Criação e edição de tarefas
- **Viewer**: Apenas visualização

### Permissões Principais
- `users.*`: Gerenciamento de usuários
- `teams.*`: Gerenciamento de equipes
- `projects.*`: Gerenciamento de projetos
- `tasks.*`: Gerenciamento de tarefas

## Códigos de Status HTTP

- **200**: Sucesso
- **201**: Criado com sucesso
- **400**: Dados inválidos
- **401**: Não autenticado
- **403**: Sem permissão
- **404**: Recurso não encontrado
- **422**: Erro de validação
- **500**: Erro interno do servidor

## Estrutura de Resposta

### Sucesso
```json
{
    "data": {
        // dados do recurso
    },
    "message": "Operação realizada com sucesso"
}
```

### Erro de Validação
```json
{
    "message": "Os dados fornecidos são inválidos",
    "errors": {
        "campo": ["Mensagem de erro específica"]
    }
}
```

### Erro de Autorização
```json
{
    "message": "Você não tem permissão para realizar esta ação"
}
```

## Paginação

Recursos que retornam listas suportam paginação:

```json
{
    "data": [...],
    "links": {
        "first": "http://api.exemplo.com/resource?page=1",
        "last": "http://api.exemplo.com/resource?page=10",
        "prev": null,
        "next": "http://api.exemplo.com/resource?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

## Filtros e Ordenação

Muitos endpoints suportam filtros via query parameters:

- `?status=active`: Filtrar por status
- `?priority=high`: Filtrar por prioridade
- `?sort=created_at`: Ordenar por campo
- `?order=desc`: Direção da ordenação

## Rate Limiting

A API implementa rate limiting para prevenir abuso:
- **60 requests por minuto** para usuários autenticados
- **10 requests por minuto** para usuários não autenticados

## Versionamento

A API utiliza versionamento via URL:
- Versão atual: `/api/v1/`
- Versões futuras: `/api/v2/`, etc.