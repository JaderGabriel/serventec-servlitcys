/**
 * Data/hora no fuso America/Sao_Paulo (GMT-3) para o rodapé do PNG quando o servidor não envia `generatedAt`.
 *
 * @returns {string}
 */
export function formatFooterTimestampGmt3() {
    try {
        const parts = new Intl.DateTimeFormat("pt-BR", {
            timeZone: "America/Sao_Paulo",
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false,
        }).formatToParts(new Date());
        const g = (t) => parts.find((p) => p.type === t)?.value ?? "";
        const day = g("day");
        const month = g("month");
        const year = g("year");
        const hour = g("hour");
        const minute = g("minute");
        if (!day || !month || !year) {
            return "";
        }

        return `${day}/${month}/${year} ${hour}:${minute} GMT-3`;
    } catch {
        return "";
    }
}

/**
 * Aplica cores «claras» para exportação sem redesenhar (evita layout Chart.js / fullSize).
 * O redesenho opcional fica em {@see safeChartUpdate}.
 *
 * @returns {() => void}
 */
export function applyLightThemeForExport(chart) {
    if (!chart?.options) {
        return () => {};
    }
    const o = chart.options;
    const backup = {
        legendLabelsColor: o.plugins?.legend?.labels?.color,
        xTicksColor: o.scales?.x?.ticks?.color,
        yTicksColor: o.scales?.y?.ticks?.color,
        xGridColor: o.scales?.x?.grid?.color,
        yGridColor: o.scales?.y?.grid?.color,
        datalabelsColor: o.plugins?.datalabels?.color,
    };

    if (o.plugins?.legend?.labels) {
        o.plugins.legend.labels.color = "#1f2937";
    }
    ["x", "y"].forEach((axis) => {
        if (o.scales?.[axis]?.ticks) {
            o.scales[axis].ticks.color = "#4b5563";
        }
        if (o.scales?.[axis]?.grid) {
            o.scales[axis].grid.color = "rgba(148, 163, 184, 0.35)";
        }
    });
    if (o.plugins?.datalabels) {
        o.plugins.datalabels.color = (ctx) => {
            const t = ctx.chart?.config?.type;
            if (t === "doughnut" || t === "pie") {
                return "#f8fafc";
            }
            return "#111827";
        };
    }

    return () => {
        if (
            o.plugins?.legend?.labels &&
            backup.legendLabelsColor !== undefined
        ) {
            o.plugins.legend.labels.color = backup.legendLabelsColor;
        }
        ["x", "y"].forEach((axis) => {
            if (
                o.scales?.[axis]?.ticks &&
                backup[`${axis}TicksColor`] !== undefined
            ) {
                o.scales[axis].ticks.color = backup[`${axis}TicksColor`];
            }
            if (
                o.scales?.[axis]?.grid &&
                backup[`${axis}GridColor`] !== undefined
            ) {
                o.scales[axis].grid.color = backup[`${axis}GridColor`];
            }
        });
        if (o.plugins?.datalabels && backup.datalabelsColor !== undefined) {
            o.plugins.datalabels.color = backup.datalabelsColor;
        }
        try {
            chart.update("none");
        } catch (e) {
            console.warn("applyLightThemeForExport restore update", e);
        }
    };
}

/**
 * Redesenho Chart.js com tolerância a falhas (evita «Cannot set properties of undefined (fullSize)»).
 */
export function safeChartUpdate(chart) {
    if (!chart) {
        return false;
    }
    try {
        chart.update("none");
        return true;
    } catch (e) {
        console.warn("safeChartUpdate", e);
        return false;
    }
}

/**
 * Redimensiona o gráfico com tolerância a falhas.
 */
export function safeChartResize(chart) {
    if (!chart) {
        return false;
    }
    try {
        chart.resize();
        return true;
    } catch (e) {
        console.warn("safeChartResize", e);
        return false;
    }
}

