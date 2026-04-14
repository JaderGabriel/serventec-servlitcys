import L from "leaflet";
import "leaflet/dist/leaflet.css";

function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s ?? "");
    return d.innerHTML;
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
                const label = escapeHtml(mk.label || "—");
                const meta = escapeHtml(mk.meta || "");
                const popupHtml = meta
                    ? `<div class="text-sm font-medium">${label}</div><p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400 leading-snug">${meta}</p>`
                    : `<div class="text-sm">${label}</div>`;
                L.circleMarker([mk.lat, mk.lng], {
                    radius: 8,
                    color: "#4f46e5",
                    weight: 2,
                    fillColor: "#818cf8",
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
