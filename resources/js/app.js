import "./bootstrap";

import Alpine from "alpinejs";
import Chart from "chart.js/auto";
import ChartDataLabels from "chartjs-plugin-datalabels";
import {
    cartesianInteractionDefaults,
    chartResetZoomView,
    chartZoomInButton,
    chartZoomOutButton,
    mergeAnnotationAndZoomPlugins,
    registerChartVisualPlugins,
} from "./chartVisualDefaults.js";
import { radialCalloutsPlugin } from "./chartRadialCallouts.js";
import {
    buildCanvasOnlyExport,
    buildCompositeExport,
    triggerPngDownload,
} from "./chartExportHelpers.js";
import { initAnalyticsFilterTurno } from "./analyticsFilterTurno.js";
import createSchoolUnitsMap from "./schoolUnitsMap.js";

registerChartVisualPlugins(Chart);

/** Linha fina entre a legenda (topo) e a área do gráfico — não aplicar a pizza/donut/medidor. */
const legendChartSeparatorPlugin = {
    id: "legendChartSeparator",
    afterDraw(chart) {
        const t = chart.config.type;
        if (t === "doughnut" || t === "pie" || t === "polarArea") {
            return;
        }
        const opt = chart.options || {};
        if (
            t === "doughnut" &&
            Number(opt.circumference) === 180 &&
            Number(opt.rotation) === -90
        ) {
            return;
        }
        if (opt.plugins?.legend?.display === false) {
            return;
        }
        const ca = chart.chartArea;
        if (!ca || ca.bottom <= ca.top) {
            return;
        }
        const ctx = chart.ctx;
        ctx.save();
        ctx.strokeStyle = chartGridColor();
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(ca.left, ca.top + 0.5);
        ctx.lineTo(ca.right, ca.top + 0.5);
        ctx.stroke();
        ctx.restore();
    },
};

Chart.register(ChartDataLabels, radialCalloutsPlugin, legendChartSeparatorPlugin);

function chartTextColor() {
    return document.documentElement.classList.contains("dark")
        ? "#e5e7eb"
        : "#374151";
}

function chartGridColor() {
    return document.documentElement.classList.contains("dark")
        ? "rgba(148,163,184,0.2)"
        : "rgba(100,116,139,0.2)";
}

/** Rótulos longos (ex.: nomes de escolas): texto na legenda/eixo sem estourar o layout. */
function truncateChartLabel(text, maxLen) {
    const s = String(text ?? "");
    const m = Math.max(8, maxLen | 0);
    if (s.length <= m) {
        return s;
    }
    return `${s.slice(0, Math.max(1, m - 1))}…`;
}

/** Junta opções de eixos do payload com os padrões (evita perder eixo X ao só definir Y). */
function mergeCartesianScales(base, extra) {
    if (!extra || typeof extra !== "object") {
        return base;
    }
    return {
        x: { ...(base?.x || {}), ...(extra.x || {}) },
        y: { ...(base?.y || {}), ...(extra.y || {}) },
    };
}

