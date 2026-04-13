import { jsPDF } from "jspdf";

/**
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
    chart.update("none");

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
        chart.update("none");
    };
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
) {
    ctx.font = font;
    ctx.fillStyle = color;
    if (!text) {
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
    return y;
}

/**
 * Monta imagem final (cabeçalho + gráfico) de forma síncrona, para o download PNG/PDF
 * funcionar no mesmo «gesto» do utilizador (evita promessas / decode assíncrono).
 *
 * @param {object} meta - documentTitle, cityLine, filterLines[], footerLine, generatedAt
 * @returns {{ dataUrl: string, width: number, height: number }}
 */
export function buildCompositeExport(chart, meta, chartTitle) {
    const restore = applyLightThemeForExport(chart);
    try {
        chart.update("none");
        const src = chart.canvas;
        let imgW = src.width;
        let imgH = src.height;

        const pad = 28;
        const maxContentW = 1320;
        const innerW = Math.min(maxContentW, Math.max(720, imgW + pad * 2));
        const textMax = innerW - 2 * pad;

        if (imgW > textMax) {
            const s = textMax / imgW;
            imgW = Math.round(imgW * s);
            imgH = Math.round(imgH * s);
        }

        const foot = [meta.footerLine, meta.generatedAt]
            .filter(Boolean)
            .join(" · ");

        const fontTitle =
            'bold 17px system-ui, -apple-system, "Segoe UI", sans-serif';
        const fontSub =
            '600 14px system-ui, -apple-system, "Segoe UI", sans-serif';
        const fontCity =
            '13px system-ui, -apple-system, "Segoe UI", sans-serif';
        const fontFoot =
            '11px system-ui, -apple-system, "Segoe UI", sans-serif';

        const linesSub = countWrappedLines(chartTitle || "", textMax, fontSub);
        const linesFoot = countWrappedLines(foot, textMax, fontFoot);

        let headerH = pad + 20;
        headerH += linesSub * 18 + 10;
        if (meta.cityLine) {
            headerH += 20;
        }
        (meta.filterLines || []).forEach(() => {
            headerH += 18;
        });
        headerH += 14;

        const footerH = linesFoot * 14 + pad;
        const w = innerW;
        const h = Math.ceil(headerH + imgH + 20 + footerH);

        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext("2d");
        if (!ctx) {
            throw new Error("Canvas unsupported");
        }
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, w, h);

        let y = pad;
        ctx.font = fontTitle;
        ctx.fillStyle = "#111827";
        ctx.fillText(meta.documentTitle || "Relatório", pad, y);
        y += 24;

        y = drawWrappedLines(
            ctx,
            chartTitle || "",
            pad,
            y,
            textMax,
            18,
            fontSub,
            "#374151",
        );
        y += 10;

        ctx.font = fontCity;
        ctx.fillStyle = "#1f2937";
        if (meta.cityLine) {
            ctx.fillText(meta.cityLine, pad, y);
            y += 20;
        }
        (meta.filterLines || []).forEach((line) => {
            ctx.fillText(String(line), pad, y);
            y += 18;
        });
        y += 12;

        const imgX = pad + (w - 2 * pad - imgW) / 2;
        ctx.drawImage(src, imgX, y, imgW, imgH);
        y += imgH + 16;

        drawWrappedLines(ctx, foot, pad, y, textMax, 14, fontFoot, "#6b7280");

        return {
            dataUrl: canvas.toDataURL("image/png", 1),
            width: canvas.width,
            height: canvas.height,
        };
    } finally {
        restore();
    }
}

/**
 * PDF síncrono (import estático de jsPDF; sem Image.onload).
 *
 * @param {string} dataUrl
 * @param {number} pxWidth
 * @param {number} pxHeight
 * @param {string} filenameBase
 */
export function downloadPdfFromSizedDataUrl(
    dataUrl,
    pxWidth,
    pxHeight,
    filenameBase,
) {
    const doc = new jsPDF({
        orientation: "landscape",
        unit: "mm",
        format: "a4",
    });
    const pageW = doc.internal.pageSize.getWidth();
    const pageH = doc.internal.pageSize.getHeight();
    const margin = 8;
    const maxW = pageW - 2 * margin;
    const maxH = pageH - 2 * margin;
    const ratio = pxHeight / pxWidth;
    let rw = maxW;
    let rh = rw * ratio;
    if (rh > maxH) {
        rh = maxH;
        rw = rh / ratio;
    }
    const x = margin + (maxW - rw) / 2;
    const y = margin + (maxH - rh) / 2;
    doc.addImage(dataUrl, "PNG", x, y, rw, rh);
    const safe = (filenameBase || "grafico").replace(/[^a-zA-Z0-9-_]/g, "_");
    doc.save(`${safe}.pdf`);
}

export function triggerPngDownload(dataUrl, filenameBase) {
    const a = document.createElement("a");
    const safe = (filenameBase || "grafico").replace(/[^a-zA-Z0-9-_]/g, "_");
    a.download = `${safe}.png`;
    a.href = dataUrl;
    a.click();
}
