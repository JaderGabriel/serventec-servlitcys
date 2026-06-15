# Release `20260615a-Mnemosyne` — ServLitcys 4.4.7

**Data:** 2026-06-15 · **Ramo:** `main` · **Figura:** *Mnemosyne* (memória e registo — toolkit Educacenso no painel RX).

## Resumo

Patch **4.4.7** sobre **4.4.6** ([RELEASE_20260609d_THEMIS.md](RELEASE_20260609d_THEMIS.md)):

### Painel RX — Educacenso 2026

- **Toolkit** na tela RX: calendário oficial (Portaria Inep nº 219/2026), dados necessários na 1ª etapa, regras de retificação e prévia da 2ª etapa.
- **Banner de prazo contextual**: fases do Censo (coleta, aguardando DOU, retificação, 2ª etapa) com contagem regressiva e barra da janela vigente.
- **Config** `rx.censo_calendar` com datas oficiais 2026 (referência 27/05, coleta até 31/07, DOU 27/08, retificação 30 dias).

## Deploy em produção

```bash
git fetch --tags
git checkout 20260615a-Mnemosyne
# ou: git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

## Verificação pós-deploy

```bash
php artisan test --filter=RxCensoDeadlineTest
```

Na UI:

1. `/dashboard/rx` — banner com fase **1ª etapa — Matrícula inicial** e dias até 31/07/2026.
2. Expandir **Toolkit Educacenso** — calendário, dados da 1ª etapa e retificação.
3. Confirmar links para fontes oficiais Inep.
