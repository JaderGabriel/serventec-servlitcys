# Release `20260620b-Sleipnir` — ServLITCYS 5.7.1

**Data:** 2026-06-20 · **Ramo:** `main` · **Minor:** **5.7.1** sobre **5.7.0** (Metis).

> Terceira release do dia 20/06 — sufixo **`b`** após `20260620-Urania` e `20260620a-Metis` (`ProductReleaseTag`). Anterior: [RELEASE_20260620a_METIS.md](RELEASE_20260620a_METIS.md).

**Sleipnir** (mitologia nórdica): cavalo de oito patas de Odin, capaz de atravessar os nove mundos sem parar — alinhado ao **loop nacional Horizonte** que corre em **GNU screen** até concluir todas as fases, mesmo após fechar SSH.

---

## Resumo

Versão **5.7.1** — bump do **3.º segmento** (*minor* / ajuste incremental sobre o marco 5.7):

1. **Sync BR em screen** — `scripts/horizonte-sync-br-screen.sh` (`start` / `attach` / `status` / `stop`); sessão detached que sobrevive a desligamento SSH; documentado em [HORIZONTE.md](HORIZONTE.md) §9.1b.
2. **Convenção de versões** — `MAJOR.VERSÃO.MINOR` documentada (1.º / 2.º / 3.º segmento ≠ SemVer npm); exemplos em [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md), [HUB_DOCUMENTACAO.md](HUB_DOCUMENTACAO.md), [ARQUITETURA_E_FLUXOS.md](ARQUITETURA_E_FLUXOS.md).
3. **Codenames mitológicos** — além de greco-romano, passam a constar **nórdico** e **asteca** na convenção; codename deve aludir às melhorias (`ProductReleaseTag`, [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) §6).

---

## Deploy

```bash
git fetch --tags && git checkout 20260620b-Sleipnir
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Sem rebuild de assets (`npm run build`) — release só docs + scripts operacionais.

### Pós-deploy (abastecimento Horizonte)

```bash
# Iniciar sync nacional em screen (recomendado em produção)
./scripts/horizonte-sync-br-screen.sh start
./scripts/horizonte-sync-br-screen.sh status
tail -f storage/logs/horizonte-sync-br-nohup.log
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte — sync BR e screen | [HORIZONTE.md](HORIZONTE.md) §9.1b |
| Convenção versões e tags | [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) § convenção |
| Anterior (marco 5.7) | [RELEASE_20260620a_METIS.md](RELEASE_20260620a_METIS.md) |
