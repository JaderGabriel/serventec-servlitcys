# Release `20260724b-Asclepius` — ServLITCYS 8.1.0

**Data:** 2026-07-24 · **Ramo:** `main` · **Versão (2.º segmento):** **8.1.0** sobre **8.0.4** (Theia).

**Asclepius** (mitologia grega): deus da medicina — quadro **Diagnóstico Geral** da rede e PDF do gestor com leitura clínica da coleta.

---

## Resumo

Versão **8.1.0** — bump do **2.º segmento** (*versão* / marco funcional sobre a linha 8.x):

1. **Diagnóstico Geral** — escolas ativas × erros/avisos (inclui Cor/Raça sem declaração e sem lançamento); totalizador; PDF detalhado + gerencial; aba Excel.
2. **PDF do gestor** — export gerencial (KPIs BI, insights sem `error`, etapas, série municipal, tempo escolar); menu Downloads.
3. **Home Clio** — slider no card municipal (cobertura ↔ série histórica de matrículas via Horizonte).
4. **Tempo escolar semanal** — CH ponderada por alunos e segmentos (infantil, Fund. I/II, Médio, EJA, Profissional).
5. **Jornada** — turnos canónicos + detalhe de «Outros»; carga horária em **faixas pedagógicas** (≤14 / 15–19 / 20–24 / 25–34 / ≥35 / N/I) com valores exactos.
6. **Testes** — alinhamento jornada/demografia; unitários Diagnóstico Geral e SchoolTime (smoke).

---

## Deploy

```bash
git fetch --tags && git checkout 20260724b-Asclepius
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Se o data mart ainda não estiver populado nas coletas analisadas:

```bash
php artisan bi:refresh-clio-campaigns --all --year=2026
```

Ou **Insights** → **Actualizar dataset**. Reanalisar coletas para refrescar `INF-JOR` com as faixas novas.

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260724b-Asclepius --product-version=8.1.0
php artisan product:release-publish 20260724b-Asclepius --product-version=8.1.0
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Roadmap Clio | [ROADMAP_CLIO.md](ROADMAP_CLIO.md) |
| Power BI / mart | [POWERBI.md](POWERBI.md) |
| Anterior | [RELEASE_20260724a_THEIA.md](RELEASE_20260724a_THEIA.md) |
