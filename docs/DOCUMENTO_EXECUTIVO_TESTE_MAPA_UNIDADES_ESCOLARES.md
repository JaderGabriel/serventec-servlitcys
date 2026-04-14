## Documento executivo — teste do mapa de Unidades Escolares

### Objetivo
Validar, com evidência de execução, por que o card **“MAPA (CONSULTA RÁPIDA)”** está a exibir:

- “Sem marcadores para exibir no mapa neste momento.”

e confirmar se o problema está na lógica (ArcGIS/coords) ou no acesso à base i-Educar da cidade.

### Escopo do teste
- **Cidade testada (cadastro local)**: `ITAMARI` (id `1`)
- **Filtro usado**: `ano_letivo=all` (obrigatório para a aba “Unidades Escolares”)
- **Procedimentos**:
  - limpeza de caches Laravel (application/config/view)
  - tentativa de executar a rotina do repositório do mapa (`SchoolUnitsRepository::snapshot`)
  - teste de conectividade e existência das tabelas i-Educar via `CityDataConnection`

### Evidência (execução real no ambiente)
1) **Caches limpos (Laravel)**
- `php artisan cache:clear`
- `php artisan config:clear`
- `php artisan view:clear`

2) **Cidade disponível no cadastro local**
- Existem `2` cidades na base local.
- A primeira cidade com `forAnalytics()` é `ITAMARI` (id `1`).

3) **Tentativa de validar o schema i-Educar (conexão por cidade)**
- A conexão por cidade inicia e resolve tabelas como:
  - `pmieducar.escola`
  - `pmieducar.turma`
  - `pmieducar.matricula`
- Porém, ao tentar consultar `information_schema.tables` (para confirmar existência da tabela), ocorreu erro de rede:

**Erro capturado** (resumo):
- `SQLSTATE[08006] [7] connection to server at "95.216.29.92", port 5432 failed: timeout expired`
- Host/DB: `95.216.29.92:5432` / `serventec_itamari_ba`

4) **Teste dirigido do geocoding INEP (ArcGIS) com INEP 29309255 (independente do i-Educar)**
- A chave de cache foi removida antes do teste: `inep_geo_v2_29309255`
- Resultado do método `lookupByInepCodes([29309255])`:
  - tempo: ~`2247 ms`
  - retorno: **sem linha** (`row=null`)
  - cache gravado como “miss”: `['miss' => true]`

5) **Verificação do endpoint ArcGIS (saúde)**
- Consulta “contagem” funcionou:
  - `where=1=1` + `returnCountOnly=true` → `count=1033`
- Ou seja, o endpoint está acessível, mas **não retornou feature para o INEP 29309255** nas tentativas abaixo.

6) **Tentativas de query ArcGIS para o INEP 29309255**
- `where=Codigo_INEP IN (29309255)` → erro: `Cannot perform query. Invalid query parameters.`
- `where=CODIGO_INEP IN (29309255)` → erro: `Cannot perform query. Invalid query parameters.`
- `where="Código_INEP" IN (29309255)` → `features=0`
- `where=Código_INEP = 29309255` → `features=0`
- `where=Código_INEP LIKE '%29309255%'` → `features=0`

**Interpretação**:
- O serviço expõe o atributo `Código_INEP`, mas o registro **29309255 não foi encontrado nessa camada** no momento do teste (ou o código não pertence ao universo de 1033 features da camada publicada).
- Como consequência, o sistema marca cache como “miss” para evitar consultas repetidas e o mapa não consegue obter coordenadas via INEP para esse código.

**Evidência adicional (metadados da camada ArcGIS)**:
- A própria camada consultada (`inep_escolas_fmt_250609_geocode`) tem **extensão geográfica muito limitada**, com “Full Extent” aproximado:
  - longitude de `-56.72` a `-54.06`
  - latitude de `-16.67` a `-11.69`
- Isso indica que a camada configurada é um **recorte regional** e, portanto, não é adequada como fonte universal para geocoding de escolas do país.

