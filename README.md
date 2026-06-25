# servlitcys

Plataforma web Laravel para **dados educacionais por município**: painéis, análise e ligação a bases **i-Educar** por cidade (ligação **MySQL ou PostgreSQL** conforme configuração da cidade).

**Versão em produção (`main`):** **6.1.0** · tag **`20260624-Horizonte`** · [release](docs/RELEASE_20260624_HORIZONTE.md) · [histórico](docs/HISTORICO_VERSOES.md)

---

## Visão geral

```mermaid
flowchart LR
    subgraph Utilizadores
        Admin[Administrador]
        Analista[User / Municipal]
    end

    subgraph servlitcys
        Inicio[Início / RX]
        Analytics[Consultoria<br/>16 abas · 5 áreas]
        AdminPanel[Admin<br/>importações · compatibilidade]
    end

    subgraph Bases
        AppDB[(MySQL app)]
        Ieducar[(i-Educar<br/>por município)]
        APIs[Fontes públicas<br/>FNDE · IBGE · MDS]
    end

    Admin --> Inicio
    Admin --> Analytics
    Admin --> AdminPanel
    Analista --> Analytics
    servlitcys --> AppDB
    servlitcys --> Ieducar
    servlitcys --> APIs
```

| Pergunta | Onde ler |
|----------|----------|
| O que está implementado? | [docs/STATUS_PROJETO.md](docs/STATUS_PROJETO.md) |
| Diagramas e fluxos (deploy, FUNDEB, releases) | [docs/ARQUITETURA_E_FLUXOS.md](docs/ARQUITETURA_E_FLUXOS.md) |
| Índice completo da documentação | [docs/README.md](docs/README.md) |
| Hub visual (timeline 4.x, mapa de docs) | [docs/HUB_DOCUMENTACAO.md](docs/HUB_DOCUMENTACAO.md) · leitor `/admin/documentacao` · canvas Cursor em [canvases/documentacao-hub.canvas.tsx](canvases/documentacao-hub.canvas.tsx) |

### Consultoria — 5 áreas

```mermaid
flowchart TB
    R[1 Resumo → Diagnóstico] --> C[2 Cadastro · 5 abas]
    C --> P[3 Pedagógico · 3 abas]
    P --> Ce[4 Censo]
    Ce --> F[5 Finanças · Discrepâncias · FUNDEB · Tempo Real · Comparativo · Financiamentos]
```

Detalhe: [docs/ANALYTICS_NAVEGACAO_UI.md](docs/ANALYTICS_NAVEGACAO_UI.md).

---

## Requisitos

- PHP **8.3+** com extensões: `pdo_mysql`, `pdo_pgsql` (bases i-Educar em PostgreSQL), `pdo_sqlite` (testes), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2
- Node.js **20+** e npm (apenas para **desenvolvimento** ou para **recompilar** CSS/JS após alterações — ver abaixo)
- **MySQL/MariaDB** para a base principal da aplicação (utilizadores, cidades, sessões) em local e produção

## Instalação (desenvolvimento)

```bash
# Copie o modelo versionado e preencha segredos (DB, APP_KEY, admin, APIs públicas).
cp .env.example .env
composer install
php artisan key:generate   # só se APP_KEY estiver vazio

# Base de dados: configurar DB_* no .env, depois:
php artisan migrate

# Utilizador administrador (credenciais via .env — ver abaixo)
php artisan db:seed --class=AdminUserSeeder

npm install
npm run dev
```

Noutro terminal: `php artisan serve` (ou use o script `composer run dev` se configurado).

**Produção:** edite apenas o `.env` no servidor. Lista completa e checklist de deploy: **[docs/VARIAVEIS_AMBIENTE.md](docs/VARIAVEIS_AMBIENTE.md)** (não copie `.env.example` por cima do `.env` existente).

