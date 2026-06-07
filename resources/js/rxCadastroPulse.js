/**
 * Mini-gráfico «batimento» de cadastro recente (turmas + matrículas) no painel RX.
 *
 * @param {object|null} payload
 */
export default function rxCadastroPulse(payload) {
    const data = payload && typeof payload === "object" ? payload : { available: false };

    return {
        payload: data,
        hover: null,
        tooltipX: 0,
        tooltipY: 0,
        width: 132,
        height: 28,

        get series() {
            return Array.isArray(this.payload.series) ? this.payload.series : [];
        },

        get windows() {
            return Array.isArray(this.payload.windows) ? this.payload.windows : [];
        },

        get hasChart() {
            return this.series.length > 0;
        },

        windowTitle(window) {
            if (!window) {
                return "";
            }
            const tur = Number(window.turmas ?? 0).toLocaleString("pt-BR");
            const mat = Number(window.matriculas ?? 0).toLocaleString("pt-BR");
            const tot = Number(window.total ?? 0).toLocaleString("pt-BR");
            return `${window.hours}h · ${tot} total (${tur} tur., ${mat} mat.)`;
        },

        ecgPath() {
            const series = this.series;
            if (series.length === 0) {
                return "";
            }

            const max = Math.max(1, Number(this.payload.series_max ?? 0));
            const w = this.width;
            const h = this.height;
            const mid = h * 0.72;
            const amp = h * 0.58;
            const step = w / series.length;

            let d = `M 0 ${mid.toFixed(2)}`;
            series.forEach((point, index) => {
                const total = Number(point.total ?? 0);
                const x = (index + 0.5) * step;
                const peak = mid - (total / max) * amp;
                const left = Math.max(0, x - step * 0.18);
                const right = Math.min(w, x + step * 0.18);
                d += ` L ${left.toFixed(2)} ${mid.toFixed(2)}`;
                d += ` L ${x.toFixed(2)} ${peak.toFixed(2)}`;
                d += ` L ${right.toFixed(2)} ${mid.toFixed(2)}`;
            });
            d += ` L ${w} ${mid.toFixed(2)}`;

            return d;
        },

        hoverPoint() {
            if (this.hover === null || !this.series[this.hover]) {
                return null;
            }
            return this.series[this.hover];
        },

        hoverLabel() {
            const point = this.hoverPoint();
            if (!point) {
                return "";
            }
            const tur = Number(point.turmas ?? 0).toLocaleString("pt-BR");
            const mat = Number(point.matriculas ?? 0).toLocaleString("pt-BR");
            const tot = Number(point.total ?? 0).toLocaleString("pt-BR");
            return `${point.label} · ${tot} (${tur} tur., ${mat} mat.)`;
        },

        markerX() {
            const point = this.hoverPoint();
            if (!point) {
                return 0;
            }
            const step = this.width / this.series.length;
            return (Number(point.index ?? 0) + 0.5) * step;
        },

        onMove(event) {
            if (!this.hasChart) {
                return;
            }
            const rect = event.currentTarget.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const step = this.width / this.series.length;
            const index = Math.min(
                this.series.length - 1,
                Math.max(0, Math.floor(x / step)),
            );
            this.hover = index;
            this.tooltipX = x;
            this.tooltipY = event.clientY - rect.top;
        },

        clearHover() {
            this.hover = null;
        },
    };
}