const EXPORT_COLORS = {
    accent: "#0d9488",
    accentDark: "#115e59",
    headerBg: "#ffffff",
    headerBorder: "#cbd5e1",
    chartTitle: "#0f766e",
    city: "#0f172a",
    filter: "#475569",
    legendBg: "#f8fffe",
    legendStripe: "#ecfdf5",
    legendBorder: "#5eead4",
    legendHead: "#134e4a",
    legendColHead: "#64748b",
    legendLabel: "#1e293b",
    legendValue: "#0d9488",
    legendTotal: "#0f766e",
    legendDivider: "#99f6e4",
    footnote: "#64748b",
    footerBg: "#f8fafc",
    footerBorder: "#94a3b8",
    footerText: "#475569",
};

const FONT_STACK = 'system-ui, -apple-system, "Segoe UI", sans-serif';

const LOGO_ASPECT = 1;
const LOGO_BRAND = "#2563eb";

/** Tokens de layout — manter alturas de cálculo e desenho alinhadas. */
const EXPORT_LAYOUT = {
    accentBarH: 6,
    pad: 36,
    logoMaxH: 40,
    logoGap: 14,
    /** Acima disto: legenda em duas colunas (melhor densidade sem PNG alto). */
    legendTwoColThreshold: 11,
    legendMaxRowsSingle: 20,
    legendMaxRegularTwoCol: 24,
    legendColGap: 28,
    legendRowH: 18,
    legendTitleH: 22,
    legendColHeadH: 16,
    legendTotalsGap: 10,
    legendBlockPad: 28,
    legendLabelMaxChars: 80,
    legendLabelMaxCharsTwoCol: 42,
};

const exportLogoCache = new Map();

/**
 * Pré-carrega a logomarca para o cabeçalho do PNG (mesma origem da página).
 *
 * @param {string} url
 * @returns {Promise<HTMLImageElement|null>}
 */
export function ensureExportLogo(url) {
    const src = String(url ?? "").trim();
    if (!src || typeof Image === "undefined") {
        return Promise.resolve(null);
    }

    const cached = exportLogoCache.get(src);
    if (cached?.image?.complete && cached.image.naturalWidth > 0) {
        return Promise.resolve(cached.image);
    }
    if (cached?.promise) {
        return cached.promise;
    }

    const promise = new Promise((resolve) => {
        const img = new Image();
        let settled = false;
        const finish = (result) => {
            if (settled) {
                return;
            }
            settled = true;
            exportLogoCache.set(src, {
                image: result,
                promise: Promise.resolve(result),
            });
            resolve(result);
        };

        img.onload = () => {
            finish(img.naturalWidth > 0 ? img : null);
        };
        img.onerror = () => finish(null);
        img.src = src;
        window.setTimeout(() => {
            finish(img.complete && img.naturalWidth > 0 ? img : null);
        }, 200);
    });

    exportLogoCache.set(src, { promise });
    return promise;
}

function exportLogoDimensions(maxH = EXPORT_LAYOUT.logoMaxH) {
    const height = maxH;
    const width = Math.round(height * LOGO_ASPECT);

    return { width, height };
}

function headerLogoColumn(pad, textMax) {
    const { width: logoW, height: logoH } = exportLogoDimensions();
    const gap = EXPORT_LAYOUT.logoGap;

    return {
        logoW,
        logoH,
        textX: pad + logoW + gap,
        textColumnW: Math.max(160, textMax - logoW - gap),
    };
}

/**
 * @param {CanvasRenderingContext2D} ctx
 */
function drawBrandLogoFallback(ctx, x, y, w, h) {
    const sx = w / 24;
    const sy = h / 24;
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(sx, sy);

    ctx.fillStyle = "#93c5fd";
    roundRect(ctx, 2, 14, 3.25, 6, 0.7);
    ctx.fill();

    ctx.fillStyle = "#3b82f6";
    roundRect(ctx, 6.5, 10.5, 3.25, 9.5, 0.7);
    ctx.fill();

    ctx.fillStyle = "#1d4ed8";
    roundRect(ctx, 11, 7, 3.25, 13, 0.7);
    ctx.fill();

    ctx.fillStyle = "#0d9488";
    ctx.beginPath();
    ctx.moveTo(11, 7);
    ctx.lineTo(14.25, 7);
    ctx.lineTo(14.625, 4.65);
    ctx.lineTo(17.25, 7);
    ctx.closePath();
    ctx.fill();

    ctx.strokeStyle = "#5eead4";
    ctx.lineWidth = 1.15;
    ctx.lineCap = "round";
    ctx.beginPath();
    ctx.moveTo(14.625, 4.65);
    ctx.lineTo(14.625, 6.2);
    ctx.stroke();

    ctx.strokeStyle = "#f59e0b";
    ctx.lineWidth = 2.05;
    ctx.lineCap = "round";
    ctx.beginPath();
    ctx.moveTo(3.25, 16.25);
    ctx.quadraticCurveTo(12.75, 4.5, 20.5, 8);
    ctx.stroke();

    ctx.fillStyle = "#fbbf24";
    ctx.beginPath();
    ctx.arc(20.5, 8, 2.85, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = "rgba(255, 247, 237, 0.6)";
    ctx.beginPath();
    ctx.arc(20.5, 8, 1.15, 0, Math.PI * 2);
    ctx.fill();

    ctx.restore();
}

function roundRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
}

