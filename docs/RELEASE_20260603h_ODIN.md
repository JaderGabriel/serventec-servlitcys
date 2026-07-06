# Release `20260603h-Odin` — ServLITCYS 6.0.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Versão:** **6.0.0** sobre **5.8.0** (Thor).

**Odin** (mitologia nórdica): visão ampla e sabedoria — marco **6.x** com identidade visual que integra consultoria, gráficos e o mapa **Horizonte**, além de refinamentos de UX no GIS e no hub de dados públicos.

---

## Resumo

1. **Nova marca** — logótipo e favicon com gráfico de barras (consultoria), livro (formação) e linha do horizonte com pin territorial (Horizonte GIS); tagline atualizada em auth, PDF e landing.
2. **Horizonte — barra de comando fixa** — recorte UF/município em destaque no topo do mapa; KPIs compactos colapsados; «Ver indicadores» expande hero, FUNDEB e segmentos.
3. **Horizonte — resumo UF inline** — botão `UF — Resumo` no cabeçalho do mapa; painel no fluxo (sem modal); funciona em tela inteira.
4. **Horizonte — desenhar todos** — quando há truncagem regional, botão «Desenhar todos» repõe o limite de renderização e desenha todos os municípios do recorte.
5. **Horizonte — modal municipal** — overlay centrado (`fixed`), fecha painéis anteriores; partial na raiz Alpine para evitar cortes por `overflow`.
6. **Dados públicos — notificação clara** — contagens separadas (novo, atenção, alinhado); corpo da notificação e flash agrupam o que mudou vs. o que está conforme; tabela de achados no painel admin.

---

## Deploy

```bash
git pull origin main
git checkout 20260603h-Odin   # ou tag equivalente no servidor
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Pós-deploy recomendado:**

```bash
php artisan cache:clear
php artisan public-data:daily-check --notify
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md)
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md)
