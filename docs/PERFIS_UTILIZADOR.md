# Perfis de usuário — servlitcys

> **Índice:** [README.md](README.md) · **Ponderações:** [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §10.

## Visão geral

O acesso à aplicação é controlado pelo campo `users.role` (`admin`, `user`, `municipal`). A coluna legada `is_admin` é sincronizada automaticamente com `role` ao gravar e não deve ser editada manualmente.

| Perfil | Valor em BD | Página inicial após login |
|--------|-------------|---------------------------|
| Administrador | `admin` | Painel (`/dashboard`) |
| Usuário | `user` | Análise educacional (`/dashboard/analytics`) |
| Municipal | `municipal` | Análise educacional (`/dashboard/analytics`; com um só município vinculado, `?city_id=` automático) |

---

## Administrador

**Quem é:** equipa de sistema ou gestão central da plataforma.

**Pode:**
- Ver o **Painel** (estatísticas globais, probe de conexão por cidade)
- Ver **Análise** em todos os municípios com dados configurados (`forAnalytics`)
- Gerir **Cidades** (CRUD, credenciais encriptadas)
- Executar **sincronizações** (geográficas, pedagógicas, compatibilidade i-Educar, FUNDEB)
- Consultar **Comandos Artisan** (referência CLI)
- Configurar **e-mail (SMTP)**
- Ver **Monitorização (Pulse)**, sessões activas e histórico de logins
- Criar e editar usuários de **qualquer perfil**, incluindo vincular municípios a municipais
- **Desativar**, **reativar** e **excluir** usuários na lista `/users` (com confirmação)
- Consultar **histórico de logins** por usuário

**Não pode:** desativar ou excluir a própria conta; remover o único administrador ativo ou a única conta admin do sistema.

---

## Usuário

**Quem é:** analista ou decisor com visão da **rede inteira** de municípios cadastrados.

**Pode:**
- Ver **Análise educacional** em todos os municípios ativos com conexão a dados
- Exportar discrepâncias (CSV) para municípios a que tem acesso analítico
- **Gerir usuários** apenas do perfil **Usuário** (criar, editar, alterar senha)
- Editar o próprio perfil

**Não pode:**
- Aceder ao **Painel** administrativo (`/dashboard` redirecciona para Análise)
- Importar dados, sincronizar, configurar cidades ou SMTP
- Criar administradores ou municipais
- Ver Pulse, sessões globais ou histórico de logins de outros

---

## Municipal

**Quem é:** equipa de um ou mais municípios específicos (secretaria, gestão local).

**Pode:**
- Ver **Análise educacional** **somente** nos municípios associados na tabela `city_user`
- Exportar discrepâncias desses municípios
- **Gerir usuários** do perfil **Municipal**, desde que os municípios atribuídos sejam **subconjunto** dos seus
- Editar o próprio perfil

**Não pode:**
- Aceder ao Painel administrativo
- Ver ou analisar municípios não vinculados
- Atribuir municípios fora do seu âmbito (validação no servidor após sanitize de `city_ids`)
- Importar, sincronizar ou configurar o sistema
- Criar administradores ou usuários “rede inteira”

**Nota:** um municipal **sem** municípios vinculados não vê cidades na análise (lista vazia) até um administrador associar cidades à conta.

---

## Matriz de permissões (resumo)

| Funcionalidade | Admin | Usuário | Municipal |
|----------------|:-----:|:----------:|:---------:|
| Painel `/dashboard` | Sim | Não | Não |
| Análise (todos `forAnalytics`) | Sim | Sim | — |
| Análise (só vinculados) | Sim | — | Sim |
| Cidades / sync / FUNDEB / SMTP | Sim | Não | Não |
| Pulse / sessões / logins | Sim | Não | Não |
| Criar Admin | Sim | Não | Não |
| Criar Usuário | Sim | Sim | Não |
| Criar Municipal | Sim | Não | Sim* |
| Desativar / excluir usuários | Sim | Não | Não |

\* Municipal só com municípios ⊆ dos do editor.

---

## Implementação técnica (referência)

| Componente | Arquivo / nota |
|------------|-----------------|
| Enum de perfis | `app/Enums/UserRole.php` |
| Modelo e scopes | `app/Models/User.php` — `hasCityAccess()`, `scopeVisibleTo()` |
| Filtro de cidades | `app/Support/Auth/UserCityAccess.php` |
| Auditoria admin | `app/Support/Auth/AdminUserAuditLogger.php` |
| Sessões | `app/Support/Auth/UserSessionTerminator.php` |
| Destino após login | `User::homeRouteName()`, `User::homeUrl()` |
| Policies | `app/Policies/UserPolicy.php`, `app/Policies/CityPolicy.php` |
| Middleware | `admin`, `manage.users` em `bootstrap/app.php` |
| Formulários | `app/Http/Requests/Concerns/ValidatesManagedUserAttributes.php` |
| Gate auditoria | `manageUserAudit` (só admin) em `AppServiceProvider` |
| Pivot municípios | `city_user` (`user_id`, `city_id`) |

---

## Operação

### Promover usuário existente a municipal

1. Entrar como **admin**
2. **Usuários** → editar conta
3. Perfil **Municipal** → marcar municípios → guardar

### Criar conta municipal nova

1. Admin (ou outro municipal com âmbito) → **Novo usuário**
2. Perfil **Municipal**, senha, municípios obrigatórios

### Migração de contas antigas

Usuários com `is_admin = true` na BD foram migrados para `role = admin` pela migration `2026_05_16_140000_add_role_and_city_user_table`.

---

*Segurança geral: [SEGURANCA.md](SEGURANCA.md). Resumo executivo: [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md).*