/**
 * @param {CanvasRenderingContext2D} ctx
 * @param {HTMLImageElement|null} image
 */
function drawBrandLogo(ctx, image, x, y, maxH = EXPORT_LAYOUT.logoMaxH) {
    const { width: w, height: h } = exportLogoDimensions(maxH);

    if (image && image.naturalWidth > 0) {
        ctx.drawImage(image, x, y, w, h);
    } else {
        drawBrandLogoFallback(ctx, x, y, w, h);
    }

    return { width: w, height: h };
}

function countWrappedLines(text, maxWidth, font) {
    const ctx = document.createElement("canvas").getContext("2d");
    if (!ctx || !text) {
        return 1;
    }
    ctx.font = font;
    const words = String(text).split(/\s+/);
    let line = "";
    let n = 0;
    words.forEach((word) => {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            n += 1;
            line = word;
        } else {
            line = test;
        }
    });
    if (line) {
        n += 1;
    }
    return Math.max(1, n);
}

function drawWrappedLines(
    ctx,
    text,
    x,
    startY,
    maxWidth,
    lineHeight,
    font,
    color,
    align = "left",
) {
    const oldAlign = ctx.textAlign;
    ctx.textAlign = align;
    ctx.font = font;
    ctx.fillStyle = color;
    if (!text) {
        ctx.textAlign = oldAlign;
        return startY;
    }
    const words = String(text).split(/\s+/);
    let line = "";
    let y = startY;
    words.forEach((word) => {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            ctx.fillText(line, x, y);
            y += lineHeight;
            line = word;
        } else {
            line = test;
        }
    });
    if (line) {
        ctx.fillText(line, x, y);
        y += lineHeight;
    }
    ctx.textAlign = oldAlign;
    return y;
}

function formatLegendValue(v) {
    if (v === undefined || v === null || v === "") {
        return "";
    }
    if (typeof v === "number") {
        if (Number.isInteger(v)) {
            return v.toLocaleString("pt-BR");
        }
        return (Math.round(v * 10) / 10).toLocaleString("pt-BR");
    }
    return String(v);
}

/**
 * @param {object|null} payload
 * @returns {Array<{label: string, valueText: string, isTotal?: boolean}>}
 */
export function legendRowsFromPayload(payload) {
    if (!payload || typeof payload !== "object") {
        return [];
    }

    const labels = Array.isArray(payload.labels) ? payload.labels : [];
    const datasets = Array.isArray(payload.datasets) ? payload.datasets : [];
    const rows = [];

    if (labels.length && datasets.length === 1) {
        const data = datasets[0]?.data ?? [];
        labels.forEach((label, i) => {
            rows.push({
                label: String(label ?? ""),
                valueText: formatLegendValue(data[i]),
                isTotal: false,
            });
        });
    } else if (labels.length && datasets.length > 1) {
        datasets.forEach((ds, di) => {
            const series = String(ds?.label ?? `Série ${di + 1}`);
            const data = ds?.data ?? [];
            labels.forEach((label, i) => {
                rows.push({
                    label: `${String(label ?? "")} (${series})`,
                    valueText: formatLegendValue(data[i]),
                    isTotal: false,
                });
            });
        });
    }

    const fmt = (n) =>
        Number(n).toLocaleString("pt-BR", { maximumFractionDigits: 0 });
    if (payload.kpi_total !== undefined && payload.kpi_total !== null) {
        rows.push({
            label: String(payload.kpi_total_label || "Total no KPI"),
            valueText: fmt(payload.kpi_total),
            isTotal: true,
        });
    }
    if (
        payload.kpi_total_secondary !== undefined &&
        payload.kpi_total_secondary !== null
    ) {
        rows.push({
            label: String(
                payload.kpi_total_secondary_label || "Soma das barras",
            ),
            valueText: fmt(payload.kpi_total_secondary),
            isTotal: true,
        });
    }

    return rows.filter((r) => r.label.trim() !== "");
}

