import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "leaflet.markercluster";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";
import Chart from "chart.js/auto";
import { cartesianInteractionDefaults } from "./chartVisualDefaults.js";

/** Paleta multi-linha (estilo gráfico de linhas clássico — pontos visíveis por ano). */
const ENROLLMENT_SERIES_STYLES = [
    { color: "#2563eb", borderWidth: 3, pointRadius: 6, pointHoverRadius: 8 },
    { color: "#0d9488", borderWidth: 2.5, pointRadius: 5.5, pointHoverRadius: 7.5 },
    { color: "#7c3aed", borderWidth: 2.5, pointRadius: 5.5, pointHoverRadius: 7.5 },
    { color: "#ea580c", borderWidth: 2.5, pointRadius: 5.5, pointHoverRadius: 7.5 },
    { color: "#059669", borderWidth: 2.5, pointRadius: 5.5, pointHoverRadius: 7.5 },
];

function enrollmentChartIsDark() {
    return document.documentElement.classList.contains("dark");
}

function enrollmentChartTextColor() {
    return enrollmentChartIsDark() ? "#e2e8f0" : "#334155";
}

function enrollmentChartMutedColor() {
    return enrollmentChartIsDark() ? "#94a3b8" : "#64748b";
}

function enrollmentChartGridColor() {
    return enrollmentChartIsDark()
        ? "rgba(148,163,184,0.18)"
        : "rgba(100,116,139,0.16)";
}

function enrollmentChartFont(size = 12, weight = "500") {
    return {
        family: 'Outfit, "DM Sans", ui-sans-serif, system-ui, sans-serif',
        size,
        weight,
    };
}

function formatEnrollmentAxisValue(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return "";
    }
    if (Math.abs(n) >= 10000) {
        const k = n / 1000;
        return `${k >= 100 ? Math.round(k) : k.toFixed(1).replace(/\.0$/, "")}k`;
    }

    return n.toLocaleString("pt-BR");
}

function styleEnrollmentDataset(dataset, index) {
    const style =
        ENROLLMENT_SERIES_STYLES[index % ENROLLMENT_SERIES_STYLES.length];
    const color = style.color;
    const pointBorder = enrollmentChartIsDark() ? "#0f172a" : "#ffffff";
    const baseRadius = style.pointRadius;
    const hoverRadius = style.pointHoverRadius;

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
        pointBorderColor: pointBorder,
        pointBorderWidth: 2,
        pointHoverBackgroundColor: color,
        pointHoverBorderColor: pointBorder,
        pointHoverBorderWidth: 2.5,
        pointRadius: (ctx) => {
            const value = ctx.dataset.data?.[ctx.dataIndex];
            return value == null || Number.isNaN(Number(value)) ? 0 : baseRadius;
        },
        pointHoverRadius: (ctx) => {
            const value = ctx.dataset.data?.[ctx.dataIndex];
            return value == null || Number.isNaN(Number(value)) ? 0 : hoverRadius;
        },
    };
}

function enrollmentSeriesChartOptions() {
    const text = enrollmentChartTextColor();
    const muted = enrollmentChartMutedColor();
    const grid = enrollmentChartGridColor();

    return {
        ...cartesianInteractionDefaults(),
        responsive: true,
        maintainAspectRatio: false,
        layout: {
            padding: { top: 4, right: 4, bottom: 0, left: 0 },
        },
        plugins: {
            legend: {
                display: true,
                position: "bottom",
                align: "center",
                labels: {
                    color: text,
                    usePointStyle: true,
                    pointStyle: "circle",
                    boxWidth: 7,
                    boxHeight: 7,
                    padding: 10,
                    font: enrollmentChartFont(10, "600"),
                },
            },
            tooltip: {
                backgroundColor: enrollmentChartIsDark()
                    ? "rgba(15,23,42,0.94)"
                    : "rgba(255,255,255,0.98)",
                titleColor: text,
                bodyColor: muted,
                borderColor: enrollmentChartIsDark()
                    ? "rgba(51,65,85,0.9)"
                    : "rgba(226,232,240,0.95)",
                borderWidth: 1,
                padding: 12,
                boxPadding: 6,
                titleFont: enrollmentChartFont(12, "700"),
                bodyFont: enrollmentChartFont(11, "500"),
                footerFont: enrollmentChartFont(10, "500"),
                displayColors: true,
                usePointStyle: true,
                callbacks: {
                    title(items) {
                        const item = items?.[0];
                        if (!item) {
                            return "";
                        }

                        return `Ano ${item.label}`;
                    },
                    label(context) {
                        const value = context.parsed?.y;
                        if (value == null || Number.isNaN(value)) {
                            return `${context.dataset.label}: —`;
                        }

                        return `${context.dataset.label}: ${Number(value).toLocaleString("pt-BR")}`;
                    },
                },
            },
            datalabels: {
                display: false,
            },
        },
        scales: {
            x: {
                border: { display: false },
                grid: {
                    display: true,
                    color: grid,
                    drawTicks: false,
                },
                ticks: {
                    color: text,
                    font: enrollmentChartFont(11, "600"),
                    maxRotation: 0,
                    autoSkip: false,
                    padding: 8,
                },
                title: {
                    display: false,
                },
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: {
                    color: grid,
                    drawTicks: false,
                },
                ticks: {
                    color: muted,
                    font: enrollmentChartFont(10, "500"),
                    precision: 0,
                    maxTicksLimit: 5,
                    padding: 4,
                    callback: (value) => formatEnrollmentAxisValue(value),
                },
                title: {
                    display: false,
                },
            },
        },
    };
}

const BRAZIL_BOUNDS = L.latLngBounds(
    L.latLng(-35.5, -76.0),
    L.latLng(7.0, -30.0),
);

function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s ?? "");
    return d.innerHTML;
}

function nf(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) {
        return "—";
    }
    return Number(n).toLocaleString("pt-BR");
}

function formatScoreValue(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) {
        return "—";
    }
    return Number(n).toLocaleString("pt-BR", { maximumFractionDigits: 0 });
}

function formatCurrencyBrl(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) {
        return "—";
    }
    return Number(n).toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function moneyEqual(a, b, epsilon = 0.005) {
    if (a == null || b == null || Number.isNaN(Number(a)) || Number.isNaN(Number(b))) {
        return false;
    }
    return Math.abs(Number(a) - Number(b)) <= epsilon;
}

function financeNoteHtml(text, tone = "info") {
    if (!text) {
        return "";
    }
    return `<p class="serv-horizonte-muni-tooltip__finance-note serv-horizonte-muni-tooltip__finance-note--${tone}">${escapeHtml(text)}</p>`;
}

function formatPercentValue(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) {
        return null;
    }
    return `${Number(n).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}%`;
}

function formatVaafPerStudent(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) {
        return "—";
    }
    return (
        Number(n).toLocaleString("pt-BR", {
            style: "currency",
            currency: "BRL",
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + "/aluno"
    );
}

function nfCompact(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) {
        return "—";
    }
    const v = Number(n);
    if (v >= 1_000_000) {
        const m = v / 1_000_000;
        return `${m.toLocaleString("pt-BR", { maximumFractionDigits: 1 })}M`;
    }
    if (v >= 10_000) {
        return `${Math.round(v / 1000).toLocaleString("pt-BR")}k`;
    }
    return nf(v);
}


function muniPopulationPipelineHtml(m, matriculasOverride = null, matAnoOverride = undefined) {
    const fmt = (val) => (val != null && Number(val) > 0 ? nfCompact(val) : "—");
    const fmtMatriculas = (val) =>
        val != null && Number(val) > 0
            ? matriculasOverride != null
                ? nf(val)
                : nfCompact(val)
            : "—";
    const yearLine = (year) =>
        year != null
            ? `<span class="serv-horizonte-muni-tooltip__pipe-year">${escapeHtml(String(year))}</span>`
            : `<span class="serv-horizonte-muni-tooltip__pipe-year serv-horizonte-muni-tooltip__pipe-year--empty">—</span>`;
    const cell = (val, label, formatter = fmt) =>
        `<div class="serv-horizonte-muni-tooltip__pipe-cell">` +
        `<span class="serv-horizonte-muni-tooltip__pipe-val">${formatter(val)}</span>` +
        `<span class="serv-horizonte-muni-tooltip__pipe-label">${escapeHtml(label)}</span>` +
        `</div>`;
    const matriculas =
        matriculasOverride != null
            ? matriculasOverride
            : m.matriculas_censo ?? m.fundeb_matriculas_base ?? null;
    const matAno =
        matAnoOverride !== undefined
            ? matAnoOverride
            : m.censo_ano ?? m.fundeb_ano ?? null;

    return (
        `<div class="serv-horizonte-muni-tooltip__pipeline">` +
        `<div class="serv-horizonte-muni-tooltip__pipe-step">` +
        cell(m.populacao_total, "Pop. municipal") +
        yearLine(m.demography_ano) +
        `</div>` +
        `<div class="serv-horizonte-muni-tooltip__pipe-arrow" aria-hidden="true">›</div>` +
        `<div class="serv-horizonte-muni-tooltip__pipe-step">` +
        cell(m.sidra_pop_4_17, "Pop. 4–17") +
        yearLine(m.demography_ano) +
        `</div>` +
        `<div class="serv-horizonte-muni-tooltip__pipe-arrow" aria-hidden="true">›</div>` +
        `<div class="serv-horizonte-muni-tooltip__pipe-step">` +
        cell(m.cadunico_escolar, "CadÚnico") +
        yearLine(m.cadunico_ano) +
        `</div>` +
        `<div class="serv-horizonte-muni-tooltip__pipe-arrow" aria-hidden="true">›</div>` +
        `<div class="serv-horizonte-muni-tooltip__pipe-step serv-horizonte-muni-tooltip__pipe-step--final">` +
        cell(matriculas, "Matrículas", fmtMatriculas) +
        yearLine(matAno) +
        `</div>` +
        `</div>` +
        `<p class="serv-horizonte-muni-tooltip__pipe-hint">${escapeHtml("População municipal → faixa escolar → CadÚnico → matrículas Censo INEP")}</p>`
    );
}

function muniPropensityThermometerHtml(m, th) {
    const score = Math.max(0, Math.min(100, Number(m.success_score ?? 0)));
    const level =
        score >= th.high ? "high" : score >= th.medium ? "medium" : score > 0 ? "low" : "none";
    const tier = String(m.tier_label ?? m.tier ?? "");
    const thresholdHint =
        score >= th.high
            ? `${escapeHtml("Alta")} ≥${th.high}`
            : score >= th.medium
              ? `${escapeHtml("Média")} ≥${th.medium}`
              : `${escapeHtml("Baixa")} <${th.medium}`;

    return (
        `<div class="serv-horizonte-muni-tooltip__propensity">` +
        `<div class="serv-horizonte-muni-tooltip__propensity-head">` +
        `<span class="serv-horizonte-muni-tooltip__propensity-title">${escapeHtml("Propensão ao sistema")}</span>` +
        `<span class="serv-horizonte-muni-tooltip__propensity-score">${formatScoreValue(score)}<span class="serv-horizonte-muni-tooltip__propensity-max">/100</span></span>` +
        `</div>` +
        `<div class="serv-horizonte-muni-tooltip__thermo" role="img" aria-label="${escapeHtml("Propensão")} ${formatScoreValue(score)}">` +
        `<div class="serv-horizonte-muni-tooltip__thermo-track">` +
        `<div class="serv-horizonte-muni-tooltip__thermo-fill serv-horizonte-muni-tooltip__thermo-fill--${level}" style="width:${score}%"></div>` +
        `<div class="serv-horizonte-muni-tooltip__thermo-mark serv-horizonte-muni-tooltip__thermo-mark--medium" style="left:${th.medium}%"></div>` +
        `<div class="serv-horizonte-muni-tooltip__thermo-mark serv-horizonte-muni-tooltip__thermo-mark--high" style="left:${th.high}%"></div>` +
        `</div>` +
        `<div class="serv-horizonte-muni-tooltip__thermo-scale"><span>0</span><span>${th.medium}</span><span>${th.high}</span><span>100</span></div>` +
        `</div>` +
        `<p class="serv-horizonte-muni-tooltip__propensity-tier">${escapeHtml(tier)} · ${thresholdHint}</p>` +
        `</div>`
    );
}

const HORIZONTE_FINANCE_ICONS = {
    reference: `<svg class="serv-horizonte-muni-tooltip__finance-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>`,
    repasses: `<svg class="serv-horizonte-muni-tooltip__finance-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.375M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>`,
    realtime: `<svg class="serv-horizonte-muni-tooltip__finance-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>`,
};

function financeSectionShell(tone, title, yearLabel, yearTag, iconHtml, bodyHtml, headLayout = "default") {
    const stackedHead = headLayout === "stacked" && yearLabel;
    const headMain = stackedHead
        ? `<div class="serv-horizonte-muni-tooltip__finance-head-main">` +
          `<span class="serv-horizonte-muni-tooltip__finance-icon-wrap" aria-hidden="true">${iconHtml}</span>` +
          `<div class="serv-horizonte-muni-tooltip__finance-head-titles">` +
          `<span class="serv-horizonte-muni-tooltip__finance-title">${escapeHtml(title)}</span>` +
          `<span class="serv-horizonte-muni-tooltip__finance-subtitle">${escapeHtml(yearLabel)}</span>` +
          `</div></div>`
        : `<div class="serv-horizonte-muni-tooltip__finance-head-main">` +
          `<span class="serv-horizonte-muni-tooltip__finance-icon-wrap" aria-hidden="true">${iconHtml}</span>` +
          `<span class="serv-horizonte-muni-tooltip__finance-title">${escapeHtml(title)}</span>` +
          `</div>`;
    const tag =
        !stackedHead && yearTag
            ? `<span class="serv-horizonte-muni-tooltip__finance-tag serv-horizonte-muni-tooltip__finance-tag--${escapeHtml(yearTag)}">${escapeHtml(yearLabel)}</span>`
            : !stackedHead && yearLabel
              ? `<span class="serv-horizonte-muni-tooltip__finance-year">${escapeHtml(yearLabel)}</span>`
              : "";

    return (
        `<section class="serv-horizonte-muni-tooltip__finance-step serv-horizonte-muni-tooltip__finance-step--${tone}">` +
        `<div class="serv-horizonte-muni-tooltip__finance-head${stackedHead ? " serv-horizonte-muni-tooltip__finance-head--stacked" : ""}">` +
        headMain +
        tag +
        `</div>` +
        bodyHtml +
        `</section>`
    );
}

function fundebReferenceHtml(m) {
    const ano = m.fundeb_ano;
    const vaaf = m.fundeb_vaaf;
    const receita = m.fundeb_receita_total;
    const compl = m.complementacao_fundeb;
    const hasVaaf = vaaf != null && Number(vaaf) > 0;
    const hasReceita = receita != null && Number(receita) > 0;
    const hasCompl = compl != null && Number(compl) > 0;
    const totalPrevisto =
        hasReceita && hasCompl
            ? Number(receita) + Number(compl)
            : hasReceita
              ? Number(receita)
              : null;
    if (ano == null && !hasVaaf && !hasReceita && !hasCompl) {
        return "";
    }

    const rows = [];
    if (hasVaaf) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("VAAF")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Valor por aluno/ano na portaria")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatVaafPerStudent(vaaf)}</span>` +
                `</div>`,
        );
    }
    if (hasReceita) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Receita vinculada")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("FUNDEB do município (ICMS, ISS e demais vinculados)")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(receita)}</span>` +
                `</div>`,
        );
    }
    if (hasCompl) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Complementação federal")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Repasse da União (VAAF + VAAT + VAAR)")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(compl)}</span>` +
                `</div>`,
        );
    }
    if (totalPrevisto != null && totalPrevisto > 0 && hasReceita && hasCompl) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Total previsto")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Receita vinculada + complementação federal")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(totalPrevisto)}</span>` +
                `</div>`,
        );
    }

    const yearLabel = ano != null ? String(ano) : "—";
    const footnote = financeNoteHtml(
        "Valores publicados pelo FNDE para planeamento do exercício. Não são o extrato bancário nem o que o Tesouro já pagou.",
        "reference",
    );

    return financeSectionShell(
        "reference",
        "Previsto na portaria",
        `${escapeHtml("Exercício")} ${escapeHtml(yearLabel)}`,
        "reference",
        HORIZONTE_FINANCE_ICONS.reference,
        `<p class="serv-horizonte-muni-tooltip__finance-step-lead">${escapeHtml("Quanto o FNDE estima que o município recebe de FUNDEB neste exercício.")}</p>` +
            `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>` +
            footnote,
    );
}

function transferTooltipHtml(m, refYear) {
    const total = m.transfer_total;
    const hasValue = total != null && Number(total) > 0;
    if (!hasValue) {
        return "";
    }

    const ano = m.transfer_ano ?? refYear;
    const fundeb = m.transfer_fundeb;
    const educacao = m.transfer_educacao;
    const fundebVal = fundeb != null ? Number(fundeb) : 0;
    const educVal = educacao != null ? Number(educacao) : 0;
    const totalVal = Number(total);
    const hasFundeb = fundebVal > 0;
    const educSameFundeb = hasFundeb && educVal > 0 && moneyEqual(educVal, fundebVal);
    const onlyFundebInCkan =
        hasFundeb &&
        moneyEqual(fundebVal, totalVal) &&
        (educVal <= 0 || educSameFundeb);
    const pctFundeb = formatPercentValue(m.transfer_pct_fundeb);
    const pctEduc = formatPercentValue(m.transfer_pct_educacao);
    const rows = [];
    let footnote = "";

    if (onlyFundebInCkan) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Repasse FUNDEB")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Único programa no CKAN importado (Tesouro)")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(fundebVal)}</span>` +
                `</div>`,
        );
        footnote = financeNoteHtml(
            "Só há FUNDEB neste extrato CKAN — total, repasse e verbas de educação coincidem por definição, não por erro de soma. Para PNAE, PNATE e PDDE, aguarde linhas adicionais no pacote ou use Finanças → Tempo Real na consultoria.",
            "repasses",
        );
    } else {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Total repasses")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Soma dos programas CKAN")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(totalVal)}</span>` +
                `</div>`,
        );

        if (hasFundeb) {
            rows.push(
                `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                    `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Repasse FUNDEB")}</span>` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Valor pago — Tesouro CKAN")}</span>` +
                    `</div>` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-value">` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(fundebVal)}</span>` +
                    (pctFundeb
                        ? `<span class="serv-horizonte-muni-tooltip__fundeb-pct serv-horizonte-muni-tooltip__fundeb-pct--rose">${escapeHtml(pctFundeb)} ${escapeHtml("do total")}</span>`
                        : "") +
                    `</span></div>`,
            );
        }

        if (educVal > 0 && !educSameFundeb) {
            rows.push(
                `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                    `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Verbas educação")}</span>` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("PNAE, PNATE, PDDE e afins")}</span>` +
                    `</div>` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-value">` +
                    `<span class="serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(educVal)}</span>` +
                    (pctEduc
                        ? `<span class="serv-horizonte-muni-tooltip__fundeb-pct serv-horizonte-muni-tooltip__fundeb-pct--blue">${escapeHtml(pctEduc)} ${escapeHtml("do total")}</span>`
                        : "") +
                    `</span></div>`,
            );
        } else if (educSameFundeb) {
            footnote = financeNoteHtml(
                "Verbas de educação omitidas: mesmo valor do repasse FUNDEB no CKAN (sem outros programas discriminados neste município).",
                "repasses",
            );
        }
    }

    return financeSectionShell(
        "repasses",
        "Pago pelo Tesouro",
        `${escapeHtml("Exercício")} ${escapeHtml(String(ano))}`,
        "previous",
        HORIZONTE_FINANCE_ICONS.repasses,
        `<p class="serv-horizonte-muni-tooltip__finance-step-lead">${escapeHtml("Quanto o Tesouro Transparente (CKAN) já transferiu ao município naquele ano — inclui FUNDEB e outras verbas de educação quando discriminadas.")}</p>` +
            `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>` +
            footnote,
    );
}

const FUNDEB_MONTH_LONG_PT = {
    jan: "janeiro",
    fev: "fevereiro",
    mar: "março",
    abr: "abril",
    mai: "maio",
    jun: "junho",
    jul: "julho",
    ago: "agosto",
    set: "setembro",
    out: "outubro",
    nov: "novembro",
    dez: "dezembro",
};

function formatOutlookDetailHtml(text) {
    const safe = escapeHtml(String(text ?? "").trim());
    if (!safe) {
        return "";
    }
    return safe.replace(
        /(R\$\s?[\d.]+,\d{2})/g,
        '<span class="serv-horizonte-muni-tooltip__money-inline">$1</span>',
    );
}

function formatFundebRealtimeHeadDate(m, currentYear) {
    const label = String(m.fundeb_realtime_last_transfer_label ?? "").trim();
    if (label !== "") {
        const match = /^([a-z]{3})\/(\d{4})$/i.exec(label);
        if (match) {
            const month = FUNDEB_MONTH_LONG_PT[match[1].toLowerCase()] ?? match[1];
            return `${month}/${match[2]}`;
        }
        return label;
    }
    const recordedAt = m.fundeb_realtime_last_recorded_at;
    if (recordedAt) {
        const parsed = new Date(recordedAt);
        if (!Number.isNaN(parsed.getTime())) {
            const month = parsed.toLocaleDateString("pt-BR", { month: "long" });
            const year = parsed.getFullYear();
            return `${month}/${year}`;
        }
    }
    const ano = m.fundeb_realtime_ano ?? currentYear;
    return ano != null ? String(ano) : "";
}

function formatFundebRealtimeTemporal(m) {
    const label = String(m.fundeb_realtime_last_transfer_label ?? "").trim();
    if (label !== "") {
        return label;
    }
    const recordedAt = m.fundeb_realtime_last_recorded_at;
    if (recordedAt) {
        const parsed = new Date(recordedAt);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleDateString("pt-BR", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        }
    }
    return null;
}

function fundebRealtimeObservedDesc(m) {
    const months = m.fundeb_realtime_months;
    const lastLabel = formatFundebRealtimeTemporal(m);
    if (lastLabel) {
        const monthPart =
            months != null && Number(months) > 0
                ? `${Number(months)} ${Number(months) === 1 ? "mês" : "meses"} com pagamento · `
                : "";
        return `${monthPart}último registo: ${lastLabel}`;
    }
    if (months != null && Number(months) > 0) {
        return `${Number(months)} ${Number(months) === 1 ? "mês" : "meses"} com pagamento no CKAN`;
    }
    return "Soma dos repasses FUNDEB importados do Tesouro";
}

function fundebRealtimeProjectionDesc(m) {
    const months = m.fundeb_realtime_months;
    const observed = m.fundeb_realtime_observed;
    if (observed != null && Number(observed) > 0 && months != null && Number(months) > 0) {
        return `Se o ritmo se mantiver: (já pago ÷ ${Number(months)} ${Number(months) === 1 ? "mês" : "meses"}) × 12. Estimativa, não valor oficial.`;
    }
    return "Estimativa provisória quando ainda há poucos meses de histórico no CKAN.";
}

function fundebRealtimeExpectedDesc(m) {
    const source = String(m.fundeb_realtime_expected_source ?? "");
    const compl = m.fundeb_realtime_portaria_complementacao_total;
    const hasCompl = compl != null && Number(compl) > 0;
    if (source === "portaria_receita") {
        return hasCompl
            ? "Teto anual da portaria FNDE (vinculada + complementação federal)"
            : "Teto anual da portaria FNDE (receita vinculada)";
    }
    return "Estimativa por matrículas × VAAF (sem portaria de receita)";
}

function fundebMatriculasFonteLabel(fonte) {
    const key = String(fonte ?? "").trim().toLowerCase();
    if (key === "ieducar") {
        return "Base FNDE no i-Educar (matrículas ativas)";
    }
    if (key === "censo_inep" || key.includes("censo")) {
        return "Base FNDE no Censo INEP (quando não há i-Educar)";
    }
    if (key !== "") {
        return `Base FNDE (${key})`;
    }
    return "Base de matrículas usada na portaria FNDE para o VAAF municipal";
}

function fundebComplementacaoFederalDesc(key) {
    const k = String(key ?? "").trim().toLowerCase();
    if (k === "complementacao_vaaf") {
        return "Complementação VAAF federal deste município na portaria (por ente, não estadual)";
    }
    if (k === "complementacao_vaat") {
        return "Complementação VAAT federal deste município na portaria (por ente, não estadual)";
    }
    if (k === "complementacao_vaar") {
        return "Complementação VAAR federal deste município na portaria (por ente, não estadual)";
    }
    return "Complementação federal deste município na portaria FNDE (por ente federado)";
}

function fundebPortariaBreakdownNote(m) {
    const ano = m.fundeb_realtime_portaria_ano ?? m.fundeb_ano ?? m.fundeb_realtime_ano;
    const ibge = m.ibge != null ? String(m.ibge) : "";
    const anoPart = ano != null ? `, exercício ${ano}` : "";
    const entePart =
        ibge !== ""
            ? `ente municipal (IBGE ${ibge})`
            : "ente municipal";
    return `Composição da portaria FNDE do ${entePart}${anoPart}. Matrículas e complementações federais abaixo são só deste município — não some valores do estado.`;
}

function fundebPortariaTetoLabel(m) {
    const complTotal = m.fundeb_realtime_portaria_complementacao_total;
    const totalPrevisto = m.fundeb_realtime_portaria_total_previsto ?? m.fundeb_realtime_expected;
    const receita = m.fundeb_realtime_portaria_receita;
    const hasFullTeto =
        totalPrevisto != null &&
        Number(totalPrevisto) > 0 &&
        receita != null &&
        complTotal != null &&
        Number(complTotal) > 0;

    return hasFullTeto ? "Teto total FUNDEB (portaria)" : "Total previsto (portaria)";
}

function fundebRealtimeBalanceDescHtml(m) {
    const aLabel = fundebPortariaTetoLabel(m);

    return (
        `<span class="serv-horizonte-muni-tooltip__fundeb-formula">Saldo = A − B</span>` +
        `. ` +
        `<span class="serv-horizonte-muni-tooltip__fundeb-formula-legend">` +
        `<strong>A</strong>: «${escapeHtml(aLabel)}» em «Previsto na portaria» · ` +
        `<strong>B</strong>: «Já pago pelo Tesouro» em «Pago pelo Tesouro»` +
        `</span>`
    );
}