### Variáveis essenciais no `.env` (desenvolvimento)

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
| `ANALYTICS_LAZY_TABS` | Opcional (default `true` em `config/analytics.php`): carregar abas pesadas do painel de análise sob demanda; pedidos `.../dashboard/analytics/tab?tab=…` aparecem no Pulse por aba. |
| `SESSION_ENCRYPT` | Considerar `true` em produção com HTTPS |
| `IEDUCAR_MATRICULA_INDICADORES_INCLUIR_SITUACAO_INEP` | Opcional (default `true`): nos indicadores de matrícula, contar também matrículas com situação INEP «em curso» (`matricula_situacao.codigo`, ex. `1`) quando a coluna `ativo` na matrícula está indefinida ou inconsistente com o ecrã do i-Educar |
| `IEDUCAR_MATRICULA_SITUACAO_INEP_ATIVAS` | Opcional: lista separada por vírgulas de códigos INEP tratados como matrícula ativa em conjunto com o filtro de `ativo` (default: `1`) |
| `IEDUCAR_TABLE_FISICA_RACA` / `IEDUCAR_MYSQL_TABLE_FISICA_RACA` | Opcional: tabela pivô física ↔ raça (default PostgreSQL: `cadastro.fisica_raca`); usada no gráfico «cor ou raça» da aba Inclusão |

#### Painel de análise — FUNDEB, VAAF e novas abas

