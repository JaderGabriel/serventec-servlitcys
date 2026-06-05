# Release `20260608-Sophia` — ServLitcys 4.1.8

**Data:** 2026-06-08 · **Ramo:** `main` · **Figura:** *Sophia* (sabedoria prática — FUNDEB fiável e telas compreensíveis para gestores).

## Resumo

Patch **4.1.8** sobre **4.1.7** (Phronesis — portarias FNDE e lexicon consolidado/projeção):

### FUNDEB — VAAT, matrículas e Censo INEP

- **`FundebOpenDataImportService`** — grava VAAT da portaria; corrige namespace `IeducarFilterState` no cálculo de matrículas no import.
- **`FundebMatriculasByYearService`** — lookback Censo INEP (`IEDUCAR_FUNDEB_CENSO_MATRICULAS_LOOKBACK`, padrão 3) com `censo_ano_usado` exposto no diagnóstico.
- **`fundeb:diagnose-matriculas`** — exibe ano Censo efectivo, erro i-Educar e dica operacional pós-indexação.
- [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) §6.3 — fluxo completo: diagnóstico → indexar Censo → reimportar VAAF.

### Admin — compatibilidade i-Educar para leigos

- Guia expansível com glossário (VAAF, VAAT, exercício, probe, fila, piso).
- Tabela de fontes de dados e passos recomendados.
- Legendas da coluna «Fonte» e hints no formulário de probe.
- Textos centralizados em `lang/pt_BR/admin_ieducar_compatibility.php`.

### Dashboard — fluxo de dados / ERP

- Diagrama de integrações no Início com legendas e ícones por sistema.
- `AdminSystemFlowStatus` alinhado ao painel ERP.

## Deploy

```bash
git fetch --tags && git checkout 20260608-Sophia
composer install --no-dev
php artisan view:clear
php artisan config:clear
```

### Pós-deploy FUNDEB (recomendado)

```bash
php artisan fundeb:diagnose-matriculas --anos=2024,2025,2026
php artisan app:import-inep-microdados-cadastro-escolas-geo --fetch=1
php artisan fundeb:import-api 0 --all --from=2025 --to=2025 --nearest
```

Variável nova: `IEDUCAR_FUNDEB_CENSO_MATRICULAS_LOOKBACK` (padrão `3`).

## Testes

```bash
php artisan test --filter='FundebOpenDataImportServiceTest|FundebOfficialSourcesServiceTest|AdminSystemFlowStatusTest'
```

## Documentação

- [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) §6.2–6.3 — diagnóstico e matrículas Censo
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §4 — `fundeb:diagnose-matriculas`
- `/admin/ieducar-compatibility` — guia para gestores