function fundebRealtimePortariaBreakdownHtml(m) {
    const receita = m.fundeb_realtime_portaria_receita;
    const baseMat = m.fundeb_realtime_base_mat_vaaf;
    const vaaf = m.fundeb_realtime_vaaf;
    const matriculas = m.fundeb_realtime_matriculas;
    const matriculasFonte = m.fundeb_realtime_matriculas_fonte;
    const expectedSource = String(m.fundeb_realtime_expected_source ?? "");
    const adjustments = Array.isArray(m.fundeb_realtime_portaria_adjustments)
        ? m.fundeb_realtime_portaria_adjustments
        : [];
    const complTotal = m.fundeb_realtime_portaria_complementacao_total;
    const totalPrevisto = m.fundeb_realtime_portaria_total_previsto ?? m.fundeb_realtime_expected;
    const receitaVinculada =
        receita != null && Number(receita) > 0
            ? Number(receita)
            : baseMat != null && Number(baseMat) > 0
              ? Number(baseMat)
              : null;

    const municipalRows = [];
    if (matriculas != null && Number(matriculas) > 0) {
        municipalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Matrículas base")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebMatriculasFonteLabel(matriculasFonte))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${escapeHtml(nf(matriculas))}</span>` +
                `</div>`,
        );
    }
    if (vaaf != null && Number(vaaf) > 0) {
        municipalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("VAAF municipal")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Valor por aluno/ano do ente municipal na portaria")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatVaafPerStudent(vaaf)}</span>` +
                `</div>`,
        );
    }
    if (receita != null && Number(receita) > 0) {
        municipalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Receita vinculada")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("ICMS, ISS e demais receitas vinculadas à educação do município")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(receita)}</span>` +
                `</div>`,
        );
    } else if (baseMat != null && Number(baseMat) > 0 && matriculas != null && Number(matriculas) > 0) {
        municipalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Estimativa vinculada")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Matrículas base × VAAF (sem receita total na portaria)")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(baseMat)}</span>` +
                `</div>`,
        );
    }
    if (receitaVinculada != null && receitaVinculada > 0) {
        municipalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Receita vinculada prevista")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(expectedSource === "portaria_receita" ? "Parcela municipal na portaria FNDE" : "Estimativa da parcela municipal")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(receitaVinculada)}</span>` +
                `</div>`,
        );
    }

    const federalRows = [];
    for (const adj of adjustments) {
        const label = String(adj?.label ?? "").trim();
        const valueFmt = String(adj?.value_fmt ?? "").trim();
        const value = adj?.value;
        const key = adj?.key;
        if (!label) {
            continue;
        }
        federalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml(label)}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebComplementacaoFederalDesc(key))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${escapeHtml(valueFmt || (value != null ? formatCurrencyBrl(value) : "—"))}</span>` +
                `</div>`,
        );
    }
    if (complTotal != null && Number(complTotal) > 0 && federalRows.length === 0) {
        federalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Complementação federal")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("VAAF + VAAT + VAAR deste município na portaria")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(complTotal)}</span>` +
                `</div>`,
        );
    }
    if (complTotal != null && Number(complTotal) > 0 && federalRows.length > 1) {
        federalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Complementação federal total")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Soma VAAF + VAAT + VAAR deste município na portaria")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(complTotal)}</span>` +
                `</div>`,
        );
    }

    const totalRows = [];
    if (
        totalPrevisto != null &&
        Number(totalPrevisto) > 0 &&
        receitaVinculada != null &&
        complTotal != null &&
        Number(complTotal) > 0
    ) {
        totalRows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Teto total FUNDEB (portaria)")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Receita vinculada + complementação federal — referência da barra «Recebido do previsto»")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(totalPrevisto)}</span>` +
                `</div>`,
        );
    }

    if (municipalRows.length === 0 && federalRows.length === 0 && totalRows.length === 0) {
        return "";
    }

    const blocks = [];
    if (municipalRows.length > 0) {
        blocks.push(
            `<p class="serv-horizonte-muni-tooltip__fundeb-subsection-kicker">${escapeHtml("Ente municipal")}</p>` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${municipalRows.join("")}</div>`,
        );
    }
    if (federalRows.length > 0) {
        blocks.push(
            `<p class="serv-horizonte-muni-tooltip__fundeb-subsection-kicker">${escapeHtml("Complementação federal (deste município)")}</p>` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${federalRows.join("")}</div>`,
        );
    }
    if (totalRows.length > 0) {
        blocks.push(`<div class="serv-horizonte-muni-tooltip__fundeb-rows">${totalRows.join("")}</div>`);
    }

    return (
        `<div class="serv-horizonte-muni-tooltip__fundeb-subsection">` +
        `<p class="serv-horizonte-muni-tooltip__fundeb-subsection-title">${escapeHtml("Composição do previsto (portaria FNDE — município)")}</p>` +
        blocks.join("") +
        `<p class="serv-horizonte-muni-tooltip__fundeb-subsection-note">${escapeHtml(fundebPortariaBreakdownNote(m))}</p>` +
        `</div>`
    );
}

function fundebRealtimePortariaColumnHtml(m, currentYear) {
    const ano = m.fundeb_realtime_ano ?? currentYear;
    const expected = m.fundeb_realtime_expected;
    const hasExpected = expected != null && Number(expected) > 0;
    const portariaBreakdown = fundebRealtimePortariaBreakdownHtml(m);
    const showPortariaDetail = portariaBreakdown !== "";

    if (!hasExpected && !showPortariaDetail) {
        return "";
    }

    const rows = [];
    if (hasExpected && !showPortariaDetail) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Total previsto (portaria)")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebRealtimeExpectedDesc(m))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(expected)}</span>` +
                `</div>`,
        );
    }

    const body =
        (showPortariaDetail
            ? portariaBreakdown
            : rows.length > 0
              ? `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>`
              : "") +
        financeNoteHtml(
            "Composição e totais publicados pelo FNDE para o exercício em curso.",
            "reference",
        );

    return financeSectionShell(
        "reference",
        "Previsto na portaria",
        formatFundebRealtimeHeadDate(m, ano),
        "current",
        HORIZONTE_FINANCE_ICONS.reference,
        body,
        "stacked",
    );
}

function fundebRealtimeTesouroColumnHtml(m, currentYear) {
    const ano = m.fundeb_realtime_ano ?? currentYear;
    const observed = m.fundeb_realtime_observed;
    const expected = m.fundeb_realtime_expected;
    const projected = m.fundeb_realtime_projected;
    const balance = m.fundeb_realtime_balance;
    const pctDone = m.fundeb_realtime_pct_done;
    const outlook = String(m.fundeb_realtime_outlook ?? "unknown");
    const outlookLabel = String(m.fundeb_realtime_outlook_label ?? "");
    const hasObserved = observed != null && Number(observed) > 0;
    const hasExpected = expected != null && Number(expected) > 0;

    if (!hasObserved && !hasExpected) {
        return "";
    }

    const outlookDetail = String(m.fundeb_realtime_outlook_detail ?? "").trim();
    const outlookTone =
        outlook === "risk"
            ? "risk"
            : outlook === "surplus"
              ? "surplus"
              : outlook === "close"
                ? "close"
                : "unknown";
    const pctWidth = pctDone != null ? Math.max(0, Math.min(100, Number(pctDone))) : 0;
    const pctLabel = pctDone != null ? formatPercentValue(pctDone) : "—";

    const rows = [];
    if (hasObserved) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Já pago pelo Tesouro")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebRealtimeObservedDesc(m))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(observed)}</span>` +
                `</div>`,
        );
    }
    if (projected != null && Number(projected) > 0 && hasExpected) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Projeção até dezembro")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebRealtimeProjectionDesc(m))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(projected)}</span>` +
                `</div>`,
        );
    }
    if (balance != null && Number(balance) > 0) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Ainda a receber (indicativo)")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${fundebRealtimeBalanceDescHtml(m)}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--balance">${formatCurrencyBrl(balance)}</span>` +
                `</div>`,
        );
    }

    const progress =
        hasExpected && pctDone != null
            ? `<div class="serv-horizonte-muni-tooltip__finance-progress" role="img" aria-label="${escapeHtml("Recebido do previsto")} ${escapeHtml(pctLabel ?? "")}">` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-track">` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-fill serv-horizonte-muni-tooltip__finance-progress-fill--${outlookTone}" style="width:${pctWidth}%"></div>` +
              `</div>` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-meta">` +
              `<span>${escapeHtml("Recebido do previsto")} <strong>${escapeHtml(pctLabel ?? "—")}</strong></span>` +
              (outlookLabel ? `<span class="serv-horizonte-muni-tooltip__finance-outlook serv-horizonte-muni-tooltip__finance-outlook--${outlookTone}">${escapeHtml(outlookLabel)}</span>` : "") +
              `</div>` +
              (outlookDetail
                  ? `<p class="serv-horizonte-muni-tooltip__finance-outlook-detail">${formatOutlookDetailHtml(outlookDetail)}</p>`
                  : "") +
              `</div>`
            : "";

    const ckanBlock =
        rows.length > 0
            ? `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>`
            : `<p class="serv-horizonte-muni-tooltip__empty-msg">${escapeHtml("Ainda sem repasses importados para o exercício corrente.")}</p>`;

    return financeSectionShell(
        "repasses",
        "Pago pelo Tesouro",
        formatFundebRealtimeHeadDate(m, ano),
        "current",
        HORIZONTE_FINANCE_ICONS.repasses,
        `<p class="serv-horizonte-muni-tooltip__finance-step-lead">${escapeHtml("Repasses parciais do Tesouro Transparente (CKAN) no ano em curso.")}</p>` +
            ckanBlock +
            progress +
            financeNoteHtml(
                "Portaria = planejamento · CKAN = pagamentos observados.",
                "repasses",
            ),
        "stacked",
    );
}

function fundebRealtimeHtml(m, currentYear) {
    const ano = m.fundeb_realtime_ano ?? currentYear;
    const observed = m.fundeb_realtime_observed;
    const expected = m.fundeb_realtime_expected;
    const projected = m.fundeb_realtime_projected;
    const balance = m.fundeb_realtime_balance;
    const pctDone = m.fundeb_realtime_pct_done;
    const months = m.fundeb_realtime_months;
    const outlook = String(m.fundeb_realtime_outlook ?? "unknown");
    const outlookLabel = String(m.fundeb_realtime_outlook_label ?? "");
    const lastTemporal = formatFundebRealtimeTemporal(m);
    const hasObserved = observed != null && Number(observed) > 0;
    const hasExpected = expected != null && Number(expected) > 0;

    if (!hasObserved && !hasExpected) {
        return "";
    }

    const outlookDetail = String(m.fundeb_realtime_outlook_detail ?? "").trim();
    const outlookTone =
        outlook === "risk"
            ? "risk"
            : outlook === "surplus"
              ? "surplus"
              : outlook === "close"
                ? "close"
                : "unknown";
    const pctWidth = pctDone != null ? Math.max(0, Math.min(100, Number(pctDone))) : 0;
    const pctLabel = pctDone != null ? formatPercentValue(pctDone) : "—";

    const portariaBreakdown = fundebRealtimePortariaBreakdownHtml(m);
    const showPortariaDetail = portariaBreakdown !== "";

    const rows = [];
    if (hasExpected && !showPortariaDetail) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Total previsto (portaria)")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebRealtimeExpectedDesc(m))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(expected)}</span>` +
                `</div>`,
        );
    }
    if (hasObserved) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Já pago pelo Tesouro")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebRealtimeObservedDesc(m))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(observed)}</span>` +
                `</div>`,
        );
    }
    if (projected != null && Number(projected) > 0 && hasExpected) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Projeção até dezembro")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(fundebRealtimeProjectionDesc(m))}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(projected)}</span>` +
                `</div>`,
        );
    }
    if (balance != null && Number(balance) > 0) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Ainda a receber (indicativo)")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${fundebRealtimeBalanceDescHtml(m)}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--balance">${formatCurrencyBrl(balance)}</span>` +
                `</div>`,
        );
    }

    const progress =
        hasExpected && pctDone != null
            ? `<div class="serv-horizonte-muni-tooltip__finance-progress" role="img" aria-label="${escapeHtml("Recebido do previsto")} ${escapeHtml(pctLabel ?? "")}">` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-track">` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-fill serv-horizonte-muni-tooltip__finance-progress-fill--${outlookTone}" style="width:${pctWidth}%"></div>` +
              `</div>` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-meta">` +
              `<span>${escapeHtml("Recebido do previsto")} <strong>${escapeHtml(pctLabel ?? "—")}</strong></span>` +
              (outlookLabel ? `<span class="serv-horizonte-muni-tooltip__finance-outlook serv-horizonte-muni-tooltip__finance-outlook--${outlookTone}">${escapeHtml(outlookLabel)}</span>` : "") +
              `</div>` +
              (outlookDetail
                  ? `<p class="serv-horizonte-muni-tooltip__finance-outlook-detail">${formatOutlookDetailHtml(outlookDetail)}</p>`
                  : "") +
              `</div>`
            : "";

    const yearSubtitle = formatFundebRealtimeHeadDate(m, ano);

    const ckanBlock =
        rows.length > 0
            ? `<div class="serv-horizonte-muni-tooltip__fundeb-subsection serv-horizonte-muni-tooltip__fundeb-subsection--ckan">` +
              `<p class="serv-horizonte-muni-tooltip__fundeb-subsection-title">${escapeHtml("Pagamentos no ano (Tesouro CKAN)")}</p>` +
              `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>` +
              `</div>`
            : "";

    return financeSectionShell(
        "realtime",
        "Acompanhamento do Ano",
        yearSubtitle,
        "current",
        HORIZONTE_FINANCE_ICONS.realtime,
        `<p class="serv-horizonte-muni-tooltip__finance-step-lead">${escapeHtml("Compara o que a portaria FNDE prevê para o exercício em curso com o que o Tesouro já transferiu — valores parciais do ano (YTD).")}</p>` +
            portariaBreakdown +
            ckanBlock +
            progress +
            financeNoteHtml(
                "Portaria = planejamento · CKAN = pagamentos observados.",
                "realtime",
            ),
        "stacked",
    );
}

function financeTimelineConsultoriaNote(m, refYear) {
    const receita = m.fundeb_receita_total;
    const transferFundeb = m.transfer_fundeb;
    const hasReceita = receita != null && Number(receita) > 0;
    const hasTransfer = transferFundeb != null && Number(transferFundeb) > 0;

    if (hasReceita && hasTransfer && moneyEqual(receita, transferFundeb)) {
        return financeNoteHtml(
            `No exercício ${refYear}, a receita da portaria e o repasse CKAN coincidem em valor — são fontes diferentes (previsto × pago). Não some nem trate como duplicado.`,
            "consultoria",
        );
    }

    if (hasReceita && hasTransfer && !moneyEqual(receita, transferFundeb)) {
        return "";
    }

    return financeNoteHtml(
        "Resumo: portaria FNDE = teto previsto · Tesouro = pagamentos efetivos · ano em curso = acompanhamento YTD face ao previsto.",
        "consultoria",
    );
}

function muniDimensionMetaByKey(methodology) {
    const map = new Map();
    for (const dim of methodology?.dimensions ?? []) {
        if (dim?.key) {
            map.set(String(dim.key), dim);
        }
    }

    return map;
}

function muniDimensionsGlossaryHtml(dims, methodology) {
    const metaByKey = muniDimensionMetaByKey(methodology);
    const items = [];

    for (const d of dims) {
        const meta = metaByKey.get(d.key);
        if (!meta) {
            continue;
        }
        const label = String(meta.label ?? d.label);
        const weight = meta.weight ? `<span class="serv-horizonte-muni-tooltip__dim-glossary-weight">${escapeHtml(String(meta.weight))}</span>` : "";
        const formula = meta.formula ? String(meta.formula) : "";
        const detects = meta.detects ? String(meta.detects) : "";
        const indicates = meta.indicates ? String(meta.indicates) : "";

        items.push(
            `<div class="serv-horizonte-muni-tooltip__dim-glossary-item">` +
                `<p class="serv-horizonte-muni-tooltip__dim-glossary-term">${escapeHtml(label)}${weight}</p>` +
                (formula
                    ? `<p class="serv-horizonte-muni-tooltip__dim-glossary-desc">${escapeHtml(formula)}</p>`
                    : "") +
                (detects
                    ? `<p class="serv-horizonte-muni-tooltip__dim-glossary-detects"><span>${escapeHtml("Detecta:")}</span> ${escapeHtml(detects)}</p>`
                    : "") +
                (indicates
                    ? `<p class="serv-horizonte-muni-tooltip__dim-glossary-indicates"><span>${escapeHtml("Indica:")}</span> ${escapeHtml(indicates)}</p>`
                    : "") +
                `</div>`,
        );
    }

    if (items.length === 0) {
        return "";
    }

    return (
        `<div class="serv-horizonte-muni-tooltip__dims-glossary">` +
            `<p class="serv-horizonte-muni-tooltip__dims-glossary-title">${escapeHtml("O que significa cada dimensão")}</p>` +
            `<div class="serv-horizonte-muni-tooltip__dims-glossary-grid">${items.join("")}</div>` +
        `</div>`
    );
}

function muniDimensionsHtml(m, transferAno, methodology = null) {
    const dims = [
        { key: "financial_pressure", label: "Pressão FUNDEB" },
        { key: "pedagogical_gap", label: "Pedagógica" },
        { key: "scale_score", label: "Escala" },
        { key: "social_demand", label: "Social" },
        { key: "transfer_dependency", label: "Transf. fed." },
        { key: "data_readiness", label: "Prontidão" },
    ];
    const rows = [
        `<p class="serv-horizonte-muni-tooltip__dims-title">${escapeHtml("Dimensões (0–100)")}</p>`,
        `<div class="serv-horizonte-muni-tooltip__dims">`,
    ];
    for (const d of dims) {
        const val = Math.max(0, Math.min(100, Number(m[d.key] ?? 0)));
        const isTransfer = d.key === "transfer_dependency";
        const dimLabel =
            isTransfer && transferAno ? `${d.label} (${transferAno})` : d.label;
        const fillClass =
            isTransfer && val > 0
                ? "serv-horizonte-muni-tooltip__dim-fill--rose"
                : "serv-horizonte-muni-tooltip__dim-fill--blue";
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__dim-row">` +
                `<span class="serv-horizonte-muni-tooltip__dim-label">${escapeHtml(dimLabel)}</span>` +
                `<span class="serv-horizonte-muni-tooltip__dim-bar">` +
                `<span class="serv-horizonte-muni-tooltip__dim-fill ${fillClass}" style="width:${val}%"></span>` +
                `</span>` +
                `<span class="serv-horizonte-muni-tooltip__dim-val">${formatScoreValue(val)}</span>` +
                `</div>`,
        );
    }
    rows.push(`</div>`);
    rows.push(muniDimensionsGlossaryHtml(dims, methodology));

    return rows.join("");
}

function muniMetaHtml(m) {
    const segments = [];

    const sources = [
        m.has_fundeb ? "FUNDEB" : null,
        m.has_censo ? "Censo" : null,
        m.has_saeb ? "SAEB" : null,
        m.has_cadunico ? "CadÚnico" : null,
    ].filter(Boolean);
    if (sources.length > 0) {
        segments.push(
            `<span class="serv-horizonte-muni-tooltip__meta-item">` +
                `<span class="serv-horizonte-muni-tooltip__meta-label">${escapeHtml("Fontes")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__meta-value">${escapeHtml(sources.join(" · "))}</span>` +
                `</span>`,
        );
    }

    const sge = m.sge && typeof m.sge === "object" ? m.sge : null;
    if (sge) {
        segments.push(
            `<span class="serv-horizonte-muni-tooltip__meta-item">` +
                `<span class="serv-horizonte-muni-tooltip__meta-label">${escapeHtml("SGE")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__meta-value">${escapeHtml(sge.system_label || sge.system || "Desconhecido")}</span>` +
                `</span>`,
        );
    }

    if (segments.length === 0 && !m.analytics_url) {
        return "";
    }

    const line = segments.join("");
    const link = m.analytics_url
        ? `<a href="${escapeHtml(m.analytics_url)}" class="serv-horizonte-muni-tooltip__meta-link">${escapeHtml("Abrir consultoria")}</a>`
        : "";

    return `<div class="serv-horizonte-muni-tooltip__meta-row">${line}${link}</div>`;
}

// Base de conhecimento por tipo de pendência MEC/FNDE: explicação leiga + como resolver.
const HORIZONTE_ALERT_KNOWLEDGE = {
    vaat_inabilitado: {
        lay: "A rede municipal ficou impedida de receber a complementação VAAT do Fundeb (reforço federal por aluno) por falhas de transparência ou prestação de contas.",
        fix: "Regularizar o envio das informações ao SIOPE e a Matriz de Saldos Contábeis (MSC) ao Siconfi, dentro dos prazos do FNDE. A habilitação é reavaliada após a correção.",
    },
    vaar_nao_habilitado: {
        lay: "O município não foi habilitado à complementação VAAR do Fundeb (parcela paga por desempenho) porque não cumpriu as condicionalidades de melhoria da gestão exigidas.",
        fix: "Implementar e comprovar as condicionalidades do art. 14 da Lei 14.113/2020 (instrumentos de gestão, avaliação e regime de colaboração) e prestar as informações ao FNDE. A lista de habilitados é reeditada no exercício seguinte.",
    },
    pnae_suspenso: {
        lay: "O repasse do PNAE (verba federal da merenda escolar) está suspenso para o município, geralmente por pendência na prestação de contas.",
        fix: "Apresentar/regularizar a prestação de contas no SiGPC – Contas Online do FNDE e sanar as pendências apontadas pelo CAE/FNDE. Aprovada a análise, o repasse é reativado.",
    },
};

const HORIZONTE_ALERT_SEVERITY = {
    danger: { label: "Crítico", cls: "is-danger" },
    warning: { label: "Atenção", cls: "is-warning" },
    info: { label: "Informativo", cls: "is-info" },
};

function muniAlertsHtml(m) {
    const alerts = m && typeof m.muni_alerts === "object" ? m.muni_alerts : null;
    if (!alerts || alerts.status !== "found") {
        return "";
    }
    const items = Array.isArray(alerts.items) ? alerts.items : [];
    if (items.length === 0) {
        return "";
    }

    const order = { danger: 0, warning: 1, info: 2 };
    const sorted = [...items].sort(
        (a, b) =>
            (order[a?.severity] ?? 9) - (order[b?.severity] ?? 9),
    );

    const cards = sorted
        .map((item) => {
            const severity = HORIZONTE_ALERT_SEVERITY[item?.severity] || HORIZONTE_ALERT_SEVERITY.warning;
            const knowledge = HORIZONTE_ALERT_KNOWLEDGE[item?.kind] || null;
            const title = escapeHtml(String(item?.title || "Pendência MEC/FNDE"));
            const year = item?.exercise_year ? `<span class="serv-horizonte-muni-tooltip__alert-year">${escapeHtml(String(item.exercise_year))}</span>` : "";
            const detail = String(item?.detail || "").trim();
            const reason = detail !== ""
                ? `<p class="serv-horizonte-muni-tooltip__alert-line"><span class="serv-horizonte-muni-tooltip__alert-tag">Motivo oficial</span> ${escapeHtml(detail)}</p>`
                : "";
            const lay = knowledge?.lay
                ? `<p class="serv-horizonte-muni-tooltip__alert-line"><span class="serv-horizonte-muni-tooltip__alert-tag">O que significa</span> ${escapeHtml(knowledge.lay)}</p>`
                : "";
            const fix = knowledge?.fix
                ? `<p class="serv-horizonte-muni-tooltip__alert-line serv-horizonte-muni-tooltip__alert-line--fix"><span class="serv-horizonte-muni-tooltip__alert-tag">Como resolver</span> ${escapeHtml(knowledge.fix)}</p>`
                : "";
            const link = item?.detail_url
                ? `<a href="${escapeHtml(String(item.detail_url))}" target="_blank" rel="noopener noreferrer" class="serv-horizonte-muni-tooltip__alert-source">Fonte oficial ↗</a>`
                : "";

            return (
                `<li class="serv-horizonte-muni-tooltip__alert-item ${severity.cls}">` +
                `<div class="serv-horizonte-muni-tooltip__alert-head">` +
                `<span class="serv-horizonte-muni-tooltip__alert-title">${title}</span>` +
                `<span class="serv-horizonte-muni-tooltip__alert-sev">${escapeHtml(severity.label)}</span>` +
                year +
                `</div>` +
                reason +
                lay +
                fix +
                link +
                `</li>`
            );
        })
        .join("");

    const heading = items.length > 1
        ? `${escapeHtml("Pendências MEC/FNDE")} <span class="serv-horizonte-muni-tooltip__alert-count">${items.length}</span>`
        : escapeHtml("Pendência MEC/FNDE");

    return (
        `<section class="serv-horizonte-muni-tooltip__alerts">` +
        `<h4 class="serv-horizonte-muni-tooltip__alerts-title">${heading}</h4>` +
        `<ul class="serv-horizonte-muni-tooltip__alerts-list">${cards}</ul>` +
        `</section>`
    );
}

function muniMunicipalContextHtml(m, overlay = null) {
    if (!m) {
        return "";
    }

    const alerts = muniAlertsHtml(m);
    const matOverride =
        overlay != null &&
        String(overlay.ibge ?? "") === String(m.ibge ?? "") &&
        overlay.latestTotal != null
            ? overlay.latestTotal
            : null;
    const anoOverride =
        overlay != null &&
        String(overlay.ibge ?? "") === String(m.ibge ?? "") &&
        overlay.year != null
            ? overlay.year
            : undefined;
    const pipeline = muniPopulationPipelineHtml(m, matOverride, anoOverride);
    if (alerts === "" && pipeline === "") {
        return "";
    }

    return (
        `<div class="serv-horizonte-muni-tooltip__municipal-card">` +
        (alerts !== ""
            ? `<div class="serv-horizonte-muni-tooltip__municipal-card-alerts">${alerts}</div>`
            : "") +
        `<div class="serv-horizonte-muni-tooltip__municipal-card-pipeline">${pipeline}</div>` +
        `</div>`
    );
}

function financeYearRowHtml(variant, yearLabel, portariaHtml, tesouroHtml) {
    const variantClass =
        variant === "current"
            ? "serv-horizonte-muni-tooltip__finance-row--current"
            : "serv-horizonte-muni-tooltip__finance-row--previous";
    const title = variant === "current" ? escapeHtml("Ano vigente") : escapeHtml("Ano anterior");

    const col = (html, type, emptyMsg) => {
        if (html !== "") {
            return (
                `<div class="serv-horizonte-muni-tooltip__finance-row-col serv-horizonte-muni-tooltip__finance-row-col--${type}">` +
                html +
                `</div>`
            );
        }
        return (
            `<div class="serv-horizonte-muni-tooltip__finance-row-col serv-horizonte-muni-tooltip__finance-row-col--${type} serv-horizonte-muni-tooltip__finance-row-col--empty">` +
            `<p class="serv-horizonte-muni-tooltip__empty-msg">${escapeHtml(emptyMsg)}</p>` +
            `</div>`
        );
    };

    return (
        `<section class="serv-horizonte-muni-tooltip__finance-row ${variantClass}">` +
        `<div class="serv-horizonte-muni-tooltip__finance-row-head">` +
        `<span class="serv-horizonte-muni-tooltip__finance-row-title">${title}</span>` +
        `<span class="serv-horizonte-muni-tooltip__finance-row-badge">${escapeHtml(String(yearLabel))}</span>` +
        `</div>` +
        `<div class="serv-horizonte-muni-tooltip__finance-row-cols">` +
        col(portariaHtml, "reference", "Sem previsto na portaria FNDE.") +
        col(tesouroHtml, "repasses", "Sem repasses do Tesouro para este exercício.") +
        `</div>` +
        `</section>`
    );
}

function financePreviousYearRowHtml(m, refYear) {
    const reference = fundebReferenceHtml(m);
    const repasses = transferTooltipHtml(m, refYear);
    if (!reference && !repasses) {
        return "";
    }
    const yearLabel = m.transfer_ano ?? m.fundeb_ano ?? refYear;

    return financeYearRowHtml("previous", yearLabel, reference, repasses);
}

function financeCurrentYearRowHtml(m, refYear, currentYear) {
    if (currentYear <= refYear) {
        return "";
    }
    const portaria = fundebRealtimePortariaColumnHtml(m, currentYear);
    const tesouro = fundebRealtimeTesouroColumnHtml(m, currentYear);
    if (!portaria && !tesouro) {
        return "";
    }
    const yearLabel = m.fundeb_realtime_ano ?? currentYear;

    return financeYearRowHtml("current", yearLabel, portaria, tesouro);
}

