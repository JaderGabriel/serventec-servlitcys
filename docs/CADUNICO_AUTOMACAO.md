# CadÚnico — automação sem upload

## Configuração mínima (.env)

```env
IEDUCAR_CADUNICO_NACIONAL_CSV_URL=https://SEU_ENDPOINT/cadunico/nacional_{ano}.csv
```

O sistema descarrega o ficheiro para `storage/app/cadunico/cecad/nacional_{ano}.csv` e importa **todos os municípios** de uma vez.

Opcional por município (lacunas):

```env
IEDUCAR_CADUNICO_API_URL_TEMPLATE=https://…/{ibge}/{ano}.json
IEDUCAR_CADUNICO_MUNICIPAL_CSV_URL=https://…/{ibge}_{ano}.csv
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

## Ordem do pipeline

1. Download URL nacional (`IEDUCAR_CADUNICO_NACIONAL_CSV_URL`)
2. Pesquisa opcional dados.gov.br (`IEDUCAR_CADUNICO_DADOS_GOV_SEARCH=true`)
3. Importação CSV nacional → `cadunico_municipio_snapshots`
4. Por cada cidade registada sem snapshot: API → CSV municipal → cache

## Nota MDS

Não existe API pública estável do Cecad para todos os municípios. A automação assume um **endpoint HTTP seu** (exportação Cecad agendada no servidor) ou API/CKAN quando o MDS publicar `resource_id`.
