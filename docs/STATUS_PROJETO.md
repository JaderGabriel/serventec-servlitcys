# Estado do projeto — servlitcys

**Versão:** 2.0.1 · **Ramo:** `main` · **Última revisão:** maio/2026

Documento de referência rápida do que está implementado, em evolução e como navegar na documentação.

---

## Resumo executivo

| Área | Estado |
|------|--------|
| RBAC (admin / user / municipal) | Implementado |
| Painel de análise i-Educar (abas lazy) | Implementado |
| Discrepâncias + export CSV | Implementado |
| FUNDEB / VAAF (import + cascata de anos) | Implementado |
| Gestão de utilizadores (ativar / desativar / excluir) | Implementado |
| Pulse / monitorização | Implementado |
| CI/CD remoto | Planeado (ver DOCUMENTACAO_EXECUTIVA) |

---

## Perfis e acesso

| Perfil | Página inicial | Escopo |
|--------|----------------|--------|
| `admin` | `/dashboard` | Sistema completo |
| `user` | `/dashboard/analytics` | Todos os municípios `forAnalytics` |
| `municipal` | `/dashboard/analytics` (+ `city_id` se um só município) | Só `city_user` |

- Contas **`is_active = false`**: login bloqueado; sessão terminada pelo middleware `EnsureUserIsActive`.
- Admin em `/users`: **Desativar**, **Ativar**, **Excluir** (com proteção do último admin).
- Detalhe: [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) · [SEGURANCA.md](SEGURANCA.md)

---

## Painel de análise (`/dashboard/analytics`)

Abas: Visão Geral, Matrículas, Rede & Oferta, Unidades Escolares, Inclusão, Desempenho, Frequência, FUNDEB, Discrepâncias e Erros, Diagnóstico Geral.

| Funcionalidade | Notas |
|----------------|-------|
| Lazy load por aba | `ANALYTICS_LAZY_TABS` (default `true`) — ver [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) |
| Filtros i-Educar | Cidade, ano letivo, escola, curso, turno |
| Export discrepâncias | `GET /dashboard/analytics/discrepancies/export` — linhas por escola + agregado rede (`DiscrepanciesCsvRowsBuilder`) |
| `export_params` no snapshot | URL de export alinhada aos filtros da consulta |

---

## FUNDEB / VAAF

| Componente | Ficheiro / comando |
|------------|-------------------|
| Ordem de anos (cascata) | `FundebReferenceYearOrder` |
| Resolver municipal | `FundebMunicipalReferenceResolver` |
| Import API / ficheiro | `FundebOpenDataImportService`, `fundeb:import-api` |
| UI admin | Compatibilidade i-Educar → card FUNDEB |
| Config `.env` | `IEDUCAR_FUNDEB_JSON_URL`, `storage://`, `file://` |

Importação: **cache** `storage/app/fundeb/api/{ibge}/{ano}.json` → se ausente, **CKAN** → grava cache + BD. Exige `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` para preencher automaticamente (CKAN FNDE pode devolver HTML sem resource id). Ver [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md).

---

## Código — organização recomendada

| Camada | Convenção |
|--------|-----------|
| Controllers | Finos; autorização + orquestração |
| Form requests | Validação e `authorize()` |
| Repositories `app/Repositories/Ieducar/` | Consultas pesadas ao i-Educar |
| Support `app/Support/` | Regras de negócio reutilizáveis |
| Services `app/Services/` | Integrações (FUNDEB, INEP, geo) |
| Auth helpers | `UserCityAccess`, `AdminUserAuditLogger`, `UserSessionTerminator` |

---

## Testes

```bash
php artisan test
```

| Suíte | Cobertura recente |
|-------|-------------------|
| Feature | Auth, RBAC, analytics, gestão utilizadores |
| Unit | FUNDEB, `DiscrepanciesCsvRowsBuilder` |

Requer extensão `pdo_sqlite` para testes com `RefreshDatabase`.

---

## Ambiente

- **Um único `.env`** na raiz (não versionado; sem `.env.example` no repositório).
- Variáveis documentadas no [README.md](../README.md).
- Assets: `public/build/` versionados; `npm run build` após alterar `resources/js` ou `resources/css`.

---

## Mapa da documentação

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) | Visão para decisores |
| [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) | RBAC e operação |
| [SEGURANCA.md](SEGURANCA.md) | Senhas, autorização, checklist produção |
| [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) | CLI (geo, SAEB, FUNDEB, deploy) |
| [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) | VAAF e onda 1 inclusão |
| [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) | Performance por aba / Pulse |
| [DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md](DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md) | Roadmap pedagógico |
| [DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md](DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md) | Revisão técnica |

---

## Alterações recentes (lote maio/2026)

1. RBAC completo + redireccionamento por perfil (`homeRouteName`, `homeUrl`).
2. Municipal: entrada directa na Análise; auto-selecção de cidade única.
3. Discrepâncias: UI compacta; CSV com linhas agregadas; `export_params`.
4. FUNDEB: cascata de anos; import `storage://` / `file://`.
5. Utilizadores: desactivar / activar / excluir (admin); auditoria centralizada.
6. Refactor: `AdminUserAuditLogger`, `UserSessionTerminator`, `DiscrepanciesCsvRowsBuilder`.

---

*Manter este ficheiro actualizado em commits que alterem comportamento visível ou contratos de API/CLI.*
