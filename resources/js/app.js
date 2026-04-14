import "./bootstrap";

import Alpine from "alpinejs";
import Chart from "chart.js/auto";
import ChartDataLabels from "chartjs-plugin-datalabels";
import { radialCalloutsPlugin } from "./chartRadialCallouts.js";
import {
    buildCanvasOnlyExport,
    buildCompositeExport,
    triggerPngDownload,
} from "./chartExportHelpers.js";
import { initAnalyticsFilterTurno } from "./analyticsFilterTurno.js";
import createSchoolUnitsMap from "./schoolUnitsMap.js";

Chart.register(ChartDataLabels, radialCalloutsPlugin);

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
    Alpine.data("schoolUnitsMap", (markers) => createSchoolUnitsMap(markers));

    Alpine.data(
        "analyticsTabs",
        (allowedKeys, initialFromServer = "overview") => ({
            tab: "overview",
            init() {
                const allowed = Array.isArray(allowedKeys) ? allowedKeys : [];
                let next = "overview";
                try {
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
            _cleanupMq: null,
            _cleanupIo: null,
            _cleanupRo: null,
            _onViewport: null,
            _pulseChartSize: null,
            _origLegendGen: null,
            _legendTruncate: 48,
            _tickTruncate: 36,
            menuOpen: false,
            legendModalOpen: false,
            expanded: false,
            legendVisible: true,
            /** Classes do contentor / canvas (actualizadas por syncLayoutClasses; Alpine 3.4 não rastreia bem métodos em :class). */
            panelBodyClass: "",
            canvasExtraClass: "",
            init() {
                if (!payload?.labels?.length || !payload?.datasets?.length) {
                    return;
                }
                this._compact = compact !== false;
                this.syncLayoutClasses();
                this.$watch("expanded", () => {
                    this.syncLayoutClasses();
                    this.$nextTick(() => {
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                this._pulseChartSize?.();
                            });
                        });
                    });
                });
                this._payload = payload;
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
                };
                this.$nextTick(() => {
                    try {
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
                                      grid: { color: chartGridColor() },
                                  },
                                  y: {
                                      beginAtZero: true,
                                      ticks: { color: chartTextColor() },
                                      grid: { color: chartGridColor() },
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
                                display: true,
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
                        this.chart = new Chart(ctx, {
                            type: payload.type,
                            data: {
                                labels: payload.labels,
                                datasets: payload.datasets,
                            },
                            options: {
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
                            },
                        });

                        const legLabels = this.chart.options.plugins.legend
                            .labels;
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
                        });
                    } catch (e) {
                        console.error("chartPanel", e);
                    }
                });
            },
            syncLayoutClasses() {
                const c = this._compact;
                const e = this.expanded;
                let body =
                    "p-2 sm:p-4 relative w-full overflow-x-auto overflow-y-hidden transition-all duration-200 ease-out ";
                if (e) {
                    body += c
                        ? "min-h-[min(30rem,80vh)] min-h-[30rem] sm:min-h-[36rem] h-[min(42rem,90vh)]"
                        : "min-h-[min(36rem,92vh)] h-[min(44rem,92vh)]";
                } else {
                    body += c
                        ? "min-h-[220px] h-[min(22rem,calc(100vw-2.5rem))] sm:h-72 md:min-h-[18rem]"
                        : "min-h-[min(28rem,70vh)] h-[min(28rem,70vh)]";
                }
                this.panelBodyClass = body;

                let cv =
                    "block w-full max-w-full chart-panel-canvas transition-all duration-200 ";
                if (!e && c) {
                    cv +=
                        "max-h-[min(20rem,55vw)] sm:max-h-64";
                } else if (e && c) {
                    cv +=
                        "min-h-[18rem] max-h-[min(36rem,78vh)] sm:min-h-[22rem] sm:max-h-[min(40rem,82vh)]";
                } else {
                    cv += "min-h-[16rem] h-full max-h-none";
                }
                this.canvasExtraClass = cv;
            },
            legendRows() {
                if (!this.chart?.data?.labels) {
                    return [];
                }
                const labels = this.chart.data.labels;
                const ds0 = this.chart.data.datasets[0];
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
            toggleExpanded() {
                this.expanded = !this.expanded;
                this.applyDensityOptions();
            },
            toggleLegend() {
                if (!this.chart) {
                    return;
                }
                this.legendVisible = !this.legendVisible;
                const plugins = this.chart.options.plugins;
                if (!plugins.legend) {
                    plugins.legend = {};
                }
                plugins.legend.display = !!this.legendVisible;
                try {
                    this.chart.update("none");
                } catch (e) {
                    console.warn("toggleLegend update", e);
                    try {
                        this.chart.draw();
                    } catch (e2) {
                        console.warn("toggleLegend draw", e2);
                    }
                }
            },
            applyDensityOptions() {
                if (!this.chart) {
                    return;
                }
                const labels = this.chart.data.labels || [];
                const n = labels.length;
                const longLine = labels.some(
                    (l) =>
                        String(l ?? "").length > (this.expanded ? 42 : 28),
                );
                const e = this.expanded;

                this._legendTruncate = e ? 72 : n > 10 || longLine ? 38 : 52;
                this._tickTruncate = e ? 56 : n > 14 ? 20 : n > 8 ? 28 : 38;

                const leg = this.chart.options.plugins.legend;
                if (leg) {
                    if (!e && n > 6 && this.legendVisible) {
                        leg.maxHeight = 140;
                    } else {
                        delete leg.maxHeight;
                    }
                    if (leg.labels) {
                        leg.labels.boxWidth = e ? 14 : n > 12 ? 10 : 12;
                        leg.labels.padding = e ? 12 : n > 12 ? 8 : 14;
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
                            ticks.maxTicksLimit = e ? 28 : 18;
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
