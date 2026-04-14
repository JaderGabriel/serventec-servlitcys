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

        const pad = 28;
        const maxContentW = 1320;
        const innerW = Math.min(maxContentW, Math.max(720, imgW + pad * 2));
        const textMax = innerW - 2 * pad;

        if (imgW > textMax) {
            const s = textMax / imgW;
            imgW = Math.round(imgW * s);
            imgH = Math.round(imgH * s);
        }

        const generatedLine =
            (meta.generatedAt && String(meta.generatedAt).trim()) ||
            formatFooterTimestampGmt3();
        const foot = [meta.footerLine, generatedLine]
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

        const headerBandPad = 14;
        const footerBandPad = 16;
        const footerH = footerBandPad + linesFoot * 14 + footerBandPad;

        const w = innerW;
        /** Altura aproximada do cabeçalho (texto + faixa), alinhada ao desenho abaixo. */
        const headerHApprox =
            headerBandPad +
            pad +
            20 +
            linesSub * 18 +
            10 +
            (meta.cityLine ? 20 : 0) +
            (meta.filterLines || []).length * 18 +
            headerBandPad +
            8;

        const h = Math.ceil(headerHApprox + imgH + 20 + footerH + 32);

        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext("2d");
        if (!ctx) {
            throw new Error("Canvas unsupported");
        }
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, w, h);

        let y = headerBandPad + pad;
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
        y += headerBandPad;

        const headerBg = "#f3f4f6";
        const headerBorder = "#e5e7eb";
        const headerFillH = y;
        ctx.save();
        ctx.globalCompositeOperation = "destination-over";
        ctx.fillStyle = headerBg;
        ctx.fillRect(0, 0, w, headerFillH);
        ctx.restore();
        ctx.strokeStyle = headerBorder;
        ctx.beginPath();
        ctx.moveTo(0, headerFillH);
        ctx.lineTo(w, headerFillH);
        ctx.stroke();

        const imgX = pad + (w - 2 * pad - imgW) / 2;
        if (!src.width || !src.height) {
            throw new Error(
                "Canvas do gráfico sem dimensões. Redimensione a janela ou mude de aba e tente de novo.",
            );
        }
        ctx.drawImage(src, imgX, y, imgW, imgH);
        y += imgH + 16;

        const footerTop = y;
        const footerBg = "#f9fafb";
        const footerBorder = "#e5e7eb";
        ctx.fillStyle = footerBg;
        ctx.fillRect(0, footerTop, w, h - footerTop);
        ctx.strokeStyle = footerBorder;
        ctx.beginPath();
        ctx.moveTo(0, footerTop);
        ctx.lineTo(w, footerTop);
        ctx.stroke();

        drawWrappedLines(
            ctx,
            foot,
            pad,
            footerTop + footerBandPad,
            textMax,
            14,
            fontFoot,
            "#64748b",
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
