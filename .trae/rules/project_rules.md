<?xml version="1.0" encoding="UTF-8"?>
<ProjectRules version="1.0" stack="laravel" db="postgresql" env="dsv">
  <!-- ===================== META ===================== -->
  <Settings>
    <DefaultAction>FAIL_BUILD</DefaultAction>
    <MinPhpVersion>8.2</MinPhpVersion>
    <LaravelMinVersion>10</LaravelMinVersion>
  </Settings>

  <!-- ============== GIT: BRANCHES & COMMITS ============== -->
  <Rule id="BR-001" name="BranchNaming">
    <Description>Nome de branch padronizado</Description>
    <Condition>branch.name NOT MATCHES ^(feature|bugfix|hotfix|release)/[a-z0-9._-]+$</Condition>
    <Action>REJECT</Action>
    <Message>Branch inválida. Use feature/ , bugfix/ , hotfix/ , release/ + slug-kebab.</Message>
  </Rule>

  <Rule id="CM-001" name="CommitHeaderFormat">
    <Description>Commit header no padrão &lt;tag&gt;: &lt;short description&gt; (em inglês)</Description>
    <Condition>commit.header NOT MATCHES ^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert):\s[ -~]{5,80}$</Condition>
    <Action>REJECT</Action>
    <Message>Use: &lt;tag&gt;: short message (ASCII/inglês). Ex: feat: add user login.</Message>
  </Rule>

  <Rule id="CM-002" name="CommitBodyBlankLine">
    <Description>Linha em branco entre header e body quando body existir</Description>
    <Condition>commit.bodyExists == true AND commit.raw NOT MATCHES ^.+\n\n.+$</Condition>
    <Action>REJECT</Action>
    <Message>Deixe uma linha em branco entre o header e o corpo do commit.</Message>
  </Rule>

  <Rule id="CM-003" name="CommitBodyLanguage">
    <Description>Body do commit em inglês</Description>
    <Condition>commit.bodyExists == true AND commit.body MATCHES [À-ÿ]</Condition>
    <Action>WARN</Action>
    <Message>Escreva o corpo do commit em inglês para padronização.</Message>
  </Rule>

  <!-- ============== DEPENDÊNCIAS & BUILD ============== -->
  <Rule id="DP-001" name="ComposerValidate">
    <Description>composer.json válido</Description>
    <Condition>composer.validate == false</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>composer.json inválido. Rode: composer validate --strict</Message>
  </Rule>

  <Rule id="DP-002" name="LockInSync">
    <Description>composer.lock sincronizado</Description>
    <Condition>composer.lockInSync == false</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>composer.lock desatualizado. Rode: composer update --lock</Message>
  </Rule>

  <Rule id="DP-003" name="VulnerabilityScan">
    <Description>Sem vulnerabilidades conhecidas</Description>
    <Condition>composer.audit.hasVulnerabilities == true</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Vulnerabilidades detectadas. Atualize dependências (composer audit).</Message>
  </Rule>

  <!-- ============== QUALIDADE: LINT, STATIC ANALYSIS, TESTS ============== -->
  <Rule id="QL-001" name="LaravelPint">
    <Description>Code style com Laravel Pint sem erros</Description>
    <Condition>pint.errors &gt; 0</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Erros de estilo. Rode: ./vendor/bin/pint -v</Message>
  </Rule>

  <Rule id="QL-002" name="PHPStanLevelMax">
    <Description>PHPStan nível 8 (ou configurado) sem erros</Description>
    <Condition>phpstan.errors &gt; 0</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Falhas no PHPStan. Corrija tipagens e contratos (./vendor/bin/phpstan analyse).</Message>
  </Rule>

  <Rule id="QL-003" name="PHPUnitCoverage">
    <Description>Cobertura mínima de testes</Description>
    <Condition>phpunit.coverage &lt; 80</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Cobertura abaixo de 80%. Adicione testes (./vendor/bin/phpunit --coverage-text).</Message>
  </Rule>

  <!-- ============== BANCO: MIGRAÇÕES & SQL ============== -->
  <Rule id="DB-001" name="PendingMigrations">
    <Description>Não pode haver migrações pendentes antes do deploy</Description>
    <Condition>laravel.migrations.pending &gt; 0</Condition>
    <Action>BLOCK_DEPLOY</Action>
    <Message>Existem migrações pendentes. Execute php artisan migrate --force.</Message>
  </Rule>

  <Rule id="DB-002" name="PgNamingConventions">
    <Description>Convenção de nomes no PostgreSQL (snake_case)</Description>
    <Condition>schema.objects NOT MATCHES ^[a-z][a-z0-9_]*$</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Use snake_case para tabelas, colunas e índices.</Message>
  </Rule>

  <!-- ============== DOCKER: POSTGRES AMBIENTE DSV ============== -->
  <Rule id="DK-001" name="DockerPostgresRunning">
    <Description>Container PostgreSQL deve estar ativo no ambiente de desenvolvimento</Description>
    <Condition>env.APP_ENV == 'dsv' AND docker.container('postgres').status != 'running'</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>PostgreSQL não está rodando. Execute: docker compose up -d postgres</Message>
  </Rule>

  <Rule id="DK-002" name="PostgresPersistenceVolume">
    <Description>Volume de dados do Postgres deve estar persistente</Description>
    <Condition>env.APP_ENV == 'dsv' AND docker.volume('pg_data').exists == false</Condition>
    <Action>WARN</Action>
    <Message>Volume pg_data ausente. Configure persistência no docker-compose.yml</Message>
  </Rule>

  <Rule id="DK-003" name="PostgresCredentials">
    <Description>Usuário e senha do Postgres obrigatórios</Description>
    <Condition>env.APP_ENV == 'dsv' AND (env.POSTGRES_USER == '' OR env.POSTGRES_PASSWORD == '')</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Defina POSTGRES_USER e POSTGRES_PASSWORD no .env.dsv</Message>
  </Rule>

  <Rule id="DK-004" name="PostgresPortBinding">
    <Description>Porta 5432 deve estar exposta e acessível localmente</Description>
    <Condition>env.APP_ENV == 'dsv' AND docker.container('postgres').ports.5432 != 'open'</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>PostgreSQL não acessível na porta 5432. Verifique docker-compose.yml.</Message>
  </Rule>

  <Rule id="DK-005" name="MigrationVolumeMounted">
    <Description>Volume de migrations deve estar montado no container</Description>
    <Condition>env.APP_ENV == 'dsv' AND docker.container('app').volumes NOT CONTAINS 'database/migrations'</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Monte o volume de migrations no serviço app no docker-compose.yml</Message>
  </Rule>

  <Rule id="DK-006" name="PgConnectionCheck">
    <Description>Verifica conexão Laravel → PostgreSQL no container</Description>
    <Condition>env.APP_ENV == 'dsv' AND artisan.dbConnect('pgsql') == false</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Laravel não conseguiu conectar ao Postgres. Verifique DB_HOST, DB_PORT, DB_USER, DB_PASS.</Message>
  </Rule>

  <!-- ============== SEGURANÇA & DEPLOY (INVARIÁVEL) ============== -->
  <Rule id="SC-001" name="SecretsDetection">
    <Description>Bloqueia segredos em commits</Description>
    <Condition>diff.content MATCHES (AKIA|APP_KEY=base64:|PASSWORD=|PRIVATE KEY)</Condition>
    <Action>REJECT</Action>
    <Message>Segredo detectado no diff. Remova e gere novo segredo.</Message>
  </Rule>

  <Rule id="DPY-001" name="ProdOnlyFromMain">
    <Description>Deploy em produção só da main</Description>
    <Condition>target.environment == 'production' AND branch.name != 'main'</Condition>
    <Action>BLOCK_DEPLOY</Action>
    <Message>Produção só pode ser implantada a partir da branch main.</Message>
  </Rule>
</ProjectRules>
