import Chart from "chart.js/auto";

const SERIES_STYLES = [
    { color: "#0f766e", borderWidth: 3, pointRadius: 5, pointHoverRadius: 7 },
    { color: "#2563eb", borderWidth: 2.5, pointRadius: 4.5, pointHoverRadius: 6.5 },
    { color: "#7c3aed", borderWidth: 2.5, pointRadius: 4.5, pointHoverRadius: 6.5 },
    { color: "#ea580c", borderWidth: 2.5, pointRadius: 4.5, pointHoverRadius: 6.5 },
    { color: "#059669", borderWidth: 2.5, pointRadius: 4.5, pointHoverRadius: 6.5 },
];

function styleDataset(dataset, index) {
    const style = SERIES_STYLES[index % SERIES_STYLES.length];
    const color = style.color;

    return {
        ...dataset,
        spanGaps: true,
        borderColor: color,
        backgroundColor: `${color}18`,
        borderWidth: style.borderWidth,
        tension: 0.32,
        fill: false,
        pointStyle: "circle",
        pointBackgroundColor: color,
        pointBorderColor: "#ffffff",
        pointBorderWidth: 2,
        pointHoverBackgroundColor: color,
        pointHoverBorderColor: "#ffffff",
        pointHoverBorderWidth: 2.5,
        pointRadius: (ctx) => {
            const value = ctx.dataset.data?.[ctx.dataIndex];
            return value == null || Number.isNaN(Number(value)) ? 0 : style.pointRadius;
        },
        pointHoverRadius: (ctx) => {
            const value = ctx.dataset.data?.[ctx.dataIndex];
            return value == null || Number.isNaN(Number(value))
                ? 0
                : style.pointHoverRadius;
        },
    };
}

function chartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        layout: { padding: { top: 2, right: 2, bottom: 0, left: 0 } },
        plugins: {
            legend: {
                display: true,
                position: "bottom",
                labels: {
                    usePointStyle: true,
                    pointStyle: "circle",
                    boxWidth: 6,
                    boxHeight: 6,
                    padding: 8,
                    font: { size: 9, weight: "600" },
                    color: "#475569",
                },
            },
            tooltip: {
                callbacks: {
                    label(ctx) {
                        const raw = ctx.parsed?.y;
                        if (raw == null || Number.isNaN(Number(raw))) {
                            return `${ctx.dataset.label}: —`;
                        }
                        return `${ctx.dataset.label}: ${Number(raw).toLocaleString("pt-BR")}`;
                    },
                },
            },
            datalabels: { display: false },
        },
        scales: {
            x: {
                ticks: { color: "#64748b", font: { size: 9, weight: "600" }, maxRotation: 0 },
                grid: { display: false },
            },
            y: {
                beginAtZero: true,
                ticks: {
                    color: "#64748b",
                    font: { size: 9 },
                    callback(value) {
                        const n = Number(value);
                        if (!Number.isFinite(n)) {
                            return value;
                        }
                        if (Math.abs(n) >= 1000) {
                            return `${(n / 1000).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}k`;
                        }
                        return n.toLocaleString("pt-BR");
                    },
                },
                grid: { color: "rgba(148,163,184,0.28)" },
            },
        },
    };
}

/**
 * Slider do card municipal: cobertura da tríade ↔ série histórica (Censo INEP).
 *
 * @param {string} seriesUrl
 */