function financeYearColumnShell(side, yearLabel, bodyHtml, emptyMsg) {
    const sideClass =
        side === "current"
            ? "serv-horizonte-muni-tooltip__year-col--current"
            : "serv-horizonte-muni-tooltip__year-col--previous";
    const title =
        side === "current" ? escapeHtml("Ano atual") : escapeHtml("Ano anterior");
    const content =
        bodyHtml !== ""
            ? `<div class="serv-horizonte-muni-tooltip__year-col-body">${bodyHtml}</div>`
            : `<div class="serv-horizonte-muni-tooltip__year-col-body serv-horizonte-muni-tooltip__year-col-body--empty"><p class="serv-horizonte-muni-tooltip__empty-msg">${escapeHtml(emptyMsg)}</p></div>`;

    return (
        `<section class="serv-horizonte-muni-tooltip__year-col ${sideClass}">` +
        `<div class="serv-horizonte-muni-tooltip__year-col-head">` +
        `<span class="serv-horizonte-muni-tooltip__year-col-title">${title}</span>` +
        `<span class="serv-horizonte-muni-tooltip__year-col-badge">${escapeHtml(String(yearLabel))}</span>` +
        `</div>` +
        content +
        `</section>`
    );
}

function financePreviousYearColumnHtml(m, refYear) {
    const reference = fundebReferenceHtml(m);
    const repasses = transferTooltipHtml(m, refYear);
    const body = [reference, repasses].filter(Boolean).join("");
    const yearLabel = m.transfer_ano ?? m.fundeb_ano ?? refYear;

    return financeYearColumnShell(
        "previous",
        yearLabel,
        body,
        "Sem referência FNDE nem repasses CKAN para o exercício anterior.",
    );
}

function financeCurrentYearColumnHtml(m, refYear, currentYear) {
    const realtime = currentYear > refYear ? fundebRealtimeHtml(m, currentYear) : "";
    const yearLabel = m.fundeb_realtime_ano ?? currentYear;

    return financeYearColumnShell(
        "current",
        yearLabel,
        realtime,
        currentYear > refYear
            ? "Sem repasses parciais importados para o exercício corrente."
            : "Ano corrente coincide com o exercício de referência — use a coluna anterior.",
    );
}

function financeTimelineHtml(m, refYear, currentYear) {
    const reference = fundebReferenceHtml(m);
    const repasses = transferTooltipHtml(m, refYear);
    const realtime = currentYear > refYear ? fundebRealtimeHtml(m, currentYear) : "";

    if (!reference && !repasses && !realtime) {
        if (m.has_transfers) {
            return `<p class="serv-horizonte-muni-tooltip__empty-msg">${escapeHtml("Repasses importados sem valor agregado para este município.")}</p>`;
        }
        return "";
    }

    const steps = [reference, repasses, realtime].filter(Boolean);
    let body = "";
    for (let i = 0; i < steps.length; i++) {
        body += steps[i];
        if (i < steps.length - 1) {
            body += `<div class="serv-horizonte-muni-tooltip__finance-connector" aria-hidden="true"></div>`;
        }
    }

    return (
        `<div class="serv-horizonte-muni-tooltip__finance">` +
        `<p class="serv-horizonte-muni-tooltip__finance-intro">${escapeHtml("Recursos da educação neste município — três leituras complementares (não some os valores entre blocos):")}</p>` +
        `<ul class="serv-horizonte-muni-tooltip__finance-legend">` +
        `<li><span class="serv-horizonte-muni-tooltip__finance-legend-dot serv-horizonte-muni-tooltip__finance-legend-dot--reference"></span>${escapeHtml("Portaria FNDE — quanto está previsto receber no exercício de referência")}</li>` +
        `<li><span class="serv-horizonte-muni-tooltip__finance-legend-dot serv-horizonte-muni-tooltip__finance-legend-dot--repasses"></span>${escapeHtml("Tesouro CKAN — quanto já foi pago naquele ano")}</li>` +
        `<li><span class="serv-horizonte-muni-tooltip__finance-legend-dot serv-horizonte-muni-tooltip__finance-legend-dot--realtime"></span>${escapeHtml("Ano em curso — pagamentos parciais face ao previsto da portaria")}</li>` +
        `</ul>` +
        body +
        financeTimelineConsultoriaNote(m, refYear) +
        `</div>`
    );
}

const HORIZONTE_TOUR_STORAGE_KEY = "horizonte_onboarding_v2";

function uniqueSortedUfs(markers) {
    return [...new Set(markers.map((m) => String(m.uf ?? "").trim()).filter(Boolean))].sort();
}

function heatColor(intensity) {
    const t = Math.max(0, Math.min(1, Number(intensity) || 0));
    if (t <= 0.5) {
        return lerpColor("#fef3c7", "#d97706", t / 0.5);
    }

    return lerpColor("#d97706", "#be123c", (t - 0.5) / 0.5);
}

const HEAT_CIRCLE_BORDER = {
    color: "#000000",
    weight: 1.25,
    opacity: 1,
};

function municipalHeatCircleStyle(intensity) {
    const t = Math.max(0, Math.min(1, Number(intensity) || 0));

    return {
        radius: 7 + t * 14,
        fillColor: heatColor(t),
        ...HEAT_CIRCLE_BORDER,
        fillOpacity: 0.28 + t * 0.62,
    };
}

const IBGE_PREFIX_TO_UF = {
    "11": "RO",
    "12": "AC",
    "13": "AM",
    "14": "RR",
    "15": "PA",
    "16": "AP",
    "17": "TO",
    "21": "MA",
    "22": "PI",
    "23": "CE",
    "24": "RN",
    "25": "PB",
    "26": "PE",
    "27": "AL",
    "28": "SE",
    "29": "BA",
    "31": "MG",
    "32": "ES",
    "33": "RJ",
    "35": "SP",
    "41": "PR",
    "42": "SC",
    "43": "RS",
    "50": "MS",
    "51": "MT",
    "52": "GO",
    "53": "DF",
};

const MESO_PALETTE = [
    "#2563eb",
    "#0d9488",
    "#d97706",
    "#7c3aed",
    "#db2777",
    "#0891b2",
    "#65a30d",
    "#c026d3",
    "#ea580c",
    "#4f46e5",
    "#059669",
    "#dc2626",
    "#0284c7",
    "#9333ea",
    "#ca8a04",
];

function isLeafletCanvasRenderer(layer) {
    if (layer == null || typeof layer !== "object") {
        return false;
    }

    if (typeof L.Canvas !== "undefined" && layer instanceof L.Canvas) {
        return true;
    }

    return layer._container instanceof HTMLCanvasElement;
}

function isLeafletSvgRenderer(layer) {
    if (layer == null || typeof layer !== "object") {
        return false;
    }

    if (typeof L.SVG !== "undefined" && layer instanceof L.SVG) {
        return true;
    }

    return layer._container instanceof SVGSVGElement;
}

function ibgeCodareaToUf(codarea) {
    const digits = String(codarea ?? "").replace(/\D/g, "");
    if (digits.length < 2) {
        return "";
    }

    return IBGE_PREFIX_TO_UF[digits.slice(0, 2)] ?? "";
}

function normalizeIbgeCodarea(value) {
    const digits = String(value ?? "").replace(/\D/g, "");
    if (digits.length === 0) {
        return "";
    }
    if (digits.length >= 7) {
        return digits.slice(0, 7);
    }

    return digits.padStart(7, "0");
}

function mesoPaletteColor(mesoId, index = 0) {
    const raw = String(mesoId ?? "");
    const hash = raw.split("").reduce((sum, ch) => sum + ch.charCodeAt(0), 0);

    return MESO_PALETTE[(hash + index) % MESO_PALETTE.length];
}

function quantizeGeoPoint(point, precision = 4) {
    const lng = Number(point?.[0]);
    const lat = Number(point?.[1]);
    if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
        return null;
    }
    const factor = 10 ** precision;

    return [
        Math.round(lng * factor) / factor,
        Math.round(lat * factor) / factor,
    ];
}

function extractMesoOuterRings(geometry) {
    if (!geometry || typeof geometry !== "object") {
        return [];
    }

    if (geometry.type === "Polygon") {
        const ring = geometry.coordinates?.[0];
        return Array.isArray(ring) && ring.length > 2 ? [ring] : [];
    }

    if (geometry.type === "MultiPolygon") {
        return (geometry.coordinates ?? [])
            .map((polygon) => polygon?.[0])
            .filter((ring) => Array.isArray(ring) && ring.length > 2);
    }

    return [];
}

function mesoRingEdgeKeys(ring, precision = 4) {
    const edges = new Set();
    if (!Array.isArray(ring) || ring.length < 2) {
        return edges;
    }

    for (let i = 0; i < ring.length - 1; i++) {
        const p1 = quantizeGeoPoint(ring[i], precision);
        const p2 = quantizeGeoPoint(ring[i + 1], precision);
        if (!p1 || !p2) {
            continue;
        }
        if (p1[0] === p2[0] && p1[1] === p2[1]) {
            continue;
        }

        const left =
            p1[0] < p2[0] || (p1[0] === p2[0] && p1[1] <= p2[1]) ? p1 : p2;
        const right = left === p1 ? p2 : p1;
        edges.add(`${left[0]},${left[1]}|${right[0]},${right[1]}`);
    }

    return edges;
}

function mesoFeatureBorderEdges(feature, precision = 4) {
    const edges = new Set();
    for (const ring of extractMesoOuterRings(feature?.geometry)) {
        for (const edge of mesoRingEdgeKeys(ring, precision)) {
            edges.add(edge);
        }
    }

    return edges;
}

function mesoRegionsShareBorder(edgesA, edgesB) {
    if (!edgesA?.size || !edgesB?.size) {
        return false;
    }

    for (const edge of edgesA) {
        if (edgesB.has(edge)) {
            return true;
        }
    }

    return false;
}

/** Garante cores distintas entre mesorregiões com fronteira comum (coloração gulosa). */
function buildMesoAdjacencyColorMap(geo) {
    const features = Array.isArray(geo?.features) ? geo.features : [];
    const ids = [];
    const edgesById = new Map();

    for (const feature of features) {
        const id = String(feature?.properties?.codarea ?? "").trim();
        if (id === "") {
            continue;
        }
        ids.push(id);
        edgesById.set(id, mesoFeatureBorderEdges(feature));
    }

    const adjacency = new Map(ids.map((id) => [id, new Set()]));
    for (let i = 0; i < ids.length; i++) {
        for (let j = i + 1; j < ids.length; j++) {
            const a = ids[i];
            const b = ids[j];
            if (mesoRegionsShareBorder(edgesById.get(a), edgesById.get(b))) {
                adjacency.get(a)?.add(b);
                adjacency.get(b)?.add(a);
            }
        }
    }

    const colorIndex = new Map();
    const order = [...ids].sort(
        (a, b) => (adjacency.get(b)?.size ?? 0) - (adjacency.get(a)?.size ?? 0),
    );

    for (const id of order) {
        const used = new Set();
        for (const neighbor of adjacency.get(id) ?? []) {
            if (colorIndex.has(neighbor)) {
                used.add(colorIndex.get(neighbor));
            }
        }

        let idx = 0;
        while (used.has(idx)) {
            idx++;
        }
        colorIndex.set(id, idx % MESO_PALETTE.length);
    }

    const colors = new Map();
    for (const id of ids) {
        colors.set(id, MESO_PALETTE[colorIndex.get(id) ?? 0]);
    }

    return colors;
}

function mesoChoroplethStyle(fillColor, intensity) {
    const t = Math.max(0.12, Number(intensity) || 0.12);

    return {
        fillColor,
        fillOpacity: 0.5 + t * 0.22,
        color: "#475569",
        weight: 1.65,
        opacity: 1,
    };
}

function mesoChoroplethHoverStyle(baseStyle) {
    return {
        ...baseStyle,
        weight: (baseStyle.weight ?? 1.1) + 0.65,
        fillOpacity: Math.min(0.68, (baseStyle.fillOpacity ?? 0.5) + 0.14),
        color: "#94a3b8",
        opacity: 0.95,
    };
}

function microRegionOverlayStyle(fillColor) {
    return {
        fillColor,
        fillOpacity: 0.07,
        color: fillColor,
        weight: 1.35,
        opacity: 0.5,
        dashArray: "5 4",
    };
}

function municipalBoundaryStyle() {
    return {
        fillColor: "#64748b",
        fillOpacity: 0.045,
        color: "#94a3b8",
        weight: 0.85,
        opacity: 0.42,
    };
}

function municipalBoundaryViewStyle({ highlighted = false } = {}) {
    return {
        fillColor: highlighted ? "#3b82f6" : "#64748b",
        fillOpacity: highlighted ? 0.24 : 0.14,
        color: "#0f172a",
        weight: highlighted ? 2 : 1.15,
        opacity: highlighted ? 0.98 : 0.82,
    };
}

function ufChoroplethStyle(intensity) {
    const t = Math.max(0.08, Number(intensity) || 0.08);

    return {
        fillColor: heatColor(t),
        fillOpacity: 0.58,
        color: "#1e293b",
        weight: 2,
        opacity: 1,
    };
}

function bindChoroplethInteractions(layer, baseStyle, tooltipHtml, hoverStyleFn = null) {
    if (tooltipHtml) {
        layer.bindTooltip(tooltipHtml, {
            direction: "top",
            sticky: true,
            opacity: 0.96,
            className: "serv-horizonte-geo-tooltip",
        });
    }

    layer.on("mouseover", () => {
        layer.setStyle(
            hoverStyleFn
                ? hoverStyleFn(baseStyle)
                : {
                      ...baseStyle,
                      weight: (baseStyle.weight ?? 1.75) + 1,
                      fillOpacity: Math.min(0.72, (baseStyle.fillOpacity ?? 0.5) + 0.18),
                      color: "#0f172a",
                      opacity: 1,
                  },
        );
        layer.bringToFront();
        if (tooltipHtml) {
            layer.openTooltip();
        }
    });
    layer.on("mouseout", () => {
        layer.setStyle(baseStyle);
        if (tooltipHtml) {
            layer.closeTooltip();
        }
    });
}

function ufOverviewTooltipHtml(p, ufLabelFn) {
    const ufLabel = escapeHtml(
        p.uf_name ? `${p.uf} — ${p.uf_name}` : ufLabelFn(p.uf),
    );

    return (
        `<strong>${ufLabel}</strong><br>` +
        `${nf(p.high_pressure ?? 0)} alta pressão · ${nf(p.high_prospect ?? 0)} alta propensão<br>` +
        `${nf(p.total)} com dados · ${nf(p.prospect_count)} prospectos<br>` +
        `<span class="text-slate-500">Clique para abrir camada municipal filtrada</span>`
    );
}

function mesoOverviewTooltipHtml(p) {
    const mesoLabel = escapeHtml(String(p.meso_name ?? p.meso_id ?? ""));

    return (
        `<strong>${mesoLabel}</strong><br>` +
        `${nf(p.high_pressure ?? 0)} alta pressão · ${nf(p.high_prospect ?? 0)} alta propensão<br>` +
        `${nf(p.total)} municípios · ${nf(p.prospect_count)} prospectos<br>` +
        `<span class="text-slate-500">Clique para abrir municípios desta região</span>`
    );
}

function addCapitalMarkers(layerGroup, points, pane = "") {
    for (const p of points) {
        const lat = Number(p.capital_lat ?? p.lat);
        const lng = Number(p.capital_lng ?? p.lng);
        if (!isValidCoord(lat, lng)) {
            continue;
        }

        const markerOpts = {
            radius: 7,
            fillColor: "#ffffff",
            color: "#f59e0b",
            weight: 1.5,
            fillOpacity: 0.92,
            opacity: 1,
            interactive: false,
            className: "serv-horizonte-capital-marker serv-horizonte-capital-marker--halo",
        };
        const coreOpts = {
            radius: 4,
            fillColor: "#f59e0b",
            color: "#ffffff",
            weight: 2,
            fillOpacity: 1,
            opacity: 1,
            interactive: false,
            className: "serv-horizonte-capital-marker serv-horizonte-capital-marker--core",
        };
        if (pane) {
            markerOpts.pane = pane;
            coreOpts.pane = pane;
        }

        L.circleMarker([lat, lng], markerOpts).addTo(layerGroup);

        L.circleMarker([lat, lng], coreOpts).addTo(layerGroup);
    }
}

function lerpColor(hexA, hexB, t) {
    const parse = (hex) => {
        const h = String(hex).replace("#", "");
        return {
            r: parseInt(h.slice(0, 2), 16),
            g: parseInt(h.slice(2, 4), 16),
            b: parseInt(h.slice(4, 6), 16),
        };
    };
    const a = parse(hexA);
    const b = parse(hexB);
    const u = Math.max(0, Math.min(1, Number(t) || 0));
    const r = Math.round(a.r + (b.r - a.r) * u);
    const g = Math.round(a.g + (b.g - a.g) * u);
    const bl = Math.round(a.b + (b.b - a.b) * u);

    return `rgb(${r},${g},${bl})`;
}

/** Pressão FUNDEB (0–100) como base do mapa de calor municipal. */
function pressureHeatRaw(marker) {
    const pressure = Number(marker?.financial_pressure);
    if (Number.isFinite(pressure) && pressure > 0) {
        return Math.max(0, Math.min(100, pressure));
    }
    const fromHeat = Number(marker?.heat_intensity);
    if (Number.isFinite(fromHeat) && fromHeat > 0) {
        return Math.max(0, Math.min(100, fromHeat * 100));
    }
    const success = Number(marker?.success_score);
    if (Number.isFinite(success) && success > 0) {
        return Math.max(0, Math.min(100, success));
    }

    return 0;
}

/**
 * Normaliza intensidades no recorte visível para haver contraste regional
 * (percentil linear; ranking quando as pressões são muito parecidas).
 *
 * @param {Array<{ ibge?: string|number }>} markers
 * @returns {Map<string, number>}
 */
function buildHeatIntensityMap(markers) {
    const entries = markers.map((marker) => ({
        marker,
        raw: pressureHeatRaw(marker),
    }));
    const ranked = entries
        .filter((entry) => entry.raw > 0)
        .sort((a, b) => a.raw - b.raw || String(a.marker.ibge).localeCompare(String(b.marker.ibge)));
    const map = new Map();

    if (ranked.length === 0) {
        for (const entry of entries) {
            map.set(String(entry.marker.ibge ?? ""), 0.08);
        }

        return map;
    }

    if (ranked.length === 1) {
        map.set(String(ranked[0].marker.ibge ?? ""), 0.82);
        for (const entry of entries) {
            const ibge = String(entry.marker.ibge ?? "");
            if (!map.has(ibge)) {
                map.set(ibge, 0.08);
            }
        }

        return map;
    }

    const min = ranked[0].raw;
    const max = ranked[ranked.length - 1].raw;
    const span = max - min;
    const useRank = span < 8;

    for (const entry of entries) {
        const ibge = String(entry.marker.ibge ?? "");
        if (entry.raw <= 0) {
            map.set(ibge, 0.06);
            continue;
        }

        if (useRank) {
            const rank = ranked.findIndex((row) => String(row.marker.ibge) === ibge);
            const t = rank / Math.max(1, ranked.length - 1);
            map.set(ibge, 0.14 + t * 0.86);
            continue;
        }

        const t = (entry.raw - min) / span;
        map.set(ibge, 0.12 + t * 0.88);
    }

    return map;
}

function isValidCoord(lat, lng) {
    return (
        Number.isFinite(lat) &&
        Number.isFinite(lng) &&
        lat >= -34.0 &&
        lat <= 5.5 &&
        lng >= -74.5 &&
        lng <= -32.0
    );
}

const APPROX_COORD_SOURCES = new Set(["uf_spread", "overview"]);

function isApproxCoord(marker) {
    if (!marker) {
        return false;
    }
    if (marker.coord_approximate) {
        return true;
    }
    return APPROX_COORD_SOURCES.has(String(marker.coord_source ?? ""));
}

function matchesHighPressure(marker, threshold) {
    if (!marker || marker.consultoria_active) {
        return false;
    }
    const tier = String(marker.tier ?? "");
    if (!tier.startsWith("prospect_")) {
        return false;
    }

    return tier === "prospect_high" || Number(marker.financial_pressure ?? 0) >= threshold;
}

/** Lentes de decisão — uma audiência por vez; refinamentos são camada separada. */
const DECISION_LENSES = {
    high_pressure: {
        label: "Alta pressão FUNDEB",
        short: "Alta pressão",
        color: "#be123c",
        overviewOk: true,
    },
    prospects: {
        label: "Todos prospectos",
        short: "Prospectos",
        color: "#b45309",
        overviewOk: false,
    },
    prospect_high: {
        label: "Alta propensão",
        short: "Alta propensão",
        color: "#be123c",
        overviewOk: false,
    },
    all: {
        label: "Todos os municípios",
        short: "Todos",
        color: "#64748b",
        overviewOk: false,
    },
    consultoria_active: {
        label: "Consultoria activa",
        short: "Consultoria",
        color: "#2563eb",
        overviewOk: false,
    },
    catalog_pending: {
        label: "Catálogo pendente",
        short: "Catálogo",
        color: "#ea580c",
        overviewOk: false,
    },
};

function lensAudiencePass(marker, viewPreset, filterTier, pressureThreshold) {
    const tier = String(marker?.tier ?? "");
    switch (viewPreset) {
        case "high_pressure":
            return matchesHighPressure(marker, pressureThreshold);
        case "prospects":
            return tier.startsWith("prospect_");
        case "prospect_high":
            return tier === "prospect_high";
        case "all":
            return true;
        case "custom":
            if (filterTier === "prospects") {
                return tier.startsWith("prospect_");
            }
            if (filterTier === "all") {
                return true;
            }
            return tier === filterTier;
        default:
            return tier.startsWith("prospect_");
    }
}

function buildMesoMapPointsFromMarkers(markers, pressureThreshold, nameCatalog = []) {
    const nameById = new Map(
        (Array.isArray(nameCatalog) ? nameCatalog : []).map((p) => [
            String(p.meso_id ?? ""),
            String(p.meso_name ?? p.meso_id ?? ""),
        ]),
    );
    const byMeso = new Map();

    for (const m of markers) {
        const mesoId = String(m.meso_id ?? "").trim();
        if (mesoId === "") {
            continue;
        }
        if (!byMeso.has(mesoId)) {
            byMeso.set(mesoId, {
                meso_id: mesoId,
                meso_name:
                    nameById.get(mesoId) ||
                    String(m.meso_name ?? "").trim() ||
                    mesoId,
                uf: String(m.uf ?? "").trim().toUpperCase(),
                total: 0,
                prospect_count: 0,
                high_prospect: 0,
                high_pressure: 0,
                lat_sum: 0,
                lng_sum: 0,
                coord_count: 0,
            });
        }
        const row = byMeso.get(mesoId);
        row.total += 1;
        const tier = String(m.tier ?? "");
        if (tier === "prospect_high") {
            row.high_prospect += 1;
        }
        if (matchesHighPressure(m, pressureThreshold)) {
            row.high_pressure += 1;
        }
        if (tier.startsWith("prospect_")) {
            row.prospect_count += 1;
        }
        const lat = Number(m.lat);
        const lng = Number(m.lng);
        if (isValidCoord(lat, lng)) {
            row.lat_sum += lat;
            row.lng_sum += lng;
            row.coord_count += 1;
        }
    }

    const points = [];
    for (const row of byMeso.values()) {
        const coordCount = row.coord_count;
        points.push({
            meso_id: row.meso_id,
            meso_name: row.meso_name,
            uf: row.uf,
            lat: coordCount > 0 ? row.lat_sum / coordCount : 0,
            lng: coordCount > 0 ? row.lng_sum / coordCount : 0,
            total: row.total,
            prospect_count: row.prospect_count,
            high_prospect: row.high_prospect,
            high_pressure: row.high_pressure,
            heat_intensity: Math.min(
                1,
                row.high_pressure / Math.max(1, row.prospect_count),
            ),
        });
    }

    return points.sort(
        (a, b) =>
            b.high_pressure - a.high_pressure ||
            b.high_prospect - a.high_prospect ||
            String(a.meso_name).localeCompare(String(b.meso_name), "pt-BR"),
    );
}

function markerVisualStyle(marker, colors) {
    const tier = String(marker?.tier ?? "");
    const fill = colors[tier] || "#64748b";
    const approx = isApproxCoord(marker);
    const sparse = tier === "data_sparse";

    return {
        radius: sparse ? 10 : approx ? 9 : 8,
        fillColor: fill,
        color: approx ? "#d97706" : sparse ? "#475569" : "#ffffff",
        weight: approx ? 2.5 : 2,
        dashArray: approx ? "4 3" : null,
        fillOpacity: sparse ? 0.98 : 0.92,
    };
}

