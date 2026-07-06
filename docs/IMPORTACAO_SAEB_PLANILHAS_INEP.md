# Importação SAEB — planilhas oficiais INEP (sem Python)

**Comando:** `php artisan saeb:import-planilhas-inep`  
**Versão:** 3.0.0 (base importações 2.4.0) · **Release:** [RELEASE_20260525_APOLLO.md](RELEASE_20260525_APOLLO.md)  
**Relacionado:** [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §2 · [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §12 · [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md)

---

## 1. Porquê planilhas e não só microdados

| Fonte INEP | Identificador município | Uso recomendado |
|------------|-------------------------|-----------------|
| **Planilha de resultados** (aba *Municípios*) | `CO_MUNICIPIO` (IBGE 7 dígitos) | Séries municipais LP/MAT por etapa — **esta importação** |
| Microdados ZIP (`TS_ESCOLA`, etc.) | `ID_MUNICIPIO` mascarado (LGPD) | Agregação escola/rede; complemento via `saeb:sync-microdados` |

Para municípios cadastrados na plataforma, as planilhas são a fonte correta para médias municipais oficiais.

---

## 2. Pré-requisitos no servidor

| Requisito | Notas |
|-----------|--------|
| PHP 8.3+ | Extensões habituais do projeto |
| `composer install` | Pacote `phpoffice/phpspreadsheet` (leitura XLSX/XLS) |
| `unrar` **ou** `p7zip` | Obrigatório para o RAR de 2023 (`p7zip-full` / `unrar` no Debian/Ubuntu) |
| Cidades com `ibge_municipio` | 7 dígitos; só esses municípios entram no CSV canónico |
| `IEDUCAR_SAEB_SERIES_ENABLED=true` | SAEB activo |
| SSL INEP | Se `cURL 60`, correr `php artisan saeb:refresh-ca-bundle` antes |

URLs por ano em `config/ieducar.php` → `saeb.planilha_resultados_urls` (2021 XLSX, 2023 RAR). Cache: `storage/app/{IEDUCAR_SAEB_PLANILHA_CACHE_PATH}` (default `saeb/planilhas`).

---

## 3. Procedimento padrão (produção)

```bash
cd /caminho/para/servlitcys

# 1) Dependências (uma vez por deploy)
composer install --no-dev   # ou --no-dev conforme ambiente
# apt install unrar   # ou p7zip-full

# 2) Importar anos configurados (descarrega, converte, grava historico.json)
php artisan saeb:import-planilhas-inep --years=2021,2023

# 3) Reimportar sem voltar a descarregar (usa cache local)
php artisan saeb:import-planilhas-inep --years=2021,2023 --no-download

# 4) Substituir pontos existentes (em vez de fundir)
# php artisan saeb:import-planilhas-inep --years=2023 --no-merge
```

**Saída esperada:** mensagem de sucesso + linhas por ano (`:y — :n linha(s), :m município(s)`). Avisos (URL em falta, aba sem dados para um IBGE) aparecem como `warn`.

Persistência: `SaebCsvPedagogicalImportService` → `public/storage/saeb/historico.json` (e JSON por município se `IEDUCAR_SAEB_MUNICIPIO_JSON_FILES=true`).

---

## 4. Opções do comando

| Opção | Efeito |
|-------|--------|
| `--years=2021,2023` | Anos a processar (URLs em config). Vazio sem `--url` usa todas as chaves de `planilha_resultados_urls` |
| `--url=` | Arquivo ou URL única (XLSX, XLSB, RAR ou caminho em `storage/app/`) |
| `--year=` | Ano de referência com `--url` |
| `--no-download` | Não descarrega; usa arquivos em cache |
| `--no-merge` | Substitui pontos SAEB em vez de fundir |
| `--no-resolve-inep` | Não mapeia INEP→`cod_escola` |
| `--keep-cache` | Mantém pastas extraídas do RAR |

---

## 5. Arquivo local ou URL extra

```bash
php artisan saeb:import-planilhas-inep \
  --url=/caminho/planilha_de_resultados_2023.rar \
  --year=2023

php artisan saeb:import-planilhas-inep \
  --url=storage/app/saeb/planilhas/saeb_planilha_2021.xlsx \
  --year=2021 \
  --no-download
```

---

## 6. Variáveis de ambiente

| Variável | Default | Descrição |
|----------|---------|-----------|
| `IEDUCAR_SAEB_PLANILHA_CACHE_PATH` | `saeb/planilhas` | Pasta sob `storage/app/` |
| `IEDUCAR_SAEB_PLANILHA_DEPENDENCIA` | `Municipal` | Linha preferida na aba *Municípios* (`DEPENDENCIA_ADM`) |

Ver `.env.example` e [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md).

---

## 7. Pipeline interno (referência)

```mermaid
flowchart LR
  A[URL INEP ou cache] --> B{RAR?}
  B -->|sim| C[unrar / 7z]
  B -->|não| D[XLSX/XLSB]
  C --> D
  D --> E[PhpSpreadsheet aba Municípios]
  E --> F[CSV canónico IBGE]
  F --> G[saeb historico.json]
```

| Classe | Função |
|--------|--------|
| `SaebPlanilhaInepImportService` | Orquestra anos, merge, import CSV |
| `SaebPlanilhaInepArchive` | Extrai RAR, escolhe melhor XLSX |
| `SaebPlanilhaInepSpreadsheetResolver` | XLSB → leitura compatível |
| `SaebPlanilhaInepConverter` | `CO_MUNICIPIO` + colunas `MEDIA_*` → CSV |
| `SaebMicrodadosInepDownloader` | Download HTTPS (reutilizado) |

Colunas canónicas: `municipio_ibge;ano_aplicacao;disciplina;etapa;valor;status;inep_escola`.

---

## 8. Ordem no hub de dados públicos

1. Geo / Censo (se ainda não indexado) — ver [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §6  
2. **FUNDEB** — `fundeb:import-api` / receita CSV  
3. **SAEB planilhas** — `saeb:import-planilhas-inep` (municipal IBGE)  
4. Opcional — `saeb:sync-microdados` (complemento escola/rede)

---

## 9. Resolução de problemas

| Sintoma | Acção |
|---------|--------|
| `Nenhuma planilha XLSX/XLSB dentro do RAR` | Instalar `unrar` ou `p7zip`; ou extrair manualmente e usar `--url` no XLSX |
| `cURL error 60` | `php artisan saeb:refresh-ca-bundle` |
| `Nenhuma linha gerada` | Confirmar IBGE das cidades; verificar `IEDUCAR_SAEB_PLANILHA_DEPENDENCIA` (tentar `Total` se necessário) |
| `Aba Municípios não encontrada` | Arquivo não é planilha de resultados INEP esperada |
| Ano sem URL | Adicionar entrada em `planilha_resultados_urls` no config ou `.env` via deploy |

Teste unitário: `tests/Unit/SaebPlanilhaInepConverterTest.php`.
