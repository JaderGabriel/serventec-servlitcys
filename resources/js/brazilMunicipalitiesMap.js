import L from "leaflet";
import "leaflet/dist/leaflet.css";

const STATUS_COLORS = {
    ready: "#10b981",
    incomplete: "#f59e0b",
    inactive_setup: "#64748b",
    inactive: "#94a3b8",
};

export default function createBrazilMunicipalitiesMap(markers = []) {
    return {
        map: null,
        layer: null,
        markers: Array.isArray(markers) ? markers : [],
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
        yearsLoading: false,
        yearsError: null,
        schoolYears: [],

        init() {
            if (!this.$refs.map) {
                return;
            }

            this.map = L.map(this.$refs.map, {
                zoomControl: true,
                scrollWheelZoom: true,
                attributionControl: true,
            });

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 12,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            }).addTo(this.map);

            this.layer = L.layerGroup().addTo(this.map);

            const bounds = [];

            const count = this.markers.length;
            const radius = count > 40 ? 5 : count > 15 ? 6 : 7;

            this.markers.forEach((m) => {
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }

                bounds.push([lat, lng]);

                const circle = L.circleMarker([lat, lng], {
                    radius,
                    fillColor: STATUS_COLORS[m.status] || "#64748b",
                    color: "#ffffff",
                    weight: count > 30 ? 1.5 : 2,
                    opacity: 1,
                    fillOpacity: 0.92,
                });

                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });

                circle.addTo(this.layer);
            });

            this.map.on("click", () => {
                this.closeTooltip();
            });

            if (bounds.length > 0) {
                const maxZoom = count <= 3 ? 8 : count <= 12 ? 6 : 5;
                this.map.fitBounds(bounds, { padding: [40, 40], maxZoom });
            } else {
                this.map.setView([-14.5, -52], 4);
            }
        },

        selectMarker(marker, event) {
            this.active = marker;
            this.tooltipPinned = true;
            this.schoolYears = [];
            this.yearsError = null;
            this.positionTooltip(event);
            this.loadSchoolYears(marker);
        },

        closeTooltip() {
            this.tooltipPinned = false;
            this.active = null;
            this.schoolYears = [];
            this.yearsLoading = false;
            this.yearsError = null;
        },

        async loadSchoolYears(marker) {
            if (!marker?.school_years_url) {
                return;
            }
            this.yearsLoading = true;
            this.yearsError = null;
            try {
                const r = await fetch(marker.school_years_url, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!r.ok) {
                    this.yearsError = "Não foi possível carregar os anos letivos.";
                    return;
                }
                const data = await r.json();
                if (this.active?.id === marker.id) {
                    this.schoolYears = Array.isArray(data.school_years)
                        ? data.school_years
                        : [];
                    if (data.implemented_at_label && this.active) {
                        this.active = {
                            ...this.active,
                            implemented_at_label: data.implemented_at_label,
                        };
                    }
                }
            } catch {
                this.yearsError = "Erro de rede ao carregar anos letivos.";
            } finally {
                this.yearsLoading = false;
            }
        },

        positionTooltip(event) {
            const el = this.$refs.map;
            if (!el || !event?.containerPoint) {
                return;
            }
            const rect = el.getBoundingClientRect();
            const x = rect.left + event.containerPoint.x;
            const y = rect.top + event.containerPoint.y;
            this.tooltipStyle = `left:${Math.min(x + 12, window.innerWidth - 300)}px;top:${Math.min(y + 12, window.innerHeight - 280)}px`;
        },

        yearStateIcon(state) {
            if (state === "open") {
                return "open";
            }
            if (state === "closed") {
                return "closed";
            }
            return "unknown";
        },

        destroy() {
            this.map?.remove();
            this.map = null;
        },
    };
}