export default function createHorizonteMap(markers = [], colors = {}, options = {}) {
    return {
        map: null,
        layer: null,
        clusterGroup: null,
        ufLayer: null,
        heatLayer: null,
        markerLayers: [],
        markers: Array.isArray(markers) ? markers : [],
        ufMapPoints: [],
        colors: colors && typeof colors === "object" ? colors : {},
        legend: Array.isArray(options.legend) ? options.legend : [],
        heatLegend: Array.isArray(options.heatLegend) ? options.heatLegend : [],
        summary: options.summary && typeof options.summary === "object" ? options.summary : {},
        ufRankings: Array.isArray(options.ufRankings) ? options.ufRankings : [],
        topProspects: Array.isArray(options.topProspects) ? options.topProspects : [],
        focusSegments: Array.isArray(options.focusSegments) ? options.focusSegments : [],
        sgeSummary: options.sgeSummary && typeof options.sgeSummary === "object" ? options.sgeSummary : {},
        refYear: Number(options.refYear) || new Date().getFullYear() - 1,
        currentYear: Number(options.currentYear) || new Date().getFullYear(),
        loadUrl: typeof options.loadUrl === "string" ? options.loadUrl : "",
        mapGeoUrl: typeof options.mapGeoUrl === "string" ? options.mapGeoUrl : "",
        mapGeoFallbackUrl:
            typeof options.mapGeoFallbackUrl === "string"
                ? options.mapGeoFallbackUrl
                : "",
        _geoCache: {},
        pageLoading: Boolean(options.loadUrl),
        regionalLoading: false,
        pendingRegionalUf: "",
        mapRefreshTimer: null,
        mapRefreshGeneration: 0,
        mapRendering: false,
        renderProgress: 0,
        loadingMessage: "",
        pageError: null,
        mapView: options.defaultViewFilter?.map_view ?? "heat",
        mapMode: "overview",
        scopeUf: "",
        loadedRegionalUf: "",
        scopeMeso: "",
        mesoMapPoints: [],
        meta: {},
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
        geoCoordCopied: false,
        _geoCoordCopiedTimer: null,
        ufSummaryOpen: false,
        cmdBarExpanded: false,
        _cmdDockObserver: null,
        _lastChoroplethLayer: null,
        choroplethLayer: null,
        microOverlayLayer: null,
        municipalBoundaryLayer: null,
        _choroplethPaneReady: false,
        _mapUserAdjustedView: false,
        _preserveViewOnNextRender: false,
        searchQuery: "",
        viewPreset: options.defaultViewFilter?.preset ?? "high_pressure",
        filterTier: options.defaultViewFilter?.tier ?? "prospects",
        displayPolicy: null,
        defaultViewFilter:
            options.defaultViewFilter && typeof options.defaultViewFilter === "object"
                ? options.defaultViewFilter
                : {},
        pressureThreshold: Number(
            options.defaultViewFilter?.pressure_min ??
                options.defaultViewFilter?.min_financial ??
                60,
        ),
        hideApproxOnMap: options.defaultViewFilter?.hide_approximate_on_map !== false,
        initialViewNotice: null,
        showAllOnMap: false,
        renderCapDismissed: false,
        mapRenderLimit: 400,
        regionalDisplayPolicy: null,
        filteredMarkersList: [],
        _filterSignature: "",
        _tooltipHtmlCache: {},
        canvasRenderer: null,
        mapRefreshDebounceMs: 150,
        minSuccessScore: 0,
        minBenefitScore: 0,
        minMatriculas: 0,
        minFinancial: 0,
        minPedagogical: 0,
        minReadiness: 0,
        minSocialDemand: 0,
        requireFundeb: options.defaultViewFilter?.require_fundeb === true,
        requireCenso: false,
        requireSaeb: false,
        requireCadunico: false,
        onlyMissingSge: false,
        onlyWithAlerts: false,
        hideConsultoria: options.defaultViewFilter?.hide_consultoria !== false,
        ufList: Array.isArray(options.ufList) ? options.ufList : [],
        nationalUfRankings: Array.isArray(options.ufRankings) ? options.ufRankings : [],
        nationalUfMapPoints: [],
        ufFundebInsights: null,
        ufNames:
            options.ufNames && typeof options.ufNames === "object" ? options.ufNames : {},
        prospectSort: "success_score",
        canRefreshData: Boolean(options.canRefreshData),
        canManageSge: Boolean(options.canManageSge),
        sgeShowUrl:
            typeof options.sgeShowUrl === "string" ? options.sgeShowUrl : "",
        sgeRegistryUrl:
            typeof options.sgeRegistryUrl === "string" ? options.sgeRegistryUrl : "",
        enrollmentSeriesUrl:
            typeof options.enrollmentSeriesUrl === "string"
                ? options.enrollmentSeriesUrl
                : "",
        enrollmentSeriesLoading: false,
        enrollmentSeriesError: null,
        enrollmentSeriesFootnote: "",
        enrollmentSeriesReady: false,
        enrollmentSeriesIbge: "",
        enrollmentSeriesDependencia: "total",
        enrollmentSeriesDependenciaOptions: [
            { value: "total", label: "Total" },
            { value: "municipal", label: "Municipal" },
            { value: "nao_municipal", label: "Não municipal" },
        ],
        _enrollmentSeriesLoadedDependencia: "",
        enrollmentSeriesStageCounters: [],
        enrollmentSeriesStageYear: null,
        enrollmentSeriesDependenciaLabel: "",
        enrollmentSeriesLatestTotal: null,
        _enrollmentSeriesChart: null,
        _enrollmentSeriesAbort: null,
        sgeFormOpen: false,
        sgeFormReadOnly: false,
        sgeFormSaving: false,
        sgeFormError: null,
        filterPanelOpen: false,
        filterDockOpen: false,
        filtersVisible: true,
        mapFullscreen: false,
        _fullscreenChangeHandler: null,
        _mapLayoutObserver: null,
        _mapLayoutFrame: null,
        _mapLayoutDebounceTimer: null,
        _mapLayoutSize: { w: 0, h: 0 },
        nationalOverviewSnapshot: null,
        methodologyPanelOpen: false,
        workspaceTab: "actions",
        guideOpen: false,
        tourActive: false,
        tourStepIndex: 0,
        tourSpotlightStyle: "display:none",
        tourCardStyle: "",
        tourResizeHandler: null,
        highlightIbge: "",
        methodology:
            options.methodology && typeof options.methodology === "object"
                ? options.methodology
                : {},
        sgeForm: {
            ibge: "",
            name: "",
            uf: "",
            system: "",
            vendor: "",
            notes: "",
            app_url: "",
            has_entry: false,
        },

        get totalMarkers() {
            return Number(this.summary?.total ?? this.meta?.marker_count ?? 0);
        },

        get kpiLoading() {
            return this.pageLoading || this.regionalLoading;
        },

        get tourStepsList() {
            return [
                {
                    target: '[data-horizonte-tour="kpi"]',
                    title: "Prioridade comercial",
                    text: "Indicadores do recorte actual — alta pressão FUNDEB, prospectos, consultoria activa e matrículas em prospecto.",
                },
                {
                    target: '[data-horizonte-tour="recorte"]',
                    title: "Recorte geográfico",
                    text: "Brasil em coroplético IBGE por UF. Escolha um estado para mesorregiões (UF extensa) ou municípios no mapa e na lista.",
                },
                {
                    target: '[data-horizonte-tour="segments"]',
                    title: "Segmentos rápidos",
                    text: "Atalhos de prospecção — cada cartão aplica um recorte típico e abre a lista filtrada.",
                },
                {
                    target: '[data-horizonte-tour="map"]',
                    title: "Mapa GIS",
                    text: "Hover em UF, mesorregião ou município para ver dados. Clique para navegar. Use «Resumo UF» para centrar o estado ou a região activa.",
                },
                {
                    target: '[data-horizonte-tour="filters"]',
                    title: "Filtros laterais",
                    text: "Lentes de decisão e refinamento — activos com UF aberta. No telemóvel use o botão «Filtros» sobre o mapa.",
                    openFilters: true,
                },
                {
                    target: '[data-horizonte-tour="rail"]',
                    title: "Próximas acções",
                    text: "Municípios para abordar primeiro e UFs prioritárias — clique para centrar ou abrir a UF.",
                },
                {
                    target: '[data-horizonte-tour="workspace"]',
                    title: "Área de trabalho",
                    text: "Lista de prospecção, cobertura de dados, metodologia com glossário Detecta/Indica e passos «Como usar».",
                },
            ];
        },

        get currentTourStep() {
            return this.tourStepsList[this.tourStepIndex] ?? null;
        },

        get filteredCount() {
            return this.filteredMarkersList.length;
        },

        get isOverviewMode() {
            return this.mapMode === "overview";
        },

        get isMesoOverviewMode() {
            return this.mapMode === "meso_overview";
        },

        get isRegionalMode() {
            return this.mapMode === "regional";
        },

        get isUfScopedMode() {
            return this.isMesoOverviewMode || this.isRegionalMode;
        },

        get activeMesoMapPoints() {
            if (!this.isMesoOverviewMode) {
                return this.mesoMapPoints;
            }

            const source =
                this.filteredMarkersList.length > 0
                    ? this.filteredMarkersList
                    : this.markers;

            return buildMesoMapPointsFromMarkers(
                source,
                this.pressureThreshold,
                this.mesoMapPoints,
            );
        },

        get mapMarkersForRender() {
            if (this.isOverviewMode || this.isMesoOverviewMode) {
                return [];
            }
            let list = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            if (this.hideApproxOnMap) {
                list = list.filter((m) => !isApproxCoord(m));
            }
            const limit = Number(this.mapRenderLimit) || 400;
            const safetyMax = Math.max(
                limit,
                Number(this.displayPolicy?.max_render_markers) || 800,
            );
            let rendered = [];
            if (!this.showAllOnMap && list.length > limit) {
                rendered = [...list]
                    .sort(
                        (a, b) =>
                            Number(b.success_score ?? 0) - Number(a.success_score ?? 0) ||
                            String(a.name).localeCompare(String(b.name), "pt-BR"),
                    )
                    .slice(0, limit);
            } else {
                rendered =
                    list.length > safetyMax ? list.slice(0, safetyMax) : list;
            }

            const pinnedIbge = String(this.highlightIbge ?? "").trim();
            const allValid = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            if (pinnedIbge !== "") {
                const pinnedInFilter = allValid.find(
                    (m) => String(m.ibge) === pinnedIbge,
                );
                const pinned =
                    pinnedInFilter ||
                    this.markers.find((m) => String(m.ibge) === pinnedIbge);
                if (
                    pinned &&
                    isValidCoord(Number(pinned.lat), Number(pinned.lng)) &&
                    !rendered.some((m) => String(m.ibge) === pinnedIbge)
                ) {
                    rendered = [pinned, ...rendered.slice(0, Math.max(0, limit - 1))];
                }
            }

            return rendered;
        },

        get mapRenderTruncated() {
            if (this.isOverviewMode) {
                return false;
            }
            const limit = Number(this.mapRenderLimit) || 400;
            const valid = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            return !this.showAllOnMap && valid.length > limit;
        },

        get canShowAllOnMap() {
            if (this.isOverviewMode || this.isMesoOverviewMode) {
                return false;
            }
            if (this.regionalDisplayPolicy?.allow_show_all === false) {
                return false;
            }
            const valid = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            const limit = Number(this.mapRenderLimit) || 400;
            return valid.length > limit;
        },

        get mapRenderShownCount() {
            return this.isOverviewMode
                ? this.ufMapPoints.length
                : this.mapMarkersForRender.length;
        },

        get mapHeavyDataset() {
            return Boolean(this.displayPolicy?.heavy_dataset);
        },

        get ufSelectOptions() {
            const fromNames = Object.keys(this.ufNames || {});
            const codes =
                fromNames.length > 0
                    ? fromNames
                    : this.nationalUfMapPoints.length > 0
                      ? this.nationalUfMapPoints.map((p) => p.uf)
                      : this.ufMapPoints.length > 0
                        ? this.ufMapPoints.map((p) => p.uf)
                        : this.ufRankings.map((r) => r.uf);
            return [...new Set(codes.map((uf) => String(uf ?? "").trim()).filter(Boolean))]
                .sort((a, b) => this.ufLabel(a).localeCompare(this.ufLabel(b), "pt-BR"))
                .map((uf) => ({
                    uf,
                    name: this.ufName(uf),
                    label: this.ufLabel(uf),
                }));
        },

        get approxHiddenOnMapCount() {
            if (!this.isRegionalMode || !this.hideApproxOnMap) {
                return 0;
            }

            return this.filteredMarkersList.filter(
                (m) =>
                    isValidCoord(Number(m.lat), Number(m.lng)) && isApproxCoord(m),
            ).length;
        },

        get decisionViewBanner() {
            const df = this.defaultViewFilter || {};
            if (this.isOverviewMode) {
                const totalPressure = this.ufMapPoints.reduce(
                    (sum, p) => sum + Number(p.high_pressure ?? 0),
                    0,
                );
                return {
                    kind: "overview",
                    title: "Visão executiva — alta pressão por UF",
                    message:
                        "Contornos = estados · cor = intensidade de alta pressão FUNDEB · passe o mouse para ver números. Clique num estado para abrir a camada municipal filtrada.",
                    count: totalPressure,
                    unit: "municípios de alta pressão (BR)",
                };
            }
            if (this.viewPreset === "high_pressure") {
                return {
                    kind: "regional",
                    title: df.label || "Alta pressão FUNDEB",
                    message:
                        df.description ||
                        `Prospectos com pressão financeira ≥ ${this.pressureThreshold} ou propensão alta — camada inicial para decisão.`,
                    count: this.filteredCount,
                    total: this.markers.length,
                    unit: "no recorte filtrado",
                };
            }

            return null;
        },

        get mapHiddenByFilters() {
            return (
                !this.pageLoading &&
                !this.regionalLoading &&
                this.isRegionalMode &&
                this.markers.length > 0 &&
                this.filteredMarkersList.length === 0
            );
        },

        get filteredMarkers() {
            return this.filteredMarkersList;
        },

        filterSignature() {
            return [
                this.markers.length,
                this.viewPreset,
                this.filterTier,
                this.minSuccessScore,
                this.minBenefitScore,
                this.minMatriculas,
                this.minFinancial,
                this.minPedagogical,
                this.minReadiness,
                this.minSocialDemand,
                this.requireFundeb,
                this.requireCenso,
                this.requireSaeb,
                this.requireCadunico,
                this.onlyMissingSge,
                this.onlyWithAlerts,
                this.hideConsultoria,
                this.searchQuery.trim().toLowerCase(),
                this.pressureThreshold,
                this.hideApproxOnMap,
            ].join("|");
        },

        recomputeFilteredMarkers() {
            const q = this.searchQuery.trim().toLowerCase();
            const mesoScope =
                this.isRegionalMode && this.scopeMeso
                    ? String(this.scopeMeso)
                    : "";
            this.filteredMarkersList = this.markers.filter((m) => {
                if (mesoScope !== "" && String(m.meso_id ?? "") !== mesoScope) {
                    return false;
                }
                if (this.hideConsultoria && m.consultoria_active) {
                    return false;
                }
                if (
                    !lensAudiencePass(
                        m,
                        this.viewPreset,
                        this.filterTier,
                        this.pressureThreshold,
                    )
                ) {
                    return false;
                }
                if (Number(m.success_score ?? 0) < this.minSuccessScore) {
                    return false;
                }
                if (Number(m.benefit_score ?? 0) < this.minBenefitScore) {
                    return false;
                }
                if (Number(m.matriculas_censo ?? 0) < this.minMatriculas) {
                    return false;
                }
                if (Number(m.financial_pressure ?? 0) < this.minFinancial) {
                    return false;
                }
                if (Number(m.pedagogical_gap ?? 0) < this.minPedagogical) {
                    return false;
                }
                if (Number(m.data_readiness ?? 0) < this.minReadiness) {
                    return false;
                }
                if (Number(m.social_demand ?? 0) < this.minSocialDemand) {
                    return false;
                }
                if (this.requireFundeb && !m.has_fundeb) {
                    return false;
                }
                if (this.requireCenso && !m.has_censo) {
                    return false;
                }
                if (this.requireSaeb && !m.has_saeb) {
                    return false;
                }
                if (this.requireCadunico && !m.has_cadunico) {
                    return false;
                }
                if (this.onlyMissingSge && (m.sge_found ?? false)) {
                    return false;
                }
                if (this.onlyMissingSge && (m.in_catalog ?? false)) {
                    return false;
                }
                if (this.onlyWithAlerts && (m.muni_alerts_status ?? "") !== "found") {
                    return false;
                }
                if (q === "") {
                    return true;
                }
                const hay = `${m.name} ${m.uf} ${m.ibge}`.toLowerCase();
                return hay.includes(q);
            });
        },

        applyRegionalRenderPolicy() {
            const policy =
                this.meta?.regional_display_policy &&
                typeof this.meta.regional_display_policy === "object"
                    ? this.meta.regional_display_policy
                    : null;
            this.applyRegionalRenderPolicyFromData(policy);
        },

        applyRegionalRenderPolicyFromData(policy) {
            if (!policy) {
                return;
            }
            this.regionalDisplayPolicy = policy;
            this.mapRenderLimit = Number(policy.max_render_markers) || this.mapRenderLimit;
            const prefer = String(policy.prefer_map_view || "");
            if (
                prefer === "markers" &&
                this.mapView === "heat" &&
                Number(policy.marker_count ?? 0) > Number(policy.heat_max ?? 220)
            ) {
                this.mapView = "markers";
            }
        },

        get sortedProspects() {
            const key = this.prospectSort;
            return [...this.filteredMarkersList]
                .filter((m) => String(m.tier || "").startsWith("prospect_"))
                .sort((a, b) => {
                    const av = Number(a[key] ?? 0);
                    const bv = Number(b[key] ?? 0);
                    return bv - av || String(a.name).localeCompare(String(b.name), "pt-BR");
                })
                .slice(0, 50);
        },

        get searchSuggestions() {
            const q = this.searchQuery.trim().toLowerCase();
            if (q.length < 2 || this.isOverviewMode) {
                return [];
            }
            return this.markers
                .filter((m) => {
                    const hay = `${m.name} ${m.uf} ${m.ibge}`.toLowerCase();
                    return hay.includes(q);
                })
                .slice(0, 12);
        },

        get coverage() {
            return this.summary?.coverage ?? {};
        },

        get scoreThresholds() {
            const t = this.methodology?.thresholds;
            return {
                high: Number(t?.high ?? 70),
                medium: Number(t?.medium ?? 40),
            };
        },

        get decisionLensKey() {
            if (this.viewPreset === "custom") {
                if (this.filterTier === "consultoria_active") {
                    return "consultoria_active";
                }
                if (this.filterTier === "catalog_pending") {
                    return "catalog_pending";
                }
                if (this.filterTier === "prospect_high") {
                    return "prospect_high";
                }
                return "custom";
            }
            return this.viewPreset || "high_pressure";
        },

        get decisionLensLabel() {
            const key = this.decisionLensKey;
            if (key === "custom") {
                return "Recorte personalizado";
            }
            return DECISION_LENSES[key]?.label ?? key;
        },

        get decisionLensOptions() {
            return Object.entries(DECISION_LENSES).map(([key, meta]) => ({
                key,
                ...meta,
                disabled: this.isOverviewMode && !meta.overviewOk,
            }));
        },

        get hasAdvancedFilters() {
            return (
                this.minSuccessScore > 0 ||
                this.minBenefitScore > 0 ||
                this.minMatriculas > 0 ||
                this.minFinancial > 0 ||
                this.minPedagogical > 0 ||
                this.minReadiness > 0 ||
                this.minSocialDemand > 0 ||
                this.requireFundeb ||
                this.requireCenso ||
                this.requireSaeb ||
                this.requireCadunico ||
                this.onlyMissingSge ||
                this.onlyWithAlerts ||
                this.searchQuery.trim() !== "" ||
                (this.viewPreset === "all" && this.hideConsultoria) ||
                !this.hideApproxOnMap
            );
        },

        get activeFilterCount() {
            let count = this.viewPreset === "custom" || this.hasAdvancedFilters ? 1 : 0;
            if (this.minSuccessScore > 0) count += 1;
            if (this.minBenefitScore > 0) count += 1;
            if (this.minMatriculas > 0) count += 1;
            if (this.minFinancial > 0) count += 1;
            if (this.minPedagogical > 0) count += 1;
            if (this.minReadiness > 0) count += 1;
            if (this.minSocialDemand > 0) count += 1;
            if (this.requireFundeb) count += 1;
            if (this.requireCenso) count += 1;
            if (this.requireSaeb) count += 1;
            if (this.requireCadunico) count += 1;
            if (this.onlyMissingSge) count += 1;
            if (this.onlyWithAlerts) count += 1;
            if (this.viewPreset === "all" && this.hideConsultoria) count += 1;
            if (!this.hideApproxOnMap) count += 1;
            if (this.searchQuery.trim() !== "") count += 1;
            return count;
        },

        get mapInteractionStats() {
            if (this.isMesoOverviewMode) {
                return {
                    onMap: this.activeMesoMapPoints.length,
                    approximate: 0,
                    sparse: 0,
                    total: this.filteredCount,
                };
            }
            if (!this.isRegionalMode) {
                return { onMap: 0, approximate: 0, sparse: 0 };
            }
            const valid = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            return {
                onMap: this.mapMarkersForRender.length,
                total: valid.length,
                approximate: valid.filter((m) => isApproxCoord(m)).length,
                sparse: valid.filter((m) => m.tier === "data_sparse").length,
            };
        },

        get activeFilterChips() {
            const chips = [];
            const tierLabels = {
                high_pressure: `Alta pressão FUNDEB ≥ ${this.pressureThreshold}`,
                prospects: "Todos prospectos",
                prospect_high: "Alta propensão",
                all: "Todos os municípios",
                consultoria_active: "Consultoria activa",
                catalog_pending: "Catálogo pendente",
                custom: "Recorte personalizado",
            };

            const lensKey = this.decisionLensKey;
            chips.push({
                key: "lens",
                label: tierLabels[lensKey] || this.decisionLensLabel,
                removable: false,
            });

            if (this.minSuccessScore > 0) {
                chips.push({ key: "success", label: `Propensão ≥ ${this.minSuccessScore}`, removable: true });
            }
            if (this.minBenefitScore > 0) {
                chips.push({ key: "benefit", label: `Benefício ≥ ${this.minBenefitScore}`, removable: true });
            }
            if (this.minMatriculas > 0) {
                chips.push({ key: "matriculas", label: `Matrículas ≥ ${nf(this.minMatriculas)}`, removable: true });
            }
            if (this.minFinancial > 0) {
                chips.push({ key: "financial", label: `Pressão FUNDEB ≥ ${this.minFinancial}`, removable: true });
            }
            if (this.minPedagogical > 0) {
                chips.push({ key: "pedagogical", label: `Déficit SAEB ≥ ${this.minPedagogical}`, removable: true });
            }
            if (this.minReadiness > 0) {
                chips.push({ key: "readiness", label: `Prontidão ≥ ${this.minReadiness}`, removable: true });
            }
            if (this.minSocialDemand > 0) {
                chips.push({ key: "social", label: `Demanda social ≥ ${this.minSocialDemand}`, removable: true });
            }
            if (this.requireFundeb) {
                chips.push({ key: "fundeb", label: "Com FUNDEB", removable: true });
            }
            if (this.requireCenso) {
                chips.push({ key: "censo", label: "Com Censo", removable: true });
            }
            if (this.requireSaeb) {
                chips.push({ key: "saeb", label: "Com SAEB", removable: true });
            }
            if (this.requireCadunico) {
                chips.push({ key: "cadunico", label: "Com CadÚnico", removable: true });
            }
            if (this.onlyMissingSge) {
                chips.push({ key: "sge", label: "Sem SGE", removable: true });
            }
            if (this.onlyWithAlerts) {
                chips.push({ key: "alerts", label: "Com alerta MEC/FNDE", removable: true });
            }
            if (this.viewPreset === "all" && this.hideConsultoria) {
                chips.push({ key: "hide_consultoria", label: "Sem consultoria", removable: true });
            }
            if (!this.hideApproxOnMap) {
                chips.push({ key: "approx_map", label: "Coords. aproximadas no mapa", removable: true });
            }
            const q = this.searchQuery.trim();
            if (q !== "") {
                chips.push({ key: "search", label: `Busca «${q}»`, removable: true });
            }
            return chips;
        },

        async init() {
            this.initMap();
            this._guideListener = (event) => {
                this.onHorizonteGuide(event?.detail ?? {});
            };
            window.addEventListener("horizonte-guide", this._guideListener);
            this.tourResizeHandler = () => {
                if (this.tourActive) {
                    this.positionTourStep();
                }
            };
            window.addEventListener("resize", this.tourResizeHandler, { passive: true });
            this._fullscreenChangeHandler = () => this.onFullscreenChange();
            document.addEventListener("fullscreenchange", this._fullscreenChangeHandler);
            this.bindMapLayoutObservers();
            this.bindCmdDockObservers();
            if (this.loadUrl) {
                await this.fetchOverview();
                await this.applyInitialNavigation();
                this.maybeStartOnboarding();
            }
        },

        formatKpiCount(value) {
            if (this.kpiLoading) {
                return "…";
            }
            return nf(value ?? 0);
        },

        formatCount(value) {
            return nf(value ?? 0);
        },

        formatScoreDisplay(value) {
            return formatScoreValue(value);
        },

        formatCurrencyDisplay(value) {
            return formatCurrencyBrl(value);
        },

        formatFundebCurrency(value) {
            if (this.kpiLoading) {
                return "…";
            }
            if (value === null || value === undefined || value === "") {
                return "—";
            }
            return formatCurrencyBrl(value);
        },

        formatFundebPct(value) {
            if (this.kpiLoading) {
                return "…";
            }
            if (value === null || value === undefined || value === "") {
                return "—";
            }
            const n = Number(value);
            if (!Number.isFinite(n)) {
                return "—";
            }
            return `${n.toLocaleString("pt-BR", { minimumFractionDigits: 0, maximumFractionDigits: 1 })}%`;
        },

        ufFundebPortariaLabel() {
            const p = this.ufFundebInsights?.portaria;
            if (!p) {
                return "—";
            }
            const label = p.publication_label || "";
            const year = p.exercise_year || this.refYear;
            return label || `Portaria FNDE ${year}`;
        },

        ufFundebExerciseLabel() {
            const year = this.ufFundebInsights?.exercise_year || this.refYear;
            const mat = this.ufFundebInsights?.matriculas_fundeb;
            if (mat && Number(mat) > 0) {
                return `${Number(mat).toLocaleString("pt-BR")} matrículas · exercício ${year}`;
            }
            return `Exercício ${year}`;
        },

        ufFundebMunicipalitiesLabel() {
            const withFundeb = Number(this.ufFundebInsights?.municipalities_with_fundeb ?? 0);
            const total = Number(this.ufFundebInsights?.municipalities_total ?? 0);
            if (total <= 0) {
                return "";
            }
            return `${withFundeb.toLocaleString("pt-BR")} de ${total.toLocaleString("pt-BR")} municípios`;
        },

        ufFundebRealtimeSubLabel() {
            const rt = this.ufFundebInsights?.realtime;
            if (!rt?.available) {
                return "";
            }
            const observed = this.formatFundebCurrency(rt.observed_total);
            const expected = this.formatFundebCurrency(rt.expected_total);
            const last = rt.last_transfer_label;
            const parts = [`${observed} de ${expected}`];
            if (last) {
                parts.push(`último: ${last}`);
            }
            return parts.join(" · ");
        },

        ufFundebNationalRankLabel() {
            const nat = this.ufFundebInsights?.national;
            if (!nat?.rank_receita) {
                return "—";
            }
            const rank = nat.rank_receita;
            const total = nat.total_ufs || 27;
            return `${rank}º em receita`;
        },

        ufFundebNationalSubLabel() {
            const nat = this.ufFundebInsights?.national;
            if (!nat) {
                return "";
            }
            const parts = [];
            if (nat.share_receita_pct !== null && nat.share_receita_pct !== undefined) {
                parts.push(
                    `${Number(nat.share_receita_pct).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 })}% do Brasil`,
                );
            }
            if (nat.rank_pct_done) {
                parts.push(`${nat.rank_pct_done}º em avanço YTD`);
            }
            if (
                nat.delta_pct_vs_national !== null &&
                nat.delta_pct_vs_national !== undefined &&
                nat.national_avg_pct_done !== null
            ) {
                const delta = Number(nat.delta_pct_vs_national);
                const sign = delta > 0 ? "+" : "";
                parts.push(
                    `${sign}${delta.toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 })} p.p. vs média (${Number(nat.national_avg_pct_done).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}%)`,
                );
            }
            return parts.join(" · ");
        },

        ufSummaryMetaLabel() {
            const total = Number(this.markers.length ?? 0);
            const filtered = Number(this.filteredCount ?? 0);
            const parts = [
                `${total.toLocaleString("pt-BR")} municípios com dados`,
            ];
            if (filtered !== total) {
                parts.push(`${filtered.toLocaleString("pt-BR")} no recorte actual`);
            }
            return parts.join(" · ");
        },

        ufSummaryButtonLabel() {
            const uf = String(this.scopeUf ?? "")
                .trim()
                .toUpperCase();
            if (!uf) {
                return "Resumo UF";
            }
            return `${uf} — Resumo`;
        },

        toggleCmdBarExpanded() {
            this.cmdBarExpanded = !this.cmdBarExpanded;
            this.$nextTick(() => this.syncCmdDockLayout());
        },

        cmdBarExpandLabel() {
            return this.cmdBarExpanded
                ? "Ocultar indicadores"
                : "Ver indicadores";
        },

        syncCmdDockLayout() {
            const dock = this.$refs.cmdDock;
            const header = document.querySelector(".serv-app-header");
            if (!(dock instanceof HTMLElement)) {
                return;
            }
            const headerHeight =
                header instanceof HTMLElement ? header.getBoundingClientRect().height : 0;
            dock.style.top = `${Math.round(headerHeight)}px`;
            document.documentElement.style.setProperty(
                "--horizonte-cmd-sticky-top",
                `${Math.round(headerHeight)}px`,
            );
            document.documentElement.style.setProperty(
                "--horizonte-cmd-height",
                `${Math.round(dock.getBoundingClientRect().height)}px`,
            );
        },

        bindCmdDockObservers() {
            const sync = () => this.syncCmdDockLayout();
            sync();
            window.addEventListener("resize", sync, { passive: true });
            if (typeof ResizeObserver === "undefined") {
                return;
            }
            this._cmdDockObserver = new ResizeObserver(sync);
            const dock = this.$refs.cmdDock;
            if (dock instanceof HTMLElement) {
                this._cmdDockObserver.observe(dock);
            }
            const header = document.querySelector(".serv-app-header");
            if (header instanceof HTMLElement) {
                this._cmdDockObserver.observe(header);
            }
        },

        mapControlLabelUfSummary() {
            if (this.ufSummaryOpen) {
                return "Ocultar resumo estadual";
            }
            const meso = this.mesoScopeLabel();
            if (meso) {
                return `Centrar ${meso} e mostrar resumo estadual`;
            }
            const uf = String(this.scopeUf ?? "").trim().toUpperCase();
            if (this.isMesoOverviewMode && uf) {
                return `Centrar ${uf} e mostrar resumo estadual`;
            }
            return "Centrar UF e mostrar resumo estadual";
        },

        ufSummaryCoverageLabel() {
            const cov = this.coverage || {};
            const insights = this.ufFundebInsights || {};
            const sources = [
                cov.with_fundeb > 0 ? `FUNDEB ${nf(cov.with_fundeb)}` : null,
                cov.with_censo > 0 ? `Censo ${nf(cov.with_censo)}` : null,
                cov.with_saeb > 0 ? `SAEB ${nf(cov.with_saeb)}` : null,
                cov.with_cadunico > 0 ? `CadÚnico ${nf(cov.with_cadunico)}` : null,
            ].filter(Boolean);
            const parts = [];
            if (insights.municipalities_total) {
                parts.push(
                    `${nf(insights.municipalities_with_fundeb ?? 0)} municípios com FUNDEB · ${nf(insights.municipalities_total)} no recorte`,
                );
            }
            if (sources.length > 0) {
                parts.push(`Cobertura: ${sources.join(" · ")}`);
            }
            return parts.join(" · ");
        },

        openFiltersDock() {
            this.filtersVisible = true;
            this.filterDockOpen = true;
        },

        toggleFiltersPanel() {
            const isDesktop = window.matchMedia("(min-width: 1280px)").matches;
            if (isDesktop) {
                this.filtersVisible = !this.filtersVisible;
                if (!this.filtersVisible) {
                    this.filterDockOpen = false;
                }
            } else {
                this.filterDockOpen = !this.filterDockOpen;
                if (this.filterDockOpen) {
                    this.filtersVisible = true;
                }
            }
        },

        async toggleMapFullscreen() {
            const el = this.$refs.mapShell;
            if (!el) {
                return;
            }
            try {
                if (document.fullscreenElement === el) {
                    await document.exitFullscreen();
                } else if (!document.fullscreenElement) {
                    await el.requestFullscreen();
                } else {
                    await document.exitFullscreen();
                    await el.requestFullscreen();
                }
            } catch {
                this.mapFullscreen = !this.mapFullscreen;
                this.refreshMapLayout({ immediate: true, force: true });
                if (this.tooltipPinned && this.active) {
                    this.$nextTick(() => this.positionTooltip());
                }
            }
        },

        onFullscreenChange() {
            const el = this.$refs.mapShell;
            this.mapFullscreen = el != null && document.fullscreenElement === el;
            this.refreshMapLayout({ immediate: true, force: true });
            if (this.tooltipPinned && this.active) {
                this.$nextTick(() => this.positionTooltip());
            }
        },

        muniModalTeleportTarget() {
            if (this.mapFullscreen && this.$refs.mapShell) {
                return this.$refs.mapShell;
            }

            return document.body;
        },

        refreshMapLayout({ immediate = false, force = false } = {}) {
            const map = this.map;
            if (!map) {
                return;
            }

            if (!force && (this.pageLoading || this.regionalLoading || this.mapRendering)) {
                return;
            }

            const run = () => {
                const liveMap = this.map;
                if (!liveMap) {
                    return;
                }
                if (!force && (this.pageLoading || this.regionalLoading || this.mapRendering)) {
                    return;
                }

                const center = liveMap.getCenter();
                const zoom = liveMap.getZoom();
                liveMap.invalidateSize({ animate: false });
                const size = liveMap.getSize();
                if (!size || size.x <= 0 || size.y <= 0) {
                    return;
                }

                const prev = this._mapLayoutSize;
                const changed =
                    force ||
                    !prev ||
                    prev.w !== size.x ||
                    prev.h !== size.y;
                this._mapLayoutSize = { w: size.x, h: size.y };

                if (!changed) {
                    return;
                }

                if (
                    force &&
                    (this.isOverviewMode || this.isMesoOverviewMode) &&
                    this._lastChoroplethLayer?.getBounds?.()?.isValid?.() &&
                    !this._mapUserAdjustedView
                ) {
                    liveMap.fitBounds(this._lastChoroplethLayer.getBounds(), {
                        padding: [48, 48],
                        maxZoom: this.isMesoOverviewMode ? 8 : 5,
                        animate: false,
                    });
                    this.applyChoroplethPointerPolicy(true);
                    this.redrawChoroplethLayer();
                    this.refreshCanvasMarkersAfterZoom();
                    this.repositionFloatingPanels();

                    return;
                }

                liveMap.setView(center, zoom, { animate: false });
                this.refreshCanvasMarkersAfterZoom();
                this.repositionFloatingPanels();
            };

            const schedule = () => {
                if (this._mapLayoutFrame !== null) {
                    cancelAnimationFrame(this._mapLayoutFrame);
                }
                this._mapLayoutFrame = requestAnimationFrame(() => {
                    this._mapLayoutFrame = null;
                    run();
                });
            };

            if (this._mapLayoutDebounceTimer !== null) {
                clearTimeout(this._mapLayoutDebounceTimer);
                this._mapLayoutDebounceTimer = null;
            }

            if (immediate) {
                schedule();
                return;
            }

            this._mapLayoutDebounceTimer = window.setTimeout(() => {
                this._mapLayoutDebounceTimer = null;
                schedule();
            }, 120);
        },

        bindMapLayoutObservers() {
            const canvas = this.$refs.mapCanvas;
            if (!(canvas instanceof HTMLElement)) {
                return;
            }

            const onLayoutChange = () => {
                if (this.pageLoading || this.regionalLoading || this.mapRendering) {
                    return;
                }
                this.refreshMapLayout();
            };

            if (typeof ResizeObserver !== "undefined") {
                this._mapLayoutObserver = new ResizeObserver(onLayoutChange);
                this._mapLayoutObserver.observe(canvas);
            }

            window.addEventListener("resize", onLayoutChange, { passive: true });

            const dock = this.$refs.filterDock;
            if (dock instanceof HTMLElement) {
                dock.addEventListener("transitionend", (event) => {
                    if (
                        event.propertyName === "width" ||
                        event.propertyName === "opacity" ||
                        event.propertyName === "transform"
                    ) {
                        this.refreshMapLayout({ immediate: true });
                    }
                });
            }
        },

        mapControlLabelFilters() {
            const isDesktop = window.matchMedia("(min-width: 1280px)").matches;
            if (isDesktop) {
                return this.filtersVisible ? "Ocultar filtros" : "Mostrar filtros";
            }
            return this.filterDockOpen ? "Ocultar filtros" : "Mostrar filtros";
        },

        mapControlLabelFullscreen() {
            return this.mapFullscreen ? "Sair da tela inteira" : "Tela inteira";
        },

        mapLoadingStatusLabel() {
            if (this.loadingMessage) {
                return this.loadingMessage;
            }
            if (this.regionalLoading) {
                const uf = String(this.pendingRegionalUf || this.scopeUf || "")
                    .trim()
                    .toUpperCase();

                return uf ? `Carregando UF ${uf}` : "Carregando UF";
            }

            return "A carregar…";
        },

        onHorizonteGuide(detail = {}) {
            const mode = String(detail?.mode ?? "").toLowerCase();
            if (mode === "tour") {
                this.startTour();
                return;
            }
            if (mode === "demo") {
                this.openGuideDemo();
            }
        },

        openGuideDemo() {
            this.tourActive = false;
            this.guideOpen = true;
            this.workspaceTab = "actions";
            this.$nextTick(() => {
                const demo = this.$el?.querySelector?.('[data-horizonte-guide="demo"]');
                demo?.scrollIntoView({ behavior: "smooth", block: "center" });
            });
        },

        startTour() {
            this.tourActive = true;
            this.tourStepIndex = 0;
            this.guideOpen = false;
            this.$nextTick(() => this.positionTourStep());
        },

        endTour(remember = true) {
            this.tourActive = false;
            this.tourSpotlightStyle = "display:none";
            if (remember) {
                try {
                    localStorage.setItem(HORIZONTE_TOUR_STORAGE_KEY, "1");
                } catch (_) {
                    /* ignore */
                }
            }
        },

        skipTour() {
            this.endTour(true);
        },

        prevTourStep() {
            if (this.tourStepIndex <= 0) {
                return;
            }
            this.tourStepIndex -= 1;
            this.$nextTick(() => this.positionTourStep());
        },

        nextTourStep() {
            if (this.tourStepIndex >= this.tourStepsList.length - 1) {
                this.endTour(true);
                return;
            }
            this.tourStepIndex += 1;
            this.$nextTick(() => this.positionTourStep());
        },

        maybeStartOnboarding() {
            if (this.pageError) {
                return;
            }
            try {
                if (localStorage.getItem(HORIZONTE_TOUR_STORAGE_KEY)) {
                    return;
                }
            } catch (_) {
                /* ignore */
            }
            window.setTimeout(() => {
                if (!this.pageError && !this.tourActive) {
                    this.startTour();
                }
            }, 700);
        },

        positionTourStep() {
            const step = this.currentTourStep;
            if (!step) {
                return;
            }
            if (step.openFilters && this.isUfScopedMode && window.innerWidth < 1280) {
                this.filterDockOpen = true;
            }
            const el = document.querySelector(step.target);
            const margin = 16;
            const bottomSafe = 28;
            const viewportH =
                window.visualViewport?.height ?? window.innerHeight;
            const viewportW =
                window.visualViewport?.width ?? window.innerWidth;
            const preferCenter =
                step.target.includes("workspace") ||
                step.target.includes("rail");

            if (!el || (el.offsetParent === null && step.target.includes("filters"))) {
                this.tourSpotlightStyle = "display:none";
                this.tourCardStyle =
                    "top:50%;left:50%;transform:translate(-50%,-50%);max-width:min(22rem,calc(100vw - 2rem));max-height:calc(100dvh - 2rem);overflow-y:auto;";
                return;
            }

            el.scrollIntoView({
                block: preferCenter ? "center" : "nearest",
                behavior: "smooth",
            });

            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    const rect = el.getBoundingClientRect();
                    const pad = 10;
                    this.tourSpotlightStyle = [
                        `top:${Math.max(8, rect.top - pad)}px`,
                        `left:${Math.max(8, rect.left - pad)}px`,
                        `width:${rect.width + pad * 2}px`,
                        `height:${rect.height + pad * 2}px`,
                    ].join(";");

                    const cardEl = this.$refs?.tourCard;
                    const cardWidth = Math.min(320, viewportW - margin * 2);
                    const cardHeight = cardEl?.offsetHeight ?? 210;

                    let left = rect.left;
                    if (left + cardWidth > viewportW - margin) {
                        left = viewportW - cardWidth - margin;
                    }
                    left = Math.max(margin, left);

                    let top = rect.bottom + 12;
                    const fitsBelow =
                        top + cardHeight <= viewportH - bottomSafe;
                    const fitsAbove =
                        rect.top - cardHeight - 12 >= margin;

                    if (!fitsBelow && fitsAbove) {
                        top = rect.top - cardHeight - 12;
                    } else if (!fitsBelow && !fitsAbove) {
                        top = Math.max(
                            margin,
                            Math.min(
                                rect.top,
                                viewportH - cardHeight - bottomSafe,
                            ),
                        );
                    }

                    top = Math.max(
                        margin,
                        Math.min(top, viewportH - cardHeight - bottomSafe),
                    );

                    this.tourCardStyle = [
                        `top:${top}px`,
                        `left:${left}px`,
                        `width:${cardWidth}px`,
                        `max-height:${Math.max(160, viewportH - margin * 2)}px`,
                        "overflow-y:auto",
                    ].join(";");
                });
            });
        },

        applyDefaultDecisionView() {
            const df =
                (this.meta?.default_filter &&
                typeof this.meta.default_filter === "object"
                    ? this.meta.default_filter
                    : null) ||
                (this.defaultViewFilter && typeof this.defaultViewFilter === "object"
                    ? this.defaultViewFilter
                    : {});

            this.pressureThreshold = Number(
                df.pressure_min ?? df.min_financial ?? 60,
            );
            this.applyDecisionLens("high_pressure", { keepMapView: false });
        },

        clearSecondaryFilters() {
            this.minSuccessScore = 0;
            this.minBenefitScore = 0;
            this.minMatriculas = 0;
            this.minFinancial = 0;
            this.minPedagogical = 0;
            this.minReadiness = 0;
            this.minSocialDemand = 0;
            this.requireFundeb = false;
            this.requireCenso = false;
            this.requireSaeb = false;
            this.requireCadunico = false;
            this.onlyMissingSge = false;
            this.onlyWithAlerts = false;
            this.searchQuery = "";
        },

        preferredMapViewForRegional() {
            return this.regionalDisplayPolicy?.heavy_regional ||
                Number(this.regionalDisplayPolicy?.marker_count ?? 0) >
                    Number(this.regionalDisplayPolicy?.heat_max ?? 150)
                ? "markers"
                : "heat";
        },

        applyDecisionLens(lensKey, opts = {}) {
            const keepMapView = opts.keepMapView === true;
            const enteringRegional = opts.enteringRegional === true;
            this.clearSecondaryFilters();
            this.showAllOnMap = false;
            this.renderCapDismissed = false;
            this.searchQuery = "";
            if (!enteringRegional) {
                this.hideApproxOnMap = true;
            }

            switch (lensKey) {
                case "high_pressure":
                    this.viewPreset = "high_pressure";
                    this.filterTier = "prospects";
                    this.hideConsultoria = true;
                    if (!keepMapView) {
                        this.mapView = this.preferredMapViewForRegional();
                    }
                    break;
                case "prospects":
                    this.viewPreset = "prospects";
                    this.filterTier = "prospects";
                    this.hideConsultoria = true;
                    if (!keepMapView) {
                        this.mapView = this.preferredMapViewForRegional();
                    }
                    break;
                case "prospect_high":
                    this.viewPreset = "prospect_high";
                    this.filterTier = "prospect_high";
                    this.hideConsultoria = true;
                    if (!keepMapView) {
                        this.mapView = this.preferredMapViewForRegional();
                    }
                    break;
                case "all":
                    this.viewPreset = "all";
                    this.filterTier = "all";
                    this.hideConsultoria = false;
                    if (!keepMapView) {
                        this.mapView = this.preferredMapViewForRegional();
                    }
                    break;
                case "consultoria_active":
                    this.viewPreset = "custom";
                    this.filterTier = "consultoria_active";
                    this.hideConsultoria = false;
                    if (!keepMapView) {
                        this.mapView = "markers";
                    }
                    break;
                case "catalog_pending":
                    this.viewPreset = "custom";
                    this.filterTier = "catalog_pending";
                    this.hideConsultoria = false;
                    if (!keepMapView) {
                        this.mapView = "markers";
                    }
                    break;
                default:
                    return;
            }

            this._filterSignature = "";
            if (this.markers.length > 0) {
                this.recomputeFilteredMarkers();
            }
        },

        setViewPreset(preset) {
            if (Object.prototype.hasOwnProperty.call(DECISION_LENSES, preset)) {
                this.applyDecisionLens(preset);
            }
        },

        removeFilterChip(key) {
            switch (key) {
                case "success":
                    this.minSuccessScore = 0;
                    break;
                case "benefit":
                    this.minBenefitScore = 0;
                    break;
                case "matriculas":
                    this.minMatriculas = 0;
                    break;
                case "financial":
                    this.minFinancial = 0;
                    break;
                case "pedagogical":
                    this.minPedagogical = 0;
                    break;
                case "readiness":
                    this.minReadiness = 0;
                    break;
                case "social":
                    this.minSocialDemand = 0;
                    break;
                case "fundeb":
                    this.requireFundeb = false;
                    break;
                case "censo":
                    this.requireCenso = false;
                    break;
                case "saeb":
                    this.requireSaeb = false;
                    break;
                case "cadunico":
                    this.requireCadunico = false;
                    break;
                case "sge":
                    this.onlyMissingSge = false;
                    break;
                case "alerts":
                    this.onlyWithAlerts = false;
                    break;
                case "hide_consultoria":
                    this.hideConsultoria = false;
                    break;
                case "approx_map":
                    this.hideApproxOnMap = true;
                    break;
                case "search":
                    this.searchQuery = "";
                    break;
                default:
                    return;
            }
            this.markFiltersCustom();
        },

        markFiltersCustom() {
            if (
                this.viewPreset !== "custom" &&
                (this.minSuccessScore > 0 ||
                    this.minBenefitScore > 0 ||
                    this.minMatriculas > 0 ||
                    this.minFinancial > 0 ||
                    this.minPedagogical > 0 ||
                    this.minReadiness > 0 ||
                    this.minSocialDemand > 0 ||
                    this.requireFundeb ||
                    this.requireCenso ||
                    this.requireSaeb ||
                    this.requireCadunico ||
                    this.onlyMissingSge ||
                    this.onlyWithAlerts ||
                    this.searchQuery.trim() !== "" ||
                    (this.viewPreset === "all" && this.hideConsultoria) ||
                    !this.hideApproxOnMap)
            ) {
                this.viewPreset = "custom";
                if (this.filterTier === "prospects" && this.hideConsultoria) {
                    // mantém tier prospects como base do custom
                }
            }
        },

        setFilterTier(tier) {
            if (tier === "consultoria_active") {
                this.applyDecisionLens("consultoria_active");
                return;
            }
            if (tier === "catalog_pending") {
                this.applyDecisionLens("catalog_pending");
                return;
            }
            if (tier === "prospect_high") {
                this.applyDecisionLens("prospect_high");
                return;
            }
            if (tier === "all") {
                this.applyDecisionLens("all");
                return;
            }
            if (tier === "prospects") {
                this.applyDecisionLens("prospects");
            }
        },

        async applyInitialNavigation() {
            this.applyDefaultDecisionView();
            // Só faz deep-link automático quando há ?uf=XX explícito na URL.
            // A sugestão do backend (displayPolicy.initial_uf) NÃO deve navegar:
            // a vista inicial é sempre o mapa do Brasil (overview nacional).
            const uf = String(options.initialUf ?? "").trim().toUpperCase();
            if (uf !== "" && !this.pageError) {
                await this.selectUf(uf, false);
            }
        },

        ufName(uf) {
            const code = String(uf ?? "").trim().toUpperCase();
            if (code === "") {
                return "";
            }
            const fromPoint = this.ufMapPoints.find((p) => p.uf === code);
            if (fromPoint?.uf_name) {
                return String(fromPoint.uf_name);
            }
            const fromRank = this.ufRankings.find((r) => r.uf === code);
            if (fromRank?.uf_name) {
                return String(fromRank.uf_name);
            }
            return String(this.ufNames?.[code] ?? code);
        },

        ufLabel(uf) {
            const code = String(uf ?? "").trim().toUpperCase();
            if (code === "") {
                return "";
            }
            const name = this.ufName(code);
            return name !== code ? `${code} — ${name}` : code;
        },

        mesoScopeLabel() {
            const scoped = String(this.scopeMeso ?? "").trim();
            if (scoped === "") {
                return "";
            }
            const point = this.mesoMapPoints.find(
                (p) => String(p.meso_id) === scoped,
            );
            return point?.meso_name || scoped;
        },

        dataUrl(scope, uf = "") {
            const url = new URL(this.loadUrl, window.location.origin);
            url.searchParams.set("scope", scope);
            if (uf) {
                url.searchParams.set("uf", uf);
            }
            return url.toString();
        },

        async fetchOverview() {
            this.pageLoading = true;
            this.pageError = null;
            this.loadingMessage = "A carregar painel nacional…";

            const preset = window.servDataLoading?.presets?.horizonteData;
            window.servDataLoading?.start?.(
                preset?.title ?? "Montando mapa Horizonte",
                preset?.message ?? "A agregar indicadores por UF. Aguarde…",
            );

            try {
                const response = await fetch(this.dataUrl("overview"), {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                this.applyOverviewPayload(data);
            } catch (error) {
                console.error("horizonte overview", error);
                this.pageError =
                    error instanceof Error ? error.message : "Erro ao carregar o Horizonte.";
            } finally {
                this.pageLoading = false;
                window.servDataLoading?.finish?.();
                await this.refreshMapLayers();
                this.$nextTick(() =>
                    this.refreshMapLayout({ immediate: true, force: true }),
                );
            }
        },

        async fetchRegional(uf) {
            const scoped = String(uf ?? "").trim().toUpperCase();
            if (!scoped) {
                return;
            }
            if (this.regionalLoading && this.pendingRegionalUf === scoped) {
                return;
            }
            this.pendingRegionalUf = scoped;
            this.regionalLoading = true;
            this.pageError = null;
            this.loadingMessage = `Carregando UF ${scoped}`;
            this.scopeUf = scoped;

            try {
                const response = await fetch(this.dataUrl("regional", scoped), {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                if (!Array.isArray(data.markers) || data.markers.length === 0) {
                    throw new Error(
                        `Nenhum município com coordenadas em ${scoped}. Corra o feed ibge_catalog para esta UF.`,
                    );
                }
                this.applyRegionalPayload(data, scoped);
            } catch (error) {
                console.error("horizonte regional", error);
                this.scopeUf = "";
                this.loadedRegionalUf = "";
                this.pageError =
                    error instanceof Error
                        ? error.message
                        : `Erro ao carregar UF ${scoped}.`;
            } finally {
                this.regionalLoading = false;
                if (this.pendingRegionalUf === scoped) {
                    this.pendingRegionalUf = "";
                }
                if (this.isRegionalMode && this.ensurePointsVisibleOnMap()) {
                    this._filterSignature = this.filterSignature();
                }
                await this.scheduleMapRefresh();
            }
        },

        applyOverviewPayload(data) {
            if (!data || typeof data !== "object") {
                return;
            }
            this.mapMode = "overview";
            this.scopeUf = "";
            this.loadedRegionalUf = "";
            this.markers = [];
            this.filteredMarkersList = [];
            this.ufMapPoints = Array.isArray(data.uf_map_points) ? data.uf_map_points : [];
            this.nationalUfMapPoints = this.ufMapPoints;
            this.nationalOverviewSnapshot = {
                summary: data.summary,
                topProspects: data.top_prospects,
                focusSegments: data.focus_segments,
                sgeSummary: data.sge_summary,
                meta: data.meta,
            };
            if (Array.isArray(data.uf_rankings) && data.uf_rankings.length > 0) {
                this.nationalUfRankings = data.uf_rankings;
            }
            this.ufFundebInsights = null;
            this.applyCommonPayload(data);
            this.setOverviewNotice();
            this._mapUserAdjustedView = false;
        },

        applyRegionalPayload(data, uf) {
            if (!data || typeof data !== "object") {
                return;
            }
            this._mapUserAdjustedView = false;
            this._preserveViewOnNextRender = false;
            this.closeUfSummary();
            this.scopeMeso = "";
            this.mesoMapPoints = Array.isArray(data.meso_map_points) ? data.meso_map_points : [];
            const mesoMeta =
                data.meta?.meso_overview && typeof data.meta.meso_overview === "object"
                    ? data.meta.meso_overview
                    : null;
            if (mesoMeta?.enabled && this.mesoMapPoints.length >= 1) {
                this.mapMode = "meso_overview";
            } else {
                this.mesoMapPoints = [];
                this.mapMode = "regional";
            }
            const scopedUf = String(uf ?? "").trim().toUpperCase();
            this.scopeUf = scopedUf;
            this.loadedRegionalUf = scopedUf;
            this.markers = Array.isArray(data.markers) ? data.markers : [];
            this.ufMapPoints = [];
            this.ufFundebInsights =
                data.uf_fundeb_insights && typeof data.uf_fundeb_insights === "object"
                    ? data.uf_fundeb_insights
                    : null;
            this.applyCommonPayload(data);
            this.recomputeFilteredMarkers();
            this._filterSignature = this.filterSignature();
            this.applyRegionalRenderPolicy();
            this._tooltipHtmlCache = {};
            this.ensurePointsVisibleOnMap();
            if (this.isMesoOverviewMode) {
                this.initialViewNotice = {
                    kind: "meso",
                    message:
                        mesoMeta?.reason ||
                        `${this.mesoMapPoints.length.toLocaleString("pt-BR")} mesorregiões em ${this.ufLabel(uf)} · passe o mouse para ver dados e clique numa região para ver municípios.`,
                    uf,
                };
            } else {
                this.initialViewNotice = {
                    kind: "regional",
                    message:
                        this.meta?.regional_display_policy?.reason ||
                        `${this.markers.length.toLocaleString("pt-BR")} municípios com dados em ${this.ufLabel(uf)} · ${Number(this.summary?.prospect_count ?? 0).toLocaleString("pt-BR")} prospectos.`,
                    uf,
                };
            }
        },

        applyCommonPayload(data) {
            this.colors =
                data.colors && typeof data.colors === "object" ? data.colors : this.colors;
            this.legend = Array.isArray(data.legend) ? data.legend : this.legend;
            this.heatLegend = Array.isArray(data.heat_legend)
                ? data.heat_legend
                : this.heatLegend;
            this.summary =
                data.summary && typeof data.summary === "object"
                    ? data.summary
                    : this.summary;
            this.ufRankings = Array.isArray(data.uf_rankings)
                ? data.uf_rankings
                : this.ufRankings;
            if (
                Array.isArray(data.uf_rankings) &&
                data.uf_rankings.length > 1 &&
                this.isOverviewMode
            ) {
                this.nationalUfRankings = data.uf_rankings;
            } else if (
                Array.isArray(data.uf_rankings) &&
                data.uf_rankings.length > 1 &&
                this.nationalUfRankings.length === 0
            ) {
                this.nationalUfRankings = data.uf_rankings;
            }
            if (this.nationalUfRankings.length > 0) {
                this.ufRankings = this.nationalUfRankings;
            }
            this.topProspects = Array.isArray(data.top_prospects)
                ? data.top_prospects
                : this.topProspects;
            this.focusSegments = Array.isArray(data.focus_segments)
                ? data.focus_segments
                : this.focusSegments;
            this.sgeSummary =
                data.sge_summary && typeof data.sge_summary === "object"
                    ? data.sge_summary
                    : this.sgeSummary;
            this.refYear = Number(data.reference_year) || this.refYear;
            this.currentYear = Number(data.current_year) || this.currentYear;
            this.meta = data.meta && typeof data.meta === "object" ? data.meta : {};
            if (this.meta.default_filter && typeof this.meta.default_filter === "object") {
                this.defaultViewFilter = this.meta.default_filter;
            }
            this.displayPolicy =
                this.meta.display_policy && typeof this.meta.display_policy === "object"
                    ? this.meta.display_policy
                    : null;
            this.mapRenderLimit = Number(this.displayPolicy?.max_render_markers) || 400;
        },

        /** Se há municípios no recorte mas nenhum desenhável (coords. aproximadas ocultas), mostra no mapa. */
        ensurePointsVisibleOnMap() {
            if (!this.isRegionalMode || this.markers.length === 0) {
                return false;
            }
            const withCoords = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            const onMap = withCoords.filter((m) => !this.hideApproxOnMap || !isApproxCoord(m));
            if (withCoords.length > 0 && onMap.length === 0 && this.hideApproxOnMap) {
                this.hideApproxOnMap = false;
                this.recomputeFilteredMarkers();
                return true;
            }
            return false;
        },

        setOverviewNotice() {
            const policy = this.displayPolicy;
            if (!policy?.heavy_dataset) {
                this.initialViewNotice = null;
                return;
            }
            this.initialViewNotice = {
                kind: "overview",
                message:
                    policy.reason ||
                    "Selecione uma UF no mapa ou na barra lateral para ver municípios.",
                total: Number(policy.marker_count_total ?? this.totalMarkers),
            };
        },

        initMap() {
            if (!this.$refs.map) {
                return;
            }

            this.map = L.map(this.$refs.map, {
                zoomControl: true,
                dragging: true,
                touchZoom: true,
                scrollWheelZoom: true,
                doubleClickZoom: true,
                boxZoom: false,
                keyboard: true,
                preferCanvas: false,
                maxZoom: 12,
                zoomSnap: 0.5,
                zoomDelta: 0.5,
                maxBounds: BRAZIL_BOUNDS,
                maxBoundsViscosity: 0.3,
                minZoom: 3,
            });
            this.canvasRenderer = L.canvas({ padding: 0.5 });

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 12,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            }).addTo(this.map);

            this.layer = L.layerGroup();
            this.ufLayer = L.layerGroup();
            this.heatLayer = L.layerGroup();
            this.clusterGroup = L.markerClusterGroup({
                chunkedLoading: true,
                chunkInterval: 100,
                chunkDelay: 40,
                maxClusterRadius: 48,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                disableClusteringAtZoom: 9,
                zoomToBoundsOnClick: true,
                removeOutsideVisibleBounds: true,
            });

            this.map.setView([-14.2, -51.9], 4);
            this.$nextTick(() => {
                this.refreshMapLayout({ immediate: true, force: true });
            });

            this.map.on("click", () => {
                if (!this.sgeFormOpen) {
                    this.closeMapOverlays();
                }
            });

            this.map.on("dragend", () => {
                this._mapUserAdjustedView = true;
                this.repositionFloatingPanels();
            });

            this.map.on("zoomend", () => {
                this.refreshCanvasMarkersAfterZoom();
                this.repositionFloatingPanels();
            });

            this.map.on("move", () => {
                if (this.tooltipPinned && this.active) {
                    this.repositionFloatingPanels();
                }
            });

            const onFilterChange = () => {
                if (!this.isUfScopedMode || this.regionalLoading || this.pageLoading) {
                    return;
                }
                const sig = this.filterSignature();
                if (sig === this._filterSignature) {
                    return;
                }
                this._filterSignature = sig;
                this.recomputeFilteredMarkers();
                this.showAllOnMap = false;
                this.renderCapDismissed = false;
                // Refinar filtros (ex.: "Só coord. IBGE") não deve reposicionar a câmera:
                // re-enquadrar provoca zoom inesperado e desalinha o hit-area do canvas
                // (marcadores aparecem mas ficam "não clicáveis"). Preserva a vista atual.
                // Só vale para a vista de marcadores; em coroplético (UF/meso) não há marcador.
                if (this.isRegionalMode) {
                    this._preserveViewOnNextRender = true;
                }
                void this.scheduleMapRefresh();
            };

            [
                "markers",
                "viewPreset",
                "filterTier",
                "minSuccessScore",
                "minBenefitScore",
                "minMatriculas",
                "minFinancial",
                "minPedagogical",
                "minReadiness",
                "minSocialDemand",
                "requireFundeb",
                "requireCenso",
                "requireSaeb",
                "requireCadunico",
                "onlyMissingSge",
                "onlyWithAlerts",
                "hideConsultoria",
                "searchQuery",
                "pressureThreshold",
                "hideApproxOnMap",
            ].forEach((key) => this.$watch(key, onFilterChange));

            this.$watch("mapView", () => void this.scheduleMapRefresh());
        },

        cancelPendingMapRefresh() {
            this.mapRefreshGeneration += 1;
            if (this.mapRefreshTimer !== null) {
                clearTimeout(this.mapRefreshTimer);
                this.mapRefreshTimer = null;
            }
        },

        scheduleMapRefresh() {
            this.mapRefreshGeneration += 1;
            const generation = this.mapRefreshGeneration;

            if (this.mapRefreshTimer !== null) {
                clearTimeout(this.mapRefreshTimer);
            }

            return new Promise((resolve) => {
                this.mapRefreshTimer = window.setTimeout(async () => {
                    this.mapRefreshTimer = null;
                    if (generation !== this.mapRefreshGeneration) {
                        resolve();
                        return;
                    }
                    await this.refreshMapLayers();
                    if (this.mapFullscreen) {
                        this.$nextTick(() =>
                            this.refreshMapLayout({ immediate: true, force: true }),
                        );
                    }
                    resolve();
                }, this.mapRefreshDebounceMs);
            });
        },

        /** Corrige desalinhamento SVG/canvas quando o contentor muda após o GeoJSON. */
        syncChoroplethMapLayout(
            geoLayer,
            { maxZoom = 5, padding = [48, 48], force = false } = {},
        ) {
            if (!this.map || !geoLayer) {
                return;
            }

            const bounds = geoLayer.getBounds?.();
            if (!bounds?.isValid?.()) {
                return;
            }

            this._lastChoroplethLayer = geoLayer;

            const fit = () => {
                if (!this.map || (this._mapUserAdjustedView && !force)) {
                    return;
                }
                this.map.invalidateSize({ animate: false });
                this.map.fitBounds(bounds, {
                    padding,
                    maxZoom,
                    animate: force,
                });
            };

            fit();
            requestAnimationFrame(fit);
            window.setTimeout(() => {
                if (this.isOverviewMode || this.isMesoOverviewMode) {
                    fit();
                    this.applyChoroplethPointerPolicy(true);
                    this.redrawChoroplethLayer();
                }
            }, 180);
        },

        async whenMapHasSize(maxAttempts = 12) {
            if (!this.map) {
                return;
            }

            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                this.map.invalidateSize({ animate: false });
                const size = this.map.getSize();
                if (size && size.x > 0 && size.y > 0) {
                    return;
                }
                await new Promise((resolve) => requestAnimationFrame(resolve));
            }
        },

        setChoroplethOverviewUi(active) {
            const el = this.$refs.map;
            if (el instanceof HTMLElement) {
                el.classList.toggle("is-choropleth-overview", active);
            }
            this.applyChoroplethPointerPolicy(active);
        },

        applyChoroplethPointerPolicy(active) {
            if (!this.map) {
                return;
            }

            for (const paneName of ["overlayPane", "markerPane", "shadowPane"]) {
                const pane = this.map.getPane(paneName);
                if (!pane) {
                    continue;
                }
                pane.querySelectorAll("canvas").forEach((canvas) => {
                    canvas.style.pointerEvents = active ? "none" : "";
                });
            }

            const overlay = this.map.getPane("overlayPane");
            if (overlay) {
                overlay.querySelectorAll("svg").forEach((svg) => {
                    svg.style.pointerEvents = active ? "auto" : "";
                });
            }
        },

        ensureRegionalCanvasRenderer() {
            if (!this.canvasRenderer) {
                this.canvasRenderer = L.canvas({ padding: 0.5 });
            }

            return this.canvasRenderer;
        },

        ensureChoroplethPane() {
            if (!this.map || this._choroplethPaneReady) {
                return;
            }

            if (!this.map.getPane("horizonteChoropleth")) {
                this.map.createPane("horizonteChoropleth");
                const pane = this.map.getPane("horizonteChoropleth");
                if (pane) {
                    pane.style.zIndex = "450";
                }
            }

            this._choroplethPaneReady = true;
        },

        clearChoroplethLayer() {
            if (this.choroplethLayer && this.map) {
                this.map.removeLayer(this.choroplethLayer);
            }
            this.choroplethLayer = null;
            this._lastChoroplethLayer = null;
            this.clearMicroOverlayLayer();
            this.clearMunicipalBoundaryLayer();
        },

        clearMunicipalBoundaryLayer() {
            if (this.municipalBoundaryLayer && this.map) {
                this.map.removeLayer(this.municipalBoundaryLayer);
            }
            this.municipalBoundaryLayer = null;
        },

        ensureMunicipalBoundaryPane() {
            if (!this.map) {
                return;
            }
            if (!this.map.getPane("horizonteMunicipalBoundary")) {
                this.map.createPane("horizonteMunicipalBoundary");
                const pane = this.map.getPane("horizonteMunicipalBoundary");
                if (pane) {
                    pane.style.zIndex = "398";
                }
            }
        },

        ibgeIdsForScopedMeso() {
            const mesoId = String(this.scopeMeso ?? "").trim();
            if (mesoId === "") {
                return [];
            }
            const ids = new Set();
            for (const m of this.markers) {
                if (String(m.meso_id ?? "") !== mesoId) {
                    continue;
                }
                const ibge = String(m.ibge ?? "").trim();
                if (ibge !== "") {
                    ids.add(ibge);
                }
            }

            return [...ids];
        },

        ibgeIdsForVisibleMunicipalities() {
            const ids = new Set();
            for (const m of this.mapMarkersForRender) {
                const ibge = normalizeIbgeCodarea(m?.ibge);
                if (ibge !== "") {
                    ids.add(ibge);
                }
            }
            const pinnedIbge = normalizeIbgeCodarea(this.highlightIbge);
            if (pinnedIbge !== "") {
                ids.add(pinnedIbge);
            }

            return [...ids];
        },

        async renderBoundariesLayer() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.ufLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.markerLayers = [];
            this.clearMunicipalBoundaryLayer();
            this.clearMicroOverlayLayer();

            if (!this.map || !this.isRegionalMode || !this.scopeUf) {
                return;
            }

            const ibgeIds = this.ibgeIdsForVisibleMunicipalities();
            if (ibgeIds.length === 0) {
                return;
            }

            const ibgeSet = new Set(ibgeIds);
            const markerByIbge = new Map(
                this.mapMarkersForRender.map((m) => [
                    normalizeIbgeCodarea(m.ibge),
                    m,
                ]),
            );

            let geo;
            try {
                geo = await this.fetchGeoMalha("municipal", this.scopeUf);
            } catch (error) {
                console.warn("horizonte geo malha (municipal)", this.scopeUf, error);

                return;
            }

            const features = (geo?.features ?? []).filter((feature) =>
                ibgeSet.has(normalizeIbgeCodarea(feature?.properties?.codarea)),
            );
            if (features.length === 0) {
                return;
            }

            this.ensureMunicipalBoundaryPane();
            const activeIbge = normalizeIbgeCodarea(this.active?.ibge);
            const filtered = { type: "FeatureCollection", features };
            const geoLayer = L.geoJSON(filtered, {
                pane: "horizonteMunicipalBoundary",
                smoothFactor: 1.25,
                style: (feature) => {
                    const ibge = normalizeIbgeCodarea(feature?.properties?.codarea);

                    return municipalBoundaryViewStyle({
                        highlighted: ibge !== "" && ibge === activeIbge,
                    });
                },
                onEachFeature: (feature, layer) => {
                    const ibge = normalizeIbgeCodarea(feature?.properties?.codarea);
                    const m = markerByIbge.get(ibge);
                    if (!m) {
                        return;
                    }
                    const name = escapeHtml(String(m.name ?? ""));
                    layer.bindTooltip(name, {
                        direction: "top",
                        sticky: true,
                        className: "serv-horizonte-geo-tooltip",
                    });
                    layer.on("click", (e) => {
                        L.DomEvent.stopPropagation(e);
                        this.selectMarker(m, e);
                    });
                    layer.on("mouseover", () => {
                        layer.setStyle(municipalBoundaryViewStyle({ highlighted: true }));
                        layer.bringToFront();
                    });
                    layer.on("mouseout", () => {
                        layer.setStyle(
                            municipalBoundaryViewStyle({
                                highlighted: ibge === activeIbge,
                            }),
                        );
                    });
                },
            });

            this.municipalBoundaryLayer = geoLayer;
            geoLayer.addTo(this.map);
            this.applyChoroplethPointerPolicy(true);

            if (this._preserveViewOnNextRender) {
                this._preserveViewOnNextRender = false;
            } else {
                const bounds = [];
                geoLayer.eachLayer((layer) => {
                    const b = layer.getBounds?.();
                    if (b?.isValid?.()) {
                        bounds.push(b.getSouthWest(), b.getNorthEast());
                    }
                });
                if (bounds.length > 0) {
                    this.map.fitBounds(bounds, {
                        padding: [40, 40],
                        maxZoom: this.scopeMeso ? 9 : 8,
                    });
                }
            }

            await this.renderMicroRegionsOverlay();
        },

        async renderMunicipalBoundariesOverlay() {
            if (this.mapView === "boundaries") {
                return;
            }
            this.clearMunicipalBoundaryLayer();
            if (!this.map || !this.isRegionalMode || !this.scopeUf) {
                return;
            }
            const mesoId = String(this.scopeMeso ?? "").trim();
            if (mesoId === "") {
                return;
            }

            const ibgeIds = this.ibgeIdsForScopedMeso();
            if (ibgeIds.length === 0) {
                return;
            }

            const ibgeSet = new Set(ibgeIds);
            let geo;
            try {
                geo = await this.fetchGeoMalha("municipal", this.scopeUf);
            } catch (error) {
                console.warn("horizonte geo malha (municipal)", this.scopeUf, error);

                return;
            }

            const features = (geo?.features ?? []).filter((feature) =>
                ibgeSet.has(normalizeIbgeCodarea(feature?.properties?.codarea)),
            );
            if (features.length === 0) {
                return;
            }

            this.ensureMunicipalBoundaryPane();
            const filtered = { type: "FeatureCollection", features };
            const geoLayer = L.geoJSON(filtered, {
                pane: "horizonteMunicipalBoundary",
                interactive: false,
                smoothFactor: 1.25,
                style: () => municipalBoundaryStyle(),
            });

            this.municipalBoundaryLayer = geoLayer;
            geoLayer.addTo(this.map);
            geoLayer.bringToBack();
        },

        clearMicroOverlayLayer() {
            if (this.microOverlayLayer && this.map) {
                this.map.removeLayer(this.microOverlayLayer);
            }
            this.microOverlayLayer = null;
        },

        ensureMicroOverlayPane() {
            if (!this.map) {
                return;
            }
            if (!this.map.getPane("horizonteMicroOverlay")) {
                this.map.createPane("horizonteMicroOverlay");
                const pane = this.map.getPane("horizonteMicroOverlay");
                if (pane) {
                    pane.style.zIndex = "405";
                }
            }
        },

        microIdsForScopedMeso() {
            const mesoId = String(this.scopeMeso ?? "").trim();
            if (mesoId === "") {
                return [];
            }
            const ids = new Set();
            for (const m of this.markers) {
                if (String(m.meso_id ?? "") !== mesoId) {
                    continue;
                }
                const microId = String(m.micro_id ?? "").trim();
                if (microId !== "") {
                    ids.add(microId);
                }
            }

            return [...ids];
        },

        async renderMicroRegionsOverlay() {
            this.clearMicroOverlayLayer();
            if (!this.map || !this.isRegionalMode) {
                return;
            }
            const mesoId = String(this.scopeMeso ?? "").trim();
            if (mesoId === "" || !this.scopeUf) {
                return;
            }

            const microIds = this.microIdsForScopedMeso();
            if (microIds.length === 0) {
                return;
            }

            const microSet = new Set(microIds);
            let geo;
            try {
                geo = await this.fetchGeoMalha("micro", this.scopeUf);
            } catch (error) {
                console.warn("horizonte geo malha (micro)", this.scopeUf, error);
                this.renderMicroRegionsFallback(microIds);

                return;
            }

            const features = (geo?.features ?? []).filter((feature) =>
                microSet.has(String(feature?.properties?.codarea ?? "")),
            );
            if (features.length === 0) {
                this.renderMicroRegionsFallback(microIds);

                return;
            }

            this.ensureMicroOverlayPane();
            const filtered = { type: "FeatureCollection", features };
            const geoLayer = L.geoJSON(filtered, {
                pane: "horizonteMicroOverlay",
                interactive: false,
                style: (feature) => {
                    const microId = String(feature?.properties?.codarea ?? "");
                    const idx = microIds.indexOf(microId);

                    return microRegionOverlayStyle(
                        mesoPaletteColor(microId, Math.max(0, idx)),
                    );
                },
            });

            this.microOverlayLayer = geoLayer;
            geoLayer.addTo(this.map);
            geoLayer.bringToBack();
        },

        renderMicroRegionsFallback(microIds) {
            if (!this.map || microIds.length === 0) {
                return;
            }

            const mesoId = String(this.scopeMeso ?? "").trim();
            const group = L.layerGroup();
            const byMicro = new Map();

            for (const m of this.markers) {
                if (mesoId !== "" && String(m.meso_id ?? "") !== mesoId) {
                    continue;
                }
                const microId = String(m.micro_id ?? "").trim();
                if (!microIds.includes(microId)) {
                    continue;
                }
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                if (!byMicro.has(microId)) {
                    byMicro.set(microId, []);
                }
                byMicro.get(microId).push([lat, lng]);
            }

            let idx = 0;
            for (const [microId, coords] of byMicro.entries()) {
                if (coords.length === 0) {
                    continue;
                }
                const centerLat =
                    coords.reduce((sum, c) => sum + c[0], 0) / coords.length;
                const centerLng =
                    coords.reduce((sum, c) => sum + c[1], 0) / coords.length;
                let maxKm = 8;
                for (const [lat, lng] of coords) {
                    const dLat = lat - centerLat;
                    const dLng = lng - centerLng;
                    const km = Math.sqrt(dLat * dLat + dLng * dLng) * 111;
                    maxKm = Math.max(maxKm, km * 1.35);
                }
                const fill = mesoPaletteColor(microId, idx++);
                const circle = L.circle([centerLat, centerLng], {
                    pane: "horizonteMicroOverlay",
                    radius: maxKm * 1000,
                    ...microRegionOverlayStyle(fill),
                    interactive: false,
                });
                group.addLayer(circle);
            }

            if (group.getLayers().length === 0) {
                return;
            }

            this.ensureMicroOverlayPane();
            this.microOverlayLayer = group;
            group.addTo(this.map);
            group.bringToBack();
        },

        redrawChoroplethLayer() {
            if (!this.map || !this.choroplethLayer) {
                return;
            }

            this.map.invalidateSize({ animate: false });
            this.choroplethLayer.eachLayer((layer) => {
                if (typeof layer.redraw === "function") {
                    layer.redraw();
                }
            });
        },

        purgeBlockingOverlays(active = true) {
            if (!this.map) {
                return;
            }

            const remove = [];
            this.map.eachLayer((layer) => {
                if (isLeafletCanvasRenderer(layer) || isLeafletSvgRenderer(layer)) {
                    remove.push(layer);
                }
            });
            for (const layer of remove) {
                this.map.removeLayer(layer);
            }

            if (this.canvasRenderer) {
                try {
                    this.map.removeLayer(this.canvasRenderer);
                } catch {
                    /* já removido */
                }
                this.canvasRenderer = null;
            }

            for (const paneName of [
                "tilePane",
                "overlayPane",
                "markerPane",
                "shadowPane",
            ]) {
                const pane = this.map.getPane(paneName);
                if (!pane) {
                    continue;
                }
                pane.querySelectorAll("canvas").forEach((canvas) => {
                    canvas.style.pointerEvents = active ? "none" : "";
                    canvas.style.visibility = active ? "hidden" : "";
                });
            }
        },

        setOverviewLayerVisibility() {
            if (!this.map) {
                return;
            }

            if (this.isOverviewMode || this.isMesoOverviewMode) {
                this.setChoroplethOverviewUi(true);
                if (this.map.hasLayer(this.clusterGroup)) {
                    this.map.removeLayer(this.clusterGroup);
                }
                if (this.map.hasLayer(this.heatLayer)) {
                    this.map.removeLayer(this.heatLayer);
                }
                if (this.map.hasLayer(this.layer)) {
                    this.map.removeLayer(this.layer);
                }
                if (this.choroplethLayer && !this.map.hasLayer(this.choroplethLayer)) {
                    this.choroplethLayer.addTo(this.map);
                }
                if (this.choroplethLayer) {
                    this.choroplethLayer.bringToFront();
                }

                return;
            }

            this.setChoroplethOverviewUi(false);
            this.clearChoroplethLayer();
            this.purgeBlockingOverlays(false);
            this.ensureRegionalCanvasRenderer();
            if (!this.map.hasLayer(this.layer)) {
                this.layer.addTo(this.map);
            }
            if (!this.map.hasLayer(this.heatLayer)) {
                this.heatLayer.addTo(this.map);
            }
        },

        /** Canvas regional cobre o coroplético SVG e bloqueia hover/clique na visão nacional. */
        prepareChoroplethMapMode() {
            if (!this.map) {
                return;
            }

            this.clusterGroup.clearLayers();
            if (this.map.hasLayer(this.clusterGroup)) {
                this.map.removeLayer(this.clusterGroup);
            }
            this.heatLayer.clearLayers();
            this.layer.clearLayers();
            this.ufLayer.clearLayers();
            this.clearChoroplethLayer();
            this.purgeBlockingOverlays(true);
            this.setChoroplethOverviewUi(true);
        },

        async refreshMapLayers() {
            if (!this.map) {
                return;
            }

            const pinnedMarker =
                this.tooltipPinned && this.active ? { ...this.active } : null;
            const pinnedIbge = pinnedMarker ? String(pinnedMarker.ibge ?? "") : "";
            const pinnedStillVisible =
                pinnedIbge !== "" &&
                (this.isOverviewMode ||
                    this.filteredMarkersList.some((m) => String(m.ibge) === pinnedIbge));

            if (!pinnedStillVisible) {
                this.closeTooltip();
            }

            this.mapRendering = true;
            this.renderProgress = 0;

            try {
                if (this.isOverviewMode) {
                    this._preserveViewOnNextRender = false;
                    if (this.map.hasLayer(this.clusterGroup)) {
                        this.map.removeLayer(this.clusterGroup);
                    }
                    this.prepareChoroplethMapMode();
                    this.setOverviewLayerVisibility();
                    await this.renderUfOverview();
                } else if (this.isMesoOverviewMode) {
                    this._preserveViewOnNextRender = false;
                    if (this.map.hasLayer(this.clusterGroup)) {
                        this.map.removeLayer(this.clusterGroup);
                    }
                    this.prepareChoroplethMapMode();
                    this.setOverviewLayerVisibility();
                    await this.renderMesoOverview();
                } else {
                    this.setOverviewLayerVisibility();
                    if (this.mapView === "heat") {
                        if (this.map.hasLayer(this.clusterGroup)) {
                            this.map.removeLayer(this.clusterGroup);
                        }
                        await this.renderHeatLayer();
                    } else if (this.mapView === "boundaries") {
                        if (this.map.hasLayer(this.clusterGroup)) {
                            this.map.removeLayer(this.clusterGroup);
                        }
                        await this.renderBoundariesLayer();
                    } else {
                        if (!this.map.hasLayer(this.clusterGroup)) {
                            this.map.addLayer(this.clusterGroup);
                        }
                        await this.renderMarkers();
                    }
                }
            } finally {
                this.mapRendering = false;
                this.renderProgress = 100;
                this.loadingMessage = "";

                if (pinnedStillVisible && pinnedMarker && this.isRegionalMode) {
                    const latest =
                        this.markers.find((m) => String(m.ibge) === pinnedIbge) ||
                        this.mapMarkersForRender.find((m) => String(m.ibge) === pinnedIbge) ||
                        pinnedMarker;
                    this.active = latest;
                    this.tooltipPinned = true;
                    this.positionTooltip();
                    this.syncMuniModalScrollLock();
                }

                if (this.isOverviewMode || this.isMesoOverviewMode) {
                    this.$nextTick(() =>
                        this.refreshMapLayout({ immediate: true, force: true }),
                    );
                }
            }
        },

        async fetchGeoMalha(scope, uf = "") {
            const key =
                scope === "meso"
                    ? `meso:${uf}`
                    : scope === "micro"
                      ? `micro:${uf}`
                      : scope === "municipal"
                        ? `municipal:${uf}`
                        : "brazil";
            if (this._geoCache[key]) {
                return this._geoCache[key];
            }

            const urls = [];
            if (this.mapGeoUrl) {
                const apiUrl = new URL(this.mapGeoUrl, window.location.origin);
                apiUrl.searchParams.set(
                    "scope",
                    scope === "meso" || scope === "micro" || scope === "municipal"
                        ? scope
                        : "brazil",
                );
                if (
                    (scope === "meso" ||
                        scope === "micro" ||
                        scope === "municipal") &&
                    uf
                ) {
                    apiUrl.searchParams.set("uf", uf);
                }
                urls.push(apiUrl.toString());
            }
            if (
                scope !== "meso" &&
                scope !== "micro" &&
                scope !== "municipal" &&
                this.mapGeoFallbackUrl
            ) {
                urls.push(this.mapGeoFallbackUrl);
            }

            let lastError = null;
            for (const url of urls) {
                try {
                    const geo = await this.fetchGeoJsonFromUrl(url);
                    this._geoCache[key] = geo;

                    return geo;
                } catch (error) {
                    lastError = error;
                    console.warn("horizonte geo malha", scope, uf || "brazil", error);
                }
            }

            throw lastError ?? new Error("malha indisponível");
        },

        async fetchGeoJsonFromUrl(url) {
            const res = await fetch(url, {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });
            if (!res.ok) {
                throw new Error(`malha HTTP ${res.status}`);
            }

            const geo = await res.json();
            if (!geo || geo.type !== "FeatureCollection") {
                throw new Error("malha inválida");
            }

            return geo;
        },

        renderUfOverviewFallback(points) {
            this.ensureChoroplethPane();
            const group = L.layerGroup();
            const bounds = [];

            for (const p of points) {
                const lat = Number(p.capital_lat ?? p.lat);
                const lng = Number(p.capital_lng ?? p.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const intensity = Math.max(0.15, Number(p.heat_intensity ?? 0));
                const circle = L.circleMarker([lat, lng], {
                    pane: "horizonteChoropleth",
                    radius: 10,
                    fillColor: heatColor(intensity),
                    ...HEAT_CIRCLE_BORDER,
                    fillOpacity: 0.72,
                });
                circle.bindTooltip(ufOverviewTooltipHtml(p, this.ufLabel.bind(this)), {
                    direction: "top",
                    sticky: true,
                    className: "serv-horizonte-geo-tooltip",
                });
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    void this.selectUfFromOverview(p.uf);
                });
                group.addLayer(circle);
            }

            this.choroplethLayer = group;
            group.addTo(this.map);
            this._lastChoroplethLayer = group;
            this.applyChoroplethPointerPolicy(true);
            this.fitMapBounds(bounds, 4);
        },

        async renderUfOverview() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.ufLayer.clearLayers();
            this.clearChoroplethLayer();
            this.markerLayers = [];

            const points =
                this.ufMapPoints.length > 0 ? this.ufMapPoints : this.nationalUfMapPoints;
            const byUf = new Map(points.map((p) => [String(p.uf), p]));

            let geo;
            try {
                geo = await this.fetchGeoMalha("brazil");
            } catch (error) {
                console.warn("horizonte geo malha (brazil)", error);
                this.renderUfOverviewFallback(points);

                return;
            }

            await this.whenMapHasSize();
            this.ensureChoroplethPane();

            const geoLayer = L.geoJSON(geo, {
                pane: "horizonteChoropleth",
                interactive: true,
                style: (feature) => {
                    const uf = ibgeCodareaToUf(feature?.properties?.codarea);
                    const p = byUf.get(uf);
                    const intensity = p?.heat_intensity ?? 0.08;

                    return ufChoroplethStyle(intensity);
                },
                onEachFeature: (feature, layer) => {
                    const uf = ibgeCodareaToUf(feature?.properties?.codarea);
                    if (!uf) {
                        return;
                    }
                    const p = byUf.get(uf);
                    const baseStyle = ufChoroplethStyle(p?.heat_intensity ?? 0.08);
                    const tooltipHtml = p
                        ? ufOverviewTooltipHtml(p, this.ufLabel.bind(this))
                        : `<strong>${escapeHtml(this.ufLabel(uf))}</strong>`;

                    bindChoroplethInteractions(layer, baseStyle, tooltipHtml);
                    layer.on("click", (e) => {
                        L.DomEvent.stopPropagation(e);
                        void this.selectUfFromOverview(uf);
                    });
                },
            });

            addCapitalMarkers(geoLayer, points, "horizonteChoropleth");

            this.choroplethLayer = geoLayer;
            geoLayer.addTo(this.map);
            this._lastChoroplethLayer = geoLayer;
            geoLayer.eachLayer((layer) => {
                if (typeof layer.bringToFront === "function") {
                    layer.bringToFront();
                }
            });
            this.applyChoroplethPointerPolicy(true);
            this.syncChoroplethMapLayout(geoLayer, {
                maxZoom: 5,
                padding: [48, 48],
                force: true,
            });
        },

        renderMesoOverviewFallback(points) {
            this.ensureChoroplethPane();
            const group = L.layerGroup();
            const bounds = [];

            for (const p of points) {
                const lat = Number(p.lat);
                const lng = Number(p.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const intensity = Math.max(0.15, Number(p.heat_intensity ?? 0));
                const idx = bounds.length - 1;
                const base = mesoPaletteColor(p.meso_id, idx);
                const circle = L.circleMarker([lat, lng], {
                    pane: "horizonteChoropleth",
                    radius: 10,
                    fillColor: base,
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.78,
                });
                circle.bindTooltip(mesoOverviewTooltipHtml(p), {
                    direction: "top",
                    sticky: true,
                    className: "serv-horizonte-geo-tooltip",
                });
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    void this.selectMesoFromOverview(p.meso_id);
                });
                group.addLayer(circle);
            }

            this.choroplethLayer = group;
            group.addTo(this.map);
            this._lastChoroplethLayer = group;
            this.applyChoroplethPointerPolicy(true);

            if (bounds.length > 0) {
                this.fitMapBounds(bounds, 6);
            } else {
                const center = this.resolveUfCenter(this.scopeUf);
                this.map.setView(center, this.regionalUfZoom());
            }
        },

        async renderMesoOverview() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.ufLayer.clearLayers();
            this.clearChoroplethLayer();
            this.markerLayers = [];

            const points = this.activeMesoMapPoints;
            const byMeso = new Map(points.map((p) => [String(p.meso_id), p]));

            let geo;
            try {
                geo = await this.fetchGeoMalha("meso", this.scopeUf);
            } catch (error) {
                console.warn("horizonte geo malha (meso)", this.scopeUf, error);
                this.renderMesoOverviewFallback(points);

                return;
            }

            const mesoColors = buildMesoAdjacencyColorMap(geo);

            await this.whenMapHasSize();
            this.ensureChoroplethPane();

            const geoLayer = L.geoJSON(geo, {
                pane: "horizonteChoropleth",
                interactive: true,
                style: (feature) => {
                    const mesoId = String(feature?.properties?.codarea ?? "");
                    const p = byMeso.get(mesoId);
                    const fill =
                        mesoColors.get(mesoId) ??
                        mesoPaletteColor(mesoId, mesoColors.size);

                    return mesoChoroplethStyle(fill, p?.heat_intensity ?? 0.12);
                },
                onEachFeature: (feature, layer) => {
                    const mesoId = String(feature?.properties?.codarea ?? "");
                    if (mesoId === "") {
                        return;
                    }
                    const p = byMeso.get(mesoId);
                    const fill =
                        mesoColors.get(mesoId) ??
                        mesoPaletteColor(mesoId, mesoColors.size);
                    const baseStyle = mesoChoroplethStyle(
                        fill,
                        p?.heat_intensity ?? 0.12,
                    );
                    const tooltipHtml = p
                        ? mesoOverviewTooltipHtml(p)
                        : `<strong>${escapeHtml(mesoId)}</strong>`;

                    bindChoroplethInteractions(
                        layer,
                        baseStyle,
                        tooltipHtml,
                        mesoChoroplethHoverStyle,
                    );
                    layer.on("click", (e) => {
                        L.DomEvent.stopPropagation(e);
                        void this.selectMesoFromOverview(mesoId);
                    });
                },
            });

            this.choroplethLayer = geoLayer;
            geoLayer.addTo(this.map);
            this._lastChoroplethLayer = geoLayer;
            geoLayer.eachLayer((layer) => {
                if (typeof layer.bringToFront === "function") {
                    layer.bringToFront();
                }
            });
            this.applyChoroplethPointerPolicy(true);
            this.syncChoroplethMapLayout(geoLayer, {
                maxZoom: 8,
                padding: [40, 40],
                force: true,
            });
        },

        async renderMarkers() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.ufLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.markerLayers = [];
            this.clearMunicipalBoundaryLayer();

            const list = this.mapMarkersForRender;
            const total = list.length;

            for (let i = 0; i < list.length; i++) {
                const m = list[i];
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                const style = markerVisualStyle(m, this.colors);
                const marker = L.circleMarker([lat, lng], {
                    ...style,
                    renderer: this.ensureRegionalCanvasRenderer(),
                    className: isApproxCoord(m) ? "serv-horizonte-marker--approx" : "",
                });
                marker.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });
                this.clusterGroup.addLayer(marker);
                this.markerLayers.push({ circle: marker, marker: m });

                if (i > 0 && i % 80 === 0) {
                    this.renderProgress = Math.round((i / total) * 100);
                    await new Promise((r) => requestAnimationFrame(r));
                }
            }

            // Garante que o canvas dos marcadores receba cliques (a política de
            // pointer-events pode ter ficado desativada vinda de uma vista coroplética).
            this.applyChoroplethPointerPolicy(false);

            if (this._preserveViewOnNextRender) {
                this._preserveViewOnNextRender = false;
                this.refreshCanvasMarkersAfterZoom();
            } else {
                const bounds = list
                    .map((m) => [Number(m.lat), Number(m.lng)])
                    .filter(([la, ln]) => isValidCoord(la, ln));
                this.fitMapBounds(bounds, this.scopeUf ? 5 : 4);
            }

            await this.renderMicroRegionsOverlay();
        },

        refreshBoundaryHighlight() {
            if (this.mapView !== "boundaries" || !this.municipalBoundaryLayer) {
                return;
            }
            const activeIbge = normalizeIbgeCodarea(this.active?.ibge);
            this.municipalBoundaryLayer.eachLayer((layer) => {
                const ibge = normalizeIbgeCodarea(
                    layer.feature?.properties?.codarea,
                );
                layer.setStyle(
                    municipalBoundaryViewStyle({
                        highlighted: ibge !== "" && ibge === activeIbge,
                    }),
                );
            });
        },

        async renderHeatLayer() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.ufLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.markerLayers = [];
            this.clearMunicipalBoundaryLayer();

            const list = this.mapMarkersForRender.filter((m) => !m.consultoria_active);
            const intensityMap = buildHeatIntensityMap(list);
            const total = list.length;
            const batchSize = this.regionalDisplayPolicy?.heavy_regional ? 50 : 80;
            const bounds = [];

            for (let i = 0; i < list.length; i++) {
                const m = list[i];
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const ibge = String(m.ibge ?? "");
                const intensity = intensityMap.get(ibge) ?? 0.08;
                const pressure = pressureHeatRaw(m);
                const circle = L.circleMarker([lat, lng], {
                    ...municipalHeatCircleStyle(intensity),
                    renderer: this.ensureRegionalCanvasRenderer(),
                });
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });
                circle.bindTooltip(
                    `<strong>${escapeHtml(m.name)}</strong><br>` +
                        `${escapeHtml("Pressão FUNDEB")}: ${formatScoreValue(pressure)}/100<br>` +
                        `<span class="text-slate-500">${escapeHtml("Intensidade no recorte")}: ${Math.round(intensity * 100)}%</span>`,
                    { direction: "top", sticky: true },
                );
                circle.addTo(this.heatLayer);

                if (i > 0 && i % batchSize === 0) {
                    this.renderProgress = Math.round((i / total) * 100);
                    await new Promise((r) => requestAnimationFrame(r));
                }
            }

            this.applyChoroplethPointerPolicy(false);

            if (this._preserveViewOnNextRender) {
                this._preserveViewOnNextRender = false;
                this.refreshCanvasMarkersAfterZoom();
            } else {
                this.fitMapBounds(bounds, this.scopeUf ? 5 : 4);
            }

            await this.renderMicroRegionsOverlay();
        },

        fitMapBounds(bounds, fallbackZoom = 4, force = false) {
            if (!this.map) {
                return;
            }
            if (this._mapUserAdjustedView && !force) {
                return;
            }
            const valid = bounds.filter(([la, ln]) => isValidCoord(la, ln));
            if (valid.length > 0) {
                const heavy = Boolean(this.regionalDisplayPolicy?.heavy_regional);
                const maxZoom = this.scopeUf
                    ? heavy
                        ? 7
                        : valid.length > 80
                          ? 7
                          : 8
                    : valid.length > 80
                      ? 5
                      : 6;
                this.map.fitBounds(valid, { padding: [48, 48], maxZoom, animate: !heavy });
            } else {
                this.map.setView([-14.2, -51.9], fallbackZoom);
            }
        },

        refreshCanvasMarkersAfterZoom() {
            if (!this.map || !this.isRegionalMode || !this.canvasRenderer) {
                return;
            }
            const layer =
                this.mapView === "heat" && this.map.hasLayer(this.heatLayer)
                    ? this.heatLayer
                    : this.map.hasLayer(this.clusterGroup)
                      ? this.clusterGroup
                      : null;
            if (!layer) {
                return;
            }
            layer.eachLayer((marker) => {
                if (typeof marker.redraw === "function") {
                    marker.redraw();
                }
            });
        },

        onScopeUfPick(event) {
            const uf = String(event?.target?.value ?? "")
                .trim()
                .toUpperCase();
            if (uf === "") {
                void this.backToOverview();
                return;
            }
            void this.selectUf(uf, true);
        },

        async enterRegionalWithPressureLens(uf) {
            this.hideApproxOnMap = false;
            this.applyDecisionLens("high_pressure", { enteringRegional: true });
            this.mapView = "markers";
            await this.selectUf(uf, false, true);
            if (!this.isRegionalMode) {
                return;
            }
            if (this.filteredCount === 0 && this.markers.length > 0) {
                this.applyDecisionLens("prospects", { enteringRegional: true });
                this.recomputeFilteredMarkers();
                this.ensurePointsVisibleOnMap();
                await this.scheduleMapRefresh();
            }
            if (this.filteredCount === 0 && this.markers.length > 0) {
                this.applyDecisionLens("all", { enteringRegional: true });
                this.recomputeFilteredMarkers();
                this.ensurePointsVisibleOnMap();
                await this.scheduleMapRefresh();
            }
        },

        async selectMesoFromOverview(mesoId) {
            const scoped = String(mesoId ?? "").trim();
            if (!scoped) {
                return;
            }
            this.closeTooltip();
            this.closeUfSummary();
            this.scopeMeso = scoped;
            this.mapMode = "regional";
            this.showAllOnMap = false;
            this.renderCapDismissed = false;
            this._mapUserAdjustedView = false;
            this._preserveViewOnNextRender = false;
            const mesoPoint = this.mesoMapPoints.find(
                (p) => String(p.meso_id) === scoped,
            );
            if (mesoPoint?.display_policy) {
                this.applyRegionalRenderPolicyFromData(mesoPoint.display_policy);
            }
            this.recomputeFilteredMarkers();
            this._filterSignature = this.filterSignature();
            this.ensurePointsVisibleOnMap();
            if (this.filteredCount === 0 && this.markers.length > 0) {
                this.applyDecisionLens("all", { enteringRegional: true });
                this.recomputeFilteredMarkers();
            }
            const mesoName = mesoPoint?.meso_name || scoped;
            this.initialViewNotice = {
                kind: "meso_detail",
                message: `${mesoName} · ${this.filteredCount.toLocaleString("pt-BR")} municípios no recorte`,
                uf: this.scopeUf,
            };
            await this.scheduleMapRefresh();
        },

        async backToMesoOverview() {
            if (!this.scopeUf || this.mesoMapPoints.length < 1) {
                await this.backToOverview();
                return;
            }
            this.closeTooltip();
            this.scopeMeso = "";
            this.mapMode = "meso_overview";
            this.showAllOnMap = false;
            this.renderCapDismissed = false;
            this._mapUserAdjustedView = false;
            this.applyRegionalRenderPolicy();
            this.recomputeFilteredMarkers();
            this._filterSignature = this.filterSignature();
            const mesoMeta = this.meta?.meso_overview;
            this.initialViewNotice = {
                kind: "meso",
                message:
                    mesoMeta?.reason ||
                    `${this.mesoMapPoints.length.toLocaleString("pt-BR")} mesorregiões · passe o mouse para ver dados.`,
                uf: this.scopeUf,
            };
            await this.scheduleMapRefresh();
        },

        async selectUfFromOverview(uf) {
            await this.enterRegionalWithPressureLens(uf);
        },

        async selectPriorityUf(uf) {
            await this.enterRegionalWithPressureLens(uf);
        },

        async selectUf(uf, userInitiated = true, force = false) {
            const scoped = String(uf ?? "").trim().toUpperCase();
            if (!scoped) {
                return;
            }
            if (
                !force &&
                this.isUfScopedMode &&
                this.loadedRegionalUf === scoped &&
                this.markers.length > 0 &&
                !this.regionalLoading
            ) {
                return;
            }
            if (this.regionalLoading && this.pendingRegionalUf === scoped) {
                return;
            }
            this.highlightIbge = "";
            this.scopeMeso = "";
            if (this.loadedRegionalUf !== scoped) {
                this.closeMapOverlays();
            }
            if (userInitiated && !this.isOverviewMode) {
                this.applyDefaultDecisionView();
            }
            await this.fetchRegional(scoped);
        },

        async backToOverview() {
            this.cancelPendingMapRefresh();
            this.closeTooltip();
            this.scopeUf = "";
            this.loadedRegionalUf = "";
            this.scopeMeso = "";
            this.mesoMapPoints = [];
            this.markers = [];
            this.mapMode = "overview";
            this.ufFundebInsights = null;
            this.ufSummaryOpen = false;
            this._mapUserAdjustedView = false;
            this.showAllOnMap = false;
            if (this.nationalUfMapPoints.length > 0) {
                this.ufMapPoints = this.nationalUfMapPoints;
                const snap = this.nationalOverviewSnapshot;
                if (snap && typeof snap === "object") {
                    if (snap.summary && typeof snap.summary === "object") {
                        this.summary = snap.summary;
                    }
                    if (Array.isArray(snap.topProspects)) {
                        this.topProspects = snap.topProspects;
                    }
                    if (Array.isArray(snap.focusSegments)) {
                        this.focusSegments = snap.focusSegments;
                    }
                    if (snap.sgeSummary && typeof snap.sgeSummary === "object") {
                        this.sgeSummary = snap.sgeSummary;
                    }
                    if (snap.meta && typeof snap.meta === "object") {
                        this.meta = snap.meta;
                        this.displayPolicy =
                            this.meta.display_policy &&
                            typeof this.meta.display_policy === "object"
                                ? this.meta.display_policy
                                : null;
                    }
                }
                this.setOverviewNotice();
                await this.scheduleMapRefresh();
                return;
            }
            await this.fetchOverview();
        },

        selectMarker(m, ev = null) {
            if (!m) {
                return;
            }
            if (ev && typeof L !== "undefined" && L.DomEvent?.stopPropagation) {
                L.DomEvent.stopPropagation(ev);
            }
            this.closeUfSummary();
            this.closeSgeForm();
            this.highlightIbge = String(m.ibge ?? "");
            this.geoCoordCopied = false;
            if (this._geoCoordCopiedTimer) {
                window.clearTimeout(this._geoCoordCopiedTimer);
                this._geoCoordCopiedTimer = null;
            }
            this.active = m;
            this.tooltipPinned = true;
            this.positionTooltip();
            this.syncMuniModalScrollLock();
            void this.loadEnrollmentSeries(m);
            this.refreshBoundaryHighlight();
        },

        syncMuniModalScrollLock() {
            const open = Boolean(this.tooltipPinned && this.active);
            document.documentElement.classList.toggle(
                "serv-horizonte-muni-modal-open",
                open,
            );
        },

        positionTooltip() {
            const verticalMargin = 48;
            const capPx = 720;
            const viewportEl =
                document.fullscreenElement &&
                this.$refs.mapShell &&
                document.fullscreenElement === this.$refs.mapShell
                    ? this.$refs.mapShell
                    : null;
            const viewportH = viewportEl?.clientHeight || window.innerHeight;
            const viewportCap = Math.round(viewportH * 0.88);
            const maxH = Math.min(
                Math.max(360, viewportH - verticalMargin),
                capPx,
                viewportCap,
            );
            this.tooltipStyle = `--horizonte-muni-modal-h:${Math.round(maxH)}px;`;
        },

        closeMapOverlays() {
            this.closeTooltip();
            this.closeUfSummary();
            this.closeSgeForm();
        },

        closeTooltip() {
            this.destroyEnrollmentSeriesChart();
            this.geoCoordCopied = false;
            if (this._geoCoordCopiedTimer) {
                window.clearTimeout(this._geoCoordCopiedTimer);
                this._geoCoordCopiedTimer = null;
            }
            this.active = null;
            this.tooltipPinned = false;
            this.tooltipStyle = "";
            this.syncMuniModalScrollLock();
            this.refreshBoundaryHighlight();
        },

        closeUfSummary() {
            this.ufSummaryOpen = false;
        },

        toggleUfSummaryVisibility() {
            if (!this.isUfScopedMode || !this.scopeUf) {
                return;
            }
            if (this.ufSummaryOpen) {
                this.closeUfSummary();
                return;
            }
            void this.toggleUfSummaryPanel();
        },

        /** Recentra o mapa na mesorregião activa ou no estado inteiro. */
        async centerMapToCurrentScope({ force = true } = {}) {
            if (!this.map || !this.scopeUf) {
                return;
            }

            if (force) {
                this._mapUserAdjustedView = false;
            }

            const mesoId = String(this.scopeMeso ?? "").trim();

            if (this.isRegionalMode && mesoId !== "") {
                const bounds = this.markers
                    .filter((m) => String(m.meso_id ?? "") === mesoId)
                    .map((m) => [Number(m.lat), Number(m.lng)])
                    .filter(([la, ln]) => isValidCoord(la, ln));
                if (bounds.length > 0) {
                    this.fitMapBounds(bounds, 7, force);
                    return;
                }
                const mesoPoint = this.mesoMapPoints.find(
                    (p) => String(p.meso_id) === mesoId,
                );
                const lat = Number(mesoPoint?.lat);
                const lng = Number(mesoPoint?.lng);
                if (isValidCoord(lat, lng)) {
                    this.map.flyTo([lat, lng], 8, { duration: 0.65 });
                }
                return;
            }

            if (
                this.isMesoOverviewMode &&
                this._lastChoroplethLayer?.getBounds?.()?.isValid?.()
            ) {
                this.map.invalidateSize({ animate: false });
                this.map.fitBounds(this._lastChoroplethLayer.getBounds(), {
                    padding: [40, 40],
                    maxZoom: 8,
                    animate: true,
                });
                return;
            }

            if (this.isRegionalMode) {
                const bounds = this.markers
                    .map((m) => [Number(m.lat), Number(m.lng)])
                    .filter(([la, ln]) => isValidCoord(la, ln));
                if (bounds.length > 0) {
                    this.fitMapBounds(bounds, this.scopeUf ? 5 : 4, force);
                    return;
                }
            }

            if (this.isMesoOverviewMode) {
                await this.scheduleMapRefresh();
                window.setTimeout(() => {
                    if (
                        !this.map ||
                        !this._lastChoroplethLayer?.getBounds?.()?.isValid?.()
                    ) {
                        return;
                    }
                    this.map.fitBounds(this._lastChoroplethLayer.getBounds(), {
                        padding: [40, 40],
                        maxZoom: 8,
                        animate: false,
                    });
                }, 250);
                return;
            }

            const [lat, lng] = this.resolveUfCenter(this.scopeUf);
            this.map.flyTo([lat, lng], this.regionalUfZoom(), { duration: 0.65 });
        },

        resolveUfCenter(uf) {
            const code = String(uf ?? "")
                .trim()
                .toUpperCase();
            if (!code) {
                return [-14.2, -51.9];
            }
            const points =
                this.nationalUfMapPoints.length > 0
                    ? this.nationalUfMapPoints
                    : this.ufMapPoints;
            const fromPoint = points.find((p) => p.uf === code);
            if (fromPoint) {
                const lat = Number(fromPoint.lat);
                const lng = Number(fromPoint.lng);
                if (isValidCoord(lat, lng)) {
                    return [lat, lng];
                }
            }
            const coords = this.markers
                .filter((m) => String(m.uf ?? "").toUpperCase() === code)
                .map((m) => [Number(m.lat), Number(m.lng)])
                .filter(([la, ln]) => isValidCoord(la, ln));
            if (coords.length > 0) {
                const lat = coords.reduce((sum, p) => sum + p[0], 0) / coords.length;
                const lng = coords.reduce((sum, p) => sum + p[1], 0) / coords.length;
                return [lat, lng];
            }
            return [-14.2, -51.9];
        },

        regionalUfZoom() {
            const heavy = Boolean(this.regionalDisplayPolicy?.heavy_regional);
            const count = this.markers.length;
            if (heavy) {
                return 6.5;
            }
            if (count > 120) {
                return 6;
            }
            if (count > 40) {
                return 6.5;
            }
            return 7;
        },

        async toggleUfSummaryPanel() {
            if (!this.isUfScopedMode || !this.scopeUf || !this.map) {
                return;
            }
            if (this.ufSummaryOpen) {
                this.closeUfSummary();
                return;
            }
            this.closeTooltip();
            await this.centerMapToCurrentScope({ force: true });
            this._mapUserAdjustedView = true;
            this.ufSummaryOpen = true;
            this.$nextTick(() =>
                this.refreshMapLayout({ immediate: true, force: true }),
            );
        },

        repositionFloatingPanels() {
            if (this.tooltipPinned && this.active) {
                this.positionTooltip();
            }
        },

        pickSearch(m) {
            this.searchQuery = "";
            void this.focusMunicipality(m);
        },

        async flyToMarker(m) {
            await this.focusMunicipality(m);
        },

        async prepareMunicipalityFocus(m) {
            if (!m) {
                return false;
            }

            const targetUf = String(m.uf ?? "").trim().toUpperCase();
            const targetIbge = String(m.ibge ?? "").trim();
            if (targetIbge === "") {
                return false;
            }

            if (this.isOverviewMode && targetUf) {
                await this.selectUf(targetUf, false);
            } else if (targetUf && this.scopeUf !== targetUf) {
                await this.selectUf(targetUf, false);
            }

            if (this.isMesoOverviewMode) {
                this.mapMode = "regional";
                this.scopeMeso = "";
                this._mapUserAdjustedView = false;
                this.recomputeFilteredMarkers();
                this._filterSignature = this.filterSignature();
            }

            const mesoId = String(m.meso_id ?? "").trim();
            if (
                this.isRegionalMode &&
                this.scopeMeso &&
                mesoId &&
                this.scopeMeso !== mesoId
            ) {
                this.scopeMeso = "";
                this.recomputeFilteredMarkers();
                this._filterSignature = this.filterSignature();
            }

            if (
                !this.filteredMarkersList.some(
                    (row) => String(row.ibge) === targetIbge,
                )
            ) {
                this.applyDecisionLens("all", { keepMapView: true });
                this.recomputeFilteredMarkers();
                this._filterSignature = this.filterSignature();
            }

            this.searchQuery = "";
            this.hideApproxOnMap = false;
            this.ensurePointsVisibleOnMap();
            this.highlightIbge = targetIbge;
            this._mapUserAdjustedView = true;
            this._preserveViewOnNextRender = true;

            return true;
        },

        fitMapToMunicipality(m, { animate = true } = {}) {
            if (!this.map || !m) {
                return false;
            }

            const ibge = normalizeIbgeCodarea(m.ibge);
            if (ibge !== "" && this.municipalBoundaryLayer) {
                let matched = null;
                this.municipalBoundaryLayer.eachLayer((layer) => {
                    if (
                        normalizeIbgeCodarea(layer.feature?.properties?.codarea) ===
                        ibge
                    ) {
                        matched = layer;
                    }
                });
                const bounds = matched?.getBounds?.();
                if (bounds?.isValid?.()) {
                    this.map.fitBounds(bounds, {
                        padding: [48, 48],
                        maxZoom: 10,
                        animate,
                    });

                    return true;
                }
            }

            const lat = Number(m.lat);
            const lng = Number(m.lng);
            if (!isValidCoord(lat, lng)) {
                return false;
            }

            const zoom = isApproxCoord(m) ? 7 : 8;
            if (animate) {
                this.map.flyTo([lat, lng], zoom, { duration: 0.75 });
            } else {
                this.map.setView([lat, lng], zoom, { animate: false });
            }

            return true;
        },

        async focusMunicipality(m) {
            if (!this.map) {
                return;
            }
            if (!(await this.prepareMunicipalityFocus(m))) {
                return;
            }

            await this.scheduleMapRefresh();

            const moved = this.fitMapToMunicipality(m);
            if (moved) {
                await new Promise((resolve) => window.setTimeout(resolve, 400));
            }

            const latest =
                this.markers.find(
                    (row) => String(row.ibge) === String(m.ibge ?? ""),
                ) || m;
            this.selectMarker(latest);
        },

        setMapView(view) {
            this.mapView = view;
        },

        resetFilters() {
            this.applyDefaultDecisionView();
        },

        resetFiltersToAll() {
            this.applyDecisionLens("all");
        },

        enableFullMapRender() {
            if (!this.canShowAllOnMap) {
                return;
            }
            this.showAllOnMap = true;
            this.renderCapDismissed = true;
            void this.scheduleMapRefresh();
        },

        restoreMapRenderCap() {
            this.showAllOnMap = false;
            this.renderCapDismissed = false;
            void this.scheduleMapRefresh();
        },

        mapDrawAllLabel() {
            const total = Number(this.mapInteractionStats.total ?? this.filteredCount ?? 0);
            return `Desenhar todos (${total.toLocaleString("pt-BR")})`;
        },

        dismissInitialNotice() {
            this.initialViewNotice = null;
        },

        async applyFocusSegment(segment) {
            if (!segment?.filter) {
                return;
            }
            const f = segment.filter;
            this.applyDecisionLens("prospects", { keepMapView: true });
            this.viewPreset = "custom";
            if (f.tier) {
                this.filterTier = f.tier;
            }
            if (f.min_success != null) {
                this.minSuccessScore = Number(f.min_success);
            }
            if (f.min_benefit != null) {
                this.minBenefitScore = Number(f.min_benefit);
            }
            if (f.min_matriculas != null) {
                this.minMatriculas = Number(f.min_matriculas);
            }
            if (f.min_financial != null) {
                this.minFinancial = Number(f.min_financial);
            }
            if (f.min_pedagogical != null) {
                this.minPedagogical = Number(f.min_pedagogical);
            }
            if (f.min_readiness != null) {
                this.minReadiness = Number(f.min_readiness);
            }
            if (f.require_fundeb) {
                this.requireFundeb = true;
            }
            if (f.require_censo) {
                this.requireCenso = true;
            }
            if (f.require_saeb) {
                this.requireSaeb = true;
            }
            if (f.require_cadunico) {
                this.requireCadunico = true;
            }
            if (f.only_missing_sge) {
                this.onlyMissingSge = true;
                this.filterTier = f.tier || "prospects";
            }
            if (f.only_with_alerts) {
                this.onlyWithAlerts = true;
            }
            if (f.min_social != null) {
                this.minSocialDemand = Number(f.min_social);
            }
            this.hideConsultoria = true;
            this.mapView = "heat";
            if (this.isOverviewMode) {
                const uf =
                    this.displayPolicy?.initial_uf ||
                    this.ufRankings?.[0]?.uf ||
                    this.ufMapPoints?.[0]?.uf;
                if (uf) {
                    await this.selectUf(uf, false);
                }
            }
        },

        tierLabel(m) {
            return m?.tier_label || m?.tier || "";
        },

        propensityScore(m) {
            return Math.max(0, Math.min(100, Number(m?.success_score ?? 0)));
        },

        propensityPercentLabel(m) {
            const score = this.propensityScore(m);
            return score > 0 ? `${Math.round(score)}%` : "—";
        },

        propensityLevelShort(m) {
            const score = this.propensityScore(m);
            const { high, medium } = this.scoreThresholds;
            if (score >= high) {
                return "Alta";
            }
            if (score >= medium) {
                return "Média";
            }
            if (score > 0) {
                return "Baixa";
            }
            return "";
        },

        propensityRingStyle(m) {
            const score = this.propensityScore(m);
            const { high, medium } = this.scoreThresholds;
            let tone = "rgb(100 116 139)";
            if (score >= high) {
                tone = "rgb(225 29 72)";
            } else if (score >= medium) {
                tone = "rgb(217 119 6)";
            } else if (score > 0) {
                tone = "rgb(59 130 246)";
            }
            return `--ring-score:${score};--ring-tone:${tone}`;
        },

        modalHeaderMeta(m) {
            if (!m) {
                return "";
            }

            return `IBGE ${m.ibge ?? "—"}`;
        },

        modalHeaderUfLabel(m) {
            if (!m?.uf) {
                return "";
            }

            return this.ufLabel(m.uf);
        },

        modalHeaderMesoLabel(m) {
            if (!m) {
                return "";
            }

            const meso = String(m.meso_name ?? "").trim();
            if (meso !== "") {
                return meso;
            }

            const mesoId = String(m.meso_id ?? "").trim();
            return mesoId !== "" ? `Mesorregião ${mesoId}` : "";
        },

        hasModalHeaderGeoInfo(m) {
            if (!m) {
                return false;
            }

            return (
                this.hasModalHeaderGeoPosition(m) ||
                this.hasModalHeaderGeoDistance(m) ||
                this.hasModalHeaderGeoArea(m)
            );
        },

        hasModalHeaderGeoPosition(m) {
            if (!m) {
                return false;
            }

            const lat = Number(m.lat);
            const lng = Number(m.lng);

            return isValidCoord(lat, lng);
        },

        hasModalHeaderGeoDistance(m) {
            if (!m) {
                return false;
            }

            const km = Number(m.distancia_capital_km);

            return Number.isFinite(km) && km >= 0;
        },

        hasModalHeaderGeoArea(m) {
            if (!m) {
                return false;
            }

            const area = Number(m.area_km2);

            return Number.isFinite(area) && area > 0;
        },

        modalHeaderGeoPositionLabel(m) {
            if (!m) {
                return "";
            }

            const lat = Number(m.lat);
            const lng = Number(m.lng);
            if (!isValidCoord(lat, lng)) {
                return "";
            }

            const latLabel = `${Math.abs(lat).toFixed(3).replace(".", ",")}°${lat < 0 ? "S" : "N"}`;
            const lngLabel = `${Math.abs(lng).toFixed(3).replace(".", ",")}°${lng < 0 ? "W" : "E"}`;
            if (m.coord_approximate) {
                return `Indicativa · ${latLabel} · ${lngLabel}`;
            }

            return `${latLabel} · ${lngLabel}`;
        },

        modalHeaderGeoPositionClipboard(m) {
            if (!m) {
                return "";
            }

            const lat = Number(m.lat);
            const lng = Number(m.lng);
            if (!isValidCoord(lat, lng)) {
                return "";
            }

            return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        },

        async copyModalGeoPosition(m) {
            const text = this.modalHeaderGeoPositionClipboard(m);
            if (text === "") {
                return;
            }

            let copied = false;
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                    copied = true;
                }
            } catch {
                copied = false;
            }

            if (!copied) {
                try {
                    const textarea = document.createElement("textarea");
                    textarea.value = text;
                    textarea.setAttribute("readonly", "");
                    textarea.style.position = "fixed";
                    textarea.style.left = "-9999px";
                    document.body.appendChild(textarea);
                    textarea.select();
                    copied = document.execCommand("copy");
                    document.body.removeChild(textarea);
                } catch {
                    copied = false;
                }
            }

            if (!copied) {
                return;
            }

            this.geoCoordCopied = true;
            if (this._geoCoordCopiedTimer) {
                window.clearTimeout(this._geoCoordCopiedTimer);
            }
            this._geoCoordCopiedTimer = window.setTimeout(() => {
                this.geoCoordCopied = false;
                this._geoCoordCopiedTimer = null;
            }, 2500);
        },

        modalHeaderGeoDistanceLabel(m) {
            if (!m) {
                return "";
            }

            const km = Number(m.distancia_capital_km);
            const capital = String(m.capital_nome ?? "").trim();
            if (!Number.isFinite(km) || km < 0) {
                return "";
            }

            const dist = `${km.toLocaleString("pt-BR", {
                maximumFractionDigits: 1,
            })} km`;

            return capital !== "" ? `${dist} · ${capital}` : `${dist} da capital`;
        },

        modalHeaderGeoAreaLabel(m) {
            if (!m) {
                return "";
            }

            const area = Number(m.area_km2);
            if (!Number.isFinite(area) || area <= 0) {
                return "";
            }

            return `${area.toLocaleString("pt-BR", {
                maximumFractionDigits: 1,
            })} km²`;
        },

        modalHeaderGeoLabel(m) {
            if (!m) {
                return "";
            }

            const parts = [];
            const position = this.modalHeaderGeoPositionLabel(m);
            const distance = this.modalHeaderGeoDistanceLabel(m);
            const area = this.modalHeaderGeoAreaLabel(m);
            if (position !== "") {
                parts.push(position);
            }
            if (distance !== "") {
                parts.push(distance);
            }
            if (area !== "") {
                parts.push(area);
            }

            return parts.join(" · ");
        },

        modalHeaderApproxPositionLabel(m) {
            return this.modalHeaderGeoLabel(m);
        },

        modalHeaderSaebByYear(m) {
            if (!m) {
                return [];
            }

            /** @type {Map<number, {year: number, lp: number|null, mat: number|null}>} */
            const byYear = new Map();

            const ingest = (series, key, legacyValue) => {
                const items = Array.isArray(series) ? series : [];
                if (
                    items.length === 0 &&
                    Number.isFinite(Number(legacyValue)) &&
                    Number(legacyValue) > 0
                ) {
                    items.push({ value: Number(legacyValue) });
                }
                for (const point of items) {
                    const value = Number(point?.value);
                    if (!Number.isFinite(value) || value <= 0) {
                        continue;
                    }
                    const year = Number(point?.year);
                    const yearKey =
                        Number.isFinite(year) && year > 0 ? year : 0;
                    if (!byYear.has(yearKey)) {
                        byYear.set(yearKey, {
                            year: yearKey,
                            lp: null,
                            mat: null,
                        });
                    }
                    byYear.get(yearKey)[key] = value;
                }
            };

            ingest(m.saeb_lp_series, "lp", m.saeb_lp);
            ingest(m.saeb_mat_series, "mat", m.saeb_mat);

            return [...byYear.values()]
                .filter((row) => row.lp !== null || row.mat !== null)
                .sort((a, b) => b.year - a.year)
                .slice(0, 2);
        },

        modalHeaderHasSaeb(m) {
            return this.modalHeaderSaebByYear(m).length > 0;
        },

        modalHeaderSaebYearLabel(row) {
            if (!row) {
                return "";
            }

            const parts = [];
            if (row.year > 0) {
                parts.push(String(row.year));
            }
            if (row.lp !== null && Number.isFinite(Number(row.lp))) {
                parts.push(
                    `LP ${Math.round(Number(row.lp)).toLocaleString("pt-BR")}`,
                );
            }
            if (row.mat !== null && Number.isFinite(Number(row.mat))) {
                parts.push(
                    `MAT ${Math.round(Number(row.mat)).toLocaleString("pt-BR")}`,
                );
            }

            return parts.join(" · ");
        },

        modalHeaderSaebYearToneClass(index) {
            return index === 0
                ? "serv-horizonte-muni-modal__fact--saeb-year-latest"
                : "serv-horizonte-muni-modal__fact--saeb-year-previous";
        },

        shouldShowEnrollmentSeries(m) {
            return Boolean(m && !m.consultoria_active);
        },

        enrollmentSeriesUrlFor(ibge) {
            const url = this.enrollmentSeriesUrl.replace(
                "__IBGE__",
                encodeURIComponent(String(ibge)),
            );
            const params = new URLSearchParams();
            if (this.enrollmentSeriesDependencia && this.enrollmentSeriesDependencia !== "total") {
                params.set("dependencia", this.enrollmentSeriesDependencia);
            }
            const query = params.toString();

            return query ? `${url}?${query}` : url;
        },

        setEnrollmentSeriesDependencia(value) {
            const next = String(value ?? "total");
            if (
                next === this.enrollmentSeriesDependencia &&
                this.enrollmentSeriesReady
            ) {
                return;
            }
            this.enrollmentSeriesDependencia = next;
            if (this.active && this.shouldShowEnrollmentSeries(this.active)) {
                this._enrollmentSeriesLoadedDependencia = "";
                this.clearEnrollmentSeriesVisual();
                void this.loadEnrollmentSeries(this.active);
            }
        },

        clearEnrollmentSeriesVisual() {
            if (this._enrollmentSeriesAbort) {
                this._enrollmentSeriesAbort.abort();
                this._enrollmentSeriesAbort = null;
            }
            if (this._enrollmentSeriesChart) {
                this._enrollmentSeriesChart.destroy();
                this._enrollmentSeriesChart = null;
            }
            this.enrollmentSeriesReady = false;
            this.enrollmentSeriesError = null;
            this.enrollmentSeriesFootnote = "";
            this.enrollmentSeriesStageCounters = [];
            this.enrollmentSeriesStageYear = null;
            this.enrollmentSeriesDependenciaLabel = "";
            this.enrollmentSeriesLatestTotal = null;
            this.enrollmentSeriesLoading = false;
        },

        enrollmentStageHint(item) {
            const map = {
                infantil: "Creche e pré-escola",
                fundamental_1: "Anos iniciais (1º ao 5º)",
                fundamental_2: "Anos finais (6º ao 9º)",
                medio: "Ensino médio regular",
                profissional: "Educação profissional técnica",
            };

            return map[item?.key] ?? "";
        },

        enrollmentStageShortLabel(item) {
            const map = {
                infantil: "Infantil",
                fundamental_1: "Fund. I",
                fundamental_2: "Fund. II",
                medio: "Médio",
                profissional: "Profissional",
            };

            return map[item?.key] ?? item?.label ?? "—";
        },

        updateEnrollmentSeriesSummary(data, chartPayload) {
            const scope = String(data?.dependencia ?? "total");
            const shortLabels = {
                municipal: "Municipal",
                nao_municipal: "Não municipal",
                total: "",
            };
            this.enrollmentSeriesDependenciaLabel = shortLabels[scope] ?? "";

            const summary = data?.latest_summary;
            if (summary?.total != null && Number(summary.total) > 0) {
                this.enrollmentSeriesLatestTotal = Number(summary.total);
                if (summary.ano != null) {
                    this.enrollmentSeriesStageYear = Number(summary.ano);
                }
            } else {
                const datasets = Array.isArray(chartPayload?.datasets)
                    ? chartPayload.datasets
                    : [];
                const totalDataset =
                    datasets.find((dataset) =>
                        /total/i.test(String(dataset?.label ?? "")),
                    ) ?? datasets[0];
                const values = Array.isArray(totalDataset?.data)
                    ? totalDataset.data
                    : [];

                this.enrollmentSeriesLatestTotal = null;
                for (let i = values.length - 1; i >= 0; i -= 1) {
                    const value = values[i];
                    if (value != null && !Number.isNaN(Number(value))) {
                        this.enrollmentSeriesLatestTotal = Number(value);
                        const labels = Array.isArray(chartPayload?.labels)
                            ? chartPayload.labels
                            : [];
                        if (labels[i] != null) {
                            this.enrollmentSeriesStageYear = Number(labels[i]);
                        }
                        break;
                    }
                }
            }

            const counters = data?.stage_counters;
            if (
                counters?.ano != null &&
                (this.enrollmentSeriesStageYear == null ||
                    Number.isNaN(Number(this.enrollmentSeriesStageYear)))
            ) {
                this.enrollmentSeriesStageYear = Number(counters.ano);
            }
        },

        destroyEnrollmentSeriesChart() {
            this.clearEnrollmentSeriesVisual();
            this.enrollmentSeriesIbge = "";
            this._enrollmentSeriesLoadedDependencia = "";
            this.enrollmentSeriesDependencia = "total";
        },

        async waitForEnrollmentSeriesCanvas(maxFrames = 16) {
            for (let i = 0; i < maxFrames; i += 1) {
                const canvas = this.$refs.enrollmentSeriesCanvas;
                if (
                    canvas &&
                    canvas.clientWidth > 0 &&
                    canvas.clientHeight > 0
                ) {
                    return canvas;
                }
                await this.$nextTick();
                await new Promise((resolve) => requestAnimationFrame(resolve));
            }

            const canvas = this.$refs.enrollmentSeriesCanvas;

            return canvas && canvas.clientWidth > 0 ? canvas : null;
        },

        async loadEnrollmentSeries(m) {
            if (!this.shouldShowEnrollmentSeries(m)) {
                this.destroyEnrollmentSeriesChart();

                return;
            }
            const ibge = String(m.ibge ?? "").trim();
            if (!ibge) {
                return;
            }
            const dependencia = this.enrollmentSeriesDependencia || "total";
            if (
                ibge === this.enrollmentSeriesIbge &&
                dependencia === this._enrollmentSeriesLoadedDependencia &&
                (this.enrollmentSeriesReady || this.enrollmentSeriesLoading)
            ) {
                return;
            }

            if (
                this.enrollmentSeriesIbge !== "" &&
                ibge !== this.enrollmentSeriesIbge
            ) {
                this.enrollmentSeriesDependencia = "total";
            }

            this.clearEnrollmentSeriesVisual();
            this.enrollmentSeriesIbge = ibge;
            this.enrollmentSeriesLoading = true;
            this.enrollmentSeriesError = null;

            const controller = new AbortController();
            this._enrollmentSeriesAbort = controller;
            const requestedIbge = ibge;
            const requestedDependencia = this.enrollmentSeriesDependencia || "total";

            try {
                const res = await fetch(this.enrollmentSeriesUrlFor(ibge), {
                    headers: { Accept: "application/json" },
                    signal: controller.signal,
                });
                const data = await res.json().catch(() => ({}));
                if (
                    controller.signal.aborted ||
                    String(this.enrollmentSeriesIbge) !== requestedIbge
                ) {
                    return;
                }
                if (!res.ok || !data?.ok) {
                    this.enrollmentSeriesError =
                        data?.message || "Sem série histórica disponível.";

                    return;
                }
                this.enrollmentSeriesFootnote = String(data.footnote ?? "");
                const stageCounters = data?.stage_counters;
                if (stageCounters && Array.isArray(stageCounters.items)) {
                    this.enrollmentSeriesStageCounters = stageCounters.items;
                } else {
                    this.enrollmentSeriesStageCounters = [];
                }
                this.updateEnrollmentSeriesSummary(data, data.chart);
                this.enrollmentSeriesReady = true;
                this.enrollmentSeriesLoading = false;
                this._enrollmentSeriesLoadedDependencia = requestedDependencia;

                await this.$nextTick();
                if (
                    controller.signal.aborted ||
                    String(this.enrollmentSeriesIbge) !== requestedIbge
                ) {
                    return;
                }
                await this.renderEnrollmentSeriesChart(data.chart);
            } catch (error) {
                if (
                    error?.name !== "AbortError" &&
                    String(this.enrollmentSeriesIbge) === requestedIbge
                ) {
                    this.enrollmentSeriesError =
                        "Não foi possível carregar a série de matrículas.";
                }
            } finally {
                if (this._enrollmentSeriesAbort === controller) {
                    this._enrollmentSeriesAbort = null;
                }
                if (String(this.enrollmentSeriesIbge) === requestedIbge) {
                    this.enrollmentSeriesLoading = false;
                }
            }
        },

        formatEnrollmentStageCounter(value) {
            return nf(value);
        },

        async renderEnrollmentSeriesChart(payload) {
            if (!payload) {
                return;
            }
            if (this._enrollmentSeriesChart) {
                this._enrollmentSeriesChart.destroy();
                this._enrollmentSeriesChart = null;
            }

            const canvas = await this.waitForEnrollmentSeriesCanvas();
            if (!canvas) {
                return;
            }
            const ctx = canvas.getContext("2d");
            if (!ctx) {
                return;
            }

            const datasets = (Array.isArray(payload.datasets)
                ? payload.datasets
                : []
            ).map((dataset, index) => styleEnrollmentDataset(dataset, index));

            this._enrollmentSeriesChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: Array.isArray(payload.labels) ? payload.labels : [],
                    datasets,
                },
                options: enrollmentSeriesChartOptions(),
            });
            this._enrollmentSeriesChart.resize();
        },

        tooltipMunicipalContextHtml(m) {
            if (!m) {
                return "";
            }

            const overlay =
                this.enrollmentSeriesReady &&
                String(m.ibge ?? "") === String(this.enrollmentSeriesIbge ?? "")
                    ? {
                          ibge: this.enrollmentSeriesIbge,
                          latestTotal: this.enrollmentSeriesLatestTotal,
                          year: this.enrollmentSeriesStageYear,
                      }
                    : null;

            return muniMunicipalContextHtml(m, overlay);
        },

        tooltipBodyHtml(m) {
            if (!m) {
                return "";
            }
            const cacheKey = String(m.ibge ?? "");
            if (cacheKey !== "" && this._tooltipHtmlCache[cacheKey]) {
                return this._tooltipHtmlCache[cacheKey];
            }
            const lines = ['<div class="serv-horizonte-muni-tooltip__body">'];
            if (m.tier === "data_sparse") {
                lines.push(
                    `<p class="serv-horizonte-muni-tooltip__notice serv-horizonte-muni-tooltip__notice--info">${escapeHtml("Sem dados públicos importados — score e tier indicativos. Importe FUNDEB, Censo ou SAEB para enriquecer.")}</p>`,
                );
            }

            const transferAno =
                m.transfer_ano != null && m.transfer_total != null
                    ? String(m.transfer_ano)
                    : !m.has_transfers && m.has_fundeb
                      ? "FNDE"
                      : null;

            lines.push('<div class="serv-horizonte-muni-tooltip__layout">');

            const metaBlock = muniMetaHtml(m);
            if (metaBlock) {
                lines.push('<div class="serv-horizonte-muni-tooltip__layout-full serv-horizonte-muni-tooltip__layout-full--meta">');
                lines.push(metaBlock);
                lines.push("</div>");
            }

            lines.push('<div class="serv-horizonte-muni-tooltip__layout-full serv-horizonte-muni-tooltip__layout-full--finance">');
            lines.push(financePreviousYearRowHtml(m, this.refYear));
            lines.push(financeCurrentYearRowHtml(m, this.refYear, this.currentYear));
            lines.push("</div>");

            const consultoriaNote = financeTimelineConsultoriaNote(m, this.refYear);
            if (consultoriaNote) {
                lines.push('<div class="serv-horizonte-muni-tooltip__layout-full serv-horizonte-muni-tooltip__layout-full--finance-note">');
                lines.push(consultoriaNote);
                lines.push("</div>");
            }

            lines.push('<div class="serv-horizonte-muni-tooltip__layout-full serv-horizonte-muni-tooltip__layout-full--dims">');
            lines.push(muniDimensionsHtml(m, transferAno, this.methodology));
            lines.push("</div>");

            lines.push("</div>");
            lines.push(`</div>`);
            const html = lines.join("");
            if (cacheKey !== "") {
                this._tooltipHtmlCache[cacheKey] = html;
            }
            return html;
        },

        canEditSgeFor(m) {
            if (!this.canManageSge || !m) {
                return false;
            }
            if (m.sge_editable === false) {
                return false;
            }
            if (m.sge_editable === true) {
                return true;
            }
            if (m.in_catalog || m.consultoria_active) {
                return false;
            }
            const status = String(m.sge_status ?? m.sge?.status ?? "");
            return status === "not_found" || status === "registry";
        },

        sgeHasRegistry(m) {
            if (!m) {
                return false;
            }
            const status = String(m.sge_status ?? m.sge?.status ?? "");
            return status === "registry";
        },

        sgeRegistryActionLabel(m) {
            return this.sgeHasRegistry(m) ? "Actualizar" : "Cadastrar";
        },

        sgeShowUrlFor(ibge) {
            return this.sgeShowUrl.replace("__IBGE__", encodeURIComponent(String(ibge)));
        },

        sgeUrlFor(ibge) {
            return this.sgeRegistryUrl.replace("__IBGE__", encodeURIComponent(String(ibge)));
        },

        handleSgeCellClick(m) {
            if (this.canEditSgeFor(m)) {
                this.openSgeForm(m);
                return;
            }
            if (m?.cities_url) {
                window.open(m.cities_url, "_blank", "noopener,noreferrer");
                return;
            }
            void this.flyToMarker(m);
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
        },

        openSgeForm(m, options = {}) {
            if (!this.canManageSge || !m?.ibge || !this.canEditSgeFor(m)) {
                return;
            }
            const readOnly = Boolean(options?.readOnly);
            this.sgeFormError = null;
            this.sgeFormReadOnly = readOnly;
            this.sgeForm = {
                ibge: String(m.ibge),
                name: String(m.name ?? ""),
                uf: String(m.uf ?? ""),
                system: String(m.sge?.system ?? ""),
                vendor: "",
                notes: String(m.sge?.detail ?? ""),
                app_url: String(m.sge?.app_url ?? ""),
                has_entry: this.sgeHasRegistry(m),
            };
            this.closeTooltip();
            this.sgeFormOpen = true;
            void this.loadSgeFormEntry(String(m.ibge));
        },

        enableSgeFormEdit() {
            if (!this.canManageSge || !this.sgeForm.ibge) {
                return;
            }
            this.sgeFormReadOnly = false;
        },

        closeSgeForm() {
            this.sgeFormOpen = false;
            this.sgeFormReadOnly = false;
            this.sgeFormError = null;
            this.sgeFormSaving = false;
        },

        async loadSgeFormEntry(ibge) {
            if (!this.canManageSge) {
                return;
            }
            try {
                const response = await fetch(this.sgeShowUrlFor(ibge), {
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                const entry = data.entry;
                if (entry && typeof entry === "object") {
                    this.sgeForm.system = String(entry.system ?? this.sgeForm.system);
                    this.sgeForm.vendor = String(entry.vendor ?? "");
                    this.sgeForm.notes = String(entry.notes ?? "");
                    this.sgeForm.app_url = String(entry.app_url ?? "");
                    this.sgeForm.has_entry = true;
                }
            } catch (error) {
                console.debug("horizonte sge load", error);
            }
        },

        applyMarkerSgeUpdate(ibge, sge) {
            const target = String(ibge);
            const idx = this.markers.findIndex((m) => String(m.ibge) === target);
            if (idx < 0) {
                return;
            }
            const prev = this.markers[idx];
            this.markers[idx] = {
                ...prev,
                sge,
                sge_found: Boolean(sge?.found),
                sge_status: sge?.status ?? "not_found",
                sge_system: sge?.system ?? null,
                sge_editable:
                    prev.sge_editable ??
                    (!prev.in_catalog && !prev.consultoria_active),
            };
            if (String(this.active?.ibge ?? "") === target) {
                this.active = this.markers[idx];
            }
        },

        async saveSgeEntry() {
            if (!this.canManageSge || !this.sgeForm.ibge) {
                return;
            }
            this.sgeFormSaving = true;
            this.sgeFormError = null;
            try {
                const response = await fetch(this.sgeUrlFor(this.sgeForm.ibge), {
                    method: "PUT",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.csrfToken(),
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        system: this.sgeForm.system,
                        vendor: this.sgeForm.vendor,
                        notes: this.sgeForm.notes,
                        app_url: this.sgeForm.app_url || null,
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || `HTTP ${response.status}`);
                }
                if (data.sge) {
                    this.applyMarkerSgeUpdate(this.sgeForm.ibge, data.sge);
                    await this.scheduleMapRefresh();
                }
                this.closeSgeForm();
            } catch (error) {
                this.sgeFormError =
                    error instanceof Error ? error.message : "Erro ao gravar registo SGE.";
            } finally {
                this.sgeFormSaving = false;
            }
        },

        async deleteSgeEntry() {
            if (!this.canManageSge || !this.sgeForm.ibge || !this.sgeForm.has_entry) {
                return;
            }
            if (!window.confirm("Remover registo SGE deste município?")) {
                return;
            }
            this.sgeFormSaving = true;
            try {
                const response = await fetch(this.sgeUrlFor(this.sgeForm.ibge), {
                    method: "DELETE",
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": this.csrfToken(),
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || `HTTP ${response.status}`);
                }
                this.closeSgeForm();
                if (this.scopeUf) {
                    await this.fetchRegional(this.scopeUf);
                }
            } catch (error) {
                this.sgeFormError =
                    error instanceof Error ? error.message : "Erro ao remover registo SGE.";
            } finally {
                this.sgeFormSaving = false;
            }
        },
    };
}
