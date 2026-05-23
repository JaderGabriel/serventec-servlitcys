import {
    servDataLoadingFinish,
    servDataLoadingStart,
} from "./dataLoading.js";

/**
 * Filtros da Consultoria: anos letivos (se o SSR não trouxe) e escolas/cursos/turnos (modo light).
 */
export async function initAnalyticsFilterBootstrap(root = document) {
    const yearJobs = [];
    root.querySelectorAll("form[data-analytics-filter-years-url]").forEach((form) => {
        if (form.dataset.analyticsFilterYearsFetch === "1") {
            const url = form.dataset.analyticsFilterYearsUrl;
            if (url) {
                yearJobs.push(loadYears(form, url));
            }
        }
    });

    const bootstrapJobs = [];
    root.querySelectorAll("form[data-analytics-filter-bootstrap]").forEach((form) => {
        const baseUrl = form.dataset.analyticsFilterBootstrapUrl;
        if (baseUrl) {
            bootstrapJobs.push(loadBootstrap(form, baseUrl));
        }
    });

    if (yearJobs.length === 0 && bootstrapJobs.length === 0) {
        return;
    }

    const preset = window.servDataLoading?.presets?.prepare;
    servDataLoadingStart(
        preset?.title ?? "A preparar filtros",
        preset?.message ??
            "A carregar anos letivos, escolas, cursos e turnos na base do município…",
    );

    try {
        await Promise.all([...yearJobs, ...bootstrapJobs]);
    } finally {
        servDataLoadingFinish();
    }
}

/**
 * @param {HTMLFormElement} form
 * @param {string} baseUrl
 */
async function loadYears(form, baseUrl) {
    const cityInput = form.querySelector('input[name="city_id"]');
    const ano = form.querySelector('[name="ano_letivo"]');
    if (!cityInput || !ano || !baseUrl) {
        return;
    }

    const loadingLabel =
        form.dataset.analyticsFilterYearsLoadingLabel ??
        "A carregar anos letivos…";
    const selectedYear = ano.value;

    setYearSelectLoading(ano, loadingLabel);

    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set("city_id", cityInput.value);

    try {
        const r = await fetch(url.toString(), {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
        });
        const body = await r.json();
        if (body.years && typeof body.years === "object") {
            populateYearSelect(ano, body.years, selectedYear);
        }
        showYearLoadErrors(form, Array.isArray(body.errors) ? body.errors : []);
        if (!r.ok && (!body.years || !hasNumericYears(body.years))) {
            ano.disabled = false;
        }
    } catch {
        ano.disabled = false;
    }
}

/**
 * @param {HTMLFormElement} form
 * @param {string} baseUrl
 */
async function loadBootstrap(form, baseUrl) {
    const cityInput = form.querySelector('input[name="city_id"]');
    const ano = form.querySelector('[name="ano_letivo"]');
    const escola = form.querySelector("#escola_id");
    const curso = form.querySelector("#curso_id");
    const turno = form.querySelector("#turno_id");

    if (!cityInput || !escola || !curso || !turno) {
        return;
    }

    const todosLabel =
        form.dataset.analyticsFilterTodosLabel ?? "Todos os dados";
    const loadingLabel =
        form.dataset.analyticsFilterLoadingLabel ?? "A carregar opções…";

    setSelectLoading(escola, loadingLabel);
    setSelectLoading(curso, loadingLabel);
    setSelectLoading(turno, loadingLabel);

    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set("city_id", cityInput.value);
    if (ano?.value && ano.value !== "") {
        url.searchParams.set("ano_letivo", ano.value);
    }

    const selectedEscola = escola.value;
    const selectedCurso = curso.value;
    const selectedTurno = turno.value;
    const selectedYear = ano?.value ?? "";

    try {
        const r = await fetch(url.toString(), {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
        });
        if (!r.ok) {
            resetSelectPlaceholder(escola, todosLabel);
            resetSelectPlaceholder(curso, todosLabel);
            resetSelectPlaceholder(turno, todosLabel);
            return;
        }
        const body = await r.json();

        if (ano && body.years && typeof body.years === "object") {
            if (
                form.dataset.analyticsFilterYearsFetch === "1" ||
                !hasNumericYears(yearSelectOptions(ano))
            ) {
                populateYearSelect(ano, body.years, selectedYear);
            }
        }
        showYearLoadErrors(form, Array.isArray(body.errors) ? body.errors : []);

        populateEscolaSelect(
            escola,
            Array.isArray(body.escolas) ? body.escolas : [],
            selectedEscola,
            todosLabel,
        );
        populateSimpleSelect(
            curso,
            Array.isArray(body.cursos) ? body.cursos : [],
            selectedCurso,
            todosLabel,
        );
        populateSimpleSelect(
            turno,
            Array.isArray(body.turnos) ? body.turnos : [],
            selectedTurno,
            todosLabel,
        );
    } catch {
        resetSelectPlaceholder(escola, todosLabel);
        resetSelectPlaceholder(curso, todosLabel);
        resetSelectPlaceholder(turno, todosLabel);
    }
}

