# Release `20260525-Apollo` — ServLitcys 3.0.0

**Data:** 2026-05-25 · **Ramo:** `main` · **Figura:** *Apollo* (clareza, transparência e serviço público — adequado ao marco de **conformidade LGPD**, comunicação com o usuário e consolidação da experiência de consultoria).

## Resumo

Marco **3.0.0** que consolida, sobre a base **2.4.0** (importações SAEB/FUNDEB — [RELEASE_20260524_CERES.md](RELEASE_20260524_CERES.md)), as entregas de **consultoria**, **inclusão NEE**, **desempenho SAEB**, **privacidade/LGPD**, **notificações** e **selo de versão** no rodapé. Inclui correção de responsividade da tela `/consentimento` em desktop.

## Destaques

### Conformidade e privacidade (LGPD)

- Política pública em `/privacidade` (`config/legal.php`, `LEGAL_PRIVACY_*`).
- Aceite versionado em `/consentimento` (middleware `legal.consent`), banner na welcome, colunas em `users`, auditoria em `legal_consent_logs`.
- Admin: `/admin/consentimentos-legais` (pendentes, versões vigentes, histórico).
- Layout de consentimento alinhado ao login (`auth-layout` modo `wide`, até `max-w-3xl` em desktop).

### Notificações

- Página `/notifications` e feed JSON `/notifications/feed` (sino na área autenticada).

### Consultoria e welcome

- Rodapé autenticado: versão, ambiente, município (perfis municipais), links (Perfil, Notificações, Documentação, Pulse, Suporte, Privacidade).
- Welcome: header com ícones (tema, entrar, WhatsApp).
- Home logada: 4 atalhos «Operação da plataforma»; legenda do mapa mental com ícones.
- RX: barra segmentada Censo (`censo-municipio-bar`).
- Selo `<x-product-version-badge />` (versão, tag de release, data — `ProductVersion`).

### Pedagógico — Inclusão (NEE)

- SQL unificado: `fisica_deficiencia` / `deficiencia` ou `aluno_deficiencia`; opção turma AEE (`IEDUCAR_INCLUSION_NEE_INCLUIR_TURMA_AEE`, default `true`).
- Gráfico **catálogo completo** de designações NEE (INEP / i-Educar, `includeZeros: true`); legenda INEP na view.
- Remoção do bloco duplicado «catálogo completo» nos medidores.

### Pedagógico — Desempenho (SAEB)

- Gráficos SAEB em grelha `xl:grid-cols-4`, modo compacto (`.perf-saeb-charts`).

### Herdado de 2.4.0 (sem regressão)

- `saeb:import-planilhas-inep`, FUNDEB/receita FNDE, ordem VAAF — ver [RELEASE_20260524_CERES.md](RELEASE_20260524_CERES.md).

## Deploy

```bash
git fetch --tags
git checkout 20260525-Apollo   # ou deploy de main após merge/tag
composer install --no-dev
php artisan migrate --force
php artisan route:clear
php artisan config:clear
npm run build
```

Usuárioes existentes sem versão de PP/cookies aceite são redirecionados para `/consentimento` na primeira visita autenticada (quando `LEGAL_REQUIRE_AUTHENTICATED_CONSENT=true`).

## Variáveis novas / relevantes

| Variável | Uso |
|----------|-----|
| `LEGAL_PRIVACY_VERSION` | Versão do documento de privacidade (reaceite obrigatório ao alterar) |
| `LEGAL_COOKIES_VERSION` | Versão da política de cookies essenciais |
| `LEGAL_REQUIRE_AUTHENTICATED_CONSENT` | Exige aceite na área logada (default `true`) |
| `LEGAL_PRIVACY_CONTACT_EMAIL` | Canal de contacto na política |
| `IEDUCAR_INCLUSION_NEE_INCLUIR_TURMA_AEE` | Incluir turma AEE no total NEE |

Ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) e `.env.example`.

## Migração

- `2026_05_25_140000_add_legal_consent_to_users_and_logs.php` — colunas de consentimento em `users` + `legal_consent_logs`.

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.0.0
- [STATUS_PROJETO.md](STATUS_PROJETO.md) — estado funcional
- [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md) — entregas 35–37

## Testes (referência)

- `LegalConsentTest`, `PrivacyPolicyTest`, `NotificationControllerTest`
- `InclusionNeeDesignacaoDatasetTest`, `InclusionNeeQueryAlignmentTest`
- `ProductVersionTest`, `UserFooterMunicipalityLabelTest`
