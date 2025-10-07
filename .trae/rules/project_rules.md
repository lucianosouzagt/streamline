<?xml version="1.0" encoding="UTF-8"?>
<ProjectRules version="1.0" stack="laravel" db="postgresql">
  <!-- ===================== META ===================== -->
  <Settings>
    <!-- Ajuste as ações aceitas pelo teu orquestrador: REJECT, WARN, FAIL_BUILD, BLOCK_DEPLOY, PAUSE_PIPELINE -->
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

  <Rule id="QL-004" name="NoFocusedTests">
    <Description>Sem testes "focados" (pest: only/focus)</Description>
    <Condition>tests.files MATCHES @test\\(\\)\\s*:\\s*->only\\(\\)|->focus\\(\\)</Condition>
    <Action>REJECT</Action>
    <Message>Remova marcadores de teste focado (only/focus).</Message>
  </Rule>

  <!-- ============== BANCO: MIGRAÇÕES & SQL ============== -->
  <Rule id="DB-001" name="PendingMigrations">
    <Description>Não pode haver migrações pendentes antes do deploy</Description>
    <Condition>laravel.migrations.pending &gt; 0</Condition>
    <Action>BLOCK_DEPLOY</Action>
    <Message>Existem migrações pendentes. Execute php artisan migrate --force.</Message>
  </Rule>

  <Rule id="DB-002" name="UnsafeMigrations">
    <Description>Alterações perigosas exigem flag de aprovação</Description>
    <Condition>migration.contains == ('dropColumn' OR 'renameColumn' OR 'raw ALTER TABLE') AND approvals.count &lt; 2</Condition>
    <Action>PAUSE_PIPELINE</Action>
    <Message>Migration potencialmente destrutiva requer 2 aprovações.</Message>
  </Rule>

  <Rule id="DB-003" name="PgNamingConventions">
    <Description>Convenção de nomes no PostgreSQL (snake_case)</Description>
    <Condition>schema.objects NOT MATCHES ^[a-z][a-z0-9_]*$</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Use snake_case para tabelas/colunas/index/constraints.</Message>
  </Rule>

  <!-- ============== SEGURANÇA & SEGREDOS ============== -->
  <Rule id="SC-001" name="SecretsDetection">
    <Description>Bloqueia segredos em commits</Description>
    <Condition>diff.content MATCHES (AWS[_-]?SECRET|AKIA[0-9A-Z]{16}|SECRET[_-]?KEY|APP_KEY=base64:|PASSWORD=|BEGIN RSA PRIVATE KEY|BEGIN OPENSSH PRIVATE KEY)</Condition>
    <Action>REJECT</Action>
    <Message>Segredo detectado no diff. Remova e rotate o segredo.</Message>
  </Rule>

  <Rule id="SC-002" name="IgnoreEnvFiles">
    <Description>Arquivos .env não podem ser versionados</Description>
    <Condition>file.path MATCHES (^\\.env(\\..+)?$|^config\\/secrets\\/.+)</Condition>
    <Action>REJECT</Action>
    <Message>Não versionar .env ou segredos. Use variáveis de ambiente no CI/CD.</Message>
  </Rule>

  <Rule id="SC-003" name="EnvVarsRequired">
    <Description>Variáveis mínimas para build/test</Description>
    <Condition>env.missing ANY_OF (APP_ENV, APP_KEY, DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD)</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Faltam variáveis obrigatórias no ambiente de pipeline.</Message>
  </Rule>

  <!-- ============== CONTAINER / ARTEFATOS ============== -->
  <Rule id="CT-001" name="DockerTagSemver">
    <Description>Imagem deve usar tag semver ou SHA</Description>
    <Condition>docker.image.tag NOT MATCHES ^(\\d+\\.\\d+\\.\\d+(-[0-9A-Za-z.-]+)?|sha256:[0-9a-f]{64})$</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Tag inválida. Use semver (x.y.z) ou digest SHA.</Message>
  </Rule>

  <Rule id="CT-002" name="SlimImage">
    <Description>Imagem final sem dev-deps e com cache otimizado</Description>
    <Condition>docker.image.sizeMB &gt; 300 OR docker.layers.devDependencies == true</Condition>
    <Action>WARN</Action>
    <Message>Otimize a imagem: multi-stage build, remover dev-deps, cache composer/npm.</Message>
  </Rule>

  <!-- ============== DEPLOY & APROVAÇÕES ============== -->
  <Rule id="DPY-001" name="ProdOnlyFromMain">
    <Description>Deploy em produção só a partir da main</Description>
    <Condition>target.environment == 'production' AND branch.name != 'main'</Condition>
    <Action>BLOCK_DEPLOY</Action>
    <Message>Produção só pode ser implantada a partir da branch main.</Message>
  </Rule>

  <Rule id="DPY-002" name="TwoManRuleProd">
    <Description>Deploy de produção exige 2 aprovações</Description>
    <Condition>target.environment == 'production' AND approvals.count &lt; 2</Condition>
    <Action>PAUSE_PIPELINE</Action>
    <Message>Produção requer duas aprovações no pipeline.</Message>
  </Rule>

  <Rule id="DPY-003" name="HealthChecks">
    <Description>Health-checks obrigatórios após deploy</Description>
    <Condition>postDeploy.health.pass == false</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Health-check falhou. Inverta o deploy (rollback) automaticamente.</Message>
  </Rule>

  <!-- ============== PERFORMANCE & BOAS PRÁTICAS LARAVEL ============== -->
  <Rule id="LV-001" name="ConfigCache">
    <Description>Config e routes cache em produção</Description>
    <Condition>target.environment == 'production' AND (artisan.configCached == false OR artisan.routesCached == false)</Condition>
    <Action>FAIL_BUILD</Action>
    <Message>Gere cache: php artisan config:cache && php artisan route:cache.</Message>
  </Rule>

  <Rule id="LV-002" name="NoDebugInProd">
    <Description>APP_DEBUG desativado em produção</Description>
    <Condition>target.environment == 'production' AND env.APP_DEBUG == 'true'</Condition>
    <Action>BLOCK_DEPLOY</Action>
    <Message>APP_DEBUG deve ser false em produção.</Message>
  </Rule>

  <Rule id="LV-003" name="QueueAndCacheDrivers">
    <Description>Drivers performáticos em produção</Description>
    <Condition>target.environment == 'production' AND (env.QUEUE_CONNECTION == 'sync' OR env.CACHE_STORE == 'array')</Condition>
    <Action>WARN</Action>
    <Message>Use fila (redis/sqs) e cache persistente (redis/memcached) em produção.</Message>
  </Rule>
</ProjectRules>
