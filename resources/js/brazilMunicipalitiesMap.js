import L from "leaflet";
import "leaflet/dist/leaflet.css";

const STATUS_COLORS = {
    ready: "#10b981",
    incomplete: "#f59e0b",
};

export default function createBrazilMunicipalitiesMap(markers = []) {
    return {
        map: null,
        layer: null,
        markers: Array.isArray(markers) ? markers : [],
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",

        init() {
            if (!this.$refs.map || this.markers.length === 0) {
                return;
            }

            this.map = L.map(this.$refs.map, {
                zoomControl: true,
                scrollWheelZoom: false,
                attributionControl: true,
            });

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 12,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            }).addTo(this.map);

            this.layer = L.layerGroup().addTo(this.map);

            const bounds = [];

            this.markers.forEach((m) => {
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }

                bounds.push([lat, lng]);

                const circle = L.circleMarker([lat, lng], {
                    radius: 7,
                    fillColor: STATUS_COLORS[m.status] || "#64748b",
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.9,
                });

                circle.on("mouseover", (e) => {
                    this.active = m;
                    this.positionTooltip(e);
                });
                circle.on("mousemove", (e) => this.positionTooltip(e));
                circle.on("mouseout", () => {
                    if (!this.tooltipPinned) {
                        this.active = null;
                    }
                });

                circle.addTo(this.layer);
            });

            if (bounds.length > 0) {
                this.map.fitBounds(bounds, { padding: [36, 36], maxZoom: 5 });
            } else {
                this.map.setView([-14.5, -52], 4);
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
            this.tooltipStyle = `left:${Math.min(x + 12, window.innerWidth - 280)}px;top:${Math.min(y + 12, window.innerHeight - 160)}px`;
        },

        destroy() {
            this.map?.remove();
            this.map = null;
        },
    };
}
