# Release `20260620-Urania` — ServLITCYS 5.6.0

**Data:** 2026-06-20 · **Ramo:** `main` · **Marco:** **5.6** — Horizonte camada «alta pressão» GIS, abastecimento robusto e sync BR.

---

## Resumo

Minor **5.6.0** sobre **5.5.0** (Helios):

1. **Horizonte — vista de decisão comercial** — preset **alta pressão** por defeito (`high_pressure`); KPI e filtros alinhados; ocultar coordenadas aproximadas no mapa; presets de vista (calor, prospectos, UF automática).
2. **Mapa GIS** — centroides IBGE reais por UF; resolução de sobreposições (`MunicipalityMapOverlapResolver`); métrica `high_pressure` em resumo/UF; painel metodologia com `benefit_scale`.
3. **Feed bimestral robusto** — SAEB com `HORIZONTE_SAEB_MEMORY_LIMIT=2048M`; repasses Tesouro com fallback ano ref−1; CadÚnico `HORIZONTE_CADUNICO_FILL_GAPS`; status `partial` vs `skipped` no pipeline.
4. **Bundle offline** — `horizonte:import-data-bundle --only` alinhado; export com skips CadÚnico/SIDRA/repasses; docs «quinzenal» corrigidas para **bimestral**.
5. **Script sync BR** — `horizonte-sync-br-continue.sh` com flock, detecção OOM/parcial/idle, env SAEB/CadÚnico e até 200 rondas.

---

## Deploy

```bash
git fetch --tags && git checkout 20260620-Urania
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Pós-deploy (abastecimento Horizonte)

```bash
# Retomar feed nacional (uma fase por invocação)
php artisan horizonte:fortnightly-feed --all --continue

# Ou loop automático até cobertura completa
./scripts/horizonte-sync-br-continue.sh

# SAEB pesado (planilhas INEP) — memória elevada
HORIZONTE_SAEB_MEMORY_LIMIT=2048M php artisan saeb:import-planilhas-inep
```

**`.env` (opcional):** `HORIZONTE_MAP_DEFAULT_VIEW=high_pressure`, `HORIZONTE_SAEB_MEMORY_LIMIT=2048M`, `HORIZONTE_CADUNICO_FILL_GAPS=true`.

---

## Testes

```bash
php artisan test --filter='HorizonteMapPresenter|HorizonteTesouroTransferSync|HorizonteFortnightlyFeedPipeline'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte (mapa, metodologia, feed) | [HORIZONTE.md](HORIZONTE.md) |
| Variáveis de ambiente | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) |
| Comandos CLI | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) |
| Anterior | [RELEASE_20260619c_HELIOS.md](RELEASE_20260619c_HELIOS.md) |
