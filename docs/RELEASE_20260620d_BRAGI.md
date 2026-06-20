# Release `20260620d-Bragi` — ServLITCYS 5.7.3

**Data:** 2026-06-20 · **Ramo:** `main` · **Minor:** **5.7.3** sobre **5.7.2** (Forseti).

> Quinta release do dia 20/06 — sufixo **`d`** (`ProductReleaseTag`). Anterior: [RELEASE_20260620c_FORSETI.md](RELEASE_20260620c_FORSETI.md).

**Bragi** (mitologia nórdica): deus da eloquência e das histórias — alinhado ao **tour guiado** do Horizonte, demonstração animada e uniformização de textos da UI em pt-BR.

---

## Resumo

1. **Horizonte — mapa e recorte** — cliques em bolhas UF e UFs prioritárias; selector «Recorte» com 27 estados; filtros só no dock lateral; tour in-app na 1.ª visita; demonstração animada «Como usar»; KPIs sem vazamento entre cargas; formatação financeira (FUNDEB em R$).
2. **Sync BR em screen** — runner com `setsid`, `SCREENDIR` em `storage/screen/`, reinício após falha, detecção de sessão corrigida e aviso `loginctl enable-linger`.
3. **UI pt-BR** — menu «Usuários» (antes «Utilizadores»), login, LGPD, início e telas admin alinhados a `/users`.

---

## Deploy

```bash
git fetch --tags && git checkout 20260620d-Bragi
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan cache:clear
```

**Sync BR (produção):**

```bash
loginctl enable-linger $(whoami)   # uma vez
./scripts/horizonte-sync-br-screen.sh start
./scripts/horizonte-sync-br-screen.sh status
```

---

## Testes

```bash
php artisan test --filter='HorizonteMapPresenter|ProductVersion'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte (mapa, tour, sync) | [HORIZONTE.md](HORIZONTE.md) §9.1b |
| Anterior | [RELEASE_20260620c_FORSETI.md](RELEASE_20260620c_FORSETI.md) |