function normalizeLegendRows(rows) {
    if (!Array.isArray(rows)) {
        return [];
    }
    return rows
        .map((row) => ({
            label: String(row?.label ?? "").trim(),
            valueText: String(
                row?.valueText ?? formatLegendValue(row?.value),
            ).trim(),
            isTotal: Boolean(row?.isTotal),
        }))
        .filter((r) => r.label !== "");
}

function drawAccentBar(ctx, w) {
    ctx.fillStyle = EXPORT_COLORS.accent;
    ctx.fillRect(0, 0, w, EXPORT_LAYOUT.accentBarH);
}

/**
 * @param {Array<{label: string, valueText: string, isTotal?: boolean}>} rows
 */
function planLegendLayout(rows) {
    if (!rows.length) {
        return {
            useTwoCol: false,
            leftRows: [],
            rightRows: [],
            totals: [],
            hiddenCount: 0,
            innerH: 0,
            blockH: 0,
        };
    }

    const regular = rows.filter((r) => !r.isTotal);
    const totals = rows.filter((r) => r.isTotal);
    const useTwoCol = regular.length >= EXPORT_LAYOUT.legendTwoColThreshold;
    const rowH = EXPORT_LAYOUT.legendRowH;

    let leftRows = [];
    let rightRows = [];
    let hiddenCount = 0;
    let dataRowSlots = 0;

    if (useTwoCol) {
        const cap = EXPORT_LAYOUT.legendMaxRegularTwoCol;
        const visibleRegular = regular.slice(0, cap);
        hiddenCount = regular.length - visibleRegular.length;
        const half = Math.ceil(visibleRegular.length / 2);
        leftRows = visibleRegular.slice(0, half);
        rightRows = visibleRegular.slice(half);
        dataRowSlots = Math.max(leftRows.length, rightRows.length);
        if (hiddenCount > 0) {
            dataRowSlots += 1;
        }
    } else {
        const cap = Math.max(0, EXPORT_LAYOUT.legendMaxRowsSingle - totals.length);
        const visibleRegular = regular.slice(0, cap);
        hiddenCount = regular.length - visibleRegular.length;
        leftRows = visibleRegular;
        dataRowSlots = visibleRegular.length + totals.length;
        if (hiddenCount > 0) {
            dataRowSlots += 1;
        }
    }

    let innerH =
        EXPORT_LAYOUT.legendTitleH +
        EXPORT_LAYOUT.legendColHeadH +
        dataRowSlots * rowH +
        10;

    if (useTwoCol && totals.length > 0) {
        innerH += EXPORT_LAYOUT.legendTotalsGap + totals.length * rowH;
    }

    const blockH = innerH + EXPORT_LAYOUT.legendBlockPad;

    return {
        useTwoCol,
        leftRows,
        rightRows,
        totals: useTwoCol ? totals : [],
        singleRows: useTwoCol ? [] : [...leftRows, ...totals],
        hiddenCount,
        innerH,
        blockH,
    };
}

function drawHorizontalRule(ctx, y, w, color) {
    ctx.strokeStyle = color;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(w, y);
    ctx.stroke();
}

function drawLegendDivider(ctx, x, y, width) {
    ctx.strokeStyle = EXPORT_COLORS.legendDivider;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x + width, y);
    ctx.stroke();
}

/**
 * @param {CanvasRenderingContext2D} ctx
 */
