# Segurança e operações — servlitcys

**Versão do produto:** 4.1.0 · **Última revisão:** 2026-06-05

> **Índice:** [README.md](README.md) · **Deploy:** [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) · **Ponderações:** [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §10.

## Senhas e segredos

### Usuários da aplicação

- As senhas são armazenadas com **hash** (cast `hashed` no modelo `User`), usando o driver configurado (tipicamente bcrypt).
- O registro público está **desativado**; novos usuários são criados por um administrador autenticado, com validação de senha (regras Laravel `Password::defaults()`).

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
| Painel `/dashboard` (estatísticas, probe) | `role=admin` — outros perfis são redireccionados para Análise |
| CRUD de cidades, sync, SMTP, sessões | `role=admin` (middleware `admin`) |
| Criar usuários | Admin, Usuário (só `user`), Municipal (só `municipal` no seu âmbito) — `UserPolicy` |
| Desativar / reativar / excluir usuários | Só `role=admin`; não sobre a própria conta; não desativar nem excluir o único admin — `UserPolicy::updateStatus`, `UserPolicy::delete` |
| Contas inactivas (`is_active=false`) | Login recusado (`LoginRequest`); sessão terminada em cada pedido (`EnsureUserIsActive`) |
| Análise / exportação | Admin e Usuário: todos os municípios `forAnalytics`; Municipal: só vinculados — `CityPolicy::viewAnalytics` |
| Histórico de logins | Gate `manageUserAudit` (admin) |

A coluna legada `is_admin` é sincronizada automaticamente com `role` ao gravar. A navegação reflete as regras; controladores e `FormRequest` reaplicam autorização (incl. validação pós-sanitize de `city_ids`). Guia completo: [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md).

## Proteções HTTP comuns

- **CSRF** em formulários web (middleware Laravel).
- **Sessão**: `SESSION_DRIVER=database` (ou `redis` em escala); considerar `SESSION_ENCRYPT=true` com HTTPS.
- **Throttle** em rotas sensíveis:
  - `POST /login` — 5 tentativas por minuto (por IP)
  - Pedidos de reset de senha — limitados da mesma forma

## Checklist antes de produção

- [ ] `APP_DEBUG=false`
- [ ] HTTPS com certificado válido e `APP_URL` com `https://`
- [ ] `php artisan config:cache` e `route:cache` após deploy
- [ ] Permissões de arquivos: `storage/` e `bootstrap/cache/` graváveis pelo web server
- [ ] Backup da base de dados e de `APP_KEY`
- [ ] Rever usuários `is_admin` e senhas iniciais
- [ ] Logs: não expor stack traces a usuários finais
- [ ] (Opcional) Proxy reverso: cabeçalhos `X-Forwarded-*` e `TrustProxies` configurados no Laravel se aplicável

## Importações e URLs externas (CadÚnico, FUNDEB, SAEB)

| Risco | Mitigação no código |
|--------|---------------------|
| **SSRF** em download de CSV por URL (`IEDUCAR_CADUNICO_*_CSV_URL`, CKAN) | `SafeOutboundUrl::isAllowedHttpUrl()` — bloqueia `localhost`, redes privadas e esquemas não HTTP(S) |
| **Path traversal** em `cadunico:import-cecad {path}` | `ContainedPathResolver` — ficheiro só dentro de `storage/app` ou `storage/app/cadunico/cecad` |
| **Lista Solr `fl` demasiado longa** (Misocial) | Máximo `IEDUCAR_CADUNICO_MISOGIAL_FIELDS_MAX` (default 24); acima disso usa lista compacta interna |
| **IBGE Misocial 6 vs 7 dígitos** | `CadunicoMisocialIbgeNormalizer` — consulta com ambas variantes |
| **HTML legal / documentação admin** | Conteúdo de editores confiáveis; `{!! !!}` só em vistas com origem controlada por admin |

URLs Misocial (MDS) vêm de config fixa (`IEDUCAR_CADUNICO_MISOGIAL_BASE_URL`), não de input do utilizador.

Comandos que executam `shell_exec` (ex.: `unrar`/`7z` em SAEB) usam binários resolvidos com `escapeshellarg` — restrinja PATH no servidor.

## Dependências e vulnerabilidades

- Mantenha **Composer** e **npm** atualizados; execute `composer audit` e `npm audit` regularmente.
- Subscreva alertas de segurança do Laravel e PHP.

### Revisão estruturada (2026-06-03)

- Rotas admin protegidas por `auth`, `verified`, `admin` e `legal.consent` onde aplicável.
- Login e reset com **throttle** (5/min).
- SQL dinâmico em i-Educar limitado a nomes de colunas/tabelas resolvidos por schema da cidade (não a input HTTP directo).
- Testes unitários: `ContainedPathResolverTest`, `SafeOutboundUrlTest`, CadÚnico/Misocial, FUNDEB metodologia.

## Auditoria de usuários

Acções registadas em `admin_user_logs` (via `AdminUserAuditLogger`): criação, atualização, activação, desactivação, exclusão, encerramento de sessões, logins.

## Reportar problemas

Defina um canal interno (e-mail ou issue tracker) para reportar vulnerabilidades **sem** divulgação pública até correção.
