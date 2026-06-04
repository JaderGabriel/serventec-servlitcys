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

/**
 * Mapa de pressão territorial CadÚnico (círculos) + escolas (marcadores azuis).
 */
export default function createCadunicoTerritoryMap(
    territoryMarkers = [],
    schoolMarkers = [],
    footnote = null,
) {
    const territories = Array.isArray(territoryMarkers) ? territoryMarkers : [];
    const schools = Array.isArray(schoolMarkers) ? schoolMarkers : [];

    return {
        territories,
        schools,
        footnote: typeof footnote === "string" ? footnote : null,
        map: null,
        booted: false,
        _onTab: null,

        init() {
            this._onTab = (e) => {
                if (e?.detail?.tab === "cadunico_previsao") {
                    setTimeout(() => this.tryBoot(), 450);
                }
            };
            window.addEventListener("analytics-tab-changed", this._onTab);
            this.$nextTick(() => setTimeout(() => this.tryBoot(), 600));
        },

        destroy() {
            if (this._onTab) {
                window.removeEventListener("analytics-tab-changed", this._onTab);
            }
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
            this.booted = false;
        },

        tryBoot() {
            const el = this.$refs?.mapContainer;
            if (!el || this.booted) {
                return;
            }
            if (el.offsetWidth < 20) {
                return;
            }
            this.booted = true;
            this.map = L.map(el, { scrollWheelZoom: true }).setView([-14.2, -51.9], 12);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: "© OpenStreetMap",
                maxZoom: 18,
            }).addTo(this.map);

            const bounds = [];
            for (const s of schools) {
                const lat = Number(s.lat);
                const lng = Number(s.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    continue;
                }
                L.circleMarker([lat, lng], {
                    radius: 5,
                    color: "#0ea5e9",
                    fillColor: "#38bdf8",
                    fillOpacity: 0.85,
                    weight: 1,
                })
                    .bindPopup(
                        `<strong>${escapeHtml(s.label ?? "Escola")}</strong>`,
                    )
                    .addTo(this.map);
                bounds.push([lat, lng]);
            }

            for (const t of territories) {
                const lat = Number(t.lat);
                const lng = Number(t.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    continue;
                }
                const r = Math.max(8, Number(t.radius) || 14);
                L.circle([lat, lng], {
                    radius: r * 80,
                    color: "#ea580c",
                    fillColor: "#f97316",
                    fillOpacity: 0.35,
                    weight: 2,
                })
                    .bindPopup(
                        `<strong>${escapeHtml(t.label ?? "")}</strong><br/>${escapeHtml(t.meta ?? "")}<br/>${escapeHtml(t.tipo ?? "")}`,
                    )
                    .addTo(this.map);
                bounds.push([lat, lng]);
            }

            if (bounds.length > 0) {
                this.map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
            }
        },
    };
}
