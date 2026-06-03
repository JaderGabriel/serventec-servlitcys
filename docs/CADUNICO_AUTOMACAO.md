# CadÚnico — automação sem upload

## Fonte principal (oficial MDS)

Por defeito o sistema importa agregados municipais da **API Solr Misocial** (Matriz de Informação Social — MDS/SAGI):

- URL: `https://aplicacoes.mds.gov.br/sagi/servicos/misocial/`
- ~5 500 municípios por mês de referência (`anomes_s`, ex. `202412`)
- Não exige servidor próprio nem `IEDUCAR_CADUNICO_NACIONAL_CSV_URL`

```env
IEDUCAR_CADUNICO_MISOGIAL_ENABLED=true
# IEDUCAR_CADUNICO_MISOGIAL_BASE_URL=https://aplicacoes.mds.gov.br/sagi/servicos/misocial
# IEDUCAR_CADUNICO_MISOGIAL_PAGE_SIZE=6000
```

## Complementos (opcionais)

| Fonte | Variável | Uso |
|--------|----------|-----|
| CKAN dados.gov.br | `IEDUCAR_CADUNICO_CKAN_RESOURCE_ID` ou descoberta automática | Lacunas por município |
| API municipal | `IEDUCAR_CADUNICO_API_URL_TEMPLATE` | `{ibge}` e `{ano}` |
| CSV nacional próprio | `IEDUCAR_CADUNICO_NACIONAL_CSV_URL` | Só se Misocial falhar |
| Cecad em storage | `storage/app/cadunico/cecad/` | Upload manual ou export agendado |

```env
IEDUCAR_CADUNICO_DADOS_GOV_SEARCH=true
IEDUCAR_CADUNICO_CKAN_BASES=https://dados.gov.br,https://catalogo.dados.gov.br
```

## Execução

| Canal | Comando / acção |
|--------|------------------|
| Cron semanal | `cadunico:auto-sync --queue` (segunda 03:30 por defeito) |
| Rotina massiva | fase `cadunico_snapshots` em `weekly-mass-sync` |
| Admin | `/admin/cadunico-sync` → **Sincronização automática** |
| Hub | Dados públicos → CadÚnico → **Sincronização automática** |
| Nova cidade com IBGE | fila `cadastro::import_city_year` automática |
| CLI imediato | `php artisan cadunico:auto-sync` |
| Histórico Misocial | `php artisan cadunico:import-misocial --from=2020` |

## Ordem do pipeline

1. **SAGI/Misocial** — importação nacional do ano (`importYear`)
2. Se vazio: download CSV nacional (`IEDUCAR_CADUNICO_NACIONAL_CSV_URL`)
3. Pesquisa dados.gov.br (`IEDUCAR_CADUNICO_DADOS_GOV_SEARCH=true`)
4. Importação CSV em `storage/app/cadunico/cecad/`
5. Por cidade sem snapshot: Misocial → CKAN → API → CSV municipal → cache

## Diagnóstico

Admin `/admin/cadunico-sync` mostra o probe Misocial (municípios no mês de referência).

```bash
php artisan cadunico:auto-sync --ano=2024
php artisan cadunico:import-misocial --from=2020
php artisan cadunico:import-misocial --years=2020,2021,2022,2023,2024,2025,2026
php artisan tinker --execute="echo \App\Models\CadunicoMunicipioSnapshot::count();"
```

## Nota MDS

O Cecad microdados não tem API pública estável para todos os municípios. Os totais agregados do Misocial são a fonte oficial recomendada; Cecad/CSV permanecem para auditoria ou quando a rede bloqueia o MDS.