function drawLegendDataRow(
    ctx,
    row,
    x,
    y,
    colWidth,
    rowH,
    rowFont,
    totalFont,
    maxLabel,
    stripeY,
    stripeLeft,
    stripeWidth,
    stripe,
) {
    const valueColX = x + colWidth - 4;
    const font = row.isTotal ? totalFont : rowFont;

    if (stripe && stripeWidth > 0 && !row.isTotal) {
        ctx.fillStyle = EXPORT_COLORS.legendStripe;
        ctx.fillRect(stripeLeft, stripeY, stripeWidth, rowH);
    }

    ctx.font = font;
    ctx.fillStyle = row.isTotal
        ? EXPORT_COLORS.legendTotal
        : EXPORT_COLORS.legendLabel;
    ctx.textAlign = "left";
    const label =
        row.label.length > maxLabel
            ? `${row.label.slice(0, maxLabel - 1)}…`
            : row.label;
    ctx.fillText(label, x, y);

    if (row.valueText) {
        ctx.fillStyle = row.isTotal
            ? EXPORT_COLORS.legendTotal
            : EXPORT_COLORS.legendValue;
        ctx.textAlign = "right";
        ctx.fillText(row.valueText, valueColX, y);
    }
    ctx.textAlign = "left";
}

/**
 * @param {CanvasRenderingContext2D} ctx
 */
function drawLegendColumnHeaders(ctx, x, y, colWidth, colHeadFont) {
    const valueColX = x + colWidth - 4;
    ctx.font = colHeadFont;
    ctx.fillStyle = EXPORT_COLORS.legendColHead;
    ctx.textAlign = "left";
    ctx.fillText("Indicador", x, y);
    ctx.textAlign = "right";
    ctx.fillText("Valor", valueColX, y);
    ctx.textAlign = "left";
}

/**
 * @param {CanvasRenderingContext2D} ctx
 * @param {Array<{label: string, valueText: string, isTotal?: boolean}>} rows
 */
function drawLegendTable(ctx, rows, x, y, width, blockLeft = 0, blockWidth = 0) {
    if (!rows.length) {
        return y;
    }

    const layout = planLegendLayout(rows);
    const rowH = EXPORT_LAYOUT.legendRowH;
    const headFont = `700 12px ${FONT_STACK}`;
    const colHeadFont = `600 10px ${FONT_STACK}`;
    const rowFont = `12px ${FONT_STACK}`;
    const totalFont = `700 12px ${FONT_STACK}`;
    const startY = y;

    ctx.fillStyle = EXPORT_COLORS.legendHead;
    ctx.font = headFont;
    ctx.textAlign = "left";
    ctx.fillText("Legenda de leituras", x, y);
    y += EXPORT_LAYOUT.legendTitleH;

    const dataTop = y + EXPORT_LAYOUT.legendColHeadH;

    if (layout.useTwoCol) {
        const colWidth = Math.floor(
            (width - EXPORT_LAYOUT.legendColGap) / 2,
        );
        const rightX = x + colWidth + EXPORT_LAYOUT.legendColGap;
        const maxLabel = EXPORT_LAYOUT.legendLabelMaxCharsTwoCol;
        const pairCount = Math.max(
            layout.leftRows.length,
            layout.rightRows.length,
        );

        drawLegendColumnHeaders(ctx, x, y, colWidth, colHeadFont);
        drawLegendColumnHeaders(ctx, rightX, y, colWidth, colHeadFont);
        y = dataTop;

        for (let i = 0; i < pairCount; i += 1) {
            const stripeY = y - rowH + 4;
            if (layout.leftRows[i]) {
                drawLegendDataRow(
                    ctx,
                    layout.leftRows[i],
                    x,
                    y,
                    colWidth,
                    rowH,
                    rowFont,
                    totalFont,
                    maxLabel,
                    stripeY,
                    blockLeft,
                    colWidth,
                    i % 2 === 0,
                );
            }
            if (layout.rightRows[i]) {
                drawLegendDataRow(
                    ctx,
                    layout.rightRows[i],
                    rightX,
                    y,
                    colWidth,
                    rowH,
                    rowFont,
                    totalFont,
                    maxLabel,
                    stripeY,
                    rightX,
                    colWidth,
                    i % 2 === 0,
                );
            }
            y += rowH;
        }

        if (layout.hiddenCount > 0) {
            ctx.font = rowFont;
            ctx.fillStyle = EXPORT_COLORS.footnote;
            ctx.fillText(
                `… e mais ${layout.hiddenCount} item(ns) — consulte o painel interativo`,
                x,
                y,
            );
            y += rowH;
        }

        if (layout.totals.length > 0) {
            y += EXPORT_LAYOUT.legendTotalsGap;
            drawLegendDivider(ctx, x, y - 6, width);
            layout.totals.forEach((row) => {
                drawLegendDataRow(
                    ctx,
                    row,
                    x,
                    y,
                    width,
                    rowH,
                    rowFont,
                    totalFont,
                    EXPORT_LAYOUT.legendLabelMaxChars,
                    y - rowH + 4,
                    blockLeft,
                    blockWidth,
                    false,
                );
                y += rowH;
            });
        }
    } else {
        const maxLabel = EXPORT_LAYOUT.legendLabelMaxChars;
        const valueColX = x + width - 4;

        ctx.font = colHeadFont;
        ctx.fillStyle = EXPORT_COLORS.legendColHead;
        ctx.fillText("Indicador", x, y);
        ctx.textAlign = "right";
        ctx.fillText("Valor", valueColX, y);
        ctx.textAlign = "left";
        y = dataTop;

        let drewTotalDivider = false;
        layout.singleRows.forEach((row, rowIndex) => {
            if (row.isTotal && !drewTotalDivider) {
                const hasRegularBefore = layout.singleRows
                    .slice(0, rowIndex)
                    .some((r) => !r.isTotal);
                if (hasRegularBefore) {
                    drawLegendDivider(ctx, x, y - 6, width);
                }
                drewTotalDivider = true;
            }

            drawLegendDataRow(
                ctx,
                row,
                x,
                y,
                width,
                rowH,
                rowFont,
                totalFont,
                maxLabel,
                y - rowH + 4,
                blockLeft,
                blockWidth,
                !row.isTotal && rowIndex % 2 === 0,
            );
            y += rowH;
        });

        if (layout.hiddenCount > 0) {
            ctx.font = rowFont;
            ctx.fillStyle = EXPORT_COLORS.footnote;
            ctx.fillText(
                `… e mais ${layout.hiddenCount} item(ns) — consulte o painel interativo`,
                x,
                y,
            );
            y += rowH;
        }
    }

    return startY + layout.innerH;
}

