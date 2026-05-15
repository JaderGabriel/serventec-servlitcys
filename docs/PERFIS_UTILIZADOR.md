# Perfis de utilizador — servlitcys

## Visão geral

O acesso à aplicação é controlado pelo campo `users.role` (`admin`, `user`, `municipal`). A coluna legada `is_admin` é sincronizada automaticamente com `role` ao gravar e não deve ser editada manualmente.

| Perfil | Valor em BD | Página inicial após login |
|--------|-------------|---------------------------|
| Administrador | `admin` | Painel (`/dashboard`) |
| Utilizador | `user` | Análise educacional (`/dashboard/analytics`) |
| Municipal | `municipal` | Análise educacional (`/dashboard/analytics`; com um só município vinculado, `?city_id=` automático) |

---

## Administrador

**Quem é:** equipa de sistema ou gestão central da plataforma.

**Pode:**
- Ver o **Painel** (estatísticas globais, probe de ligação por cidade)
- Ver **Análise** em todos os municípios com dados configurados (`forAnalytics`)
- Gerir **Cidades** (CRUD, credenciais encriptadas)
- Executar **sincronizações** (geográficas, pedagógicas, compatibilidade i-Educar, FUNDEB)
- Consultar **Comandos Artisan** (referência CLI)
- Configurar **e-mail (SMTP)**
- Ver **Monitorização (Pulse)**, sessões activas e histórico de logins
- Criar e editar utilizadores de **qualquer perfil**, incluindo vincular municípios a municipais
- **Desactivar**, **reactivar** e **excluir** utilizadores na lista `/users` (com confirmação)
- Consultar **histórico de logins** por utilizador

**Não pode:** desactivar ou excluir a própria conta; remover o único administrador activo ou a única conta admin do sistema.

---

## Utilizador

**Quem é:** analista ou decisor com visão da **rede inteira** de municípios cadastrados.

**Pode:**
- Ver **Análise educacional** em todos os municípios activos com ligação a dados
- Exportar discrepâncias (CSV) para municípios a que tem acesso analítico
- **Gerir utilizadores** apenas do perfil **Utilizador** (criar, editar, alterar senha)
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
- **Gerir utilizadores** do perfil **Municipal**, desde que os municípios atribuídos sejam **subconjunto** dos seus
- Editar o próprio perfil

**Não pode:**
- Aceder ao Painel administrativo
- Ver ou analisar municípios não vinculados
- Atribuir municípios fora do seu âmbito (validação no servidor após sanitize de `city_ids`)
- Importar, sincronizar ou configurar o sistema
- Criar administradores ou utilizadores “rede inteira”

**Nota:** um municipal **sem** municípios vinculados não vê cidades na análise (lista vazia) até um administrador associar cidades à conta.

---

## Matriz de permissões (resumo)

| Funcionalidade | Admin | Utilizador | Municipal |
|----------------|:-----:|:----------:|:---------:|
| Painel `/dashboard` | Sim | Não | Não |
| Análise (todos `forAnalytics`) | Sim | Sim | — |
| Análise (só vinculados) | Sim | — | Sim |
| Cidades / sync / FUNDEB / SMTP | Sim | Não | Não |
| Pulse / sessões / logins | Sim | Não | Não |
| Criar Admin | Sim | Não | Não |
| Criar Utilizador | Sim | Sim | Não |
| Criar Municipal | Sim | Não | Sim* |
| Desactivar / excluir utilizadores | Sim | Não | Não |

\* Municipal só com municípios ⊆ dos do editor.

---

## Implementação técnica (referência)

| Componente | Ficheiro / nota |
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

### Promover utilizador existente a municipal

1. Entrar como **admin**
2. **Utilizadores** → editar conta
3. Perfil **Municipal** → marcar municípios → guardar

### Criar conta municipal nova

1. Admin (ou outro municipal com âmbito) → **Novo utilizador**
2. Perfil **Municipal**, senha, municípios obrigatórios

### Migração de contas antigas

Utilizadores com `is_admin = true` na BD foram migrados para `role = admin` pela migration `2026_05_16_140000_add_role_and_city_user_table`.

---

*Segurança geral: [SEGURANCA.md](SEGURANCA.md). Resumo executivo: [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md).*
