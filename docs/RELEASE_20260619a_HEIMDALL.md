# Release `20260619a-Heimdall` — ServLitcys 5.0.1

**Data:** 2026-06-19 · **Ramo:** `main` · **Marco:** **5.0.1** — data de produção corrigida + operação admin (Horizonte, monitor, dados públicos).

## Resumo

Patch **5.0.1** sobre **5.0.0** (Horizonte). O commit de produção foi feito em **19/06/2026**; a tag `20260603b-Horizonte` tinha prefixo de data incorrecto — usar **`20260619a-Heimdall`** para deploy.

### Correcções de release

- `revision_date` e selo no rodapé alinhados à data real de produção (**19/06/2026**).
- Tag de deploy actualizada (`20260619a-Heimdall`).

### Monitor de módulos (UI)

- Layout legível: KPIs fora do hero, filtro por URL (`?status=`), cartões com faixa de estado e chips de métricas.
- Incidentes expansíveis (detalhe completo, sem truncar).
- Acentos **teal** e **fuchsia** nos cartões temáticos.

### Verificação dados públicos (admin)

- Painel **Verificação de fontes oficiais** no hub (`#verificacao-oficial`).
- Cache do último scan, botão «Verificar agora», CLI com `--no-notify` e tabela no terminal.

### Herdado de 5.0.0 (Horizonte)

- Mapa de oportunidade `/dashboard/horizonte` — [HORIZONTE.md](HORIZONTE.md).
- KPIs Início, `public-data:check-official`, Educacenso 4.4.8.

Ver nota original do marco 5.0: [RELEASE_20260603b_HORIZONTE.md](RELEASE_20260603b_HORIZONTE.md) (conteúdo funcional; **data de deploy superseded** por este release).

## Deploy

```bash
git fetch --tags && git checkout 20260619a-Heimdall
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan view:clear && php artisan config:clear
```

## Testes

```bash
php artisan test --filter='HorizonteOpportunityScorerTest|PublicDataAvailabilityPresenterTest|PublicDataDailyCheckScheduleTest|ModuleMonitorCatalogTest'
```

## Documentação

| Área | Documento |
|------|-----------|
| Horizonte | [HORIZONTE.md](HORIZONTE.md) |
| Dados públicos | [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §10 |
| Monitor | `/admin/monitor-modulos` |
| Histórico | [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) |
