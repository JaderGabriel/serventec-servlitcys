# servlitcys

Plataforma web Laravel para **dados educacionais por município**: painéis, análise e ligação a bases **i-Educar** por cidade (ligação **MySQL ou PostgreSQL** conforme configuração da cidade).

**Versão actual:** **2.0.1** (tag `v2.0.1` no repositório).

## Requisitos

- PHP **8.3+** com extensões: `pdo_mysql`, `pdo_pgsql` (bases i-Educar em PostgreSQL), `pdo_sqlite` (testes), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2
- Node.js **20+** e npm (apenas para **desenvolvimento** ou para **recompilar** CSS/JS após alterações — ver abaixo)
- **MySQL/MariaDB** para a base principal da aplicação (utilizadores, cidades, sessões) em local e produção

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
| `IEDUCAR_MATRICULA_INDICADORES_INCLUIR_SITUACAO_INEP` | Opcional (default `true`): nos indicadores de matrícula, contar também matrículas com situação INEP «em curso» (`matricula_situacao.codigo`, ex. `1`) quando a coluna `ativo` na matrícula está indefinida ou inconsistente com o ecrã do i-Educar |
| `IEDUCAR_MATRICULA_SITUACAO_INEP_ATIVAS` | Opcional: lista separada por vírgulas de códigos INEP tratados como matrícula ativa em conjunto com o filtro de `ativo` (default: `1`) |
| `IEDUCAR_TABLE_FISICA_RACA` / `IEDUCAR_MYSQL_TABLE_FISICA_RACA` | Opcional: tabela pivô física ↔ raça (default PostgreSQL: `cadastro.fisica_raca`); usada no gráfico «cor ou raça» da aba Inclusão |

Credenciais de ligação à base i-Educar por cidade (`db_*` no modelo `City`) são guardadas **encriptadas** na base (cast `encrypted`).

## Produção (sem Node no servidor)

A pasta **`public/build/`** (manifest + CSS/JS gerados pelo Vite) **está versionada** no Git. No servidor de produção basta:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Não é necessário** `npm install` nem `npm run build` na máquina de produção, desde que faças `git pull` com o repositório atualizado.

Servidor web: document root = `public/`.

### Vite / CORS (`[::1]:5173` ou `localhost:5173`)

Se o browser tentar carregar scripts de `http://127.0.0.1:5173` ou `[::1]:5173` em produção, o Laravel encontrou o ficheiro **`public/hot`** (criado localmente por `npm run dev`) e pensa que o Vite está em modo desenvolvimento.

1. **`APP_ENV=production`** no `.env` do servidor (e `php artisan config:cache` depois).
2. **Apagar `public/hot`** no servidor: `rm -f public/hot` (não deve existir em produção; está no `.gitignore`).
3. Garantir que **`public/build/manifest.json`** existe (assets compilados no repositório ou após `npm run build`).

A aplicação também **remove automaticamente** `public/hot` ao arrancar em ambiente `production`, mas convém não enviar esse ficheiro no deploy.

### Quando alterar `resources/css` ou `resources/js`

Recompila **na tua máquina ou na CI** (Node 20+) e faz commit de `public/build/`:

```bash
npm ci
npm run build
# Se só tiveres Node 18 local, podes usar Docker:
# docker run --rm -v "$(pwd)":/app -w /app node:22-alpine sh -c "npm ci && npm run build"
git add public/build && git commit -m "chore: rebuild assets"
```

## Build de produção (referência rápida)

Os mesmos comandos `npm ci` + `npm run build` acima geram os ficheiros em `public/build/` antes do deploy.

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

## Histórico de versões (resumo)

| Tag | Notas |
|-----|--------|
| **v2.0.1** | Gráfico de cor/raça (Inclusão): prioriza `fisica_raca` como no BI (aluno → `cadastro.fisica_raca` → `raca`), `COUNT(DISTINCT matrícula)` e rótulo «Não declarado»; config `IEDUCAR_TABLE_FISICA_RACA`. |
| v2.0 | Indicadores de matrícula alinhados com situação INEP quando `ativo` está indefinido; variáveis `IEDUCAR_MATRICULA_*` acima. Documentação de requisitos actualizada (`pdo_pgsql` para i-Educar em PostgreSQL). |
| v1.0 | Versão inicial etiquetada. |

## Licença

MIT (conforme `composer.json` / projeto Laravel base).