/**
 * @param {HTMLSelectElement} select
 * @param {Record<string, string>} years
 * @param {string} selectedYear
 */
function populateYearSelect(select, years, selectedYear) {
    select.disabled = false;
    select.innerHTML = "";

    for (const [value, label] of Object.entries(years)) {
        const opt = document.createElement("option");
        opt.value = String(value);
        opt.textContent = String(label);
        if (selectedYear !== "" && selectedYear === String(value)) {
            opt.selected = true;
        }
        select.appendChild(opt);
    }
}

/**
 * @param {HTMLSelectElement} select
 * @param {string} loadingLabel
 */
function setYearSelectLoading(select, loadingLabel) {
    select.disabled = true;
    select.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = loadingLabel;
    select.appendChild(opt);
}

/**
 * @param {HTMLFormElement} form
 * @param {string[]} errors
 */
function showYearLoadErrors(form, errors) {
    const box = form.querySelector("[data-analytics-year-errors]");
    if (!box) {
        return;
    }
    if (errors.length === 0) {
        box.classList.add("hidden");
        box.innerHTML = "";
        return;
    }
    box.classList.remove("hidden");
    box.innerHTML = "";
    for (const err of errors) {
        const p = document.createElement("p");
        p.textContent = err;
        box.appendChild(p);
    }
}

/**
 * @param {HTMLSelectElement} select
 * @returns {Record<string, string>}
 */
function yearSelectOptions(select) {
    /** @type {Record<string, string>} */
    const out = {};
    for (const opt of select.options) {
        out[opt.value] = opt.textContent ?? "";
    }
    return out;
}

/**
 * @param {Record<string, string>} years
 */
function hasNumericYears(years) {
    return Object.keys(years).some(
        (k) => k !== "" && k !== "all" && /^\d+$/.test(k),
    );
}

/**
 * @param {HTMLSelectElement} select
 * @param {string} loadingLabel
 */
function setSelectLoading(select, loadingLabel) {
    select.disabled = true;
    select.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = loadingLabel;
    select.appendChild(opt);
}

/**
 * @param {HTMLSelectElement} select
 * @param {string} todosLabel
 */
function resetSelectPlaceholder(select, todosLabel) {
    select.disabled = false;
    select.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = todosLabel;
    select.appendChild(opt);
}

/**
 * @param {HTMLSelectElement} select
 * @param {Array<{id?: string, name?: string, inep?: string|null, active?: boolean|null, substatus?: string|null}>} items
 * @param {string} selectedId
 * @param {string} todosLabel
 */
function populateEscolaSelect(select, items, selectedId, todosLabel) {
    select.disabled = false;
    select.innerHTML = "";
    const opt0 = document.createElement("option");
    opt0.value = "";
    opt0.textContent = todosLabel;
    select.appendChild(opt0);

    for (const item of items) {
        const id = String(item.id ?? "");
        if (id === "") {
            continue;
        }
        const opt = document.createElement("option");
        opt.value = id;
        opt.textContent = escolaOptionLabel(item);
        if (selectedId !== "" && selectedId === id) {
            opt.selected = true;
        }
        select.appendChild(opt);
    }
}

/**
 * @param {{ inep?: string|null, name?: string, active?: boolean|null, substatus?: string|null }} item
 */
function escolaOptionLabel(item) {
    const inep =
        typeof item.inep === "string" && item.inep.trim() !== ""
            ? item.inep.trim()
            : null;
    const active = item.active ?? null;
    const sub =
        typeof item.substatus === "string"
            ? item.substatus.toLowerCase().trim()
            : "";
    let marker = "⚪";
    if (active === true) {
        marker = "🟢";
    } else if (active === false) {
        if (sub.includes("paralis")) {
            marker = "🟠";
        } else if (sub.includes("extint") || sub.includes("baixad")) {
            marker = "⚫";
        } else if (sub.includes("anex") || sub.includes("integrad")) {
            marker = "🔵";
        } else {
            marker = "🔴";
        }
    }
    const name = item.name ?? "—";
    return marker + " " + (inep ? inep + " — " : "") + name;
}

/**
 * @param {HTMLSelectElement} select
 * @param {Array<{id?: string, name?: string}>} items
 * @param {string} selectedId
 * @param {string} todosLabel
 */
function populateSimpleSelect(select, items, selectedId, todosLabel) {
    select.disabled = false;
    select.innerHTML = "";
    const opt0 = document.createElement("option");
    opt0.value = "";
    opt0.textContent = todosLabel;
    select.appendChild(opt0);

    for (const item of items) {
        const id = String(item.id ?? "");
        if (id === "") {
            continue;
        }
        const opt = document.createElement("option");
        opt.value = id;
        opt.textContent = item.name ?? "—";
        if (selectedId !== "" && selectedId === id) {
            opt.selected = true;
        }
        select.appendChild(opt);
    }
}