| Variável | Default | Descrição |
|----------|---------|-----------|
| `ANALYTICS_LAZY_TABS` | `true` | Abas pesadas carregam via `GET /dashboard/analytics/tab?tab=…` |
| `ANALYTICS_FUNDEB_DISC_SUMMARY` | `true` | Na aba FUNDEB (lazy), calcular perda/ganho de cadastro sem abrir a aba Discrepâncias completa |
| `ANALYTICS_FUNDING_SUMMARY_CACHE` | `600` | Cache (segundos) do resumo financeiro; `0` desativa. Reaproveita após visitar Discrepâncias |
| `IEDUCAR_DISC_VAA_REFERENCIA` | `4500` | VAAF de referência (R$/aluno/ano) para estimativas e fallback da **prévia federal** |
| `IEDUCAR_DISC_AVISO_FINANCEIRO` | (texto em `config/ieducar.php`) | Aviso legal nas abas com valores indicativos |
| `IEDUCAR_FUNDEB_NATIONAL_FLOOR` | `true` | Gravar piso nacional em `fundeb_municipio_references` quando não houver VAAF municipal |
| `IEDUCAR_FUNDEB_NATIONAL_VAAF_2024` | — | Prévia federal por ano (sobrepõe `IEDUCAR_DISC_VAA_REFERENCIA` quando > 0) |
| `IEDUCAR_FUNDEB_NATIONAL_VAAF_2025` | — | Idem para 2025 |
| `IEDUCAR_FUNDEB_VAAR_PCT_BASE` | `0` | % opcional de complementação VAAR sobre a base (ordem de grandeza) |
| `IEDUCAR_FUNDEB_AVISO_PREVISAO` | (texto em config) | Aviso na aba FUNDEB |
| `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` | — | Recurso CKAN FNDE para importar VAAF municipal (admin) |
| `IEDUCAR_FUNDEB_JSON_URL` | — | URL alternativa com dados `{ibge}/{ano}` |
| `IEDUCAR_FUNDEB_CACHE_PATH` | — | Caminho de cache local do JSON FNDE |
| `IEDUCAR_OTHER_FUNDING_PUBLIC_QUERIES` | `true` | Consultas automáticas na aba **Financiamentos** (FNDE, Tesouro, Transparência) |
| `IEDUCAR_OTHER_FUNDING_PUBLIC_CACHE_TTL` | `3600` | Cache (segundos) das consultas públicas por município/ano |
| `IEDUCAR_OTHER_FUNDING_LIVE_FNDE` | `false` | Se `true`, consulta CKAN FNDE em tempo real quando não houver cache |
| `PORTAL_TRANSPARENCIA_API_KEY` | — | Chave da API do [Portal da Transparência](https://portaldatransparencia.gov.br/pagina-api) (despesas por município) |
| `IEDUCAR_TESOURO_TRANSFERENCIAS_RESOURCE_ID` | — | Recurso CKAN do Tesouro (transferências por município); vazio = tentativa de descoberta automática |
| `IEDUCAR_WORK_EXCLUDE_LOGINS` | `admin,administrador,suporte,portabilis` | Logins excluídos da contagem de cadastro na aba **Censo** |
| `IEDUCAR_WORK_EXCLUDE_USER_IDS` | `1` | IDs de utilizador excluídos |
| `IEDUCAR_WORK_EXCLUDE_NIVEL` | `1` | Níveis de utilizador excluídos (ex.: admin) |
| `IEDUCAR_WORK_MINUTES_PER_RECORD` | `3.5` | Minutos por matrícula quando não há timestamps na base |
| `IEDUCAR_WORK_HOURS_PER_DAY` | `6` | Jornada para converter minutos em «dias de trabalho» |
| `IEDUCAR_CENSO_STATUS_TABLE` | — | Tabela qualificada com estado exportado/fechado por escola (ex.: módulo Educacenso) |
| `IEDUCAR_CENSO_TABLE_CANDIDATES` | (lista em config) | Tabelas a tentar automaticamente se `STATUS_TABLE` vazio |
| `IEDUCAR_CENSO_EXPORTED_TEXT` / `IEDUCAR_CENSO_CLOSED_TEXT` | — | Palavras-chave em colunas de situação textual |
| `APP_NOTIFICATIONS_ENABLED` | `true` | Sino de notificações na barra (processos em fila, conta, etc.) |
| `APP_NOTIFICATIONS_POLL_SECONDS` | `45` | Intervalo de actualização do sino (segundos) |
| `APP_NOTIFICATIONS_QUEUE` | `default` | Fila para gravar notificações na BD |
| `ANALYTICS_PDF_QUEUE` | `default` | Fila para geração do relatório PDF (aba Serventec) |
| `ANALYTICS_PDF_SERVENTEC_NAME` / `ANALYTICS_PDF_SERVENTEC_URL` | Serventec | Rodapé e capa do PDF |
| `ANALYTICS_PDF_DEVELOPER_NAME` / `ANALYTICS_PDF_DEVELOPER_GITHUB` | — | Créditos no rodapé de cada página |
| `ANALYTICS_PDF_REGIONAL_IMAGE` | `images/pdf/regional` | Pasta em `public/` com `{uf}.jpg` ou `default.svg` |

**VAAF municipal vs prévia federal:** os cálculos usam o valor **municipal** (`fundeb_municipio_references` ou importação FNDE). A prévia aparece nos cards para comparação (`IEDUCAR_FUNDEB_NATIONAL_VAAF_*` ou `IEDUCAR_DISC_VAA_REFERENCIA`).

Exemplo mínimo para produção com FUNDEB e trabalho realizado:

```env
ANALYTICS_LAZY_TABS=true
ANALYTICS_FUNDEB_DISC_SUMMARY=true
ANALYTICS_FUNDING_SUMMARY_CACHE=600

IEDUCAR_DISC_VAA_REFERENCIA=4500
IEDUCAR_FUNDEB_NATIONAL_FLOOR=true
IEDUCAR_FUNDEB_NATIONAL_VAAF_2025=4500
# IEDUCAR_FUNDEB_CKAN_RESOURCE_ID=...   # após importação admin

IEDUCAR_WORK_EXCLUDE_LOGINS=admin,administrador,suporte,portabilis
IEDUCAR_WORK_EXCLUDE_USER_IDS=1
IEDUCAR_WORK_EXCLUDE_NIVEL=1
```

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

## Análise estática (PHPStan / Larastan)

Analisa `app/Services` e `app/Repositories` (nível 5, com `phpstan-baseline.neon` para dívida existente).

```bash
composer run phpstan
```

## Documentação

**Entrada central:** [docs/README.md](docs/README.md) · **fluxos visuais:** [docs/ARQUITETURA_E_FLUXOS.md](docs/ARQUITETURA_E_FLUXOS.md)

```mermaid
mindmap
  root((Documentação))
    Estado
      STATUS_PROJETO
      HISTORICO_VERSOES
    Produto
      DOCUMENTACAO_EXECUTIVA
      ANALYTICS_NAVEGACAO_UI
      DESIGN_SYSTEM
    Operação
      IMPLANTACAO_PRODUCAO
      VARIAVEIS_AMBIENTE
      COMANDOS_ARTISAN
      SEGURANCA
    Finanças
      FUNDEB_VAAF_E_ONDA1
      CONSULTAS_EXTERNAS
```

| Âncora | Ficheiro |
|--------|----------|
| Estado actual | [docs/STATUS_PROJETO.md](docs/STATUS_PROJETO.md) |
| Arquitectura e fluxos | [docs/ARQUITETURA_E_FLUXOS.md](docs/ARQUITETURA_E_FLUXOS.md) |
| Design system (UI) | [docs/DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md) |
| Decisões técnicas | [docs/PONDERACOES_TECNICAS.md](docs/PONDERACOES_TECNICAS.md) |
| Backlog | [docs/BACKLOG_IMPLEMENTACOES.md](docs/BACKLOG_IMPLEMENTACOES.md) |
| Padrão editorial | [docs/PADRAO_DOCUMENTACAO.md](docs/PADRAO_DOCUMENTACAO.md) |

Leitura na interface: `/admin/documentacao` (admin) · `/documentacao` (utilizador/municipal).

## Estrutura de permissões (resumo)

| Área | Quem acede |
|------|------------|
| Painel `/dashboard` | `role=admin` |
| Análise `/dashboard/analytics` | `admin`, `user`, `municipal` (este só municípios vinculados) |
| CRUD de cidades, sync, Pulse | `role=admin` |
| Gestão de utilizadores | `admin` (todos); `user` (só perfil user); `municipal` (só municipal no âmbito) |
| Desactivar / excluir utilizadores | `role=admin` |
| Registo público | **Desativado** |

Detalhe: [docs/PERFIS_UTILIZADOR.md](docs/PERFIS_UTILIZADOR.md).

## Histórico de versões (linha 4.x)

| Versão | Tag | Data | Destaque |
|--------|-----|------|----------|
| **▶ 4.4.1** | `20260607b-Peitho` | 07/06 b | Hub docs, Mermaid no leitor, rodapé dev/GitHub — [RELEASE](docs/RELEASE_20260607b_PEITHO.md) |
| 4.4.0 | `20260607a-Ananke` | 07/06 a | Sufixo no mesmo dia, paridade Discrepâncias — [RELEASE](docs/RELEASE_20260607a_ANANKE.md) |
| 4.3.0 | `20260611-Harmonia` | 11/06 | RX portaria, gráfico home, CLI `--replace` — [RELEASE](docs/RELEASE_20260611_HARMONIA.md) |
| 4.2.0 | `20260610-Clio` | 10/06 | VAAT/VAAR portaria, hub Discrepâncias — [RELEASE](docs/RELEASE_20260610_CLIO.md) |
| 4.1.9 | `20260609-Theia` | 09/06 | Outlook Finanças, diagrama ERP — [RELEASE](docs/RELEASE_20260609_THEIA.md) |
| 4.1.0 | `20260605-Athena` | 05/06 | Navegação 5 áreas (cenário C) — [RELEASE](docs/RELEASE_20260605_ATHENA.md) |
| 4.0.0 | `20260604-Hestia` | 04/06 | Início reorganizado, rebuild Tempo Real — [RELEASE](docs/RELEASE_20260604_HESTIA.md) |

Linha completa (2.x–4.x), tags Git e convenção de sufixo: **[docs/HISTORICO_VERSOES.md](docs/HISTORICO_VERSOES.md)**.

## Licença

MIT (conforme `composer.json` / projeto Laravel base).
