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
- Quem pode criar/editar cidades: apenas utilizadores com **`is_admin`**.

### Ficheiros e ambiente

- `APP_KEY` — obrigatório; em produção deve ser único e guardado em segredo (gestor de secrets, variáveis do servidor).
- `.env` em produção: `APP_DEBUG=false`, `APP_ENV=production` (ou equivalente).

## Autorização (RBAC simplificado)

| Recurso | Política |
|---------|----------|
| Criar utilizadores | `UserPolicy::create` — só `is_admin` |
| CRUD de cidades | `CityPolicy` — `viewAny`, `create`, `update`, `delete` só para `is_admin` |
| Análise por cidade | `city` deve estar ativa e com dados configurados (`viewAnalytics`) |

A navegação (menu) reflete estas regras; o servidor **reaplica** autorização em controladores e `FormRequest`.

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
