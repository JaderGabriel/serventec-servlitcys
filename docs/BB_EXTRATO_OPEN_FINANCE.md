# Extrato BB — download automático e Open Finance

Integração do extrato do [Banco do Brasil](https://demonstrativos.apps.bb.com.br/extrato) com repasses FUNDEB (`fonte = bb_extrato` em `municipal_transfer_snapshots`), usada na importação **Admin → Dados públicos → Repasses** e na aba **Finanças → Tempo Real**.

---

## 1. Download automático do CSV (implementado)

O serviço `BbExtratoCsvFetcher` grava o ficheiro em:

```text
storage/app/funding/bb_extrato/{IBGE}_{ANO}.csv
```

(pasta configurável com `IEDUCAR_BB_EXTRATO_STORAGE_PATH`).

### Ordem de resolução

1. **Cache local** — se o ficheiro existe e tem menos de `IEDUCAR_BB_EXTRATO_REFRESH_DAYS` dias, reutiliza.
2. **Download HTTP** — URL de `IEDUCAR_BB_EXTRATO_URL_TEMPLATE` (prioridade) ou `IEDUCAR_BB_EXTRATO_EXPORT_URL`.
3. **Upload manual** — se não há URL, mas o ficheiro já foi copiado para o caminho acima (SFTP, volume, etc.).

### Variáveis `.env`

| Variável | Descrição |
|----------|-----------|
| `IEDUCAR_BB_EXTRATO_FUNDEB_ENABLED` | `true` — activa fonte BB no import (default `true`) |
| `IEDUCAR_BB_EXTRATO_URL_TEMPLATE` | URL por município: suporta `{ibge}`, `{ano}`, `{uf}` |
| `IEDUCAR_BB_EXTRATO_EXPORT_URL` | URL fixa ou com placeholders (usada se template vazio) |
| `IEDUCAR_BB_EXTRATO_STORAGE_PATH` | Subpasta em `storage/app` (default `funding/bb_extrato`) |
| `IEDUCAR_BB_EXTRATO_REFRESH_DAYS` | Dias de validade do CSV em cache (default `7`) |
| `IEDUCAR_BB_EXTRATO_HTTP_TIMEOUT` | Timeout do download em segundos (default `30`) |
| `IEDUCAR_BB_EXTRATO_FUNDEB_KEYWORDS` | Palavras-chave nas linhas do CSV (FUNDEB, FNDE, etc.) |

### Exemplos

**Um endpoint por município e ano** (recomendado para várias prefeituras na mesma instância):

```env
IEDUCAR_BB_EXTRATO_URL_TEMPLATE=https://intranet.prefeitura.example/export/bb/{ibge}_{ano}.csv
```

**Um ficheiro global** (uma conta, um CSV para todos — menos comum):

```env
IEDUCAR_BB_EXTRATO_EXPORT_URL=https://storage.example.com/extrato-fundeb-atual.csv
```

**Sem URL — upload manual** após exportar no portal BB:

```bash
# Exemplo: IBGE 2927408, ano 2025
cp extrato.csv storage/app/funding/bb_extrato/2927408_2025.csv
```

Depois: **Admin → Dados públicos → Repasses** (município + ano).

### Formato do CSV

- Texto com linhas delimitadas por `;` ou `,`.
- Pelo menos uma **keyword** configurada no histórico/descrição da linha.
- Ano visível na linha (`2025` ou data `dd/mm/2025`).
- Valores em formato brasileiro (`1.234,56`).

O portal [demonstrativos.apps.bb.com.br/extrato](https://demonstrativos.apps.bb.com.br/extrato) não expõe URL pública permanente: em geral a prefeitura exporta o CSV e publica numa URL interna (template) ou envia o ficheiro para `storage/`.

### Segurança

- Apenas URLs **HTTP/HTTPS** públicas (sem localhost/privadas) — `SafeOutboundUrl`.
- O servidor da aplicação precisa de **acesso de saída** à URL configurada.

---

## 2. Open Finance BB (preparado — consulta automática futura)

Hoje o Open Finance **não** busca lançamentos. Serve para indicar na UI (**Finanças → Tempo Real → cartão Banco do Brasil**) que as credenciais estão definidas.

### Variáveis actuais

| Variável | Descrição |
|----------|-----------|
| `IEDUCAR_BB_OPEN_FINANCE_ENABLED` | `true` — activa indicador na UI |
| `IEDUCAR_BB_OPEN_FINANCE_CLIENT_ID` | Client ID no programa de desenvolvedores BB |
| `IEDUCAR_BB_OPEN_FINANCE_BASE_URL` | Base da API (default `https://api.bb.com.br`) |

### O que falta implementar (roadmap)

1. Registo da aplicação no **Portal de Desenvolvedores BB** / ecossistema Open Finance Brasil.
2. Credenciais completas: `client_secret`, certificado mTLS (conforme ambiente homologação/produção).
3. Fluxo **OAuth 2.0** + **consentimento** do titular da conta (prefeitura).
4. Chamadas às APIs de **contas** e **transações** (escopos `accounts`, `transactions`, etc.).
5. Filtro de créditos FUNDEB/FNDE (mesmas keywords do CSV) e gravação em `municipal_transfer_snapshots` com `fonte = bb_open_finance` (ou reutilizar `bb_extrato`).

Enquanto isso, use **download automático** ou **upload** na secção 1.

### Referências externas

- [Open Finance Brasil](https://openfinancebrasil.org.br/)
- [Banco do Brasil — desenvolvedores](https://developers.bb.com.br/) (portal e documentação de APIs)

---

## 3. Operação

| Passo | Acção |
|-------|--------|
| 1 | Configurar `.env` (template ou export URL) |
| 2 | `php artisan config:clear` |
| 3 | Importar repasses (fila `admin-sync`) |
| 4 | Ver **Tempo Real** → extrato simulado → ciclo **Extrato BB** |

Relacionado: [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) §3.4, [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11.