/**
 * Monta imagem final (cabeçalho + gráfico + legenda + rodapé) de forma síncrona.
 *
 * @param {object} meta - documentTitle, cityLine, filterLines[], footerLine, generatedAt
 * @param {object} [options] - subtitle, footnote, legendRows[]
 * @returns {{ dataUrl: string, width: number, height: number }}
 */
export function buildCompositeExport(chart, meta, chartTitle, options = {}) {
    const restore = applyLightThemeForExport(chart);
    try {
        safeChartResize(chart);
        safeChartUpdate(chart);

        const src = chart.canvas;
        let imgW = src.width;
        let imgH = src.height;
        if (!imgW || !imgH) {
            safeChartResize(chart);
            safeChartUpdate(chart);
            imgW = src.width;
            imgH = src.height;
        }
        if (!imgW || !imgH) {
            const rect = src.getBoundingClientRect?.() || { width: 0, height: 0 };
            imgW = Math.max(400, Math.round(rect.width) || 600);
            imgH = Math.max(240, Math.round(rect.height) || 360);
        }
        if ((!imgW || !imgH) && chart.chartArea) {
            const ca = chart.chartArea;
            const dpr =
                typeof window !== "undefined" ? window.devicePixelRatio || 1 : 1;
            if (ca.width > 0 && ca.height > 0) {
                imgW = Math.round(ca.width * dpr) || Math.round(ca.width);
                imgH = Math.round(ca.height * dpr) || Math.round(ca.height);
            }
        }
        if (!imgW || !imgH) {
            imgW = Math.max(400, Number(chart.width) || 600);
            imgH = Math.max(240, Number(chart.height) || 360);
        }

        const pad = EXPORT_LAYOUT.pad;
        const maxContentW = 1400;
        const innerW = Math.min(maxContentW, Math.max(800, imgW + pad * 2));
        const textMax = innerW - 2 * pad;

        if (imgW > textMax) {
            const s = textMax / imgW;
            imgW = Math.round(imgW * s);
            imgH = Math.round(imgH * s);
        }

        const subtitle = String(options.subtitle ?? "").trim();
        const footnote = String(options.footnote ?? "").trim();
        const legendRows = normalizeLegendRows(
            options.legendRows?.length
                ? options.legendRows
                : legendRowsFromPayload(options.payload),
        );

        const generatedLine =
            (meta.generatedAt && String(meta.generatedAt).trim()) ||
            formatFooterTimestampGmt3();

        const fontEyebrow = `700 11px ${FONT_STACK}`;
        const fontChart = `700 15px ${FONT_STACK}`;
        const fontSub = `13px ${FONT_STACK}`;
        const fontCity = `600 14px ${FONT_STACK}`;
        const fontFilter = `13px ${FONT_STACK}`;
        const fontFoot = `11px ${FONT_STACK}`;

        const logoColumn = headerLogoColumn(pad, textMax);
        const textX = logoColumn.textX;
        const textColumnW = logoColumn.textColumnW;

        const filterText = (meta.filterLines || [])
            .filter(Boolean)
            .map(String)
            .join(" · ");
        const linesFilter = filterText
            ? countWrappedLines(filterText, textColumnW, fontFilter)
            : 0;
        const linesSub = subtitle
            ? countWrappedLines(subtitle, textColumnW, fontSub)
            : 0;
        const linesFootnote = footnote
            ? countWrappedLines(footnote, textColumnW, fontSub)
            : 0;

        const legendLayout = planLegendLayout(legendRows);
        const legendH = legendLayout.blockH;

        const copyright =
            (meta.copyrightLine && String(meta.copyrightLine).trim()) ||
            (meta.appName && String(meta.appName).trim()) ||
            "";
        const powered =
            (meta.poweredByLine && String(meta.poweredByLine).trim()) || "";
        const footerMeta = [copyright, powered, generatedLine]
            .filter((s) => String(s || "").trim() !== "")
            .join(" · ");
        const linesFooter = countWrappedLines(footerMeta, textMax, fontFoot);

        const headerH = Math.max(
            EXPORT_LAYOUT.accentBarH +
                22 +
                logoColumn.logoH +
                8,
            EXPORT_LAYOUT.accentBarH +
                22 +
                24 +
                24 +
                (chartTitle ? 24 : 0) +
                linesSub * 16 +
                (meta.cityLine ? 22 : 0) +
                linesFilter * 16 +
                20,
        );

        const footnoteH = linesFootnote > 0 ? 14 + linesFootnote * 15 + 10 : 0;
        const footerH = 18 + linesFooter * 14 + 18;

        const w = innerW;
        const h = Math.ceil(
            headerH + imgH + 24 + legendH + footnoteH + footerH + 8,
        );

        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext("2d");
        if (!ctx) {
            throw new Error("Canvas unsupported");
        }
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, w, h);

        drawAccentBar(ctx, w);

        const headerTop = EXPORT_LAYOUT.accentBarH;
        const headerBottom = headerH;
        ctx.fillStyle = EXPORT_COLORS.headerBg;
        ctx.fillRect(0, headerTop, w, headerBottom - headerTop);
        drawHorizontalRule(ctx, headerBottom, w, EXPORT_COLORS.headerBorder);

        const logoImage = options.logoImage ?? null;
        const titleStartY = headerTop + 20;
        const x = pad;

        drawBrandLogo(ctx, logoImage, x, titleStartY - 2, logoColumn.logoH);

        let y = titleStartY;

        ctx.font = fontEyebrow;
        ctx.fillStyle = EXPORT_COLORS.accentDark;
        ctx.textAlign = "left";
        ctx.fillText(
            String(meta.documentTitle || "Análise educacional").toUpperCase(),
            textX,
            y,
        );
        y += 22;

        if (chartTitle) {
            y = drawWrappedLines(
                ctx,
                String(chartTitle).toUpperCase(),
                textX,
                y,
                textColumnW,
                19,
                fontChart,
                EXPORT_COLORS.chartTitle,
            );
            y += 6;
        }

        if (subtitle) {
            y = drawWrappedLines(
                ctx,
                subtitle,
                textX,
                y,
                textColumnW,
                16,
                fontSub,
                EXPORT_COLORS.filter,
            );
            y += 8;
        }

        if (meta.cityLine) {
            ctx.font = fontCity;
            ctx.fillStyle = EXPORT_COLORS.city;
            ctx.textAlign = "left";
            ctx.fillText(meta.cityLine, textX, y);
            y += 22;
        }

        if (filterText) {
            y = drawWrappedLines(
                ctx,
                `Recorte: ${filterText}`,
                textX,
                y,
                textColumnW,
                16,
                fontFilter,
                EXPORT_COLORS.filter,
            );
        }

        y = headerBottom + 18;
        const imgX = pad + (w - 2 * pad - imgW) / 2;
        if (!src.width || !src.height) {
            throw new Error(
                "Canvas do gráfico sem dimensões. Redimensione a janela ou mude de aba e tente de novo.",
            );
        }
        ctx.drawImage(src, imgX, y, imgW, imgH);
        y += imgH + 20;

        if (legendRows.length > 0) {
            const legendTop = y;
            const legendBlockH = legendLayout.blockH;
            ctx.fillStyle = EXPORT_COLORS.legendBg;
            ctx.fillRect(0, legendTop, w, legendBlockH);
            drawHorizontalRule(ctx, legendTop, w, EXPORT_COLORS.legendBorder);
            drawHorizontalRule(
                ctx,
                legendTop + legendBlockH,
                w,
                EXPORT_COLORS.legendBorder,
            );
            drawLegendTable(
                ctx,
                legendRows,
                x,
                legendTop + 14,
                textMax,
                0,
                w,
            );
            y = legendTop + legendBlockH + 10;
        }

        if (footnote) {
            y = drawWrappedLines(
                ctx,
                footnote,
                x,
                y + 4,
                textMax,
                14,
                fontSub,
                EXPORT_COLORS.footnote,
            );
            y += 10;
        }

        const footerTop = y;
        ctx.fillStyle = EXPORT_COLORS.footerBg;
        ctx.fillRect(0, footerTop, w, h - footerTop);
        drawHorizontalRule(ctx, footerTop, w, EXPORT_COLORS.footerBorder);

        ctx.textAlign = "left";
        drawWrappedLines(
            ctx,
            footerMeta,
            x,
            footerTop + 18,
            textMax,
            14,
            fontFoot,
            EXPORT_COLORS.footerText,
        );

        return {
            dataUrl: canvas.toDataURL("image/png", 1),
            width: canvas.width,
            height: canvas.height,
        };
    } finally {
        try {
            restore();
        } catch (e) {
            console.warn("buildCompositeExport restore", e);
        }
    }
}

