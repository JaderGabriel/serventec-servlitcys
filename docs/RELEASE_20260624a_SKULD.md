# Release `20260624a-Skuld` — ServLITCYS 5.7.7

**Data:** 2026-06-24 · **Ramo:** `main` · **Minor:** **5.7.7** sobre **5.7.6** (Saga).

**Skuld** (mitologia nórdica): uma das Nornas do futuro — alinhada à previsão FUNDEB do ano corrente e à leitura consultoria no modal Horizonte.

---

## Resumo

1. **Modal financeiro** — timeline FNDE (referência) → Tesouro CKAN (repasses) → exercício em curso (realizado × previsão); notas de consultoria; centavos; oculta verbas educação redundantes.
2. **SIDRA** — importa `populacao_total` além de 4–17; `--phase=sidra_demography --reset` para reprocessar UFs.
3. **Repasses nacional** — upsert em chunks (evita limite MySQL de placeholders); índice IBGE do catálogo mais rápido.
4. **Feed Horizonte** — `--reset` em fases isoladas SAEB/IBGE/SIDRA; hub CLI atualizado.
5. **Analytics** — controller fino (~276 linhas); `AnalyticsIndexAssembler`, `AnalyticsTabPartialDispatcher`, policies e chart queries partidas.
6. **Documentação** — hub visual com seções/ícones; [ANALISE_PADROES_LARAVEL.md](ANALISE_PADROES_LARAVEL.md) atualizado.

---

## Deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Pós-deploy recomendado:**

```bash
php artisan cache:clear
php artisan horizonte:fortnightly-feed --phase=sidra_demography --reset
# repetir até 27 UFs
php artisan horizonte:fortnightly-feed --phase=repasses_tesouro
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md) §6.5
