/**
 * Cascata ano letivo → opções de turno (AJAX) no formulário de filtros do painel analítico.
 * O formulário deve expor data-analytics-turno-cascade e data-analytics-filter-options-url.
 */
export function initAnalyticsFilterTurno(root = document) {
    root.querySelectorAll('form[data-analytics-turno-cascade]').forEach((form) => {
        const baseUrl = form.dataset.analyticsFilterOptionsUrl;
        const todosLabel = form.dataset.analyticsTurnoTodosLabel ?? '';

        if (!baseUrl) {
            return;
        }

        const ano = form.querySelector('[name="ano_letivo"]');
        const turno = form.querySelector('[name="turno_id"]');
        const cityInput = form.querySelector('input[name="city_id"]');

        if (!ano || !turno || !cityInput) {
            return;
        }

        ano.addEventListener('change', () => {
            void refreshTurnoOptions(baseUrl, todosLabel, ano, turno, cityInput);
        });
    });
}

/**
 * @param {HTMLSelectElement} ano
 * @param {HTMLSelectElement} turno
 * @param {HTMLInputElement} cityInput
 */
async function refreshTurnoOptions(baseUrl, todosLabel, ano, turno, cityInput) {
    const cityId = cityInput.value;
    const a = ano.value;
    const cur = turno.value;

    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('city_id', cityId);
    url.searchParams.set('kind', 'turno');
    if (a !== '' && a !== 'all') {
        url.searchParams.set('ano_letivo', a);
    }

    try {
        const r = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!r.ok) {
            return;
        }
        const body = await r.json();
        const data = Array.isArray(body.data) ? body.data : [];
        turno.innerHTML = '';
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = todosLabel;
        turno.appendChild(opt0);
        data.forEach((o) => {
            const opt = document.createElement('option');
            opt.value = String(o.id);
            opt.textContent = String(o.name);
            if (String(o.id) === String(cur)) {
                opt.selected = true;
            }
            turno.appendChild(opt);
        });
    } catch {
        /* rede ou JSON inválido: mantém opções atuais */
    }
}
