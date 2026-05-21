/**
 * Carrega escolas, cursos e turnos via AJAX quando o index usa filtros leves (só anos no SSR).
 */
export function initAnalyticsFilterBootstrap(root = document) {
    root.querySelectorAll("form[data-analytics-filter-bootstrap]").forEach((form) => {
        const baseUrl = form.dataset.analyticsFilterBootstrapUrl;
        if (!baseUrl) {
            return;
        }
        void loadBootstrap(form, baseUrl);
    });
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
