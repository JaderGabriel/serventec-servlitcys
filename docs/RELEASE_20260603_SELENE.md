# Release `20260603-Selene` — ServLitcys 3.7.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Figura:** *Selene* (clareza nos números — Finanças rápidas, VAAF com regra legal visível).

## Resumo

Marco **3.7.0** sobre **3.6.0** ([RELEASE_20260603_IRIS.md](RELEASE_20260603_IRIS.md)):

- **Analytics — Finanças mais rápidas:** bundle FUNDEB leve (sem Visão geral + amostra Matrículas na 1ª carga), perfil VAAF multi-ano opcional, Comparativo com preload só shell, memoização de `fundingImpactSnapshot` por pedido.
- **FUNDEB na consultoria:** bloco «Base FUNDEB indicativa (VAAF × matrículas)» com ponderações e rodapé de base legal (Lei 14.113/2020, portarias FNDE) em Matrículas, Rede, Tempo Real e FUNDEB.
- **Admin — hub de importação unificado:** `import-hub` (shell, action-card, callout) em CadÚnico, Geo, SAEB, Fila, FUNDEB e Dados públicos.
- **Segurança:** `SafeOutboundUrl` e `ContainedPathResolver` em fetchers CSV/CadÚnico e paths de ficheiros.

## Destaques

### Performance (Finanças)

| Variável | Default | Efeito |
|----------|---------|--------|
| `ANALYTICS_FUNDEB_LIGHT_TAB` | `true` | Matrículas do snapshot financeiro; não chama overview/sample |
| `ANALYTICS_FUNDEB_SKIP_VAAF_PROFILE` | `true` | Omitir perfil VAAF multi-ano FNDE no lazy tab |
| `ANALYTICS_COMPARATIVO_PRELOAD_SHELL` | `true` | Preload Comparativo: shell + `loadYearOptions` |
| `ANALYTICS_FINANCE_LIGHT_FUNDING` | `true` | Snapshot só matrículas + VAAF |

| Componente | Detalhe |
|------------|---------|
| `AnalyticsFundingContextResolver` | Uma passagem `fundingImpactSnapshot` por request HTTP |
| `comparativoYearOptions` | `loadYearOptions` em vez de `loadAll` |

### UX FUNDEB

| Item | Detalhe |
|------|---------|
| `FundebReferenceDisplay::referenciasLegaisLinha` | Lei + portarias para rodapé de valores |
| `x-dashboard.fundeb-valor-referencia` | Componente reutilizável |
| `analytics-tab-impact-header` | Ponderações + referências no bloco VAAF×matrículas |

### Admin / segurança

| Item | Detalhe |
|------|---------|
| `AdminImportHubCatalog` + views `import-hub/*` | Padrão unificado de importação |
| `SafeOutboundUrl` / `ContainedPathResolver` | SSRF e path traversal em imports |
| `docs/IMPORTACAO_DADOS_PUBLICOS.md` §8 | Guia do padrão de interface |

## Deploy

```bash
git fetch --tags
git checkout 20260603-Selene   # ou deploy de `main` após este commit
composer install --no-dev
npm run build
php artisan config:clear
php artisan view:clear
```

Sem migração nova obrigatória face a 3.6.0.

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.7.0
- [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) — flags Finanças
- [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §9 Performance
- [SEGURANCA.md](SEGURANCA.md) — URLs e paths contidos
