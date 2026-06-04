# CadÚnico / Cecad — previsão fora da rede

Agregados municipais do **Cecad** (Consulta, Seleção e Extração do CadÚnico) para estimar crianças em idade escolar cadastradas que podem não estar refletidas nas matrículas da rede municipal (i-Educar), com impacto financeiro indicativo via **VAAF** (ponderações FUNDEB).

## Privacidade

Não são armazenados CPF, NIS ou dados individuais — apenas totais por município e faixa etária (4–5, 6–10, 11–14, 15–17 anos).

## Importação (ordem automática)

1. **SAGI/Misocial (MDS)** — API Solr oficial (`IEDUCAR_CADUNICO_MISOGIAL_ENABLED=true` por defeito)
2. **API HTTP** — `IEDUCAR_CADUNICO_API_URL_TEMPLATE` (`{ibge}`, `{ano}`)
3. **CKAN** — descoberta em dados.gov.br ou `IEDUCAR_CADUNICO_CKAN_RESOURCE_ID`
4. **Cache** — `storage/app/cadunico/api/{ibge}/{ano}.json`
5. **CSV em storage** — `storage/app/cadunico/cecad/{ibge}_{ano}.csv` ou `nacional_{ano}.csv`
6. **Upload manual** — admin ou CLI

### Admin (fila `admin-sync`)

| Tela | URL |
|------|-----|
| Sincronização dedicada | `/admin/cadunico-sync` |
| Hub dados públicos | `/admin/dados-publicos` → `cadunico_cecad` |

**Visualização dos dados cadastrados:** na mesma tela (`#cadunico-snapshots-matrix`), matriz município × ano (população 4–17 e data de importação), filtro de intervalo e histórico detalhado por município — mesmo padrão da tabela VAAF/VAAT do FUNDEB em Compatibilidade i-Educar.

**Upload Cecad na UI:** formulário «Upload Cecad (CSV)» grava em `storage/app/cadunico/cecad/` e pode enfileirar importação automaticamente.

Tarefas: `cadastro::import_city_year`, `cadastro::import_storage_year`, `cadastro::import_csv`

### Exportação (consultoria)

Na aba CadÚnico, botões **PDF / CSV / Excel** — rota `dashboard.analytics.cadunico-previsao.export?format=pdf|csv|xlsx` (requer ano letivo e dados importados).

### CLI

```bash
php artisan migrate
php artisan cadunico:sync-city {city_id} --ano=2024
php artisan cadunico:sync-city --all --ano=2024
php artisan cadunico:import-cecad /caminho/arquivo.csv --ano=2024
php artisan cadunico:import-territorio storage/app/cadunico/territorio/arquivo.csv --ano=2024 --city={city_id}
```

CSV com delimitador `;`. Colunas: `codigo_ibge`, `ano`, faixas etárias e `populacao_escolar` (ver `config/ieducar.php` → `cadunico.cecad.column_map`).

## Painel

Aba **CadÚnico: previsão fora da rede e FUNDEB** no grupo **Cadastro e rede** (`/dashboard/analytics` → `cadunico_previsao`).

Funcionalidades avançadas (lacuna por faixa, cenários NEE/AEE/VAAR, mapa territorial, demanda×oferta): **[CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md)**.

## Leitura dos indicadores

| Indicador | Significado |
|-----------|-------------|
| População escolar CadÚnico | Soma das faixas 4–17 ou coluna `populacao_escolar_estimada` |
| Base rede (cálculo) | `min(matriculas, alunos distintos)` quando aplicável |
| Lacuna (`gap`) | `max(0, CadÚnico − base rede)` |
| Cobertura | Base rede / CadÚnico (%) |
| Impacto FUNDEB | Lacuna × VAAF (estimativa anual se integradas à rede) |
| Pressão territorial | Lacuna rateada × vulnerabilidade × distância à escola (com CSV territorial) |

**Aviso:** CadÚnico inclui famílias em vulnerabilidade no município; nem toda criança deve estar na rede municipal (estadual, privada, EJA). Use para busca ativa e planeamento, não como meta automática de matrícula.

## Variáveis de ambiente

Ver `.env.example` (`IEDUCAR_CADUNICO_*`).
