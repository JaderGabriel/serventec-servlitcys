# Documentação executiva — servlitcys

## Propósito

O **servlitcys** é uma aplicação web que consolida **informação educacional ao nível municipal**, permitindo explorar indicadores, painéis analíticos e comparações entre territórios. O objetivo é apoiar **análise, planeamento e decisão** com dados organizados e acessíveis a equipas autorizadas.

## Público-alvo

- Equipas de gestão educativa municipal ou regional
- Analistas e responsáveis que necessitam de visão agregada por cidade
- Administradores de sistema que configuram ligações a bases de dados por município

## Funcionalidades principais

1. **Painel e análise** — Visualização de métricas e filtros associados a cidades com configuração ativa e ligação a dados (ex.: ecossistema iEducar).
2. **Gestão de cidades** — Cadastro de municípios e credenciais de acesso às respetivas bases (restrito a administradores).
3. **Gestão de utilizadores** — Criação de contas no sistema após autenticação, sem registo público na Internet.
4. **Página institucional** — Apresentação da plataforma e acesso ao login.

## Modelo de governação de acesso

- **Utilizador standard**: acede ao painel e à análise conforme as cidades disponíveis e ativas.
- **Administrador (`is_admin`)**: gere cidades (incluindo credenciais) e cria novos utilizadores.

Não existe auto-registo: reduz superfície de ataque e permite controlo explícito de quem entra no sistema.

## Dependências técnicas (alto nível)

- Aplicação **Laravel** (PHP), com interface web e API interna para consultas.
- **MySQL** como base principal e, por cidade, ligação configurável a bases de dados municipais.
- **Frontend**: Vite, CSS (Tailwind), JavaScript (Alpine.js) para interatividade.

## Indicadores de sucesso (sugestão)

- Tempo para obter uma visão consolidada por município
- Redução de pedidos ad hoc de dados quando os painéis cobrem as necessidades
- Estabilidade e tempo de resposta dos painéis em horário de uso

## Próximos passos (produto)

- Integração com repositório remoto (GitHub) para CI/CD e revisão de código
- Monitorização de erros e performance em ambiente cloud
- Política de backup e recuperação documentada com a equipa de infraestruturas

---

*Documento orientado a decisores e gestão de projeto; detalhes técnicos de segurança e operação estão em [SEGURANCA.md](SEGURANCA.md).*
