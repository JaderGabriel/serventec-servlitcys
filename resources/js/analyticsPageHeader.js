/**
 * Cabeçalho fixo do painel analytics — espelha os filtros i-Educar antes/depois de aplicar.
 */
export function analyticsPageHeader(initial) {
    const labels = initial?.labels ?? {};

    return {
        cityTitle: initial?.cityTitle ?? "",
        parts: Array.isArray(initial?.parts) ? initial.parts.map((part) => ({ ...part })) : [],

        refreshFromForm() {
            const form = document.getElementById("analytics-ieducar-filters");
            if (!form) {
                return;
            }

            const read = (name, label, emptyValue) => {
                const select = form.querySelector(`[name="${name}"]`);
                if (!select) {
                    return null;
                }

                const option = select.selectedOptions?.[0];
                const raw = option ? option.textContent.trim() : "";
                const isEmpty = select.value === "";

                return {
                    label,
                    value: isEmpty ? emptyValue : stripEscolaMarker(raw),
                    muted: isEmpty,
                };
            };

            const ano = form.querySelector('[name="ano_letivo"]');
            const parts = [];

            if (ano) {
                const hasYear = ano.value !== "";
                parts.push({
                    label: labels.ano ?? "Ano letivo",
                    value: hasYear
                        ? (ano.selectedOptions?.[0]?.textContent?.trim() ?? "")
                        : (labels.naoSelecionado ?? "Não seleccionado"),
                    muted: !hasYear,
                });
            }

            const escola = read("escola_id", labels.escola ?? "Escola", labels.todas ?? "Todas");
            if (escola) {
                parts.push(escola);
            }

            const curso = read("curso_id", labels.curso ?? "Tipo/Segmento", labels.todos ?? "Todos");
            if (curso) {
                parts.push(curso);
            }

            const turno = read("turno_id", labels.turno ?? "Turno", labels.todos ?? "Todos");
            if (turno) {
                parts.push(turno);
            }

            this.parts = parts;
        },
    };
}

function stripEscolaMarker(text) {
    return text.replace(/^[\p{Extended_Pictographic}\uFE0F?\s]+/u, "").trim();
}

export function analyticsFilterDock(initial) {
    return {
        filtersOpen: Boolean(initial?.filtersOpen),
        ...analyticsPageHeader(initial?.header ?? initial),
    };
}

export function registerAnalyticsPageHeader(Alpine) {
    Alpine.data("analyticsPageHeader", analyticsPageHeader);
    Alpine.data("analyticsFilterDock", analyticsFilterDock);
}
