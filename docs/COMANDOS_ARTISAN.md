# Comandos Artisan — servlitcys

**Data:** maio de 2026  
**Interface admin:** menu **Sincronizações → Comandos Artisan** (`/admin/artisan-commands`)

Execute sempre na **raiz do projeto** (onde está o `artisan`):

```bash
cd /caminho/para/servlitcys
php artisan <comando>
```

---

## 1. Geográficas (mapa / unidades escolares)

| Comando | Descrição |
|---------|-----------|
| `app:sync-school-unit-geos` | i-Educar → `school_unit_geos` (coords locais) |
| `app:sync-school-unit-geos-official` | Coordenadas oficiais INEP + divergência |
| `app:import-inep-microdados-cadastro-escolas-geo` | Microdados cadastro escolas INEP |
| `app:sync-school-unit-geos-pipeline` | Pipeline 1→2→3 |
| `app:probe-inep-geo-fallbacks` | Diagnóstico (sem gravar) |
| `app:export-inep-geo-fallback-csv` | Export CSV para correção manual |
| `app:import-inep-geo-fallback-csv` | Import CSV de coords |
| `app:index-inep-censo-geo-agg` | Agregados geográficos Censo |

**Interface web:** `/admin/geo-sync`

**Exemplos:**

```bash
php artisan app:sync-school-unit-geos --city=1 --only-missing=1
php artisan app:sync-school-unit-geos-pipeline --city=1
php artisan app:probe-inep-geo-fallbacks --city=1
```

---

## 2. Pedagógicas (SAEB)

| Comando | Descrição |
|---------|-----------|
| `saeb:sync-microdados` | ZIP INEP ou CSV por URL → `historico.json` |
| `saeb:import-official` | Séries oficiais por IBGE |
| `saeb:import-csv` | CSV manual |

**Interface web:** `/admin/pedagogical-sync`

**Variáveis:** `IEDUCAR_SAEB_*`, `IEDUCAR_SAEB_JSON_PATH`

```bash
php artisan saeb:sync-microdados --year=2023
php artisan saeb:import-official --city=1 --year=2023
```

---

## 3. FUNDEB / VAAF

Referências gravadas em **`fundeb_municipio_references`** (`city_id`, `ibge_municipio`, `ano`, `vaaf`, `vaat`, `complementacao_vaar`). O painel Analytics usa o **ano do filtro**; se não existir linha, o ano mais recente; depois fallback global.

| Comando | Descrição |
|---------|-----------|
| `fundeb:import-api` | API CKAN FNDE ou JSON (`{ibge}`, `{ano}`) |
| `fundeb:import-references` | CSV `;` (ibge;ano;vaaf;…) |

**Interface web:** `/admin/ieducar-compatibility` (secção FUNDEB + probe)

**Variáveis:** `IEDUCAR_FUNDEB_CKAN_URL`, `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID`, `IEDUCAR_FUNDEB_JSON_URL`, `IEDUCAR_DISC_VAA_REFERENCIA`

```bash
php artisan fundeb:import-api 1 --ano=2024
php artisan fundeb:import-references storage/app/fundeb.csv
```

Ver também: [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md)

---

## 4. Compatibilidade i-Educar

| Comando | Descrição |
|---------|-----------|
| `ieducar:schema-probe` | Gera `schema_probe.json` (rotinas + schema recurso prova) |

**Interface web:** `/admin/ieducar-compatibility` (export JSON na mesma página)

```bash
php artisan ieducar:schema-probe 1 --ano=2024
php artisan ieducar:schema-probe 1 --output=storage/app/schema_probe_1.json
```

---

## 5. Operação (deploy / dev)

```bash
php artisan migrate --force          # produção
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan test
composer run phpstan
```

**Subir em desenvolvimento:**

```bash
composer install
# Configure o .env na raiz do projeto (único ficheiro de ambiente)
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
npm install && npm run dev    # terminal 1 — assets
php artisan serve             # terminal 2 — http://127.0.0.1:8000
```

Ou: `composer run dev` (se configurado no `composer.json`).

---

## 6. Relação comando ↔ interface

| Área | CLI principal | Admin |
|------|----------------|-------|
| Geo | `app:sync-school-unit-geos-pipeline` | Geo-sync |
| SAEB | `saeb:sync-microdados` | Pedagogical-sync |
| FUNDEB | `fundeb:import-api` | ieducar-compatibility |
| Schema | `ieducar:schema-probe` | ieducar-compatibility |

O catálogo em código (`App\Support\Console\ArtisanCommandsCatalog`) alimenta a tela admin; ao acrescentar comandos novos, actualize o catálogo e este documento.
