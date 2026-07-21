# Release `20260721-Aletheia` — ServLITCYS 8.0.0

**Data:** 2026-07-21 · **Ramo:** `main` · **Major:** **8.0.0** sobre **7.0.3** (Calliope).

**Aletheia** (mitologia grega): a verdade revelada — alinhada ao **Clio** como hub de relatórios da Matrícula inicial (indicadores Educacenso, AEE/AC, apontamentos e identidade visual).

---

## Resumo

1. **Relatório municipal Clio** — turmas e alunos por etapa/ano, composição curricular/AEE/atividade complementar, tabela por escola e apontamentos Acomp × Relações.
2. **Home `/clio`** — faixa de identidade, KPIs do exercício e cartões de relatório por município (tríade, erros, PDF).
3. **Parsers e análise** — agregados em Relação turma/aluno; totais AEE/AC no Acomp; INF-MAT / INF-TUR / INF-DELTA enriquecidos.

---

## Deploy

```bash
git fetch --tags && git checkout 20260721-Aletheia
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Após o deploy, nas coletas existentes: **Atualizar análise** para recalcular o relatório da rede.

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260721-Aletheia --product-version=8.0.0
php artisan product:release-publish 20260721-Aletheia --product-version=8.0.0
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Roadmap Educacenso 1ª etapa | [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) |
| Publicação de tags | [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md) |
| Anterior | [RELEASE_20260709_CALLIOPE.md](RELEASE_20260709_CALLIOPE.md) |
