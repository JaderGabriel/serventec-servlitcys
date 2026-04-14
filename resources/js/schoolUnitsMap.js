import L from "leaflet";
import "leaflet/dist/leaflet.css";

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

function markerStrokeFill(mk) {
    const key = mk?.school?.status_key;
    if (key === "ativa") {
        return { color: "#15803d", fill: "#22c55e" };
    }
    if (key === "inativa") {
        return { color: "#64748b", fill: "#94a3b8" };
    }
    if (key === "unknown") {
        return { color: "#4f46e5", fill: "#a5b4fc" };
    }
    return { color: "#4f46e5", fill: "#818cf8" };
}

function buildSchoolPopupHtml(mk) {
    const label = escapeHtml(mk.label || "—");
    const meta = escapeHtml(mk.meta || "");
    const s = mk.school;
    if (!s || typeof s !== "object") {
        return meta
            ? `<div class="text-sm font-medium">${label}</div><p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400 leading-snug">${meta}</p>`
            : `<div class="text-sm">${label}</div>`;
    }
    const nome = escapeHtml(s.nome || mk.label || "—");
    const status = escapeHtml(s.status_label || "");
    const inep =
        s.inep != null && s.inep !== ""
            ? escapeHtml(String(s.inep))
            : "—";
    const mat = nf(s.matriculas);
    const cap =
        s.capacidade_declarada != null ? nf(s.capacidade_declarada) : "—";
    const vag =
        s.vagas_disponiveis != null ? nf(s.vagas_disponiveis) : "—";
    const tel = s.telefone ? escapeHtml(s.telefone) : "—";
    const em = s.email ? escapeHtml(s.email) : "—";
    const gest = s.gestor ? escapeHtml(s.gestor) : "—";
    const end = s.endereco ? escapeHtml(s.endereco) : "—";

    const rows = [
        ["INEP", inep],
        ["Matrículas", mat],
        ["Capacidade (turmas)", cap],
        ["Vagas disponíveis", vag],
        ["Telefone", tel],
        ["E-mail", em],
        ["Gestor", gest],
        ["Endereço", end],
    ];

    let body = `<div class="text-sm font-semibold text-gray-900 dark:text-gray-100">${nome}</div>`;
    if (status) {
        body += `<p class="mt-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">${status}</p>`;
    }
    body += `<dl class="mt-2 space-y-1 text-xs text-gray-800 dark:text-gray-200">`;
    for (const [k, v] of rows) {
        body += `<div class="flex gap-2 justify-between"><dt class="text-gray-500 dark:text-gray-400 shrink-0">${escapeHtml(k)}</dt><dd class="text-right min-w-0 break-words">${v}</dd></div>`;
    }
    body += `</dl>`;
    if (meta) {
        body += `<p class="mt-2 border-t border-gray-200 dark:border-gray-600 pt-2 text-[11px] text-gray-500 dark:text-gray-400 leading-snug">${meta}</p>`;
    }
    return body;
}

/**
 * Mapa OSM (Leaflet) para unidades escolares com coordenadas.
 */
export default function createSchoolUnitsMap(markersInput) {
    const markers = Array.isArray(markersInput) ? markersInput : [];

    return {
        markers,
        map: null,
        group: null,
        booted: false,
        _onTab: null,

        init() {
            this._onTab = (e) => {
                if (e?.detail?.tab === "school_units") {
                    setTimeout(() => this.tryBoot(), 450);
                }
            };
            window.addEventListener("analytics-tab-changed", this._onTab);
            this.$nextTick(() => {
                setTimeout(() => this.tryBoot(), 600);
            });
        },

        destroy() {
            if (this._onTab) {
                window.removeEventListener(
                    "analytics-tab-changed",
                    this._onTab,
                );
            }
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },

        tryBoot() {
            if (this.booted || this.markers.length === 0) {
                return;
            }
            const el = this.$refs.mapContainer;
            if (!el || el.offsetWidth < 20) {
                return;
            }

            const latlngs = this.markers
                .filter(
                    (m) =>
                        Number.isFinite(m.lat) &&
                        Number.isFinite(m.lng) &&
                        Math.abs(m.lat) <= 90 &&
                        Math.abs(m.lng) <= 180,
                )
                .map((m) => [m.lat, m.lng]);

            if (latlngs.length === 0) {
                return;
            }

            this.booted = true;
            this.map = L.map(el, { scrollWheelZoom: true }).setView(
                latlngs[0],
                12,
            );
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.map);

            this.group = L.layerGroup().addTo(this.map);

            this.markers.forEach((mk) => {
                if (
                    !Number.isFinite(mk.lat) ||
                    !Number.isFinite(mk.lng) ||
                    Math.abs(mk.lat) > 90 ||
                    Math.abs(mk.lng) > 180
                ) {
                    return;
                }
                const { color, fill } = markerStrokeFill(mk);
                const popupHtml = buildSchoolPopupHtml(mk);
                L.circleMarker([mk.lat, mk.lng], {
                    radius: 8,
                    color,
                    weight: 2,
                    fillColor: fill,
                    fillOpacity: 0.92,
                })
                    .bindPopup(`<div class="max-w-xs">${popupHtml}</div>`)
                    .addTo(this.group);
            });

            if (latlngs.length === 1) {
                this.map.setView(latlngs[0], 14);
            } else {
                const b = L.latLngBounds(latlngs);
                this.map.fitBounds(b.pad(0.12));
            }

            setTimeout(() => {
                this.map?.invalidateSize();
            }, 120);
        },
    };
}