7) **Teste adicional: consulta INEP 31090841**
- Cache removido: `inep_geo_v2_31090841`
- Resultado do método `lookupByInepCodes([31090841])`:
  - tempo ~`2343 ms`
  - retorno: **sem linha** (`row=null`)
  - (neste teste específico, o cache permaneceu `NULL` logo após a execução; em seguida, a consulta direta ao ArcGIS confirmou ausência de feature)

8) **Tentativas de query ArcGIS para o INEP 31090841**
- `where=Código_INEP = 31090841` → erro: `Cannot perform query. Invalid query parameters.`
- `where=Codigo_INEP = 31090841` → erro: `Cannot perform query. Invalid query parameters.`
- `where=CODIGO_INEP = 31090841` → erro: `Cannot perform query. Invalid query parameters.`
- `where="Código_INEP" = 31090841` → `features=0`
- `where="Código_INEP" LIKE '%31090841%'` → `features=0`

**Interpretação**:
- Assim como no INEP `29309255`, o INEP `31090841` **não está presente** nesta camada ArcGIS consultada no momento do teste.

### Conclusão
No ambiente testado, o mapa está sem marcadores **não por falha de front-end/Leaflet nem por ausência de lógica ArcGIS**, mas porque **a aplicação não consegue conectar ao PostgreSQL i-Educar da cidade (timeout de rede)**.

Sem essa conexão:
- não é possível ler escolas no escopo de matrículas (`matricula → turma → escola`)
- não é possível cair no fallback “rede” (tabela `escola`)
- consequentemente, o backend devolve **`markers=[]`** e o card mostra “Sem marcadores…”

### Hipóteses prováveis para o timeout
- **Firewall / security group** bloqueando a origem do servidor da aplicação
- **PostgreSQL não acessível externamente** (bind apenas local/rede interna)
- **Rota/DNS/latência** entre o servidor da aplicação e o host `95.216.29.92`
- **Credenciais/host** corretos no cadastro local, mas serviço indisponível no momento do teste

### Próximas ações recomendadas (para resolver o mapa)
- Garantir conectividade do host da aplicação para `95.216.29.92:5432` (teste de rede e liberação de firewall).
- Confirmar que o Postgres aceita conexões remotas (pg_hba.conf + listen_addresses).
- Só depois, reexecutar o teste de marcadores:
  - validar quantas escolas vêm com `latitude/longitude` na tabela `escola`
  - validar quantas escolas têm **código INEP** (para permitir geocoding ArcGIS)
  - validar se o modo `map_scope` fica em `matricula` ou cai para `rede_escola`

### Plano de contingência (INEP válido, mas sem retorno no ArcGIS)
Como o portal `anonymousdata.inep.gov.br` é um Oracle Analytics (OBIEE) e tende a bloquear automação (ex.: 403 / browser check), e a camada ArcGIS configurada pode ser apenas um recorte regional, foi preparado um **fallback local**:

- **Tabela local**: `inep_school_geos` (mapeia `inep_code → lat/lng` + payload)
- **Importador**: comando `app:import-inep-school-geos` para importar um CSV exportado do portal Catálogo de Escolas (ou outra fonte que traga INEP/latitude/longitude).
- **Uso em runtime**: `InepCatalogoEscolasGeoService` consulta primeiro essa tabela local e só depois tenta ArcGIS.

Isso permite que INEPs válidos (como `29309255` e `31090841`) sejam georreferenciados mesmo quando a fonte ArcGIS pública não cobre o código.

### Observação sobre ArcGIS/INEP
O enriquecimento por ArcGIS/INEP (`InepCatalogoEscolasGeoService`) depende de:
- existir coluna de INEP na `escola` **ou** no payload carregado para o marcador
- e o backend conseguir processar as escolas do banco i-Educar

Sem acesso ao banco, o geocoding INEP não tem como ser acionado porque não há lista de escolas/INEP para consultar.

Adicionalmente, mesmo quando o geocoding INEP é testado de forma isolada, o INEP `29309255` **não retornou coordenadas** nesta camada ArcGIS (resultado “miss”).

O INEP `31090841` também **não retornou coordenadas** nesta mesma camada.

