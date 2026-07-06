# Release `20260603c-Argus` — ServLitcys 5.2.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Marco:** **5.2** — Horizonte operacional + monitor de módulos.

---

## Resumo

Minor **5.2.0** sobre **5.1.0** (Prospeccao):

1. **Hub Horizonte** — painel `#horizonte-hub` em Dados públicos (`?hub=horizonte`), cobertura nacional, botão «Abastecer Horizonte» (POST `admin.public-data.horizonte-feed`).
2. **Monitor de módulos** — comando `module-monitor:collect` (agendamento diário 07:30), sondas estruturais, estados «Em repouso» / «Por avaliar».
3. **Horizonte — acesso** — `canViewHorizonte()`: administrador e usuário; perfil municipal recebe 403.
4. **Mapa Horizonte** — tooltip e botão fechar alinhados ao dashboard analítico.

---

## Deploy

```bash
git fetch --tags && git checkout 20260603c-Argus
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan module-monitor:collect   # opcional: primeira recolha de sondas
```

---

## Testes

```bash
php artisan test --filter='Horizonte|ModuleMonitor'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte | [HORIZONTE.md](HORIZONTE.md) |
| Hub importação | [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §11 |
| Monitor | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §3.3 |
| Anterior | [RELEASE_20260619b_PROSPECCAO.md](RELEASE_20260619b_PROSPECCAO.md) |
