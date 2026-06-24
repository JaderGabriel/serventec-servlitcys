import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "leaflet.markercluster";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";

const BRAZIL_BOUNDS = L.latLngBounds(
    L.latLng(-33.75, -74.5),
    L.latLng(5.5, -32.0),
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

function muniPopulationPipelineHtml(m) {
    const fmt = (val) => (val != null && Number(val) > 0 ? nfCompact(val) : "—");
    const yearLine = (year) =>
        year != null
            ? `<span class="serv-horizonte-muni-tooltip__pipe-year">${escapeHtml(String(year))}</span>`
            : `<span class="serv-horizonte-muni-tooltip__pipe-year serv-horizonte-muni-tooltip__pipe-year--empty">—</span>`;
    const cell = (val, label) =>
        `<div class="serv-horizonte-muni-tooltip__pipe-cell">` +
        `<span class="serv-horizonte-muni-tooltip__pipe-val">${fmt(val)}</span>` +
        `<span class="serv-horizonte-muni-tooltip__pipe-label">${escapeHtml(label)}</span>` +
        `</div>`;
    const matriculas = m.matriculas_censo ?? m.fundeb_matriculas_base ?? null;
    const matAno = m.censo_ano ?? m.fundeb_ano ?? null;

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
        cell(matriculas, "Matrículas") +
        yearLine(matAno) +
        `</div>` +
        `</div>` +
        `<p class="serv-horizonte-muni-tooltip__pipe-hint">${escapeHtml("População municipal → faixa escolar → CadÚnico → matrículas Censo/FUNDEB")}</p>`
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

function financeSectionShell(tone, title, yearLabel, yearTag, iconHtml, bodyHtml) {
    const tag = yearTag
        ? `<span class="serv-horizonte-muni-tooltip__finance-tag serv-horizonte-muni-tooltip__finance-tag--${escapeHtml(yearTag)}">${escapeHtml(yearLabel)}</span>`
        : `<span class="serv-horizonte-muni-tooltip__finance-year">${escapeHtml(yearLabel)}</span>`;

    return (
        `<section class="serv-horizonte-muni-tooltip__finance-step serv-horizonte-muni-tooltip__finance-step--${tone}">` +
        `<div class="serv-horizonte-muni-tooltip__finance-head">` +
        `<div class="serv-horizonte-muni-tooltip__finance-head-main">` +
        `<span class="serv-horizonte-muni-tooltip__finance-icon-wrap" aria-hidden="true">${iconHtml}</span>` +
        `<span class="serv-horizonte-muni-tooltip__finance-title">${escapeHtml(title)}</span>` +
        `</div>` +
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
    if (ano == null && !hasVaaf && !hasReceita && !hasCompl) {
        return "";
    }

    const rows = [];
    if (hasVaaf) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("VAAF")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Valor aluno/ano (referência FNDE)")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatVaafPerStudent(vaaf)}</span>` +
                `</div>`,
        );
    }
    if (hasReceita) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Receita total")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("FUNDEB municipal (FNDE)")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(receita)}</span>` +
                `</div>`,
        );
    }
    if (hasCompl) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Complementação")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("VAAF + VAAT + VAAR (FNDE)")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(compl)}</span>` +
                `</div>`,
        );
    }

    const yearLabel = ano != null ? String(ano) : "—";
    const footnote = financeNoteHtml(
        "Portaria FNDE: receita e complementação do exercício para planejamento. Não substitui extrato bancário nem repasses observados no Tesouro Transparente.",
        "reference",
    );

    return financeSectionShell(
        "reference",
        "Referência FUNDEB",
        `${escapeHtml("Ano")} ${escapeHtml(yearLabel)}`,
        "reference",
        HORIZONTE_FINANCE_ICONS.reference,
        `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>` + footnote,
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
                        ? `<span class="serv-horizonte-muni-tooltip__fundeb-pct serv-horizonte-muni-tooltip__fundeb-pct--teal">${escapeHtml(pctEduc)} ${escapeHtml("do total")}</span>`
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
        "Repasses federais",
        `${escapeHtml("Ano")} ${escapeHtml(String(ano))}`,
        "previous",
        HORIZONTE_FINANCE_ICONS.repasses,
        `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>` + footnote,
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
    const hasObserved = observed != null && Number(observed) > 0;
    const hasExpected = expected != null && Number(expected) > 0;

    if (!hasObserved && !hasExpected) {
        return "";
    }

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
            `<div class="serv-horizonte-muni-tooltip__fundeb-row serv-horizonte-muni-tooltip__fundeb-row--highlight">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Já repassado")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(months != null && Number(months) > 0 ? `${months} mês(es) com repasse` : "Consolidado Tesouro/CKAN")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--emph">${formatCurrencyBrl(observed)}</span>` +
                `</div>`,
        );
    }
    if (hasExpected) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Previsão anual")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml(m.fundeb_realtime_expected_source === "portaria_receita" ? "Receita portaria FNDE" : "Matrículas × VAAF")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(expected)}</span>` +
                `</div>`,
        );
    }
    if (projected != null && Number(projected) > 0 && hasExpected) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Projeção até dez.")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Ritmo observado ou mensal")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value">${formatCurrencyBrl(projected)}</span>` +
                `</div>`,
        );
    }
    if (balance != null && Number(balance) > 0) {
        rows.push(
            `<div class="serv-horizonte-muni-tooltip__fundeb-row">` +
                `<div class="serv-horizonte-muni-tooltip__fundeb-cell">` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-label">${escapeHtml("Saldo a repassar")}</span>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-desc">${escapeHtml("Indicativo até dezembro")}</span>` +
                `</div>` +
                `<span class="serv-horizonte-muni-tooltip__fundeb-value serv-horizonte-muni-tooltip__fundeb-value--balance">${formatCurrencyBrl(balance)}</span>` +
                `</div>`,
        );
    }

    const progress =
        hasExpected && pctDone != null
            ? `<div class="serv-horizonte-muni-tooltip__finance-progress" role="img" aria-label="${escapeHtml("Realizado")} ${escapeHtml(pctLabel ?? "")}">` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-track">` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-fill serv-horizonte-muni-tooltip__finance-progress-fill--${outlookTone}" style="width:${pctWidth}%"></div>` +
              `</div>` +
              `<div class="serv-horizonte-muni-tooltip__finance-progress-meta">` +
              `<span>${escapeHtml("Realizado")} <strong>${escapeHtml(pctLabel ?? "—")}</strong></span>` +
              (outlookLabel ? `<span class="serv-horizonte-muni-tooltip__finance-outlook serv-horizonte-muni-tooltip__finance-outlook--${outlookTone}">${escapeHtml(outlookLabel)}</span>` : "") +
              `</div></div>`
            : "";

    return financeSectionShell(
        "realtime",
        "Repasse FUNDEB",
        `${escapeHtml("Ano")} ${escapeHtml(String(ano))}`,
        "current",
        HORIZONTE_FINANCE_ICONS.realtime,
        progress +
            `<div class="serv-horizonte-muni-tooltip__fundeb-rows">${rows.join("")}</div>` +
            financeNoteHtml(
                "Exercício em curso: compare repasse já observado com a previsão indicativa (portaria ou matrículas × VAAF). Use a aba Finanças → Tempo Real na consultoria para extrato e alertas.",
                "realtime",
            ),
    );
}

function financeTimelineConsultoriaNote(m, refYear) {
    const receita = m.fundeb_receita_total;
    const transferFundeb = m.transfer_fundeb;
    const hasReceita = receita != null && Number(receita) > 0;
    const hasTransfer = transferFundeb != null && Number(transferFundeb) > 0;

    if (hasReceita && hasTransfer && moneyEqual(receita, transferFundeb)) {
        return financeNoteHtml(
            `Receita FNDE (${refYear}) e repasse CKAN têm o mesmo valor aqui, mas medem coisas diferentes: portaria do exercício × pagamento consolidado no Tesouro. Não some nem trate como duplicidade.`,
            "consultoria",
        );
    }

    if (hasReceita && hasTransfer && !moneyEqual(receita, transferFundeb)) {
        return financeNoteHtml(
            "Receita FNDE (portaria) e repasse CKAN (pagamento) costumam divergir por calendário, complementações e critério de consolidação — compare na consultoria, não some as faixas.",
            "consultoria",
        );
    }

    return financeNoteHtml(
        "Para consultoria: use a referência FNDE no planejamento, os repasses CKAN no que já foi pago e o bloco do ano corrente para acompanhar ritmo versus previsão.",
        "consultoria",
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
        `<p class="serv-horizonte-muni-tooltip__finance-intro">${escapeHtml("Leitura para consultoria — três fontes complementares (não somar):")}</p>` +
        `<ul class="serv-horizonte-muni-tooltip__finance-legend">` +
        `<li><span class="serv-horizonte-muni-tooltip__finance-legend-dot serv-horizonte-muni-tooltip__finance-legend-dot--reference"></span>${escapeHtml("FNDE — portaria e índices do exercício")}</li>` +
        `<li><span class="serv-horizonte-muni-tooltip__finance-legend-dot serv-horizonte-muni-tooltip__finance-legend-dot--repasses"></span>${escapeHtml("Tesouro — repasses federais já pagos (CKAN)")}</li>` +
        `<li><span class="serv-horizonte-muni-tooltip__finance-legend-dot serv-horizonte-muni-tooltip__finance-legend-dot--realtime"></span>${escapeHtml("Ano corrente — realizado × previsão")}</li>` +
        `</ul>` +
        body +
        financeTimelineConsultoriaNote(m, refYear) +
        `</div>`
    );
}

const HORIZONTE_TOUR_STORAGE_KEY = "horizonte_onboarding_v1";

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
        color: "#0d9488",
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
        meta: {},
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
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
        hideConsultoria: options.defaultViewFilter?.hide_consultoria !== false,
        ufList: Array.isArray(options.ufList) ? options.ufList : [],
        nationalUfRankings: Array.isArray(options.ufRankings) ? options.ufRankings : [],
        nationalUfMapPoints: [],
        ufNames:
            options.ufNames && typeof options.ufNames === "object" ? options.ufNames : {},
        prospectSort: "success_score",
        canRefreshData: Boolean(options.canRefreshData),
        canManageSge: Boolean(options.canManageSge),
        sgeShowUrl:
            typeof options.sgeShowUrl === "string" ? options.sgeShowUrl : "",
        sgeRegistryUrl:
            typeof options.sgeRegistryUrl === "string" ? options.sgeRegistryUrl : "",
        sgeFormOpen: false,
        sgeFormReadOnly: false,
        sgeFormSaving: false,
        sgeFormError: null,
        filterPanelOpen: false,
        filterDockOpen: true,
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
                    text: "Brasil mostra bolhas por UF. Escolha um estado para carregar municípios no mapa e na lista.",
                },
                {
                    target: '[data-horizonte-tour="segments"]',
                    title: "Segmentos rápidos",
                    text: "Atalhos de prospecção — cada cartão aplica um recorte típico e abre a lista filtrada.",
                },
                {
                    target: '[data-horizonte-tour="map"]',
                    title: "Mapa GIS",
                    text: "Clique numa bolha (Brasil) ou num ponto municipal (UF). Pontos cinza também são clicáveis.",
                },
                {
                    target: '[data-horizonte-tour="filters"]',
                    title: "Filtros laterais",
                    text: "Lentes de decisão e refinamento — activos com UF aberta. No telemóvel use o botão «Filtros».",
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
                    text: "Lista de prospecção, cobertura de dados públicos e metodologia dos scores.",
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

        get isRegionalMode() {
            return this.mapMode === "regional";
        },

        get mapMarkersForRender() {
            if (this.isOverviewMode) {
                return [];
            }
            let list = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            if (this.hideApproxOnMap) {
                list = list.filter((m) => !isApproxCoord(m));
            }
            const limit = Number(this.mapRenderLimit) || 400;
            const heavyCap = Boolean(this.regionalDisplayPolicy?.heavy_regional);
            const hardMax = heavyCap ? limit : list.length;
            let rendered = [];
            if ((!this.showAllOnMap || heavyCap) && list.length > limit) {
                rendered = [...list]
                    .sort(
                        (a, b) =>
                            Number(b.success_score ?? 0) - Number(a.success_score ?? 0) ||
                            String(a.name).localeCompare(String(b.name), "pt-BR"),
                    )
                    .slice(0, limit);
            } else {
                rendered = list.slice(0, hardMax);
            }

            const allValid = this.filteredMarkersList.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            const pinnedIbge = String(this.highlightIbge ?? "").trim();
            if (pinnedIbge !== "") {
                const pinned = allValid.find((m) => String(m.ibge) === pinnedIbge);
                if (pinned && !rendered.some((m) => String(m.ibge) === pinnedIbge)) {
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
            return (
                this.regionalDisplayPolicy?.allow_show_all !== false &&
                !this.regionalDisplayPolicy?.heavy_regional
            );
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
                        "Bolhas = estados · intensidade = municípios de alta pressão FUNDEB. Clique num estado para abrir a camada municipal filtrada.",
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
                this.hideConsultoria,
                this.searchQuery.trim().toLowerCase(),
                this.pressureThreshold,
                this.hideApproxOnMap,
            ].join("|");
        },

        recomputeFilteredMarkers() {
            const q = this.searchQuery.trim().toLowerCase();
            this.filteredMarkersList = this.markers.filter((m) => {
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
            this.regionalDisplayPolicy = policy;
            if (!policy) {
                return;
            }
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
            if (this.viewPreset === "all" && this.hideConsultoria) count += 1;
            if (!this.hideApproxOnMap) count += 1;
            if (this.searchQuery.trim() !== "") count += 1;
            return count;
        },

        get mapInteractionStats() {
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

        openFiltersDock() {
            this.filterDockOpen = true;
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
            if (step.openFilters && this.isRegionalMode && window.innerWidth < 1280) {
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
            const queryUf = String(options.initialUf ?? "").trim().toUpperCase();
            const policyUf = String(this.displayPolicy?.initial_uf ?? "")
                .trim()
                .toUpperCase();
            const uf = queryUf || policyUf;
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
            this.loadingMessage = `A carregar municípios de ${scoped}… (UF extensa — pode demorar na 1.ª vez)`;
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
            this.markers = [];
            this.filteredMarkersList = [];
            this.ufMapPoints = Array.isArray(data.uf_map_points) ? data.uf_map_points : [];
            this.nationalUfMapPoints = this.ufMapPoints;
            if (Array.isArray(data.uf_rankings) && data.uf_rankings.length > 0) {
                this.nationalUfRankings = data.uf_rankings;
            }
            this.applyCommonPayload(data);
            this.setOverviewNotice();
        },

        applyRegionalPayload(data, uf) {
            if (!data || typeof data !== "object") {
                return;
            }
            this.mapMode = "regional";
            this.scopeUf = uf;
            this.markers = Array.isArray(data.markers) ? data.markers : [];
            this.ufMapPoints = [];
            this.applyCommonPayload(data);
            this.recomputeFilteredMarkers();
            this._filterSignature = this.filterSignature();
            this.applyRegionalRenderPolicy();
            this._tooltipHtmlCache = {};
            this.ensurePointsVisibleOnMap();
            this.initialViewNotice = {
                kind: "regional",
                message:
                    this.meta?.regional_display_policy?.reason ||
                    `${this.markers.length.toLocaleString("pt-BR")} municípios com dados em ${this.ufLabel(uf)} · ${Number(this.summary?.prospect_count ?? 0).toLocaleString("pt-BR")} prospectos.`,
                uf,
            };
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
                scrollWheelZoom: true,
                preferCanvas: false,
                maxZoom: 12,
                zoomSnap: 0.5,
                zoomDelta: 0.5,
                maxBounds: BRAZIL_BOUNDS,
                maxBoundsViscosity: 0.85,
                minZoom: 3,
            });
            this.canvasRenderer = L.canvas({ padding: 0.5 });

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 12,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            }).addTo(this.map);

            this.layer = L.layerGroup().addTo(this.map);
            this.ufLayer = L.layerGroup().addTo(this.map);
            this.heatLayer = L.layerGroup().addTo(this.map);
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

            this.map.on("click", () => {
                if (!this.sgeFormOpen) {
                    this.closeTooltip();
                }
            });

            this.map.on("zoomend", () => {
                this.refreshCanvasMarkersAfterZoom();
            });

            const onFilterChange = () => {
                if (!this.isRegionalMode || this.regionalLoading || this.pageLoading) {
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
                    resolve();
                }, this.mapRefreshDebounceMs);
            });
        },

        /** Canvas regional fica por cima dos círculos SVG do overview e bloqueia cliques. */
        detachCanvasRendererForOverview() {
            if (!this.map || !this.canvasRenderer) {
                return;
            }
            if (this.map.hasLayer(this.canvasRenderer)) {
                this.map.removeLayer(this.canvasRenderer);
            }
            this.canvasRenderer = L.canvas({ padding: 0.5 });
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
                    if (this.map.hasLayer(this.clusterGroup)) {
                        this.map.removeLayer(this.clusterGroup);
                    }
                    this.detachCanvasRendererForOverview();
                    await this.renderUfOverview();
                } else {
                    if (this.mapView === "heat") {
                        if (this.map.hasLayer(this.clusterGroup)) {
                            this.map.removeLayer(this.clusterGroup);
                        }
                        await this.renderHeatLayer();
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
                    const lat = Number(latest.lat);
                    const lng = Number(latest.lng);
                    if (isValidCoord(lat, lng)) {
                        this.positionTooltip({
                            containerPoint: this.map.latLngToContainerPoint([lat, lng]),
                        });
                    }
                }
            }
        },

        async renderUfOverview() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.ufLayer.clearLayers();
            this.markerLayers = [];

            const points =
                this.ufMapPoints.length > 0 ? this.ufMapPoints : this.nationalUfMapPoints;
            const bounds = [];

            for (const p of points) {
                const lat = Number(p.lat);
                const lng = Number(p.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const intensity = Math.max(0.15, Number(p.heat_intensity ?? 0));
                const radius = 12 + Math.sqrt(Number(p.total ?? 1)) * 1.8;
                const circle = L.circleMarker([lat, lng], {
                    radius: Math.min(36, radius),
                    fillColor: heatColor(intensity),
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.72,
                });
                const ufLabel = escapeHtml(p.uf_name ? `${p.uf} — ${p.uf_name}` : this.ufLabel(p.uf));
                circle.bindTooltip(
                    `<strong>${ufLabel}</strong><br>` +
                        `${nf(p.high_pressure ?? 0)} alta pressão · ${nf(p.high_prospect ?? 0)} alta propensão<br>` +
                        `${nf(p.total)} com dados · ${nf(p.prospect_count)} prospectos<br>` +
                        `<span class="text-slate-500">Clique para abrir camada municipal filtrada</span>`,
                    { direction: "top", sticky: true },
                );
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    void this.selectUfFromOverview(p.uf);
                });
                circle.addTo(this.ufLayer);
            }

            if (!this.map.hasLayer(this.ufLayer)) {
                this.ufLayer.addTo(this.map);
            }
            this.ufLayer.bringToFront();

            this.fitMapBounds(bounds, 4);
        },

        async renderMarkers() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.ufLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.markerLayers = [];

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
                    renderer: this.canvasRenderer,
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

            const bounds = list
                .map((m) => [Number(m.lat), Number(m.lng)])
                .filter(([la, ln]) => isValidCoord(la, ln));
            this.fitMapBounds(bounds, this.scopeUf ? 5 : 4);
        },

        async renderHeatLayer() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.ufLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.markerLayers = [];

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
                    radius: 7 + intensity * 14,
                    fillColor: heatColor(intensity),
                    color: intensity >= 0.72 ? "#9f1239" : intensity >= 0.4 ? "#b45309" : "#fbbf24",
                    weight: 1,
                    opacity: 0.55,
                    fillOpacity: 0.28 + intensity * 0.62,
                    renderer: this.canvasRenderer,
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

            this.fitMapBounds(bounds, this.scopeUf ? 5 : 4);
        },

        fitMapBounds(bounds, fallbackZoom = 4) {
            if (!this.map) {
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
                this.isRegionalMode &&
                this.scopeUf === scoped &&
                this.markers.length > 0 &&
                !this.regionalLoading
            ) {
                return;
            }
            if (this.regionalLoading && this.pendingRegionalUf === scoped) {
                return;
            }
            this.highlightIbge = "";
            if (userInitiated && !this.isOverviewMode) {
                this.applyDefaultDecisionView();
            }
            await this.fetchRegional(scoped);
        },

        async backToOverview() {
            this.cancelPendingMapRefresh();
            this.closeTooltip();
            this.scopeUf = "";
            this.markers = [];
            this.mapMode = "overview";
            this.showAllOnMap = false;
            if (this.ufMapPoints.length === 0 && this.nationalUfMapPoints.length > 0) {
                this.ufMapPoints = this.nationalUfMapPoints;
            }
            await this.fetchOverview();
        },

        selectMarker(m, ev = null) {
            this.active = m;
            this.tooltipPinned = true;
            window.requestAnimationFrame(() => {
                let containerPoint = ev?.containerPoint ?? null;
                if (!containerPoint && this.map && m) {
                    const lat = Number(m.lat);
                    const lng = Number(m.lng);
                    if (isValidCoord(lat, lng)) {
                        containerPoint = this.map.latLngToContainerPoint([lat, lng]);
                    }
                }
                if (containerPoint) {
                    this.positionTooltip({ containerPoint });
                }
            });
        },

        positionTooltip(event) {
            const mapEl = this.$refs.map;
            if (!mapEl || !event?.containerPoint) {
                return;
            }
            const margin = 16;
            const gap = 12;
            const rect = mapEl.getBoundingClientRect();
            const pinX = rect.left + event.containerPoint.x;
            const pinY = rect.top + event.containerPoint.y;
            this.tooltipStyle = `left:${pinX + gap}px;top:${pinY + gap}px;visibility:hidden;`;
            this.$nextTick(() => {
                const tooltip = this.$el?.querySelector?.(".serv-brazil-map-tooltip");
                if (!tooltip || !this.active) {
                    return;
                }
                const vw = window.innerWidth;
                const vh = window.innerHeight;
                const maxH = vh - margin * 2;
                tooltip.style.maxHeight = `${maxH}px`;
                const tw = tooltip.offsetWidth;
                const th = Math.min(tooltip.scrollHeight, maxH);
                let left = pinX + gap;
                let top = pinY + gap;
                if (left + tw + margin > vw) {
                    left = pinX - tw - gap;
                }
                if (top + th + margin > vh) {
                    top = pinY - th - gap;
                }
                left = Math.max(margin, Math.min(left, vw - tw - margin));
                top = Math.max(margin, Math.min(top, vh - th - margin));
                this.tooltipStyle = `left:${Math.round(left)}px;top:${Math.round(top)}px;max-height:${Math.round(maxH)}px;overflow-y:auto;visibility:visible;`;
            });
        },

        closeTooltip() {
            this.active = null;
            this.tooltipPinned = false;
        },

        pickSearch(m) {
            this.searchQuery = `${m.name} — ${m.uf}`;
            void this.flyToMarker(m);
        },

        async flyToMarker(m) {
            if (!m) {
                return;
            }
            const lat = Number(m.lat);
            const lng = Number(m.lng);
            if (!this.map || !isValidCoord(lat, lng)) {
                return;
            }
            const targetUf = String(m.uf ?? "").trim().toUpperCase();
            if (this.isOverviewMode && targetUf) {
                await this.selectUf(targetUf, false);
            } else if (
                this.isRegionalMode &&
                targetUf &&
                this.scopeUf !== targetUf
            ) {
                await this.selectUf(targetUf, false);
            }
            this.highlightIbge = String(m.ibge ?? "");
            await this.scheduleMapRefresh();
            const zoom = isApproxCoord(m) ? 7 : 8;
            this.map.flyTo([lat, lng], zoom, { duration: 0.75 });
            window.setTimeout(() => {
                if (!this.map) {
                    return;
                }
                const point = this.map.latLngToContainerPoint([lat, lng]);
                this.selectMarker(m, { containerPoint: point });
            }, 400);
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

        tooltipBodyHtml(m) {
            if (!m) {
                return "";
            }
            const cacheKey = String(m.ibge ?? "");
            if (cacheKey !== "" && this._tooltipHtmlCache[cacheKey]) {
                return this._tooltipHtmlCache[cacheKey];
            }
            const th = this.scoreThresholds;
            const lines = ['<div class="serv-horizonte-muni-tooltip__body">'];
            if (isApproxCoord(m)) {
                lines.push(
                    `<p class="serv-horizonte-muni-tooltip__notice serv-horizonte-muni-tooltip__notice--warn">${escapeHtml("Posição indicativa (centroide IBGE ou dispersão na UF) — use a lista para localizar o município.")}</p>`,
                );
            }
            if (m.tier === "data_sparse") {
                lines.push(
                    `<p class="serv-horizonte-muni-tooltip__notice serv-horizonte-muni-tooltip__notice--info">${escapeHtml("Sem dados públicos importados — score e tier indicativos. Importe FUNDEB, Censo ou SAEB para enriquecer.")}</p>`,
                );
            }
            lines.push(muniPopulationPipelineHtml(m));

            const financeBlock = financeTimelineHtml(m, this.refYear, this.currentYear);
            if (financeBlock) {
                lines.push(financeBlock);
            }

            if (m.benefit_score != null) {
                lines.push(
                    `<p class="serv-horizonte-muni-tooltip__benefit">${escapeHtml("Benefício estimado")}: <span class="serv-horizonte-muni-tooltip__benefit-val">${formatScoreValue(m.benefit_score)}/100</span></p>`,
                );
            }

            const sources = [
                m.has_fundeb ? "FUNDEB" : null,
                m.has_censo ? "Censo" : null,
                m.has_saeb ? "SAEB" : null,
                m.has_cadunico ? "CadÚnico" : null,
            ].filter(Boolean);
            const sge = m.sge && typeof m.sge === "object" ? m.sge : null;
            if (sources.length > 0 || sge) {
                lines.push(`<dl class="serv-horizonte-muni-tooltip__sources">`);
            }
            if (sources.length > 0) {
                lines.push(
                    `<dt class="serv-horizonte-muni-tooltip__sources-dt">${escapeHtml("Fontes")}</dt><dd>${escapeHtml(sources.join(" · "))}</dd>`,
                );
            }
            if (sge) {
                lines.push(
                    `<dt class="serv-horizonte-muni-tooltip__sources-dt">${escapeHtml("SGE")}</dt><dd>${escapeHtml(sge.system_label || sge.system || "—")}</dd>`,
                );
            }
            if (sources.length > 0 || sge) {
                lines.push(`</dl>`);
            }

            const transferAno =
                m.transfer_ano != null && m.transfer_total != null
                    ? String(m.transfer_ano)
                    : !m.has_transfers && m.has_fundeb
                      ? "FNDE"
                      : null;
            const dims = [
                { key: "financial_pressure", label: "Pressão FUNDEB" },
                { key: "pedagogical_gap", label: "Pedagógica" },
                { key: "scale_score", label: "Escala" },
                { key: "social_demand", label: "Social" },
                { key: "transfer_dependency", label: "Transf. fed." },
                { key: "data_readiness", label: "Prontidão" },
            ];
            lines.push(
                `<p class="serv-horizonte-muni-tooltip__dims-title">${escapeHtml("Dimensões (0–100)")}</p>`,
            );
            lines.push(`<div class="serv-horizonte-muni-tooltip__dims">`);
            for (const d of dims) {
                const val = Math.max(0, Math.min(100, Number(m[d.key] ?? 0)));
                const isTransfer = d.key === "transfer_dependency";
                const dimLabel =
                    isTransfer && transferAno
                        ? `${d.label} (${transferAno})`
                        : d.label;
                const fillClass =
                    isTransfer && val > 0
                        ? "serv-horizonte-muni-tooltip__dim-fill--rose"
                        : "serv-horizonte-muni-tooltip__dim-fill--teal";
                lines.push(
                    `<div class="serv-horizonte-muni-tooltip__dim-row">` +
                        `<span class="serv-horizonte-muni-tooltip__dim-label">${escapeHtml(dimLabel)}</span>` +
                        `<span class="serv-horizonte-muni-tooltip__dim-bar">` +
                        `<span class="serv-horizonte-muni-tooltip__dim-fill ${fillClass}" style="width:${val}%"></span>` +
                        `</span>` +
                        `<span class="serv-horizonte-muni-tooltip__dim-val">${formatScoreValue(val)}</span>` +
                        `</div>`,
                );
            }
            lines.push(`</div>`);

            lines.push(muniPropensityThermometerHtml(m, th));

            if (m.analytics_url) {
                lines.push(
                    `<a href="${escapeHtml(m.analytics_url)}" class="serv-horizonte-muni-tooltip__link">${escapeHtml("Abrir consultoria")}</a>`,
                );
            }
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
