# Release `20260528-Eos` — ServLitcys 3.3.0

**Data:** 2026-05-28 · **Ramo:** `main` · **Figura:** *Eos* (aurora — visibilidade operacional e acesso alargado à documentação).

## Resumo

Marco **3.3.0** sobre **3.2.0** ([RELEASE_20260527_NOTUS.md](RELEASE_20260527_NOTUS.md)): **monitor de módulos** admin (saúde por área, falhas e lentidões via Pulse + filas) e **acesso operacional** para perfil Usuário/Municipal (documentação, filas próprias e exportação NEE).

## Destaques

### Admin — monitor de módulos

- Página `/admin/monitor-modulos` com saúde global (fila, failed jobs, falhas sync).
- Cartões por módulo (consultoria, sincronização, infra) com semáforo e métricas Pulse.
- Histórico unificado de falhas e lentidões (24 h / 7 dias).

### Acesso — usuário e municipal

- Leitor de documentação em `/documentacao` (índice temático, sem docs de deploy admin).
- Filas em `/filas` (apenas tarefas do próprio usuário).
- Exportação NEE detalhada na aba Inclusão (CSV/Excel imediato ou fila).

### Documentação

- Índice `docs/README.md` reorganizado por percurso lógico do sistema.
- `PERFIS_UTILIZADOR.md` atualizado com novas permissões.

## Deploy

```bash
git fetch --tags
git checkout 20260528-Eos   # ou deploy de main após tag
composer install --no-dev
php artisan config:clear
php artisan cache:clear
npm run build
```

Sem novas migrações obrigatórias nesta release.

## Patch pós-release (sem bump de versão)

| Commit | Resumo |
|--------|--------|
| `504d2f9` | Monitor de módulos — visual alinhado ao design system `serv-*`. |
| `d6a1785` | Monitor — cartões exibem só saúde/funcionamento (sem atalhos). |
| `f29b30b` | RX (`/dashboard/rx`) — painel «Legendas e cores», guia completo das colunas, KPIs `serv-home-kpi`, comparativo em sky. |
**Pós-deploy (RX):** `npm run build` (classes `serv-rx-*`).

**Analytics (performance):** consolidado na release **3.3.1** — [RELEASE_20260529_HELIOS.md](RELEASE_20260529_HELIOS.md).

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.3.0
- [STATUS_PROJETO.md](STATUS_PROJETO.md)
