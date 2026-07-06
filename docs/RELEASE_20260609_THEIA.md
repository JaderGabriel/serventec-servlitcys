# Release `20260609-Theia` — ServLitcys 4.1.9

**Data:** 2026-06-09 · **Ramo:** `main` · **Figura:** *Theia* (visão clara — projeções fiáveis, fluxo legível e mapa territorial sem ambiguidade).

## Resumo

Patch **4.1.9** sobre **4.1.8** (Sophia — FUNDEB Censo lookback e admin i-Educar para leigos):

### Finanças — Tempo Real

- **`FinanceRealtimeYearEndOutlook`** — projeção até dezembro no card **Diferença** (risco, próximo repasse, sobras).
- Remoção do alerta enganoso «Repasse observado abaixo da expectativa» (comparação YTD vs meta anual em meio de ano).

### Dashboard — fluxo de integrações (ERP)

- Diagrama redesenhado no Início (`data-flow-erp-board`) com legendas, ícones por sistema e modal de ajuda alinhado.

### CadÚnico — mapa territorial

- Rótulos de distância ancorados à linha (`bindTooltip` no centro da polyline) — estáveis com zoom e pan.
- Escala de lacuna/pressão em três faixas: amarelo → laranja → vermelho.
- Escolas quase lotadas (≥90%) em **violeta**, distintas da pressão territorial.
- Legenda da sidebar atualizada.

## Deploy

```bash
git fetch --tags && git checkout 20260609-Theia
composer install --no-dev
npm ci && npm run build
php artisan view:clear
php artisan config:clear
```

## Testes

```bash
php artisan test --filter='FinanceRealtimeYearEndOutlookTest|FinanceRealtimeFundebServiceTest'
```

## Documentação

- `/dashboard/analytics` — Finanças Tempo Real (card Diferença com outlook)
- Início — diagrama ERP / fluxo de integrações
- Cadastro → CadÚnico — mapa territorial (camadas distância e legenda)
