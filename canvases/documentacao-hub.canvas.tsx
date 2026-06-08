import {
  BarChart,
  Callout,
  Card,
  CardBody,
  CardHeader,
  Code,
  CollapsibleSection,
  Divider,
  Grid,
  H1,
  H2,
  Pill,
  Row,
  Stack,
  Stat,
  Table,
  Text,
} from "cursor/canvas";

const PRODUCAO = {
  version: "4.4.1",
  tag: "20260607b-Peitho",
  date: "2026-06-07",
  commit: "52113e4",
  number: 339,
};

const RELEASES_4X = [
  { version: "4.0.0", commits: 283 },
  { version: "4.1.0", commits: 289 },
  { version: "4.1.7", commits: 307 },
  { version: "4.2.0", commits: 319 },
  { version: "4.3.0", commits: 321 },
  { version: "4.4.0", commits: 336 },
  { version: "4.4.1", commits: 339 },
];

const DOC_SECOES: { title: string; docs: string[] }[] = [
  {
    title: "Âncora",
    docs: ["STATUS_PROJETO", "HISTORICO_VERSOES", "PONDERACOES_TECNICAS", "PADRAO_DOCUMENTACAO"],
  },
  {
    title: "Produto e UI",
    docs: ["DOCUMENTACAO_EXECUTIVA", "ANALYTICS_NAVEGACAO_UI", "DESIGN_SYSTEM", "INICIO_DASHBOARD"],
  },
  {
    title: "Finanças e dados",
    docs: ["FUNDEB_VAAF_E_ONDA1", "CONSULTAS_EXTERNAS", "CADUNICO_PREVISAO_TERRITORIAL"],
  },
  {
    title: "Operação",
    docs: ["IMPLANTACAO_PRODUCAO", "VARIAVEIS_AMBIENTE", "COMANDOS_ARTISAN", "SEGURANCA"],
  },
];

const NAVEGACAO = [
  { area: "Resumo", abas: 1 },
  { area: "Cadastro", abas: 5 },
  { area: "Pedagógico", abas: 3 },
  { area: "Censo", abas: 1 },
  { area: "Finanças", abas: 5 },
];

export default function DocumentacaoHubCanvas() {
  return (
    <Stack gap={24} style={{ padding: 24, maxWidth: 960 }}>
      <Stack gap={8}>
        <H1>Hub de documentação — servlitcys</H1>
        <Text tone="secondary" size="small">
          Versão em produção · mapa de docs · linha 4.x · navegação consultoria
        </Text>
      </Stack>

      <Grid columns={4} gap={12}>
        <Stat label="Versão" value={PRODUCAO.version} tone="info" />
        <Stat label="Tag deploy" value={PRODUCAO.tag} />
        <Stat label="Commit" value={`#${PRODUCAO.number}`} />
        <Stat label="Data" value={PRODUCAO.date} />
      </Grid>

      <Callout tone="info" title="Tags no mesmo dia">
        Segunda release no mesmo dia civil: sufixo alfabético (ex.{" "}
        <Code>20260607-Phronesis</Code> depois <Code>20260607a-Ananke</Code>).
        Ordenação via <Code>ProductReleaseTag.sort_key</Code>.
      </Callout>

      <H2>Marcos 4.x — commits acumulados em main</H2>
      <Text tone="secondary" size="small">
        Eixo Y: posição do commit (#) · Fonte: HISTORICO_VERSOES.md · jun 2026
      </Text>
      <BarChart
        categories={RELEASES_4X.map((r) => r.version)}
        series={[
          {
            name: "Commit # em main",
            data: RELEASES_4X.map((r) => r.commits),
            tone: "info",
          },
        ]}
        height={220}
        showValues
      />

      <Row gap={16} wrap>
        <Card style={{ flex: 1, minWidth: 280 }}>
          <CardHeader>Consultoria — sub-abas por área</CardHeader>
          <CardBody>
            <Text tone="secondary" size="small">
              Fonte: AnalyticsTabCatalog · cenário C (4.1.0+)
            </Text>
            <BarChart
              categories={NAVEGACAO.map((n) => n.area)}
              series={[
                {
                  name: "Abas",
                  data: NAVEGACAO.map((n) => n.abas),
                  tone: "success",
                },
              ]}
              height={180}
              showValues
            />
          </CardBody>
        </Card>

        <Card style={{ flex: 1, minWidth: 280 }}>
          <CardHeader>Convenção de tag (mesmo dia)</CardHeader>
          <CardBody>
            <Stack gap={12}>
              <Row gap={8} style={{ alignItems: "center" }}>
                <Pill tone="neutral">1ª do dia</Pill>
                <Code>20260607-Phronesis</Code>
              </Row>
              <Row gap={8} style={{ alignItems: "center" }}>
                <Pill tone="info">2ª do dia</Pill>
                <Code>20260607a-Ananke</Code>
              </Row>
              <Row gap={8} style={{ alignItems: "center" }}>
                <Pill tone="neutral">3ª do dia</Pill>
                <Code>20260607b-…</Code>
              </Row>
              <Text tone="tertiary" size="small">
                Ficheiro: docs/RELEASE_20260607a_ANANKE.md
              </Text>
            </Stack>
          </CardBody>
        </Card>
      </Row>

      <Divider />

      <H2>Mapa de documentação</H2>
      <Stack gap={8}>
        {DOC_SECOES.map((sec) => (
          <CollapsibleSection
            title={sec.title}
            count={sec.docs.length}
            defaultOpen={sec.title === "Âncora"}
          >
            <Table
              headers={["Documento", "Caminho"]}
              rows={sec.docs.map((d) => [d.replace(/_/g, " "), `docs/${d}.md`])}
              columnAlign={["left", "left"]}
              striped
            />
          </CollapsibleSection>
        ))}
      </Stack>

      <Card>
        <CardHeader>Releases recentes (4.2+)</CardHeader>
        <CardBody>
          <Table
            headers={["Versão", "Codename", "Data", "Nota RELEASE"]}
            rows={[
              ["4.4.0", "Ananke", "07/06 a", "RELEASE_20260607a_ANANKE.md"],
              ["4.3.0", "Harmonia", "11/06", "RELEASE_20260611_HARMONIA.md"],
              ["4.2.0", "Clio", "10/06", "RELEASE_20260610_CLIO.md"],
            ]}
            columnAlign={["center", "left", "center", "left"]}
            striped
          />
        </CardBody>
      </Card>

      <Text tone="tertiary" size="small">
        Markdown equivalente: docs/HUB_DOCUMENTACAO.md · diagramas: docs/ARQUITETURA_E_FLUXOS.md
      </Text>
    </Stack>
  );
}
