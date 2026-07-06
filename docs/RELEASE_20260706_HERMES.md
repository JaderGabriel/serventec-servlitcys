# Release `20260706-Hermes` — ServLITCYS 7.0.2

**Data:** 2026-07-06 · **Ramo:** `main` · **Minor:** **7.0.2** sobre **7.0.1** (Moneta).

**Hermes** (mitologia grega): mensageiro dos deuses, da eloquência e da comunicação — alinhado à **unificação pt-BR** na interface, menus e documentação viva.

---

## Resumo

1. **Idioma pt-BR unificado** — mensagens da UI, PDFs, admin, Horizonte e `lang/pt_BR` (atualizar, seção, usuário, convênio, exato/efetivo).
2. **Documentação viva** — política em [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md); ~70 arquivos `docs/` alinhados; índice [ROADMAP_INDICE.md](ROADMAP_INDICE.md) e entregas julho/2026.
3. **Menus** — leitor `/admin/documentacao`: «Arquitetura», «Índice de roadmaps»; navegação já em pt-BR mantida.

Inclui também patches já integrados em `main` desde 7.0.1: hub Horizonte, checkpoint Educacenso, SICONFI 1 UF/exec., modal enriquecido (cards Finanças/Pedagogia/Social).

---

## Deploy

```bash
git fetch --tags && git checkout 20260706-Hermes
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Referências

| Tema | Doc |
|------|-----|
| Padrão editorial pt-BR | [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) §3 |
| Roadmaps e backlog | [ROADMAP_INDICE.md](ROADMAP_INDICE.md) |
| Anterior | [RELEASE_20260705b_MONETA.md](RELEASE_20260705b_MONETA.md) |
