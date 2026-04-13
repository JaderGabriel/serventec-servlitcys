import "./bootstrap";

import Alpine from "alpinejs";
import Chart from "chart.js/auto";
import ChartDataLabels from "chartjs-plugin-datalabels";
import { buildCompositeExport, triggerPngDownload } from "./chartExportHelpers.js";
import { initAnalyticsFilterTurno } from "./analyticsFilterTurno.js";

Chart.register(ChartDataLabels);

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

document.addEventListener("alpine:init", () => {
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
        ) => ({
            chart: null,
            _payload: null,
            _exportMeta: null,
            _exportFilename: "grafico",
            _panelId: "",
            _cleanupMq: null,
            _cleanupIo: null,
            _cleanupRo: null,
            _onViewport: null,
            init() {
                if (!payload?.labels?.length || !payload?.datasets?.length) {
                    return;
                }
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
                                                  (Number(value) / sum) * 100,
                                              )
                                            : 0;
                                        const fmt =
                                            typeof value === "number" &&
                                            !Number.isInteger(value)
                                                ? String(
                                                      Math.round(value * 10) /
                                                          10,
                                                  )
                                                : String(value);
                                        return `${fmt}\n(${pct}%)`;
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
                                    padding: isRadial ? {} : { top: 14 },
                                },
                                plugins: mergedPlugins,
                                scales:
                                    extra.scales !== undefined
                                        ? extra.scales
                                        : scales,
                            },
                        });

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
                            this.chart.resize();
                            this.chart.update("none");
                        };

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
                    } catch (e) {
                        console.error("chartPanel", e);
                    }
                });
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
                    root?.scrollIntoView?.({ block: "nearest", behavior: "instant" });
                } catch (e) {
                    /* ignore */
                }
                this.chart.resize();
                this.chart.update("none");
                requestAnimationFrame(() => {
                    this.chart.resize();
                    this.chart.update("none");
                    requestAnimationFrame(() => {
                        try {
                            const { dataUrl } = buildCompositeExport(
                                this.chart,
                                this._exportMeta,
                                this._payload?.title || "",
                            );
                            triggerPngDownload(dataUrl, this._exportFilename);
                        } catch (e) {
                            console.error(e);
                        }
                    });
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
