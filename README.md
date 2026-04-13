# servlitcys

Plataforma web Laravel para **dados educacionais por município**: painéis, análise e ligação a bases iEducar/MySQL por cidade.

## Requisitos

- PHP **8.3+** com extensões: `pdo_mysql`, `pdo_sqlite` (testes), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2
- Node.js **20+** e npm (assets com Vite)
- MySQL/MariaDB para ambiente local e produção

## Instalação (desenvolvimento)

```bash
cp .env.example .env
composer install
php artisan key:generate

# Base de dados: configurar DB_* no .env, depois:
php artisan migrate

# Utilizador administrador (credenciais via .env — ver abaixo)
php artisan db:seed --class=AdminUserSeeder

npm install
npm run dev
```

Noutro terminal: `php artisan serve` (ou use o script `composer run dev` se configurado).

### Variáveis essenciais no `.env`

| Variável | Descrição |
|----------|-----------|
| `APP_NAME` | Nome da aplicação (ex.: `servlitcys`) |
| `APP_ENV` | `local` / `production` |
| `APP_DEBUG` | **`false` em produção** |
| `APP_URL` | URL pública (com HTTPS em produção) |
| `APP_KEY` | Gerado com `php artisan key:generate` — **nunca commitar** |
| `ADMIN_EMAIL` / `ADMIN_USERNAME` / `ADMIN_PASSWORD` / `ADMIN_BIRTH_DATE` | Credenciais e data de nascimento do admin criado pelo `AdminUserSeeder` (a data entra na recuperação de palavra-passe) |
| `SERVENTEC_WHATSAPP_NUMBER` | Opcional: número WhatsApp (só dígitos com DDI) para o botão “Contactar Serventec” na página inicial |
| `DB_*` | Base principal Laravel (utilizadores, cidades, sessões) |
| `SESSION_ENCRYPT` | Considerar `true` em produção com HTTPS |

Credenciais MySQL por cidade (`db_*` no modelo `City`) são guardadas **encriptadas** na base (cast `encrypted`).

## Build de produção (assets)

```bash
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Servidor web deve apontar o document root para `public/`.

## Testes

Requer extensão PHP `pdo_sqlite` para a base em memória definida em `phpunit.xml`.

```bash
composer test
# ou: php artisan test
```

## Documentação

- [Documentação executiva](docs/DOCUMENTACAO_EXECUTIVA.md) — visão de negócio e stakeholders
- [Segurança e operações](docs/SEGURANCA.md) — senhas, permissões e checklist de deploy

## Estrutura de permissões (resumo)

| Área | Quem acede |
|------|------------|
| Painel e análise (cidades com dados válidos) | Utilizadores autenticados e verificados |
| CRUD de cidades e criação de utilizadores | Apenas **`is_admin`** |
| Registo público | **Desativado** — utilizadores criados no painel por administrador |

## Licença

MIT (conforme `composer.json` / projeto Laravel base).