/**
 * Export mínimo: só o canvas do Chart.js (sem cabeçalho), se o modo composto falhar.
 */
export function buildCanvasOnlyExport(chart) {
    const c = chart?.canvas;
    if (!c || !c.width || !c.height) {
        throw new Error("Canvas indisponível");
    }
    return c.toDataURL("image/png", 1);
}

export function triggerPngDownload(dataUrl, filenameBase) {
    const raw = String(filenameBase || "grafico").trim();
    const safe =
        raw.replace(/[^a-zA-Z0-9-_]/g, "_").replace(/^_+|_+$/g, "") || "grafico";
    const name = `${safe}.png`;

    const a = document.createElement("a");
    a.setAttribute("download", name);
    a.style.display = "none";

    let revokeUrl = null;
    try {
        if (typeof dataUrl === "string" && dataUrl.startsWith("data:")) {
            const parts = dataUrl.split(",");
            const b64 = parts[1];
            if (b64) {
                const mime =
                    parts[0].match(/data:([^;,]+)/)?.[1] || "image/png";
                const bin = atob(b64);
                const len = bin.length;
                const bytes = new Uint8Array(len);
                for (let i = 0; i < len; i++) {
                    bytes[i] = bin.charCodeAt(i);
                }
                const blob = new Blob([bytes], { type: mime });
                revokeUrl = URL.createObjectURL(blob);
                a.href = revokeUrl;
            } else {
                a.href = dataUrl;
            }
        } else {
            a.href = dataUrl;
        }
    } catch (e) {
        console.warn("triggerPngDownload blob fallback", e);
        a.href = dataUrl;
    }

    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
        if (revokeUrl) {
            URL.revokeObjectURL(revokeUrl);
        }
        a.remove();
    }, 400);
}
