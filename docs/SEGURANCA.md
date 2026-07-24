# Segurança e operações — servlitcys

**Versão do produto:** 8.2.0 · **Última revisão:** 2026-07-24

> **Índice:** [README.md](README.md) · **Deploy:** [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) · **Ponderações:** [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §10 · **Clio:** [modulos/MODULO_CLIO.md](modulos/MODULO_CLIO.md).

## Senhas e segredos

### Usuários da aplicação

- As senhas são armazenadas com **hash** (cast `hashed` no modelo `User`), usando o driver configurado (tipicamente bcrypt).
- O registo público está **desactivado**; novos usuários são criados por um administrador autenticado, com validação de senha (regras Laravel `Password::defaults()`).

### Administrador inicial (seeder)

- `AdminUserSeeder` utiliza **`ADMIN_EMAIL`** e **`ADMIN_PASSWORD`** definidos no `.env`.
- **Nunca** commite o arquivo `.env` nem use senhas fracas em produção.
- Após o primeiro deploy, altere a senha do admin e considere desativar ou rever o seeder em pipelines automatizados.

### Credenciais MySQL por cidade

- O campo `db_password` no modelo `City` usa cast **`encrypted`** (Laravel Encryption); requer `APP_KEY` estável — **fazer backup da chave** com o backup da base.
- **`php artisan key:generate` em ambiente com cidades cadastradas** invalida todas as senhas gravadas (erro «The MAC is invalid» / descriptografia na conexão). Corrija com `php artisan cities:reencrypt-db-passwords --password='...' --confirm=reencrypt-db-passwords` (mesma senha em todas as cidades, se for o seu caso). O comando grava direto na base sem ler a senha antiga. Alternativa: **Cidades → Editar** cidade a cidade (ver [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §7).
- Quem pode criar/editar cidades: apenas perfil **Administrador** (`role=admin`).

### Arquivos e ambiente

- `APP_KEY` — obrigatório; em produção deve ser único e guardado em segredo (gestor de secrets, variáveis do servidor).
- `.env` em produção: `APP_DEBUG=false`, `APP_ENV=production` (ou equivalente).

## Autorização (RBAC)

Perfis (`users.role`): **admin**, **user**, **municipal**. Municípios do perfil municipal: pivot `city_user`.

| Recurso | Quem |
|---------|------|
| Painel `/dashboard` (estatísticas, probe) | `role=admin` — outros perfis são redirecionados para Análise |
| CRUD de cidades, sync, SMTP, sessões | `role=admin` (middleware `admin`) |
| Criar usuários | Admin, Usuário (só `user`), Municipal (só `municipal` no seu âmbito) — `UserPolicy` |
| Desactivar / reactivar / excluir usuários | Só `role=admin`; não sobre a própria conta; não desactivar nem excluir o único admin — `UserPolicy::updateStatus`, `UserPolicy::delete` |
| Contas inactivas (`is_active=false`) | Login recusado (`LoginRequest`); sessão terminada em cada pedido (`EnsureUserIsActive`) |
| Análise / exportação | Admin e Usuário: todos os municípios `forAnalytics`; Municipal: só vinculados — `CityPolicy::viewAnalytics` |
| Histórico de logins | Gate `manageUserAudit` (admin) |

A coluna legada `is_admin` é sincronizada automaticamente com `role` ao gravar. A navegação reflete as regras; controladores e `FormRequest` reaplicam autorização (incl. validação pós-sanitize de `city_ids`). Guia completo: [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md).

## Proteções HTTP comuns

- **CSRF** em formulários web (middleware Laravel).
- **Sessão**: `SESSION_DRIVER=database` (ou `redis` em escala); em produção **`SESSION_ENCRYPT=true`** e **`SESSION_SECURE_COOKIE=true`** (defaults em `config/session.php`; em HTTP local use `false` — ver `.env.example`).
- **Throttle** em rotas sensíveis:
  - `POST /login` — 5 tentativas por minuto (por IP)
  - Pedidos de reset de senha — limitados da mesma forma
- **Mass assignment (`User`)**: `password`, `role`, `is_active`, `cpf` e campos de consentimento legal **fora** de `$fillable` — só atribuição explícita / `forceCreate` / `unguarded` no seeder.

## Checklist antes de produção

- [ ] `APP_DEBUG=false`
- [ ] HTTPS com certificado válido e `APP_URL` com `https://`
- [ ] `SESSION_ENCRYPT=true` e `SESSION_SECURE_COOKIE=true` (obrigatório com HTTPS)
- [ ] `php artisan config:cache` e `route:cache` após deploy
- [ ] Permissões de arquivos: `storage/` e `bootstrap/cache/` graváveis pelo web server
- [ ] Backup da base de dados e de `APP_KEY`
- [ ] Rever usuários `is_admin` e senhas iniciais
- [ ] Logs: não expor stack traces a usuários finais
- [ ] (Opcional) Proxy reverso: cabeçalhos `X-Forwarded-*` e `TrustProxies` configurados no Laravel se aplicável

## Importações e URLs externas (CadÚnico, FUNDEB, SAEB)

| Risco | Mitigação no código |
|--------|---------------------|
| **SSRF** em download de CSV por URL (`IEDUCAR_CADUNICO_*_CSV_URL`, CKAN) | `SafeOutboundUrl::isAllowedHttpUrl()` — bloqueia `localhost`, redes privadas, esquemas não HTTP(S) e hosts cujo DNS **não resolve** (fail-closed) |
| **SSRF** em sync SAEB / Pedagógico (URLs admin, planilhas, microdados, JSON) | Mesma allowlist antes de cada `Http::get` / sink em `SaebPedagogicalImportService`, `SaebOfficialMunicipalImportService`, `SaebMicrodadosInepDownloader` e validação no `PedagogicalSyncController` |
| **Path traversal** em `cadunico:import-cecad {path}` | `ContainedPathResolver` — arquivo só dentro de `storage/app` ou `storage/app/cadunico/cecad` |
| **Lista Solr `fl` demasiado longa** (Misocial) | Máximo `IEDUCAR_CADUNICO_MISOGIAL_FIELDS_MAX` (default 24); acima disso usa lista compacta interna |
| **IBGE Misocial 6 vs 7 dígitos** | `CadunicoMisocialIbgeNormalizer` — consulta com ambas variantes |
| **HTML legal / documentação admin** | Conteúdo de editores confiáveis; `{!! !!}` só em vistas com origem controlada por admin |

URLs Misocial (MDS) vêm de config fixa (`IEDUCAR_CADUNICO_MISOGIAL_BASE_URL`), não de input do usuário.

Comandos que executam `shell_exec` (ex.: `unrar`/`7z` em SAEB) usam binários resolvidos com `escapeshellarg` — restrinja PATH no servidor.

**Upload Educacenso (conferência CEN-01):** arquivo `.txt` temporário em `storage/app/educacenso/`; limite `EDUCACENSO_DRY_RUN_MAX_MB`; análise read-only do i-Educar — ver [EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md](EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md).

**Upload Clio / CadÚnico:** Clio aceita só `csv|txt|zip` (`mimes` + `extensions`); CadÚnico CSV `csv|txt` no `CadunicoSyncRunRequest`.

## Clio (coletas Educacenso)

| Tema | Controlo |
|------|----------|
| **Acesso** | `canViewClio()` / `CLIO_ENABLED`; inserts e ações sensíveis (upload, análise, cruzamento, vincular i-Educar, CLI) — **só Admin** |
| **Upload** | `mimes`+`extensions` `csv,txt,zip`; limites `CLIO_UPLOAD_MAX_MB` / `CLIO_MAX_FILES_PER_UPLOAD` |
| **Fila** | Jobs em `CLIO_QUEUE` (default `clio`); worker com `--timeout=1200` — ver IMPLANTAÇÃO §4.8 |
| **Drive** | URL de pasta pública ou `CLIO_DRIVE_API_KEY`; lotes `CLIO_DRIVE_BATCH_*` |
| **Storage** | Artefactos em disco configurável (`CLIO_DISK`); retenção `CLIO_RETENTION_DAYS` + `clio:prune-artifacts` |
| **Promote i-Educar** | `CLIO_PROMOTE_ENABLED=false` por defeito (Onda 3) |

## Superfícies públicas (PDF / API)

| Superfície | Auth | Mitigações |
|------------|------|------------|
| `/relatorio/{publicId}` (+ `/pdf`) | Não (QR do PDF Serventec) | Throttle `60/min`; `publicId` `[A-Za-z0-9_-]{8,64}`; log `analytics.report.public.*` |
| `/api/saeb/municipio/{ibge}` | Não | Throttle `120/min`; flag `IEDUCAR_SAEB_PUBLIC_API`; **sem token** (conteúdo público pós-import) — reavaliar se houver PII |

## Dependências e vulnerabilidades

- Mantenha **Composer** e **npm** atualizados; execute `composer audit` e `npm audit` regularmente.
- Subscreva alertas de segurança do Laravel e PHP.

### Revisão estruturada (2026-07-24 · 8.2)

- Rotas admin protegidas por `auth`, `verified`, `admin` e `legal.consent` onde aplicável.
- Login e reset com **throttle** (5/min).
- Superfície pública `/relatorio/{publicId}`: throttle `60/min`, log `analytics.report.public.*`, formato `publicId` restrito.
- API SAEB `/api/saeb/municipio/{ibge}`: **sem token**; throttle `120/min` + `IEDUCAR_SAEB_PUBLIC_API`.
- Clio: RBAC + validação de tipos de upload + fila dedicada.
- SSRF: `SafeOutboundUrl` (incl. SAEB/pedagógico) com DNS fail-closed.
- SQL dinâmico em i-Educar limitado a nomes resolvidos por schema da cidade.
- Testes: `ContainedPathResolverTest`, `SafeOutboundUrlTest`, CadÚnico/Misocial, FUNDEB, CI PHPUnit.

## Auditoria de usuários

Acções registadas em `admin_user_logs` (via `AdminUserAuditLogger`): criação, atualização, activação, desactivação, exclusão, encerramento de sessões, logins.

## Reportar problemas

Defina um canal interno (e-mail ou issue tracker) para reportar vulnerabilidades **sem** divulgação pública até correção.
