# Release `20260607b-Peitho` — ServLitcys 4.4.1

**Data:** 2026-06-07 · **Ramo:** `main` · **Figura:** *Peitho* (persuasão — rodapé com créditos e documentação acessível).

## Resumo

Patch **4.4.1** sobre **4.4.0** ([RELEASE_20260607a_ANANKE.md](RELEASE_20260607a_ANANKE.md)):

### Documentação no produto e no GitHub

- **`docs/HUB_DOCUMENTACAO.md`** — hub visual versionado; entrada no menu **Começar** do leitor.
- **Mermaid** no leitor `/admin/documentacao` e `/documentacao` (diagramas em `HUB`, `ARQUITETURA_E_FLUXOS`, etc.).
- **`canvases/documentacao-hub.canvas.tsx`** no repositório (exploração no Cursor IDE).

### Rodapé — área autenticada

- Substitui «Uso restrito a usuárioes autenticados.» por **Desenvolvimento** (perfil GitHub) e link **GitHub** (repositório `config/documentation.php`).
- Mesma linha de créditos na variante **Pulse**.

## Deploy

```bash
git fetch --tags && git checkout 20260607b-Peitho
composer install --no-dev
php artisan migrate --force
php artisan view:clear
php artisan config:clear
```

## Documentação

- [HUB_DOCUMENTACAO.md](HUB_DOCUMENTACAO.md)
- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
