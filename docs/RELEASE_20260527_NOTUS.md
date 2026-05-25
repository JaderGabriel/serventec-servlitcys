# Release `20260527-Notus` — ServLitcys 3.2.0

**Data:** 2026-05-27 · **Ramo:** `main` · **Figura:** *Notus* (vento do sul — exportação NEE e filas admin com visibilidade operacional).

## Resumo

Marco **3.2.0** sobre **3.1.0** ([RELEASE_20260526_BOREAS.md](RELEASE_20260526_BOREAS.md)): corrige **exportação NEE** (CSV/Excel com dados alinhados ao painel), reforça **medidores e impacto financeiro** na aba Inclusão e renova a **fila de processamento** admin com cards temáticos e downloads clicáveis.

## Destaques

### Inclusão — exportação NEE (admin)

- Query de exportação alinhada a `fetchNeeMatriculasComTurmaCurso` (mesmo total do painel).
- Joins de escola e nome do aluno opcionais (LEFT JOIN) — não abortam nem esvaziam o ficheiro.

### Inclusão — indicadores e FUNDEB

- Medidores e grupos NEE incluem barra «sem designação no cadastro»; fallback de coluna `nome`/`descricao` em deficiências.
- Bloco de **risco financeiro**: turma AEE sem deficiência no cadastro (perda estimada e ganho potencial com registo).

### Admin — fila de processamento

- Cards temáticos por área (FUNDEB, geo, pedagógico, i-Educar, sistema, PDF) com contagens e filtros.
- Tarefas em cards com contexto (resumo + hints do payload).
- Botão de **download com ícone** para exportações concluídas e PDFs prontos.

## Deploy

```bash
git fetch --tags
git checkout 20260527-Notus   # ou deploy de main após tag
composer install --no-dev
php artisan config:clear
php artisan cache:clear
npm run build
```

Sem novas migrações obrigatórias nesta release.

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.2.0
- [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md) — secção 44
- [STATUS_PROJETO.md](STATUS_PROJETO.md)
