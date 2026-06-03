# Release `20260603-Iris` — ServLitcys 3.6.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Figura:** *Iris* (arco entre fontes públicas e painéis — Misocial, FUNDEB, Finanças).

## Resumo

Marco **3.6.0** sobre **3.5.1** ([RELEASE_20260602_HERMES.md](RELEASE_20260602_HERMES.md)):

- **CadÚnico — SAGI/Misocial (MDS):** cliente Solr, importação nacional por ano, histórico multi-ano (`cadunico:import-misocial`), normalização IBGE 6/7 dígitos, mês de referência inteligente (evita projeções vazias tipo `202612`).
- **Analytics — Finanças «Tempo Real»:** repasses vs expectativa FUNDEB, alertas e guia para gestores (`finance_realtime`).
- **FUNDEB — metodologia e fontes oficiais:** painel de impacto nas abas, portarias/fontes no admin, verificação de actualizações.

## Destaques

### CadÚnico / Misocial

| Item | Detalhe |
|------|---------|
| `CadunicoSagiMisocialClient` | `importYear`, `importForIbge`, `resolveReferenceMonth`, lista `fl` compacta |
| `cadunico:import-misocial` | `--from=2020`, `--to=`, `--years=` — ~5 500 municípios/ano |
| `CadunicoMisocialIbgeNormalizer` | Consulta Solr com IBGE 7 e prefixo 6 dígitos |
| Auto-sync | Misocial primeiro; CKAN `catalogo.dados.gov.br` antes de `dados.gov.br` |
| Testes | Mapper, IBGE, bulk years |

### Analytics / FUNDEB

| Item | Detalhe |
|------|---------|
| `FundebImpactMethodology` | Metodologia perda/ganho e ponderações nas abas |
| `FinanceRealtimeFundebService` | Aba Finanças — tempo real (Tesouro/Transparência vs FUNDEB) |
| `FundebOfficialSourcesService` | Admin — portarias e fontes oficiais |

## Deploy

```bash
git fetch --tags
git checkout 20260603-Iris   # ou deploy de `main` após este commit
composer install --no-dev
npm run build
php artisan migrate --force
php artisan config:clear
php artisan view:clear
```

### Carga inicial Misocial (opcional, longa)

```bash
php artisan cadunico:import-misocial --from=2020
# ou por ano: php artisan cadunico:auto-sync --ano=2026 --no-gap-fill
```

Sem migração nova obrigatória face a 3.5.1 (tabela `cadunico_municipio_snapshots` já existente).

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.6.0
- [CADUNICO_AUTOMACAO.md](CADUNICO_AUTOMACAO.md)
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md)
