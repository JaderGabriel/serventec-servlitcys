/**
 * Para gráficos de pizza/rosca: linha de chamada + «balão» com o rótulo e valores por fatia.
 * Não se aplica a medidores semicirculares (gauge).
 */
export const radialCalloutsPlugin = {
    id: "radialCallouts",

    afterDatasetsDraw(chart) {
        const cfg = chart.options.plugins?.radialCallouts;
        if (cfg?.display === false) {
            return;
        }

        const type = chart.config.type;
        if (type !== "doughnut" && type !== "pie") {
            return;
        }

        const o = chart.options;
        if (
            o.circumference != null &&
            Math.abs(Number(o.circumference) - 180) < 0.01 &&
            o.rotation != null &&
            Math.abs(Number(o.rotation) - -90) < 0.01
        ) {
            return;
        }

        const ctx = chart.ctx;
        const labels = chart.data.labels || [];
        const dataset = chart.data.datasets[0];
        const data = dataset?.data || [];
        const colors = dataset?.backgroundColor;
        const meta = chart.getDatasetMeta(0);
        if (!meta?.data?.length) {
            return;
        }

        const sumRaw = data.reduce((a, b) => a + Math.abs(Number(b) || 0), 0) || 1;

        const dark = document.documentElement.classList.contains("dark");
        const fillBox = dark ? "rgba(17,24,39,0.94)" : "rgba(255,255,255,0.96)";
        const strokeBox = dark ? "rgba(75,85,99,0.9)" : "rgba(203,213,225,0.95)";
        const textPri = dark ? "#f3f4f6" : "#111827";
        const textSec = dark ? "#9ca3af" : "#4b5563";
        const lineCol = dark ? "rgba(156,163,175,0.85)" : "rgba(100,116,139,0.85)";

        ctx.save();
        ctx.font =
            '11px system-ui, -apple-system, "Segoe UI", sans-serif';
        ctx.lineWidth = 1;

        meta.data.forEach((arc, i) => {
            if (arc.skip || data[i] == null) {
                return;
            }
            const v = Number(data[i]);
            if (!Number.isFinite(v) || v === 0) {
                return;
            }

            const props = arc.getProps(
                ["x", "y", "startAngle", "endAngle", "outerRadius"],
                true,
            );
            const mid = (props.startAngle + props.endAngle) / 2;
            const { x, y, outerRadius } = props;
            const cos = Math.cos(mid);
            const sin = Math.sin(mid);
            const xEdge = x + cos * outerRadius;
            const yEdge = y + sin * outerRadius;

            const pct = Math.round((Math.abs(v) / sumRaw) * 1000) / 10;
            const labelText = String(labels[i] ?? "");
            const valText = Number.isInteger(v)
                ? String(v)
                : String(Math.round(v * 10) / 10);
            const lines = [labelText, `${valText} (${pct}% do total)`];

            const pad = 7;
            const lineH = 13;
            let maxW = 0;
            lines.forEach((ln) => {
                maxW = Math.max(maxW, ctx.measureText(ln).width);
            });
            const swatch = 10;
            const gap = 6;
            const boxW = maxW + pad * 2 + swatch + gap;
            const boxH = lines.length * lineH + pad * 2;

            const ext = 18;
            const xElbow = x + cos * (outerRadius + ext);
            const yElbow = y + sin * (outerRadius + ext);

            const ca = chart.chartArea;
            let bx = xElbow + (cos >= 0 ? 6 : -6 - boxW);
            let by = yElbow - boxH / 2;
            bx = Math.max(ca.left + 4, Math.min(bx, ca.right - boxW - 4));
            by = Math.max(ca.top + 4, Math.min(by, ca.bottom - boxH - 4));

            const joinX = cos >= 0 ? bx : bx + boxW;
            const joinY = Math.max(
                by + pad,
                Math.min(yElbow, by + boxH - pad),
            );

            ctx.beginPath();
            ctx.strokeStyle = lineCol;
            ctx.moveTo(xEdge, yEdge);
            ctx.lineTo(xElbow, yElbow);
            ctx.lineTo(joinX, joinY);
            ctx.stroke();

            const fill =
                Array.isArray(colors) && colors[i] != null
                    ? colors[i]
                    : typeof colors === "string"
                      ? colors
                      : "#6366f1";
            ctx.fillStyle = fillBox;
            ctx.strokeStyle = strokeBox;
            ctx.beginPath();
            const rr = 6;
            if (typeof ctx.roundRect === "function") {
                ctx.roundRect(bx, by, boxW, boxH, rr);
            } else {
                const r0 = Math.min(rr, boxW / 2, boxH / 2);
                ctx.moveTo(bx + r0, by);
                ctx.arcTo(bx + boxW, by, bx + boxW, by + boxH, r0);
                ctx.arcTo(bx + boxW, by + boxH, bx, by + boxH, r0);
                ctx.arcTo(bx, by + boxH, bx, by, r0);
                ctx.arcTo(bx, by, bx + boxW, by, r0);
                ctx.closePath();
            }
            ctx.fill();
            ctx.stroke();

            ctx.fillStyle = fill;
            ctx.fillRect(bx + pad, by + (boxH - swatch) / 2, swatch, swatch);

            ctx.textBaseline = "middle";
            ctx.textAlign = "left";
            const tx = bx + pad + swatch + gap;
            lines.forEach((ln, li) => {
                ctx.fillStyle = li === 0 ? textPri : textSec;
                ctx.fillText(ln, tx, by + pad + lineH / 2 + li * lineH);
            });
        });

        ctx.restore();
    },
};
