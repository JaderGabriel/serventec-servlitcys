# Release `20260603e-Hyperion` — ServLitcys 5.4.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Marco:** **5.4** — Horizonte v2 (demanda social, SIDRA, repasses, mapa performante).

---

## Resumo

Minor **5.4.0** sobre **5.3.0** (Prometheus):

1. **Scoring v2** — dimensões **demanda social** (CadÚnico), **dependência de transferências** (Tesouro) e escala com fallback **pop. 4–17 SIDRA**; pesos rebalanceados em `config/horizonte.php`.
2. **Feed bimestral (9 fases)** — ordem: FUNDEB → Censo → **CadÚnico** → **SIDRA** → **Repasses Tesouro** → SAEB → IBGE → SGE → Verificação; migração `municipal_demography_snapshots`.
3. **Correcção feed** — callback `debug` da CLI não entra no cache (fix `Serialization of 'Closure' is not allowed` em `--all --uf=XX`).
4. **Mapa performante** — bases >800 municípios: vista inicial na UF prioritária + prospectos; limite de **400** pontos no render; avisos na UI; `preferCanvas`.
5. **SGE concorrência (admin)** — registo no mapa sem criar cidade Consultoria; bloqueio se IBGE já está no catálogo; segmento «Sem SGE (concorrência)»; actualização local do marcador após save.
6. **Hub Dados públicos** — cobertura CadÚnico/SIDRA/repasses no painel Horizonte; upload de bundle consolidado (local → prod).
7. **Início** — cabeçalho do bloco Acesso rápido alinhado ao padrão visual (eyebrow + título «Operação diária»).

---

## Deploy

```bash
git fetch --tags && git checkout 20260603e-Hyperion
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Pós-deploy (abastecimento Horizonte)

```bash
# Primeira execução após migrate — reinicia pipeline
php artisan horizonte:fortnightly-feed --staged --reset

# Retomar etapas (uma fase por invocação)
php artisan horizonte:fortnightly-feed --staged --continue

# Ou por UF (ex.: DF)
php artisan horizonte:fortnightly-feed --all --uf=DF
```

---

## Testes

```bash
php artisan test --filter='Horizonte|HorizonteSocial'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte (scoring v2, mapa, feed) | [HORIZONTE.md](HORIZONTE.md) §3, §5, §6, §9 |
| Variáveis SIDRA / mapa | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11b |
| Hub importação | [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §11 |
| Anterior | [RELEASE_20260603d_PROMETHEUS.md](RELEASE_20260603d_PROMETHEUS.md) |
