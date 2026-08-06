# Release `20260806a-Polyhymnia` — ServLITCYS 9.0.3

**Data:** 2026-08-06 · **Ramo:** `main` · **Versão (3.º segmento):** **9.0.3** sobre **9.0.2** (Erato).

**Polyhymnia** (Πολυύμνια): musa da poesia sagrada e da meditação — alusão à leitura pedagógica (SAEB/IDEB) e à série histórica no Horizonte.

---

## Resumo

Versão **9.0.3** — bump do **3.º segmento** (*minor*):

1. **Consultoria → Pedagógico → Desempenho** — SAEB e IDEB em secções separadas (SAEB primeiro; IDEB a seguir), com navegação de fluxo e gráficos `ideb_charts` distintos.
2. **Horizonte — modal municipal** — tons de cor distintos por ano nos chips SAEB/IDEB (paleta de 6 tons; deixa de repetir a cor a partir do 2.º ano).
3. **Horizonte — Pedagogia e escala** — gráfico SVG com série histórica SAEB (LP/MAT, eixo esquerdo) e IDEB (eixo direito) no mesmo painel; séries alargadas até 8 anos no mapa.

---

## Deploy

```bash
git fetch --tags && git checkout 20260806a-Polyhymnia
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Sem migrações novas nesta release.

### Testar

1. **Consultoria** → Pedagógico → **Desempenho** — secções SAEB e IDEB em linhas distintas.
2. **Horizonte** → abrir município com SAEB/IDEB — chips por ano com cores diferentes; card **Pedagogia e escala** com gráfico combinado.

---

## Referências

- `resources/views/dashboard/analytics/partials/performance.blade.php`
- `app/Support/Ieducar/PerformanceSaebSeries.php`
- `resources/js/horizonteMap.js` · `resources/css/horizonte.css`
- `app/Services/Horizonte/HorizonteMapService.php`
