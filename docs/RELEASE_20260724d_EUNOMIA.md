# Release `20260724d-Eunomia` — ServLITCYS 8.2.1

**Data:** 2026-07-24 · **Ramo:** `main` · **Versão (3.º segmento):** **8.2.1** sobre **8.2.0** (Hygieia).

**Eunomia** (mitologia grega): deusa da boa ordem e da disciplina cívica — estabilização de qualidade, segurança, performance e documentação após Hygieia.

---

## Resumo

Versão **8.2.1** — bump do **3.º segmento** (*minor* / patch de estabilização sobre a linha 8.2):

1. **Qualidade (Fase A)** — `composer test` via `scripts/php-with-sqlite.sh`; consentimento legal desligado por defeito nos testes; CI PHPUnit (PHP 8.3/8.4); coverage clover opcional.
2. **Segurança (Fase B)** — `SafeOutboundUrl` (SAEB/pedagógico, DNS fail-closed); throttle+log em `/relatorio`; uploads Clio/CadÚnico tipados; sessão e `User` fillable endurecidos.
3. **Performance (Fase C)** — Vite code-split; Redis/`DB_PERSISTENT` documentados; worker `default,admin-sync,clio` (`--timeout=1200`); cache Rede & Oferta; Pulse Redis + checklist InnoDB.
4. **Documentação (Fase D)** — PERFORMANCE / IMPLANTAÇÃO / VARIÁVEIS / SEGURANÇA / README na linha **8.2**; fingerprint Horizonte; fila Clio; tipografia administrativa.

Plano: [ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md](ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md) (fases A–D).

---

## Deploy

```bash
git fetch --tags && git checkout 20260724d-Eunomia
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Worker (Supervisor), se ainda não estiver alinhado:

```bash
php artisan queue:work --queue=default,admin-sync,clio --timeout=1200 --sleep=1 --tries=3
```

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260724d-Eunomia --product-version=8.2.1
php artisan product:release-publish 20260724d-Eunomia --product-version=8.2.1
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Plano A–E | [ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md](ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md) |
| Segurança | [SEGURANCA.md](SEGURANCA.md) |
| Performance / implantação | [PERFORMANCE.md](PERFORMANCE.md) · [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) |
| Anterior | [RELEASE_20260724c_HYGIEIA.md](RELEASE_20260724c_HYGIEIA.md) |
