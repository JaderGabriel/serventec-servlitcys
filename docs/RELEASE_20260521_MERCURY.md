# Release `20260521-Mercury` — ServLitcys 2.3.8

**Data:** 2026-05-21 · **Commit:** `3c935ca` (#182) · **Figura:** *Mercury* (comunicação e ligação entre município, equipe e plataforma — contatos, perfil e leitura operacional no RX).

## Resumo

Entrega **2.3.8** no ramo `main`: VAAF municipal unificado nos cálculos da consultoria, contatos de referência por município e por usuário, painel RX com nomenclatura mais clara, admin de compatibilidade i-Educar enriquecido e perfil do usuário redesenhado. Textos das novas telas revisados para **pt-BR**.

## Destaques

### Consultoria — VAAF e FUNDEB

- `FundebMunicipalReferenceResolver::vaafParaCalculo()` como referência única em discrepâncias, sinais operacionais, matrículas/FUNDEB e projeções.
- Diagnóstico municipal e abas analytics com contexto de município alargado (`enrollmentFundebLines`, `funding_reference`).
- Seletor de município exibe **IBGE** em vez do motor de base de dados.

### Contatos

- **Cidades:** `contact_name`, `contact_phone`, `contact_whatsapp`, `contact_email` — formulário, componente `x-city.reference-contact` na faixa da Consultoria e coluna do RX.
- **Usuários:** `phone` e `whatsapp` opcionais; coluna **Contatos** em `/users`; primeiro acesso e perfil; `ContactChannels` para links `tel:`, WhatsApp e e-mail.

### Perfil (`/profile`)

- Layout em hero + navegação por seções (foto, dados, senha, conta).
- Componentes `x-profile.section` e `x-profile.save-hint`; estilos `.serv-profile-*`.

### RX

- Coluna **Indicador meta** (antes «Semáforo»); legenda **Meta de cadastro — indicador**.
- Coluna **Leitura dos dados** (antes «Situação»); valor **Completa** quando OK.
- Ajuda de colunas e alertas em pt-BR; coluna **Pendente** (antes «Em falta»); legenda **Em andamento**.

### Admin i-Educar

- Probe com referência VAAF municipal, painéis críticos/atenção, colunas impacto/correção e comparador VAAF na view.

## Deploy

```bash
git fetch --tags
git checkout 20260521-Mercury   # ou deploy de main @ 3c935ca
php artisan migrate --force
php artisan config:clear
npm run build   # se não usar public/build do repositório
```

## Testes

```bash
php artisan test --filter=CityReferenceContactTest
php artisan test --filter=ContactChannelsTest
php artisan test --filter=AnalyticsTabImpactBuilderTest
php artisan test --filter=RxDashboardTest
```

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [STATUS_PROJETO.md](STATUS_PROJETO.md)
- [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md)