document.addEventListener("alpine:init", () => {
    Alpine.data("schoolUnitsMap", (markers, footnote = null, options = null) =>
        createSchoolUnitsMap(markers, footnote, options),
    );

    Alpine.data(
        "analyticsTabs",
        (allowedKeys, initialFromServer = "overview") => ({
            tab: "overview",
            init() {
                const allowed = Array.isArray(allowedKeys) ? allowedKeys : [];
                let next = "overview";
                try {
                    let urlTab = null;
                    try {
                        urlTab = new URLSearchParams(window.location.search).get(
                            "tab",
                        );
                    } catch {
                        urlTab = null;
                    }
                    if (urlTab && allowed.includes(urlTab)) {
                        next = urlTab;
                    } else {
                        const s = sessionStorage.getItem(
                            "servlitcys_analytics_tab",
                        );
                        if (s && allowed.includes(s)) {
                            next = s;
                        } else if (
                            initialFromServer &&
                            allowed.includes(initialFromServer)
                        ) {
                            next = initialFromServer;
                        }
                    }
                } catch (e) {
                    if (
                        initialFromServer &&
                        allowed.includes(initialFromServer)
                    ) {
                        next = initialFromServer;
                    }
                }
                this.tab = next;
                this.$watch("tab", () => this.afterTabChange());
                this.$nextTick(() => this.afterTabChange());
            },
            afterTabChange() {
                try {
                    sessionStorage.setItem(
                        "servlitcys_analytics_tab",
                        this.tab,
                    );
                } catch (e) {
                    /* ignore */
                }
                const pulseResize = () =>
                    window.dispatchEvent(new Event("resize"));
                requestAnimationFrame(() => {
                    pulseResize();
                    setTimeout(pulseResize, 80);
                    setTimeout(pulseResize, 240);
                    setTimeout(pulseResize, 450);
                    window.dispatchEvent(
                        new CustomEvent("analytics-tab-changed", {
                            detail: { tab: this.tab },
                        }),
                    );
                });
            },
        }),
    );

    Alpine.data("cityDbStatus", (fetchUrl, hasSetup) => ({
        fetchUrl,
        hasSetup,
        loading: !!hasSetup,
        status: null,
        ms: null,
        message: "",
        init() {
            this.refresh();
        },
        async refresh() {
            if (!this.hasSetup) {
                this.status = "setup_missing";

                return;
            }
            this.loading = true;
            this.status = null;
            try {
                const r = await fetch(this.fetchUrl, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!r.ok) {
                    const t = await r.text();
                    throw new Error(
                        t ? `HTTP ${r.status}` : `HTTP ${r.status}`,
                    );
                }
                const d = await r.json();
                this.status = d.status;
                this.ms = d.ms;
                this.message = d.message || "";
            } catch (e) {
                this.status = "error";
                this.message = e instanceof Error ? e.message : String(e);
            } finally {
                this.loading = false;
            }
        },
        titleText() {
            if (this.loading) {
                return "Verificando…";
            }
            if (this.status === "setup_missing") {
                return "Credenciais do banco incompletas.";
            }
            if (this.status === "ok" && this.ms != null) {
                return `Conexão OK (${this.ms} ms). Clique para atualizar.`;
            }
            if (this.status === "slow" && this.ms != null) {
                return `Resposta lenta (${this.ms} ms). Clique para atualizar.`;
            }
            if (this.status === "error") {
                return this.message
                    ? `Erro: ${this.message}`
                    : "Erro de conexão. Clique para tentar de novo.";
            }

            return "";
        },
    }));

    Alpine.data(
        "chartPanel",
        (
            payload,
            exportFilename = "grafico",
            exportMeta = {},
            panelId = "",
            compact = true,
        ) => ({
            chart: null,
            _payload: null,
            _exportMeta: null,
            _exportFilename: "grafico",
            _panelId: "",
            _compact: true,
            _panelHeight: "md",
            _cleanupMq: null,
            _cleanupIo: null,
            _cleanupRo: null,
            _onViewport: null,
            _pulseChartSize: null,
            _origLegendGen: null,
            _legendTruncate: 48,
            _tickTruncate: 36,
            legendModalOpen: false,
            /** Rosca/pizza: legenda visual = plugin radialCallouts (não o legend do Chart.js). */
            _legendUsesRadialCallouts: false,
            /** Classes do contentor / canvas (actualizadas por syncLayoutClasses; Alpine 3.4 não rastreia bem métodos em :class). */
            panelBodyClass: "",
            canvasExtraClass: "",
            /** Gráficos cartesianos: controlos de zoom + pinça/arrastar. */
            zoomUi: false,
            /** Mostrar filtro por categoria (1 série) ou por série (>1 série). */
            filterUi: false,
            filterModalOpen: false,
            /** Incrementado ao alternar visibilidade (modal/legenda) para o Alpine actualizar os checkboxes. */
            _filterNonce: 0,
            /** Cópia profunda do payload para filtros (recortar dados em vez de meta.hidden). */
            _sourcePayload: null,
            /** Índices de categoria ocultos (true = oculto) — chave = índice no payload original. */
            _catHidden: {},
            /** Índices de série ocultos (multi-dataset). */
            _dsHidden: {},
            /** Mapeia posição no gráfico filtrado → índice no payload original (uma série). */
            _visibleSourceIndices: [],
            /** Idem para várias séries (barras agrupadas, etc.). */
            _visibleDatasetIndices: [],
            /** Estilo dinâmico (min-height) para barras horizontais. */
            panelBodyStyle: "",
            init() {
                if (!payload?.labels?.length || !payload?.datasets?.length) {
                    return;
                }
                const extraEarly =
                    payload.options && typeof payload.options === "object"
                        ? payload.options
                        : {};
                const isGaugeEarly =
                    payload.type === "doughnut" &&
                    Number(extraEarly.circumference) === 180 &&
                    Number(extraEarly.rotation) === -90;
                const isRadialEarly = [
                    "doughnut",
                    "pie",
                    "polarArea",
                ].includes(payload.type);
                const labelCount = payload.labels.length;
                const dsCount = payload.datasets.length;
                this.zoomUi =
                    !isRadialEarly &&
                    !isGaugeEarly &&
                    ["bar", "line", "scatter"].includes(payload.type);
                this.filterUi =
                    [
                        "bar",
                        "line",
                        "scatter",
                        "doughnut",
                        "pie",
                        "polarArea",
                    ].includes(payload.type) &&
                    !isGaugeEarly &&
                    ((dsCount === 1 && labelCount > 1) || dsCount > 1);
                this._compact = compact !== false;
                const phRaw =
                    typeof extraEarly.panelHeight === "string"
                        ? extraEarly.panelHeight.trim().toLowerCase()
                        : "";
                this._panelHeight = ["sm", "md", "lg", "xl", "xxl", "xxxl"].includes(
                    phRaw,
                )
                    ? phRaw
                    : "md";
                this.syncLayoutClasses();
                this._payload = payload;
                try {
                    this._sourcePayload =
                        typeof structuredClone === "function"
                            ? structuredClone(payload)
                            : JSON.parse(JSON.stringify(payload));
                } catch (e) {
                    this._sourcePayload = payload;
                }
                this._exportFilename = exportFilename;
                this._panelId =
                    typeof panelId === "string" && panelId
                        ? panelId
                        : "";
                this._exportMeta = {
                    documentTitle:
                        exportMeta.documentTitle || "Análise educacional",
                    cityLine: exportMeta.cityLine || "",
                    filterLines: Array.isArray(exportMeta.filterLines)
                        ? exportMeta.filterLines
                        : [],
                    footerLine: exportMeta.footerLine || "",
                    generatedAt: exportMeta.generatedAt || "",
                    appName: exportMeta.appName || "",
                    copyrightLine: exportMeta.copyrightLine || "",
                    poweredByLine: exportMeta.poweredByLine || "",
                };
                this.$nextTick(() => {
                    try {
                        const self = this;
                        const canvas = this.$refs.canvas;
                        if (!canvas) {
                            return;
                        }
                        const ctx = canvas.getContext("2d");
                        const isRadial = [
                            "doughnut",
                            "pie",
                            "polarArea",
                        ].includes(payload.type);
                        const scales = isRadial
                            ? {}
                            : {
                                  x: {
                                      ticks: {
                                          color: chartTextColor(),
                                          maxRotation: 45,
                                          autoSkip: true,
                                      },
                                      grid: {
                                          color: chartGridColor(),
                                          drawBorder: false,
                                      },
                                      border: { display: false },
                                  },
                                  y: {
                                      beginAtZero: true,
                                      ticks: { color: chartTextColor() },
                                      grid: {
                                          color: chartGridColor(),
                                          drawBorder: false,
                                      },
                                      border: { display: false },
                                  },
                              };
                        const extra =
                            payload.options &&
                            typeof payload.options === "object"
                                ? payload.options
                                : {};
                        const radialGeometry =
                            isRadial && extra
                                ? {
                                      ...(extra.rotation !== undefined
                                          ? { rotation: extra.rotation }
                                          : {}),
                                      ...(extra.circumference !== undefined
                                          ? {
                                                circumference:
                                                    extra.circumference,
                                            }
                                          : {}),
                                      ...(extra.cutout !== undefined
                                          ? { cutout: extra.cutout }
                                          : {}),
                                  }
                                : {};
                        const defaultPlugins = {
                            datalabels: {
                                clip: false,
                                display: () => true,
                                color: (ctx) => {
                                    const t = ctx.chart.config.type;
                                    if (t === "doughnut" || t === "pie") {
                                        return "#f8fafc";
                                    }
                                    return chartTextColor();
                                },
                                anchor: (ctx) => {
                                    const t = ctx.chart.config.type;
                                    const idx = ctx.chart.options.indexAxis;
                                    if (t === "bar" && idx === "y") {
                                        return "end";
                                    }
                                    if (t === "bar" || t === "line") {
                                        return "end";
                                    }
                                    return "center";
                                },
                                align: (ctx) => {
                                    const t = ctx.chart.config.type;
                                    const idx = ctx.chart.options.indexAxis;
                                    if (t === "bar" && idx === "y") {
                                        return "end";
                                    }
                                    if (t === "bar") {
                                        return "end";
                                    }
                                    if (t === "line") {
                                        return "top";
                                    }
                                    return "center";
                                },
                                offset: (ctx) => {
                                    const t = ctx.chart.config.type;
                                    if (t === "bar" || t === "line") {
                                        return 2;
                                    }
                                    return 0;
                                },
                                font: { weight: "600", size: 11 },
                                formatter: (value, ctx) => {
                                    if (value === null || value === undefined) {
                                        return "";
                                    }
                                    const t = ctx.chart.config.type;
                                    if (t === "doughnut" || t === "pie") {
                                        const data = ctx.dataset.data;
                                        const sum = data.reduce(
                                            (a, b) => a + Number(b),
                                            0,
                                        );
                                        const pct = sum
                                            ? Math.round(
                                                  (Number(value) / sum) * 1000,
                                              ) / 10
                                            : 0;
                                        const fmt =
                                            typeof value === "number" &&
                                            !Number.isInteger(value)
                                                ? String(
                                                      Math.round(value * 10) /
                                                          10,
                                                  )
                                                : String(value);
                                        return `${fmt}\n(${pct}% do total)`;
                                    }
                                    if (
                                        typeof value === "number" &&
                                        !Number.isInteger(value)
                                    ) {
                                        return String(
                                            Math.round(value * 10) / 10,
                                        );
                                    }
                                    return String(value);
                                },
                            },
                            legend: {
                                display: true,
                                position: isRadial ? "right" : "top",
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: "rectRounded",
                                    padding: 14,
                                    color: chartTextColor(),
                                    generateLabels: (chart) => {
                                        const ds0 = chart.data.datasets[0];
                                        const bg = ds0?.backgroundColor;
                                        const labels = chart.data.labels;
                                        if (
                                            Array.isArray(bg) &&
                                            labels?.length &&
                                            bg.length >= labels.length
                                        ) {
                                            return labels.map((label, i) => ({
                                                text: String(label),
                                                fillStyle: bg[i],
                                                strokeStyle: bg[i],
                                                lineWidth: 0,
                                                hidden: false,
                                                index: i,
                                                datasetIndex: 0,
                                            }));
                                        }
                                        return Chart.defaults.plugins.legend.labels.generateLabels(
                                            chart,
                                        );
                                    },
                                },
                            },
                            tooltip: {
                                enabled: true,
                            },
                        };
                        const mergedPlugins = {
                            ...defaultPlugins,
                            ...(extra.plugins &&
                            typeof extra.plugins === "object"
                                ? extra.plugins
                                : {}),
                        };
                        const isGauge =
                            payload.type === "doughnut" &&
                            Number(extra.circumference) === 180 &&
                            Number(extra.rotation) === -90;
                        const useRadialCallouts =
                            (payload.type === "doughnut" ||
                                payload.type === "pie") &&
                            !isGauge;
                        if (useRadialCallouts) {
                            mergedPlugins.radialCallouts = {
                                display: true,
                                ...(typeof mergedPlugins.radialCallouts ===
                                    "object" && mergedPlugins.radialCallouts
                                    ? mergedPlugins.radialCallouts
                                    : {}),
                            };
                            mergedPlugins.legend = {
                                ...(mergedPlugins.legend || {}),
                                display: false,
                            };
                            mergedPlugins.datalabels = {
                                ...(mergedPlugins.datalabels || {}),
                                display: false,
                            };
                        }
                        if (isGauge) {
                            const pct = Number(
                                payload.datasets?.[0]?.data?.[0] ?? 0,
                            );
                            mergedPlugins.datalabels = {
                                ...(mergedPlugins.datalabels || {}),
                                display: (ctx) => ctx.dataIndex === 0,
                                color: "#f8fafc",
                                font: { weight: "700", size: 16 },
                                formatter: () => `${Math.round(pct)}%`,
                            };
                        }
                        mergeAnnotationAndZoomPlugins(
                            mergedPlugins,
                            payload,
                            extra,
                        );

                        const userLegendOnClick = mergedPlugins.legend?.onClick;
                        mergedPlugins.legend = {
                            ...(mergedPlugins.legend || {}),
                            onClick: (e, legendItem, legend) => {
                                if (
                                    self.filterUi &&
                                    self._sourcePayload &&
                                    typeof self.applyLegendFilter ===
                                        "function"
                                ) {
                                    self.applyLegendFilter(legendItem);
                                    self._filterNonce++;
                                    return;
                                }
                                if (typeof userLegendOnClick === "function") {
                                    userLegendOnClick.call(
                                        legend.chart,
                                        e,
                                        legendItem,
                                        legend,
                                    );
                                } else {
                                    const chart = legend.chart;
                                    try {
                                        if (
                                            chart.data.datasets.length === 1
                                        ) {
                                            chart.toggleDataVisibility(
                                                legendItem.index,
                                            );
                                        } else {
                                            const di =
                                                legendItem.datasetIndex;
                                            chart.setDatasetVisibility(
                                                di,
                                                !chart.isDatasetVisible(di),
                                            );
                                        }
                                        chart.update("none");
                                    } catch (err) {
                                        console.warn(
                                            "legend onClick fallback",
                                            err,
                                        );
                                    }
                                }
                                self._filterNonce++;
                            },
                        };

                        const cartesianInteractive =
                            !isRadial &&
                            !isGauge &&
                            ["bar", "line", "scatter"].includes(
                                payload.type,
                            );
                        const interactionBlock = cartesianInteractive
                            ? cartesianInteractionDefaults()
                            : {};

                        this.chart = new Chart(ctx, {
                            type: payload.type,
                            data: {
                                labels: payload.labels,
                                datasets: payload.datasets,
                            },
                            options: {
                                ...interactionBlock,
                                ...radialGeometry,
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: extra.indexAxis,
                                layout: {
                                    ...(extra.layout &&
                                    typeof extra.layout === "object"
                                        ? extra.layout
                                        : {}),
                                    padding: {
                                        ...(typeof extra.layout?.padding ===
                                        "object"
                                            ? extra.layout.padding
                                            : {}),
                                        ...(useRadialCallouts
                                            ? {
                                                  left: 8,
                                                  right: 8,
                                                  top: 16,
                                                  bottom: 16,
                                              }
                                            : isRadial
                                              ? {}
                                              : { top: 14 }),
                                    },
                                },
                                plugins: mergedPlugins,
                                scales: mergeCartesianScales(
                                    scales,
                                    extra.scales,
                                ),
                                ...(extra.datasets &&
                                typeof extra.datasets === "object"
                                    ? { datasets: extra.datasets }
                                    : {}),
                            },
                        });

                        this._legendUsesRadialCallouts =
                            !!useRadialCallouts &&
                            this.chart.options.plugins?.radialCallouts
                                ?.display !== false;

                        const legPlugin = this.chart.options.plugins.legend;
                        if (legPlugin?.labels?.generateLabels) {
                            const legLabels = legPlugin.labels;
                            this._origLegendGen =
                                legLabels.generateLabels.bind(legLabels);
                            legLabels.generateLabels = (chart) => {
                                const items = this._origLegendGen(chart);
                                const max = this._legendTruncate ?? 48;
                                return items.map((it) => ({
                                    ...it,
                                    text: truncateChartLabel(
                                        String(it.text ?? ""),
                                        max,
                                    ),
                                }));
                            };
                        }

                        const mq = window.matchMedia("(max-width: 639px)");
                        const applyResponsive = () => {
                            if (!this.chart) {
                                return;
                            }
                            const isSmall = mq.matches;
                            const radial = [
                                "doughnut",
                                "pie",
                                "polarArea",
                            ].includes(payload.type);
                            const pos = isSmall
                                ? "bottom"
                                : radial
                                  ? "right"
                                  : "top";
                            this.chart.options.plugins.legend.position = pos;
                            if (this.chart.options.plugins.legend.labels) {
                                Object.assign(
                                    this.chart.options.plugins.legend.labels,
                                    {
                                        boxWidth: isSmall ? 10 : 12,
                                        padding: isSmall ? 10 : 14,
                                        font: {
                                            size: isSmall ? 10 : 12,
                                            family: "system-ui, sans-serif",
                                        },
                                    },
                                );
                            }
                            if (this.chart.options.scales?.x?.ticks) {
                                Object.assign(
                                    this.chart.options.scales.x.ticks,
                                    {
                                        maxRotation: isSmall ? 60 : 45,
                                        minRotation: isSmall ? 40 : 0,
                                        autoSkip: true,
                                        font: { size: isSmall ? 9 : 11 },
                                    },
                                );
                            }
                            if (this.chart.options.scales?.y?.ticks) {
                                this.chart.options.scales.y.ticks.font = {
                                    size: isSmall ? 9 : 11,
                                };
                            }
                            if (isSmall && radial) {
                                this.chart.options.layout = {
                                    ...(this.chart.options.layout || {}),
                                    padding: {
                                        ...(this.chart.options.layout
                                            ?.padding || {}),
                                        bottom: 12,
                                    },
                                };
                            }
                            this.chart.update("none");
                        };
                        applyResponsive();
                        mq.addEventListener("change", applyResponsive);
                        this._cleanupMq = () =>
                            mq.removeEventListener("change", applyResponsive);

                        const pulseChartSize = () => {
                            if (!this.chart) {
                                return;
                            }
                            try {
                                this.chart.resize();
                                this.chart.update("none");
                            } catch (e) {
                                console.warn("chartPanel resize", e);
                            }
                        };
                        this._pulseChartSize = pulseChartSize;

                        this._onViewport = () => {
                            requestAnimationFrame(() => pulseChartSize());
                        };
                        window.addEventListener("resize", this._onViewport);
                        window.addEventListener(
                            "analytics-tab-changed",
                            this._onViewport,
                        );

                        const io = new IntersectionObserver(
                            (entries) => {
                                for (const en of entries) {
                                    if (en.isIntersecting && this.chart) {
                                        pulseChartSize();
                                    }
                                }
                            },
                            { root: null, threshold: [0, 0.02, 0.15] },
                        );
                        io.observe(canvas);
                        this._cleanupIo = () => io.disconnect();

                        const roRoot = this.$el;
                        if (
                            typeof ResizeObserver !== "undefined" &&
                            roRoot instanceof Element
                        ) {
                            let roTimer = null;
                            const ro = new ResizeObserver(() => {
                                if (!this.chart) {
                                    return;
                                }
                                const r = roRoot.getBoundingClientRect();
                                if (r.width < 2 || r.height < 2) {
                                    return;
                                }
                                if (roTimer) {
                                    clearTimeout(roTimer);
                                }
                                roTimer = setTimeout(() => {
                                    roTimer = null;
                                    pulseChartSize();
                                }, 16);
                            });
                            ro.observe(roRoot);
                            this._cleanupRo = () => {
                                if (roTimer) {
                                    clearTimeout(roTimer);
                                }
                                ro.disconnect();
                            };
                        }

                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                pulseChartSize();
                                setTimeout(() => pulseChartSize(), 120);
                                setTimeout(() => pulseChartSize(), 400);
                            });
                        });
                        requestAnimationFrame(() => {
                            this.applyDensityOptions();
                            this.syncLayoutClasses();
                            if (this.chart && this._sourcePayload) {
                                this.rebuildVisibleSourceIndices();
                                this.syncChartBodyMinHeight();
                            }
                        });
                    } catch (e) {
                        console.error("chartPanel", e);
                    }
                });
            },
            rebuildVisibleSourceIndices() {
                const src = this._sourcePayload;
                if (!src?.labels?.length) {
                    this._visibleSourceIndices = [];
                    return;
                }
                const out = [];
                for (let i = 0; i < src.labels.length; i++) {
                    if (!this._catHidden[i]) {
                        out.push(i);
                    }
                }
                this._visibleSourceIndices = out;
            },
            applySourceDataFilter() {
                const c = this.chart;
                const src = this._sourcePayload;
                if (!c || !src?.datasets?.length) {
                    return;
                }
                const t = src.type;
                const nDs = src.datasets.length;

                if (nDs > 1) {
                    const kept = [];
                    for (let j = 0; j < nDs; j++) {
                        if (this._dsHidden[j]) {
                            continue;
                        }
                        try {
                            kept.push(
                                JSON.parse(
                                    JSON.stringify(src.datasets[j]),
                                ),
                            );
                        } catch (e) {
                            kept.push({ ...src.datasets[j] });
                        }
                    }
                    c.data.datasets = kept;
                    c.data.labels = src.labels
                        ? [...src.labels]
                        : c.data.labels;
                    this.rebuildVisibleDatasetIndices();
                    try {
                        chartResetZoomView(c);
                    } catch (e) {
                        /* ignore */
                    }
                    c.update("none");
                    this.applyDensityOptions();
                    this.syncChartBodyMinHeight();
                    return;
                }

                const ds0 = src.datasets[0];
                this.rebuildVisibleSourceIndices();
                const vis = this._visibleSourceIndices;
                const pick = (v) => {
                    if (!Array.isArray(v)) {
                        return v;
                    }
                    return vis.map((i) => v[i]);
                };

                if (
                    [
                        "bar",
                        "line",
                        "scatter",
                        "doughnut",
                        "pie",
                        "polarArea",
                    ].includes(t)
                ) {
                    if (!vis.length) {
                        c.data.labels = [];
                        c.data.datasets[0].data = [];
                        if (
                            Array.isArray(c.data.datasets[0].backgroundColor)
                        ) {
                            c.data.datasets[0].backgroundColor = [];
                        }
                        if (Array.isArray(c.data.datasets[0].borderColor)) {
                            c.data.datasets[0].borderColor = [];
                        }
                    } else {
                        c.data.labels = vis.map((i) => src.labels[i]);
                        c.data.datasets[0].data = vis.map((i) => ds0.data[i]);
                        if (ds0.backgroundColor !== undefined) {
                            c.data.datasets[0].backgroundColor = pick(
                                ds0.backgroundColor,
                            );
                        }
                        if (ds0.borderColor !== undefined) {
                            c.data.datasets[0].borderColor = pick(
                                ds0.borderColor,
                            );
                        }
                        if (ds0.hoverBackgroundColor !== undefined) {
                            c.data.datasets[0].hoverBackgroundColor = pick(
                                ds0.hoverBackgroundColor,
                            );
                        }
                    }
                }

                try {
                    chartResetZoomView(c);
                } catch (e) {
                    /* ignore */
                }
                c.update("none");
                this.rebuildVisibleDatasetIndices();
                this.applyDensityOptions();
                this.syncChartBodyMinHeight();
            },
            rebuildVisibleDatasetIndices() {
                const src = this._sourcePayload;
                if (!src?.datasets || src.datasets.length <= 1) {
                    this._visibleDatasetIndices = [];
                    return;
                }
                const out = [];
                for (let j = 0; j < src.datasets.length; j++) {
                    if (!this._dsHidden[j]) {
                        out.push(j);
                    }
                }
                this._visibleDatasetIndices = out;
            },
            applyLegendFilter(legendItem) {
                const src = this._sourcePayload;
                if (!src) {
                    return;
                }
                if (src.datasets.length === 1) {
                    const fi = legendItem.index;
                    const srcIdx = this._visibleSourceIndices[fi];
                    if (srcIdx === undefined) {
                        return;
                    }
                    this._catHidden[srcIdx] = !this._catHidden[srcIdx];
                } else {
                    const di = legendItem.datasetIndex;
                    const origJ = this._visibleDatasetIndices[di];
                    if (origJ === undefined) {
                        return;
                    }
                    this._dsHidden[origJ] = !this._dsHidden[origJ];
                }
                this.applySourceDataFilter();
            },
            syncChartBodyMinHeight() {
                const src = this._sourcePayload;
                if (!src?.labels) {
                    this.panelBodyStyle = "";
                    return;
                }
                const isH =
                    src.options &&
                    typeof src.options === "object" &&
                    src.options.indexAxis === "y" &&
                    src.type === "bar";
                const onChart =
                    this.chart?.data?.labels &&
                    Array.isArray(this.chart.data.labels)
                        ? this.chart.data.labels.length
                        : 0;
                const n =
                    onChart > 0
                        ? onChart
                        : src.labels.length;
                const skipHAuto =
                    src.options &&
                    typeof src.options === "object" &&
                    src.options.skipHorizontalBarAutoHeight === true;
                if (isH && n > 0 && !skipHAuto) {
                    const rowPx = ["xxxl"].includes(this._panelHeight)
                        ? 104
                        : 52;
                    const h = Math.min(10400, Math.max(320, 80 + n * rowPx));
                    this.panelBodyStyle = `min-height: ${h}px`;
                } else {
                    this.panelBodyStyle = isH && !skipHAuto ? "min-height: 280px" : "";
                }
            },
            syncLayoutClasses() {
                const c = this._compact;
                const ph = this._panelHeight || "md";
                let body =
                    "p-2 sm:p-4 relative w-full overflow-x-auto overflow-y-auto transition-all duration-200 ease-out ";
                if (c) {
                    // Compacto: usado em medidores e gráficos pequenos. Evitar crescer demais em mobile.
                    body += "min-h-[280px] h-[min(28rem,calc(100vw-2rem))] sm:h-[26rem] md:min-h-[22rem]";
                } else {
                    // Não-compacto: usado na maioria dos gráficos do painel analítico.
                    // panelHeight controla o "respiro" vertical quando há muitos rótulos/linhas.
                    if (ph === "sm") {
                        body += "min-h-[min(22rem,52vh)] w-full";
                    } else if (ph === "lg") {
                        body += "min-h-[min(40rem,78vh)] w-full";
                    } else if (ph === "xl") {
                        body += "min-h-[min(48rem,85vh)] w-full";
                    } else if (ph === "xxl") {
                        body += "min-h-[min(56rem,92vh)] w-full";
                    } else if (ph === "xxxl") {
                        body += "min-h-[min(64rem,94vh)] w-full";
                    } else {
                        body += "min-h-[min(32rem,70vh)] w-full";
                    }
                }
                this.panelBodyClass = body;

                let cv =
                    "block w-full max-w-full chart-panel-canvas transition-all duration-200 ";
                if (c) {
                    cv += "max-h-[min(26rem,72vw)] sm:max-h-[22rem] md:max-h-96";
                } else {
                    if (ph === "sm") {
                        cv += "min-h-[14rem] w-full max-h-none";
                    } else if (ph === "xl") {
                        cv += "min-h-[26rem] w-full max-h-none";
                    } else if (ph === "xxl") {
                        cv += "min-h-[32rem] w-full max-h-none";
                    } else if (ph === "xxxl") {
                        cv += "min-h-[64rem] w-full max-h-none";
                    } else {
                        cv += "min-h-[18rem] w-full max-h-none";
                    }
                }
                this.canvasExtraClass = cv;
            },
            legendRows() {
                const src = this._sourcePayload;
                if (!src?.labels?.length || !src?.datasets?.[0]) {
                    return [];
                }
                const labels = src.labels;
                const ds0 = src.datasets[0];
                const data = ds0?.data ?? [];
                return labels.map((label, i) => {
                    const v = data[i];
                    let valueText = "";
                    if (v !== undefined && v !== null) {
                        if (typeof v === "number") {
                            valueText = Number.isInteger(v)
                                ? String(v)
                                : String(Math.round(v * 10) / 10);
                        } else {
                            valueText = String(v);
                        }
                    }
                    return {
                        label: String(label ?? ""),
                        value: v,
                        valueText,
                    };
                });
            },
            filterRows() {
                void this._filterNonce;
                const src = this._sourcePayload;
                if (!src?.datasets?.length) {
                    return [];
                }
                const dsCount = src.datasets.length;
                const labels = src.labels || [];
                if (dsCount === 1 && labels.length) {
                    return labels.map((label, i) => {
                        const idx = Number(i);
                        return {
                            key: `c-${idx}`,
                            label: String(label ?? ""),
                            visible: !this._catHidden[idx],
                            kind: "category",
                            index: idx,
                        };
                    });
                }

                return src.datasets.map((ds, di) => {
                    const d = Number(di);
                    return {
                        key: `d-${d}`,
                        label: String(ds.label ?? `Série ${d + 1}`),
                        visible: !this._dsHidden[d],
                        kind: "dataset",
                        index: d,
                    };
                });
            },
            toggleFilterRow(row) {
                if (!row || !this._sourcePayload) {
                    return;
                }
                if (row.kind === "category") {
                    const idx = Number(row.index);
                    this._catHidden[idx] = !this._catHidden[idx];
                } else if (row.kind === "dataset") {
                    const di = Number(row.index);
                    this._dsHidden[di] = !this._dsHidden[di];
                }
                this.applySourceDataFilter();
                this._filterNonce++;
            },
            zoomIn() {
                chartZoomInButton(this.chart);
            },
            zoomOut() {
                chartZoomOutButton(this.chart);
            },
            resetZoomView() {
                chartResetZoomView(this.chart);
            },
            applyDensityOptions() {
                if (!this.chart) {
                    return;
                }
                const labels = this.chart.data.labels || [];
                const n = labels.length;
                const longLine = labels.some(
                    (l) => String(l ?? "").length > 28,
                );

                this._legendTruncate = n > 10 || longLine ? 38 : 52;
                this._tickTruncate = n > 14 ? 20 : n > 8 ? 28 : 38;

                const leg = this.chart.options.plugins.legend;
                if (leg) {
                    if (n > 6) {
                        leg.maxHeight = 140;
                    } else {
                        delete leg.maxHeight;
                    }
                    if (leg.labels) {
                        leg.labels.boxWidth = n > 12 ? 10 : 12;
                        leg.labels.padding = n > 12 ? 8 : 14;
                    }
                }

                const t = this.chart.config.type;
                if (
                    (t === "bar" || t === "line") &&
                    this.chart.options.scales
                ) {
                    const catAxis =
                        this.chart.options.indexAxis === "y" ? "y" : "x";
                    const ticks = this.chart.options.scales[catAxis]?.ticks;
                    if (ticks) {
                        ticks.autoSkip = true;
                        ticks.autoSkipPadding = n > 16 ? 4 : 2;
                        if (n > 24) {
                            ticks.maxTicksLimit = 18;
                        } else {
                            delete ticks.maxTicksLimit;
                        }
                    }
                }

                this._patchCategoryTickCallbacks();
                try {
                    this.chart.update("none");
                } catch (err) {
                    console.warn("applyDensityOptions", err);
                }
            },
            _patchCategoryTickCallbacks() {
                const chart = this.chart;
                if (!chart) {
                    return;
                }
                const max = this._tickTruncate ?? 36;
                const t = (raw) =>
                    truncateChartLabel(String(raw ?? ""), max);
                const type = chart.config.type;

                const patchAxis = (key) => {
                    const sc = chart.options.scales?.[key];
                    if (!sc?.ticks) {
                        return;
                    }
                    sc.ticks.callback = (tickValue) => {
                        let raw = tickValue;
                        try {
                            const scale = chart.scales?.[key];
                            if (
                                scale &&
                                typeof scale.getLabelForValue ===
                                    "function"
                            ) {
                                raw = scale.getLabelForValue(tickValue);
                            }
                        } catch (e) {
                            raw = tickValue;
                        }
                        return t(raw);
                    };
                };

                if (type === "bar" || type === "line") {
                    if (chart.options.indexAxis === "y") {
                        patchAxis("y");
                    } else {
                        patchAxis("x");
                    }
                }
            },
            destroy() {
                this._cleanupMq?.();
                this._cleanupIo?.();
                this._cleanupRo?.();
                if (this._onViewport) {
                    window.removeEventListener("resize", this._onViewport);
                    window.removeEventListener(
                        "analytics-tab-changed",
                        this._onViewport,
                    );
                }
                this.chart?.destroy();
            },
            exportPng() {
                if (!this.chart) {
                    return;
                }
                const root = this.$root;
                try {
                    root?.scrollIntoView?.({
                        block: "nearest",
                        behavior: "auto",
                    });
                } catch (e) {
                    /* ignore */
                }

                const canvas = this.chart.canvas;
                const wrap = canvas?.parentElement;
                let prevMinW = "";
                let prevMinH = "";
                if (wrap && (wrap.clientWidth < 8 || wrap.clientHeight < 8)) {
                    prevMinW = wrap.style.minWidth;
                    prevMinH = wrap.style.minHeight;
                    wrap.style.minWidth = "min(100%, 640px)";
                    wrap.style.minHeight = "280px";
                }

                const runExport = () => {
                    try {
                        let dataUrl;
                        try {
                            dataUrl = buildCompositeExport(
                                this.chart,
                                this._exportMeta,
                                this._payload?.title || "",
                            ).dataUrl;
                        } catch (e1) {
                            console.warn("exportPng composite", e1);
                            try {
                                dataUrl = buildCanvasOnlyExport(this.chart);
                            } catch (e2) {
                                console.error("exportPng canvas only", e2);
                                return;
                            }
                        }
                        triggerPngDownload(dataUrl, this._exportFilename);
                    } finally {
                        if (wrap) {
                            wrap.style.minWidth = prevMinW;
                            wrap.style.minHeight = prevMinH;
                        }
                    }
                };

                requestAnimationFrame(() => {
                    requestAnimationFrame(runExport);
                });
            },
        }),
    );
});

window.Alpine = Alpine;

Alpine.start();

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () =>
        initAnalyticsFilterTurno(),
    );
} else {
    initAnalyticsFilterTurno();
}
