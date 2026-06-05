/**
 * Tooltip HTML posicionado no contentor do canvas — fiável com scroll, zoom desligado e barras finas.
 */

function ensureTooltipEl(chart) {
    const parent = chart.canvas?.parentNode;
    if (!parent) {
        return null;
    }
    if (getComputedStyle(parent).position === "static") {
        parent.style.position = "relative";
    }
    let el = parent.querySelector(":scope > .chart-external-tooltip");
    if (!el) {
        el = document.createElement("div");
        el.className = "chart-external-tooltip";
        el.setAttribute("role", "tooltip");
        parent.appendChild(el);
    }

    return el;
}

/**
 * @param {import("chart.js").TooltipModel} tooltip
 * @param {HTMLElement} el
 */
function renderTooltipContent(tooltip, el) {
    const title = (tooltip.title || []).filter(Boolean).join(" · ");
    const lines = [];
    (tooltip.body || []).forEach((body) => {
        (body.lines || []).forEach((line) => {
            if (line) {
                lines.push(line);
            }
        });
    });
    const footerLines = (tooltip.footer || []).filter(Boolean);

    if (!title && lines.length === 0 && footerLines.length === 0) {
        el.innerHTML = "";
        return;
    }

    const titleHtml = title
        ? `<p class="chart-external-tooltip__title">${escapeHtml(title)}</p>`
        : "";
    const bodyHtml = lines
        .map(
            (line) =>
                `<p class="chart-external-tooltip__line">${escapeHtml(line)}</p>`,
        )
        .join("");
    const footerHtml = footerLines
        .map(
            (line) =>
                `<p class="chart-external-tooltip__footer">${escapeHtml(line)}</p>`,
        )
        .join("");

    el.innerHTML =
        `${titleHtml}` +
        (bodyHtml ? `<div class="chart-external-tooltip__body">${bodyHtml}</div>` : "") +
        (footerHtml ? `<div class="chart-external-tooltip__foot">${footerHtml}</div>` : "");
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

/**
 * @param {{ chart: import("chart.js").Chart, tooltip: import("chart.js").TooltipModel }} context
 */
export function chartExternalTooltipHandler(context) {
    const { chart, tooltip } = context;
    const el = ensureTooltipEl(chart);
    if (!el) {
        return;
    }

    if (!tooltip || tooltip.opacity === 0) {
        el.style.opacity = "0";
        el.style.pointerEvents = "none";
        return;
    }

    renderTooltipContent(tooltip, el);

    const { offsetLeft: left, offsetTop: top } = chart.canvas;
    el.style.opacity = "1";
    el.style.pointerEvents = "none";
    el.style.left = `${left + tooltip.caretX}px`;
    el.style.top = `${top + tooltip.caretY}px`;
    el.style.transform = "translate(-50%, calc(-100% - 10px))";
}
