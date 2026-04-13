import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

function chartTextColor() {
    return document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151';
}

function chartGridColor() {
    return document.documentElement.classList.contains('dark') ? 'rgba(148,163,184,0.2)' : 'rgba(100,116,139,0.2)';
}

document.addEventListener('alpine:init', () => {
    Alpine.data('cityDbStatus', (fetchUrl, hasSetup) => ({
        fetchUrl,
        hasSetup,
        loading: !!hasSetup,
        status: null,
        ms: null,
        message: '',
        init() {
            this.refresh();
        },
        async refresh() {
            if (!this.hasSetup) {
                this.status = 'setup_missing';

                return;
            }
            this.loading = true;
            this.status = null;
            try {
                const r = await fetch(this.fetchUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!r.ok) {
                    const t = await r.text();
                    throw new Error(t ? `HTTP ${r.status}` : `HTTP ${r.status}`);
                }
                const d = await r.json();
                this.status = d.status;
                this.ms = d.ms;
                this.message = d.message || '';
            } catch (e) {
                this.status = 'error';
                this.message = e instanceof Error ? e.message : String(e);
            } finally {
                this.loading = false;
            }
        },
        titleText() {
            if (this.loading) {
                return 'Verificando…';
            }
            if (this.status === 'setup_missing') {
                return 'Credenciais do banco incompletas.';
            }
            if (this.status === 'ok' && this.ms != null) {
                return `Conexão OK (${this.ms} ms). Clique para atualizar.`;
            }
            if (this.status === 'slow' && this.ms != null) {
                return `Resposta lenta (${this.ms} ms). Clique para atualizar.`;
            }
            if (this.status === 'error') {
                return this.message ? `Erro: ${this.message}` : 'Erro de conexão. Clique para tentar de novo.';
            }

            return '';
        },
    }));

    Alpine.data('chartPanel', (payload, exportFilename = 'grafico') => ({
        chart: null,
        init() {
            if (!payload?.labels?.length || !payload?.datasets?.length) {
                return;
            }
            this.$nextTick(() => {
                const canvas = this.$refs.canvas;
                if (!canvas) {
                    return;
                }
                const ctx = canvas.getContext('2d');
                const isRadial = ['doughnut', 'pie', 'polarArea'].includes(payload.type);
                const scales = isRadial
                    ? {}
                    : {
                          x: {
                              ticks: { color: chartTextColor() },
                              grid: { color: chartGridColor() },
                          },
                          y: {
                              beginAtZero: true,
                              ticks: { color: chartTextColor() },
                              grid: { color: chartGridColor() },
                          },
                      };
                this.chart = new Chart(ctx, {
                    type: payload.type,
                    data: {
                        labels: payload.labels,
                        datasets: payload.datasets,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: isRadial ? 'right' : 'top',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'rectRounded',
                                    padding: 14,
                                    color: chartTextColor(),
                                    generateLabels: (chart) => {
                                        const ds0 = chart.data.datasets[0];
                                        const bg = ds0?.backgroundColor;
                                        const labels = chart.data.labels;
                                        if (Array.isArray(bg) && labels?.length && bg.length >= labels.length) {
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
                                        return Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                    },
                                },
                            },
                            tooltip: {
                                enabled: true,
                            },
                        },
                        scales,
                    },
                });
            });
        },
        destroy() {
            this.chart?.destroy();
        },
        exportPng() {
            if (!this.chart) {
                return;
            }
            const a = document.createElement('a');
            const base = typeof exportFilename === 'string' && exportFilename ? exportFilename : 'grafico';
            a.download = `${base.replace(/[^a-zA-Z0-9-_]/g, '_')}.png`;
            a.href = this.chart.toBase64Image('image/png', 1);
            a.click();
        },
    }));
});

window.Alpine = Alpine;

Alpine.start();
