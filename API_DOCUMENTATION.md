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

## Recursos da API

### Usuários

#### GET /api/users
Lista todos os usuários (requer permissão `users.index`).

#### GET /api/user/profile
Retorna o perfil do usuário autenticado.

#### PUT /api/user/profile
Atualiza o perfil do usuário autenticado.

#### PUT /api/user/password
Atualiza a senha do usuário autenticado.

#### GET /api/user/dashboard
Retorna estatísticas do dashboard do usuário.

#### DELETE /api/user/account
Exclui a conta do usuário autenticado.

### Equipes (Teams)

#### GET /api/teams
Lista todas as equipes do usuário.

#### POST /api/teams
Cria uma nova equipe (requer permissão `teams.create`).

**Request Body:**
```json
{
    "name": "Equipe de Desenvolvimento",
    "description": "Equipe responsável pelo desenvolvimento do produto",
    "is_active": true
}
```

#### GET /api/teams/{id}
Exibe detalhes de uma equipe específica.

#### PUT /api/teams/{id}
Atualiza uma equipe (requer permissão `teams.update`).

#### DELETE /api/teams/{id}
Exclui uma equipe (requer permissão `teams.delete`).

#### POST /api/teams/{id}/projects/{projectId}
Adiciona um projeto à equipe.

#### DELETE /api/teams/{id}/projects/{projectId}
Remove um projeto da equipe.

### Projetos

#### GET /api/projects
Lista todos os projetos do usuário.

#### POST /api/projects
Cria um novo projeto (requer permissão `projects.create`).

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

#### GET /api/projects/status/{status}
Lista projetos por status (`planning`, `active`, `on_hold`, `completed`, `cancelled`).

#### GET /api/projects/{id}
Exibe detalhes de um projeto específico.

#### PUT /api/projects/{id}
Atualiza um projeto (requer permissão `projects.update`).

#### DELETE /api/projects/{id}
Exclui um projeto (requer permissão `projects.delete`).

#### GET /api/projects/{id}/statistics
Retorna estatísticas detalhadas do projeto.

### Tarefas

#### GET /api/tasks
Lista todas as tarefas do usuário.

#### POST /api/tasks
Cria uma nova tarefa (requer permissão `tasks.create`).

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

#### GET /api/tasks/status/{status}
Lista tarefas por status (`todo`, `in_progress`, `review`, `done`, `cancelled`).

#### GET /api/tasks/{id}
Exibe detalhes de uma tarefa específica.

#### PUT /api/tasks/{id}
Atualiza uma tarefa (requer permissão `tasks.update`).

#### DELETE /api/tasks/{id}
Exclui uma tarefa (requer permissão `tasks.delete`).

#### POST /api/tasks/{id}/assign/{userId}
Atribui um usuário à tarefa.

#### DELETE /api/tasks/{id}/unassign/{userId}
Remove a atribuição de um usuário da tarefa.

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