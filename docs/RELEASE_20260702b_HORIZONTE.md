# Release `20260702b-Horizonte` — ServLITCYS 6.3.0

**Data:** 2026-07-02 · **Ramo:** `main` · **Marco:** **6.3.0** sobre **6.2.0** (Educacenso).

Segunda release do dia **02/07/2026** (tag com sufixo **`b`**).

---

## Resumo

Refinamento **visual e de leitura** do **modal municipal Horizonte** — cabeçalho fixo, finanças em colunas, textos pt-BR e metadados geográficos no topo.

### Cabeçalho fixo (chrome)

- Modal em **overlay dedicado** (`x-teleport` no `body`) com **scroll só no corpo**; bloqueio de rolagem da página.
- Janela **~48rem × até 88% da viewport** (antes quase fullscreen).
- **Nome** e **UF por extenso** com tipografia maior; **mesorregião**, **microrregião** e **região imediata** (catálogo IBGE).
- **IBGE**, **SAEB LP/MAT** e chip **«Posição indicativa»** quando `coord_approximate`.
- **Propensão** em roda circular (% + Alta/Média/Baixa) — sem rótulo «Propensão» nem benefício estimado.

### Finanças (corpo rolável)

- **Ano anterior** e **ano vigente** em **linhas separadas**, cada uma com **duas colunas**: *Previsto na portaria* | *Pago pelo Tesouro*.
- Composição FUNDEB **só do ente municipal** na portaria (matrículas base FNDE + complementações federais deste município).
- Textos pt-BR: **Teto** (não «Tecto»), **planejamento**, fórmula **Saldo = A − B** em «Ainda a receber».
- Notas financeiras enxutas; removidos avisos redundantes sobre divergência portaria/Tesouro.

### Meta, gráfico e pipeline

- Faixa **Fontes · SGE** centrada em pílulas (sem benefício estimado).
- Gráfico Educacenso: **pontos e tooltip** mantidos; **rótulos numéricos nas linhas** removidos.
- Card municipal (pendências + pipeline) separado do gráfico; contadores por etapa abaixo da legenda (herdado de 6.2.0).

### Backend (regiões IBGE)

- `IbgeMunicipalityCatalog` expõe `micro_name` e `regiao_imediata_name` nos marcadores Horizonte.
- Após deploy, **atualizar catálogo da UF** (ou limpar cache `ibge_municipality_catalog_uf:v2:*`) para preencher microrregião/região imediata em municípios já cacheados.

---

## Deploy

```bash
git pull origin main
git checkout 20260702b-Horizonte   # tag de deploy (opcional)
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Pós-deploy recomendado (regiões IBGE no cabeçalho):**

```bash
# Por UF em uso no Horizonte — repopula micro/região imediata no catálogo
php artisan tinker --execute="app(\App\Support\Brazil\IbgeMunicipalityCatalog::class)->forgetUfCache('BA');"
# ou invalidar cache Redis/file conforme ambiente
php artisan cache:clear
```

---

## Patches pós-tag (integrados em 6.5.0)

Entregues **após** a tag `20260702b-Horizonte` e consolidados na release [RELEASE_20260702c_JORD.md](RELEASE_20260702c_JORD.md) (**6.5.0**):

| Commit | Entrega |
|--------|---------|
| `330cfcd` | Malha municipal IBGE nacional (`horizonte:import-municipal-geo --all`), área km², fase `ibge_municipal_geo`, overlay microrregiões |
| `ca837de` | Modo mapa **Contornos** (polígonos municipais); chip geográfico no modal |
| `d5bdbb9` | Pílulas separadas: posição, distância à capital, área (ícones e cores) |
| `4c420f8` | Botão **copiar coordenadas** em formato decimal para mapas externos |

**Pós-deploy (malha + área):**

```bash
php artisan migrate --force
php artisan horizonte:import-municipal-geo --all   # ~27 UFs; ver logs por estado
# Educacenso multi-ano (gráfico modal):
php artisan horizonte:sync-educacenso --reset --all
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md) §6.5–§6.9
- Release anterior no mesmo dia: [RELEASE_20260702_EDUCACENSO.md](RELEASE_20260702_EDUCACENSO.md) (**6.2.0**)
