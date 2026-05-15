# Segurança e operações — servlitcys

## Senhas e segredos

### Utilizadores da aplicação

- As palavras-passe são armazenadas com **hash** (cast `hashed` no modelo `User`), usando o driver configurado (tipicamente bcrypt).
- O registo público está **desativado**; novos utilizadores são criados por um administrador autenticado, com validação de palavra-passe (regras Laravel `Password::defaults()`).

### Administrador inicial (seeder)

- `AdminUserSeeder` utiliza **`ADMIN_EMAIL`** e **`ADMIN_PASSWORD`** definidos no `.env`.
- **Nunca** commite o ficheiro `.env` nem use palavras-passe fracas em produção.
- Após o primeiro deploy, altere a palavra-passe do admin e considere desativar ou rever o seeder em pipelines automatizados.

### Credenciais MySQL por cidade

- O campo `db_password` no modelo `City` usa cast **`encrypted`** (Laravel Encryption); requer `APP_KEY` estável — **fazer backup da chave** com o backup da base.
- Quem pode criar/editar cidades: apenas perfil **Administrador** (`role=admin`).

### Ficheiros e ambiente

- `APP_KEY` — obrigatório; em produção deve ser único e guardado em segredo (gestor de secrets, variáveis do servidor).
- `.env` em produção: `APP_DEBUG=false`, `APP_ENV=production` (ou equivalente).

## Autorização (RBAC)

Perfis (`users.role`): **admin**, **user**, **municipal**. Municípios do perfil municipal: pivot `city_user`.

| Recurso | Quem |
|---------|------|
| Painel `/dashboard` (estatísticas, probe) | `role=admin` — outros perfis são redireccionados para Análise |
| CRUD de cidades, sync, SMTP, sessões | `role=admin` (middleware `admin`) |
| Criar utilizadores | Admin, Utilizador (só `user`), Municipal (só `municipal` no seu âmbito) — `UserPolicy` |
| Análise / exportação | Admin e Utilizador: todos os municípios `forAnalytics`; Municipal: só vinculados — `CityPolicy::viewAnalytics` |
| Histórico de logins | Gate `manageUserAudit` (admin) |

A coluna legada `is_admin` é sincronizada automaticamente com `role` ao gravar. A navegação reflete as regras; controladores e `FormRequest` reaplicam autorização (incl. validação pós-sanitize de `city_ids`). Guia completo: [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md).

## Proteções HTTP comuns

- **CSRF** em formulários web (middleware Laravel).
- **Sessão**: `SESSION_DRIVER=database` (ou `redis` em escala); considerar `SESSION_ENCRYPT=true` com HTTPS.
- **Throttle** em rotas sensíveis:
  - `POST /login` — 5 tentativas por minuto (por IP)
  - Pedidos de reset de palavra-passe — limitados da mesma forma

## Checklist antes de produção

- [ ] `APP_DEBUG=false`
- [ ] HTTPS com certificado válido e `APP_URL` com `https://`
- [ ] `php artisan config:cache` e `route:cache` após deploy
- [ ] Permissões de ficheiros: `storage/` e `bootstrap/cache/` graváveis pelo web server
- [ ] Backup da base de dados e de `APP_KEY`
- [ ] Rever utilizadores `is_admin` e palavras-passe iniciais
- [ ] Logs: não expor stack traces a utilizadores finais
- [ ] (Opcional) Proxy reverso: cabeçalhos `X-Forwarded-*` e `TrustProxies` configurados no Laravel se aplicável

## Dependências e vulnerabilidades

- Mantenha **Composer** e **npm** atualizados; execute `composer audit` e `npm audit` regularmente.
- Subscreva alertas de segurança do Laravel e PHP.

## Reportar problemas

Defina um canal interno (e-mail ou issue tracker) para reportar vulnerabilidades **sem** divulgação pública até correção.
