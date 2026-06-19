# Release `20260603f-Helios` — ServLITCYS 5.5.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Marco:** **5.5** — Horizonte GIS gerencial, monitor periódico e catálogo Artisan admin.

---

## Resumo

Minor **5.5.0** sobre **5.4.0** (Hyperion):

1. **Horizonte — UI gerencial** — painel de filtros colapsável com chips activos; layout alargado e mapa mais alto; paleta GIS refinada; KPIs com tooltips; painel lateral **Metodologia e fórmulas** (pesos, dimensões, limiares); tooltip municipal com breakdown das 6 dimensões.
2. **Monitor de módulos** — `module-monitor:collect` agendado a cada **10 minutos** (`MODULE_MONITOR_COLLECT_INTERVAL_MINUTES`) em vez de recolha diária única.
3. **Comandos Artisan (admin)** — catálogo expandido (Horizonte, Educacenso, SAEB planilhas, CadÚnico Cecad, slugs de confirmação); tabela de **slugs production** resolvidos da config; detalhes, agendamento e badges por comando.

---

## Deploy

```bash
git fetch --tags && git checkout 20260603f-Helios
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Pós-deploy

```bash
# Confirmar agendamentos (monitor 10 min + Horizonte bimestral)
php artisan schedule:list | grep -E 'module-monitor|horizonte'

# Recolha imediata de sondas (opcional)
php artisan module-monitor:collect
```

**`.env`:** substituir `MODULE_MONITOR_COLLECT_TIME` (obsoleto) por `MODULE_MONITOR_COLLECT_INTERVAL_MINUTES=10` se ainda presente.

---

## Testes

```bash
php artisan test --filter='ArtisanCommandsCatalog|ModuleMonitorCollectSchedule|HorizonteMapPresenter'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte (metodologia, mapa GIS) | [HORIZONTE.md](HORIZONTE.md) |
| Comandos CLI + slugs | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) · `/admin/artisan-commands` |
| Monitor (`module-monitor:collect`) | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11c |
| Anterior | [RELEASE_20260603e_HYPERION.md](RELEASE_20260603e_HYPERION.md) |
