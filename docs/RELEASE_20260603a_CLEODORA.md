# Release `20260603a-Cleodora` — ServLitcys 4.4.8

**Data:** 2026-06-03 · **Ramo:** `main` · **Figura:** *Cleodora* (conferência da declaração Educacenso × i-Educar).

## Resumo

Patch **4.4.8** sobre **4.4.7** ([RELEASE_20260615a_MNEMOSYNE.md](RELEASE_20260615a_MNEMOSYNE.md)):

### Conferência Educacenso — 1ª etapa (CEN-01)

- **Upload** do arquivo `.txt` do portal INEP na aba **Censo** (`work_done`).
- **Parser** pipe-delimited (registos 00–60), estatísticas e achados estruturais (`EDU-CEN-001…005`).
- **Cruzamento read-only** com i-Educar: INEP, matrículas por escola e rede (`EDU-CEN-101…502`).
- **Painel** com KPIs, gráficos, tabela por escola, export CSV e cache por usuário/município.
- **CLI** `censo:analyze-educacenso-file` e fixture de carga `tests/fixtures/educacenso/stage1_2026_load_test.txt` (~15 MB, 486k linhas).

## Deploy em produção

```bash
git fetch --tags
git checkout 20260603a-Cleodora
# ou: git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

## Verificação pós-deploy

```bash
php artisan test --filter=EducacensoFileReaderTest
php artisan censo:analyze-educacenso-file tests/fixtures/educacenso/stage1_2026_minimal.txt --city=ID --ano=2026
```

Na UI:

1. **Analytics → Censo** — seção **Conferência Educacenso**.
2. Upload do arquivo Educacenso com município e ano letivo selecionados.
3. Confirmar KPIs, gráficos e export CSV de achados.

### Teste de carga local

```bash
php tests/fixtures/educacenso/generate_load_test.php --schools=200 --matriculas=1200 --turmas=30
php artisan censo:analyze-educacenso-file tests/fixtures/educacenso/stage1_2026_load_test.txt --city=ID --ano=2026
```
