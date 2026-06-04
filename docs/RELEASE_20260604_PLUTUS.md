# Release `20260604-Plutus` — ServLitcys 3.10.0

**Data:** 2026-06-04 · **Ramo:** `main` · **Figura:** *Plutus* (repasses FUNDEB, extrato público e conciliação financeira).

## Resumo

Marco **3.10.0** sobre **3.9.0** ([RELEASE_20260604_GAIA.md](RELEASE_20260604_GAIA.md)):

- **Três extratos FUNDEB** na importação `funding::import_transfers_city_year`: publicação Tesouro Transparente (XLS), SISWEB/REPASSES (espelho CKAN ou export), extrato BB (download automático).
- **Tempo Real — extrato simulado:** lançamentos por ciclo (fonte), resumo **mês/ano** com totalizador e comparativo vs. expectativa FUNDEB.
- **BB:** `BbExtratoCsvFetcher` descarrega CSV para `storage/app/funding/bb_extrato/{IBGE}_{ANO}.csv`; suporte a URL template, cache e upload manual.
- **Diagnóstico Geral:** índice de qualidade integrado no painel de decisão (executivo); remoção do bloco duplicado.

## Destaques

### Repasses (`MunicipalTransferImportService`)

| Serviço | Função |
|---------|--------|
| `TesouroFundebPublicacaoService` | Planilha anual FUNDEB (`thot-arquivos`) |
| `SiswebFundebRepassesService` | REPASSES / espelho CKAN municipal |
| `BbFundebExtratoService` | Extrato BB + `BbExtratoCsvFetcher` |
| `FundebExtratoFontePriority` | Evita somar a mesma fonte no total observado |

### Finanças → Tempo Real

| Componente | Função |
|------------|--------|
| `FundebExtratoVisualBuilder` | Ciclos, `by_period`, consolidado anual |
| `finance-realtime.blade.php` | UI tipo extrato com comparativo |

### CKAN mensal

- `TesouroTransferenciasCsvService` grava `meta.mensal` no import para detalhar meses no extrato simulado.

## Variáveis `.env` (novas)

Ver [BB_EXTRATO_OPEN_FINANCE.md](BB_EXTRATO_OPEN_FINANCE.md) e [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md):

- `IEDUCAR_FUNDEB_PUBLICACAO_ARQUIVO_{ANO}`, `IEDUCAR_SISWEB_*`, `IEDUCAR_BB_EXTRATO_URL_TEMPLATE`, `IEDUCAR_BB_EXTRATO_EXPORT_URL`, `IEDUCAR_BB_OPEN_FINANCE_*`

## Deploy

```bash
git fetch --tags
git checkout 20260604-Plutus   # ou `main` após este commit
composer install --no-dev
npm run build
php artisan config:clear
php artisan view:clear
# Importar repasses por município/ano (Admin → Dados públicos)
php artisan queue:work --queue=admin-sync --once   # se usar fila
```

Sem migrações novas nesta release.

## Documentação

- [BB_EXTRATO_OPEN_FINANCE.md](BB_EXTRATO_OPEN_FINANCE.md)
- [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) §3.4
- [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md)

## Testes

```bash
php artisan test --filter='FundebExtratoVisualBuilderTest|BbExtratoCsvFetcherTest|TesouroFundebPublicacaoServiceTest|MunicipalTransferImportServiceTest|FundebExtratoFontePriorityTest'
```

## Limitações

- Publicação STN (XLS): totais por **UF** na folha `M_TOTAL`, não por município.
- Open Finance BB: credenciais na UI; **consulta automática de transações ainda não implementada**.
