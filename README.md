# Streamline API

API para gerenciamento de projetos, equipes e tarefas desenvolvida em Laravel 11+ com PostgreSQL.

## 🚀 Tecnologias

- **Laravel 11+** - Framework PHP
- **PostgreSQL** - Banco de dados
- **PHPStan** - Análise estática de código (nível 8)
- **Laravel Pint** - Code style e formatação
- **Pest** - Framework de testes

## 📋 Pré-requisitos

- PHP 8.2+
- Composer
- PostgreSQL 12+
- Node.js 18+ (para assets)

## 🔧 Instalação

1. **Clone o repositório**
```bash
git clone <repository-url>
cd streamline-api
```

2. **Instale as dependências**
```bash
composer install
npm install
```

3. **Configure o ambiente**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure o banco de dados**
   - Crie um banco PostgreSQL chamado `streamline_api`
   - Configure as credenciais no arquivo `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=streamline_api
DB_USERNAME=postgres
DB_PASSWORD=sua_senha
```

5. **Execute as migrações**
```bash
php artisan migrate
```

6. **Compile os assets (opcional)**
```bash
npm run dev
```

## 🏃‍♂️ Executando

### Servidor de desenvolvimento
```bash
php artisan serve
```

A API estará disponível em `http://localhost:8000`

## 🧪 Testes

### Executar todos os testes
```bash
./vendor/bin/pest
```

### Executar testes com cobertura
```bash
./vendor/bin/pest --coverage
```

## 🔍 Qualidade de Código

### Análise estática (PHPStan)
```bash
./vendor/bin/phpstan analyse
```

### Formatação de código (Pint)
```bash
./vendor/bin/pint
```

### Verificar formatação sem aplicar
```bash
./vendor/bin/pint --test
```

## 📁 Estrutura do Projeto

```
streamline-api/
├── app/                    # Código da aplicação
├── config/                 # Arquivos de configuração
├── database/              # Migrações, seeders e factories
├── routes/                # Definição de rotas
├── tests/                 # Testes automatizados
├── .env.example          # Template de variáveis de ambiente
├── phpstan.neon          # Configuração do PHPStan
├── pint.json             # Configuração do Laravel Pint
└── composer.json         # Dependências PHP
```

## 🔐 Variáveis de Ambiente

Consulte o arquivo `.env.example` para ver todas as variáveis necessárias.

### Principais variáveis:
- `APP_NAME` - Nome da aplicação
- `APP_ENV` - Ambiente (local, production)
- `APP_KEY` - Chave de criptografia (gerada automaticamente)
- `DB_*` - Configurações do banco de dados

## 🤝 Contribuição

1. Crie uma branch seguindo o padrão: `feature/nome-da-feature`
2. Faça suas alterações
3. Execute os testes e verificações de qualidade
4. Faça commit seguindo o padrão: `feat: descrição da feature`
5. Abra um Pull Request

### Padrões de commit:
- `feat:` - Nova funcionalidade
- `fix:` - Correção de bug
- `docs:` - Documentação
- `style:` - Formatação
- `refactor:` - Refatoração
- `test:` - Testes
- `chore:` - Tarefas de manutenção

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.