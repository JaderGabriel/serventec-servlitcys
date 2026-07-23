# Publicação de releases — tag Git + GitHub Release

**Versão do produto:** 8.0.2 · **Última revisão:** 2026-07-23

> **Índice:** [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) · [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) §6

Fluxo único para que **tag Git**, **nota `RELEASE_*.md`**, **`config/documentation.php`** e **GitHub Release** permaneçam alinhados.

---

## Convenção

| Item | Formato | Exemplo |
|------|---------|---------|
| Tag Git | `YYYYMMDD[-letra]-Codename` | `20260709-Calliope` |
| Arquivo | `docs/RELEASE_YYYYMMDD[_letra]_CODENAME.md` | `docs/RELEASE_20260709_CALLIOPE.md` |
| Versão | `MAJOR.VERSÃO.MINOR` | `7.0.3` |
| Codename | mitologia (alusão às melhorias) | Calliope — eloquência / docs |

Implementação: `App\Support\Product\ProductReleaseTag`.

---

## Checklist antes de publicar

1. [ ] Funcionalidade em `main` e testes relevantes passando
2. [ ] Criar `docs/RELEASE_YYYYMMDD_CODENAME.md` (resumo, deploy, referências)
3. [ ] Atualizar `config/documentation.php`:
   - `product.version`
   - `product.release_tag`
   - `product.commit_short` (hash do commit de release)
   - `product.commit_number` (`git rev-list --count HEAD`)
   - `product.revision_date`
4. [ ] Atualizar [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) (cabeçalho ▶ + linha timeline)
5. [ ] Atualizar [STATUS_PROJETO.md](STATUS_PROJETO.md), [README.md](../README.md), [HUB_DOCUMENTACAO.md](HUB_DOCUMENTACAO.md)
6. [ ] Entregas do mês em [ENTREGAS_ESCALONADAS_JULHO_2026.md](ENTREGAS_ESCALONADAS_JULHO_2026.md) (se aplicável)
7. [ ] `git commit` com mensagem de release
8. [ ] Verificar e publicar (comandos abaixo)

---

## Comandos

### 1. Verificar alinhamento

```bash
php artisan product:release-status 20260723b-Harmonia --product-version=8.0.2
```

Mostra: nota RELEASE, config, tag local/remota, GitHub Release.

> Use `--product-version=` (não `--version=`, reservado pelo Artisan para exibir a versão do framework).

### 2. Publicar tag + GitHub Release

```bash
php artisan product:release-publish 20260723b-Harmonia --product-version=8.0.2
```

- Cria **tag anotada** em `HEAD`
- `git push origin TAG`
- `gh release create` com `--notes-file` da nota RELEASE

Opções:

| Opção | Uso |
|-------|-----|
| `--dry-run` | Só exibe o plano |
| `--no-push` | Tag local sem push |
| `--no-github` | Só tag Git (sem `gh release`) |
| `--title=` | Título customizado no GitHub |

### 3. Exemplo manual (legado)

```bash
git tag -a 20260723b-Harmonia -m "ServLitcys 8.0.2 — Harmonia"
git push origin 20260723b-Harmonia
gh release create 20260723b-Harmonia \
  --title "ServLitcys 8.0.2 — 20260723b-Harmonia" \
  --notes-file docs/RELEASE_20260723b_HARMONIA.md
```

Prefira `product:release-publish` para evitar drift entre tag e GitHub.

---

## Após publicar

```bash
php artisan product:release-status 20260723b-Harmonia --product-version=8.0.2
gh release view 20260723b-Harmonia
```

Deploy no servidor: `git fetch --tags && git checkout TAG`.

---

*Comandos registrados em `ArtisanCommandsCatalog` · testes: `ProductReleaseTagTest`.*
