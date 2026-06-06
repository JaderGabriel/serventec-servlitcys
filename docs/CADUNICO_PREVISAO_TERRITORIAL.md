# CadÚnico — previsão territorial e cenários financeiros

Extensão da aba **CadÚnico: previsão fora da rede** (`cadunico_previsao`) em três fases, sempre com **dados agregados** (sem CPF/NIS/endereço individual).

Documentação base Cecad/Misocial: [CADUNICO_CECAD.md](CADUNICO_CECAD.md). Faixas etárias e FUNDEB: [CADUNICO_FAIXAS_ETARIAS_FUNDEB.md](CADUNICO_FAIXAS_ETARIAS_FUNDEB.md).

---

## Fase 1 — Lacuna refinada e cenários

### Base de cálculo da rede

- **Matrículas** e **alunos distintos** vêm de `MatriculaChartQueries::volumeCounts`.
- A base para lacuna e FUNDEB usa `min(matriculas, alunos)` quando há duplicidade de matrícula do mesmo aluno (alinhado ao resto do painel Analytics).

### Lacuna por faixa etária

Para cada faixa Cecad (4–5, 6–10, 11–14, 15–17):

| Campo | Significado |
|-------|-------------|
| `cadunico` | População na faixa (snapshot municipal) |
| `ieducar_estimado` | Rateio da base municipal pelas keywords de etapa |
| `gap` | `max(0, cadunico − estimativa rede na faixa)` |
| `cobertura_label` | Percentagem de cobertura na faixa |
| `fundeb_gap_label` | Lacuna da faixa × VAAF |

Serviço: `App\Services\Cadunico\CadunicoRedeGapAnalyzer`.

### Vulnerabilidade familiar (agregado)

Indicadores derivados do snapshot Misocial/Cecad (`metadados.vulnerabilidade`, crianças PBF estimadas).

Serviço: `App\Services\Cadunico\CadunicoVulnerabilidadeIndicators`.

### Cenários financeiros sobre a lacuna

Proporções **NEE**, **AEE sem cadastro NEE** e **VAAR** observadas na rede municipal aplicadas à lacuna total (não identifica beneficiários no CadÚnico).

Serviço: `App\Services\Cadunico\CadunicoFinanceScenarioBuilder`.

---

## Fase 2 — Mapa de pressão territorial

### Pré-requisitos

1. Snapshot **municipal** CadÚnico (Cecad/Misocial) para o ano.
2. Import **territorial** (bairro, setor censitário, território CRAS, etc.).
3. Opcional: escolas georreferenciadas no filtro (`SchoolUnitsRepository::snapshot` → marcadores no mapa).

### Fórmula de pressão

Para cada território importado:

```
lacuna_est = lacuna_municipal × (cadunico_territorio / soma_cadunico_territorios)
pressao = lacuna_est × (1 + IVS/100) × (1 + min(15, dist_km_escola)/15 × 0,35)
```

- **IVS**: `indice_vulnerabilidade` do CSV (0–100).
- **dist_km_escola**: distância Haversine à escola municipal mais próxima no mapa.

Serviço: `App\Services\Cadunico\CadunicoTerritorialPressureBuilder`.

### UI

- Tabela **Faixas etárias — CadÚnico e lacuna**
- Tabela **Prioridade por território**
- Mapa Leaflet (`cadunicoTerritoryMap.js`) — círculos = pressão; pontos azuis = escolas

---

## Fase 3 — Demanda × oferta e import

### Demanda × oferta (INT-01 parcial)

Bloco indicativo que cruza lacuna CadÚnico com oferta (matrículas) e lista territórios prioritários do ranking.

Serviço: `App\Services\Cadunico\CadunicoDemandaOfertaSlice`.

### Tabela `cadunico_territorio_snapshots`

Migration: `2026_06_04_100000_create_cadunico_territorio_snapshots_table.php`.

| Coluna | Descrição |
|--------|-----------|
| `ibge_municipio`, `ano_referencia` | Chave com `territorio_codigo` |
| `territorio_nome`, `territorio_tipo` | Identificação (bairro, setor, …) |
| `criancas_4_5` … `criancas_15_17` | Faixas ou `criancas_4_17` total |
| `familias_beneficio`, `indice_vulnerabilidade` | Opcional |
| `latitude`, `longitude` | Para mapa e distância |

### Import territorial (oficial IBGE — recomendado)

Fontes públicas **sem credencial**:

| Fonte | Uso |
|-------|-----|
| [IBGE FTP — Agregados por bairro/setor (Censo 2022)](https://ftp.ibge.gov.br/Censos/Censo_Demografico_2022/Agregados_por_Setores_Censitarios/) | População total (`v0001`) por bairro ou, se ausente, por setor censitário |
| [IBGE GeoServer WFS](https://geoservicos.ibge.gov.br/geoserver/CGMAT/wfs) | Centróides (`qg_2022_650_bairro_agreg` ou `qg_2022_600_setcensitario__v02`) |

O CadÚnico **municipal** (Misocial) é **rateado** por território:  
`criancas_4_17_território ≈ CadÚnico municipal × (população Censo no território / população Censo no município)`.

**Pré-requisito:** snapshot municipal importado (`cadunico:sync-city`).

```bash
php artisan cadunico:sync-city --all --ano=2025
php artisan cadunico:sync-territorio --all --ano=2025
php artisan cadunico:sync-territorio --all --queue --ano=2025   # produção / cron
php artisan cadunico:sync-territorio 1 --ano=2025
```

**Admin:** `/admin/cadunico-sync` → «Fluxo completo — um município» ou «Mapa territorial IBGE — todos».

**Cron (após `cadunico:auto-sync`):** `IEDUCAR_CADUNICO_TERRITORIO_SCHEDULE_ENABLED=true`, horário `04:30` por defeito.

ZIPs em cache: `storage/app/cadunico/territorio/ibge-cache/` (renováveis a cada 90 dias).

### Import CSV territorial (municipal / CRAS)

Quando o município dispuser de agregados próprios (secretaria social, CRAS), use CSV manual ou **pull automático em produção**.

**Admin:** `/admin/cadunico-sync` → formulário «CSV territorial (bairro/setor)».

#### Produção — download + import (recomendado com URL fixa)

Configure no `.env` a URL pública do CSV (placeholders `{ibge}`, `{ano}`, `{city_id}`, `{city}`):

```env
IEDUCAR_CADUNICO_TERRITORIO_CSV_URL=https://dados.exemplo.gov.br/cadunico/territorio_{ibge}_{ano}.csv
IEDUCAR_CADUNICO_TERRITORIO_CSV_CACHE_DAYS=7
IEDUCAR_CADUNICO_TERRITORIO_CSV_TIMEOUT=120
```

```bash
# Um município
php artisan cadunico:pull-territorio 1 --ano=2025

# Todos os municípios com analytics (cron)
php artisan cadunico:pull-territorio --all --ano=2025

# Forçar novo download; só gravar ficheiro sem importar
php artisan cadunico:pull-territorio 1 --ano=2025 --force
php artisan cadunico:pull-territorio 1 --ano=2025 --download-only

# URL pontual (ignora .env)
php artisan cadunico:pull-territorio 1 --ano=2025 --url='https://.../territorio_{ibge}_{ano}.csv'
```

O ficheiro fica em `storage/app/cadunico/territorio/territorio_{ibge}_{ano}.csv` (mesmo padrão do upload admin). Depois importa para `cadunico_territorio_snapshots`.

#### Ficheiro já no servidor

```bash
php artisan cadunico:import-territorio storage/app/cadunico/territorio/territorio_2910800_2024.csv --ano=2024 --city=1
```

Delimitador: `;` (config `IEDUCAR_CADUNICO_TERRITORIO_DELIMITER`).

Mapeamento de colunas: `config/ieducar.php` → `cadunico.territorio.column_map`.

Exemplo mínimo:

```csv
territorio_codigo;territorio_nome;criancas_4_17;latitude;longitude;indice_vulnerabilidade
001;Centro;420;-12.9714;-38.5014;35
002;Subúrbio;680;-12.9950;-38.4550;72
```

---

## Exportação e PDF

Na aba CadÚnico: **PDF / CSV / Excel** incluem faixas com lacuna, cenários, vulnerabilidade, territórios e demanda×oferta.

Rotas: `dashboard.analytics.cadunico-previsao.export`.

---

## Limitações e ética de uso

- CadÚnico ≠ obrigação de matrícula na rede municipal (estadual, privada, EJA).
- Território depende da qualidade do CSV municipal (CRAS, IBGE, secretaria social).
- Cenários NEE/AEE são **indicativos**; não substituem cadastro individual no i-Educar.
- Sem dados territoriais, o mapa e o ranking ficam vazios; a lacuna **municipal** continua disponível.

---

## Referência técnica

| Componente | Classe |
|------------|--------|
| Relatório da aba | `CadunicoPrevisaoRepository` |
| Lacuna / faixas | `CadunicoRedeGapAnalyzer` |
| Mapa / ranking | `CadunicoTerritorialPressureBuilder` |
| Import CSV | `CadunicoTerritorioCsvImportService` |
| Informes | `CadunicoPrevisaoInformeBuilder` |
| Export | `CadunicoPrevisaoExportRowsBuilder` |