export default function clioReportCard(seriesUrl) {
    return {
        panel: "coverage",
        loading: false,
        error: null,
        loaded: false,
        stageCounters: [],
        latestSummary: null,
        footnote: "",
        seriesUrl: typeof seriesUrl === "string" ? seriesUrl : "",
        _chart: null,
        _abort: null,
        _renderGen: 0,

        get isSeries() {
            return this.panel === "series";
        },

        get toggleLabel() {
            return this.isSeries ? "Cobertura" : "Série histórica";
        },

        get toggleTitle() {
            return this.isSeries
                ? "Voltar à cobertura da tríade"
                : "Ver série histórica de matrículas (rede municipal)";
        },

        togglePanel() {
            this.panel = this.isSeries ? "coverage" : "series";
            if (this.isSeries) {
                this.$nextTick(() => this.ensureSeries());
            } else {
                this.destroyChart();
            }
        },

        async ensureSeries() {
            if (this.loaded && this._chart) {
                await this.$nextTick();
                this._chart.resize();
                return;
            }
            await this.fetchAndRender();
        },

        async fetchAndRender() {
            if (!this.seriesUrl) {
                this.error = "Série histórica indisponível.";
                return;
            }

            this.loading = true;
            this.error = null;
            this._abort?.abort();
            const controller = new AbortController();
            this._abort = controller;
            const renderGen = ++this._renderGen;

            try {
                const url = new URL(this.seriesUrl, window.location.origin);
                url.searchParams.set("dependencia", "municipal");
                url.searchParams.set("years", "5");

                const res = await fetch(url.toString(), {
                    headers: { Accept: "application/json" },
                    signal: controller.signal,
                    credentials: "same-origin",
                });
                const data = await res.json().catch(() => ({}));
                if (renderGen !== this._renderGen) {
                    return;
                }
                if (!res.ok || !data?.ok) {
                    this.error =
                        data?.message ||
                        "Sem matrículas Censo indexadas para este município.";
                    this.loaded = false;
                    return;
                }

                this.stageCounters = Array.isArray(data.stage_counters?.items)
                    ? data.stage_counters.items
                    : [];
                this.latestSummary = data.latest_summary ?? null;
                this.footnote = typeof data.footnote === "string" ? data.footnote : "";
                this.loaded = true;
                this.loading = false;

                await this.$nextTick();
                await this.renderChart(data.chart, renderGen);
            } catch (err) {
                if (err?.name === "AbortError") {
                    return;
                }
                if (renderGen === this._renderGen) {
                    this.error = "Não foi possível carregar a série histórica.";
                    this.loaded = false;
                }
            } finally {
                if (renderGen === this._renderGen) {
                    this.loading = false;
                }
            }
        },

        async waitForCanvas(maxFrames = 16) {
            for (let i = 0; i < maxFrames; i += 1) {
                const canvas = this.$refs.seriesCanvas;
                if (canvas && canvas.clientWidth > 0 && canvas.clientHeight > 0) {
                    return canvas;
                }
                await this.$nextTick();
                await new Promise((resolve) => requestAnimationFrame(resolve));
            }
            const canvas = this.$refs.seriesCanvas;
            return canvas && canvas.clientWidth > 0 ? canvas : null;
        },

        async renderChart(payload, renderGen) {
            if (!payload || !this.isSeries || renderGen !== this._renderGen) {
                return;
            }
            const canvas = await this.waitForCanvas();
            if (!canvas || renderGen !== this._renderGen || !this.isSeries) {
                return;
            }
            const ctx = canvas.getContext("2d");
            if (!ctx) {
                return;
            }

            this.destroyChart();
            const existing = Chart.getChart(canvas);
            if (existing) {
                existing.destroy();
            }

            const datasets = (Array.isArray(payload.datasets) ? payload.datasets : []).map(
                (dataset, index) => styleDataset(dataset, index),
            );

            try {
                this._chart = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: Array.isArray(payload.labels) ? payload.labels : [],
                        datasets,
                    },
                    options: chartOptions(),
                });
                await new Promise((resolve) =>
                    requestAnimationFrame(() => requestAnimationFrame(resolve)),
                );
                if (renderGen !== this._renderGen || !this.isSeries) {
                    this.destroyChart();
                    return;
                }
                this._chart.resize();
                this._chart.update("none");
            } catch (error) {
                console.warn("[Clio] falha ao desenhar série no card", error);
                this.error = "Não foi possível desenhar o gráfico.";
            }
        },

        destroyChart() {
            if (this._chart) {
                try {
                    this._chart.destroy();
                } catch {
                    /* ignore */
                }
                this._chart = null;
            }
        },

        formatCounter(value) {
            if (value == null || value === "") {
                return "—";
            }
            const n = Number(value);
            if (!Number.isFinite(n)) {
                return "—";
            }
            return n.toLocaleString("pt-BR");
        },

        destroy() {
            this._abort?.abort();
            this.destroyChart();
        },
    };
}
