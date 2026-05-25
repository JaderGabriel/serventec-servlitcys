import L from "leaflet";
import "leaflet/dist/leaflet.css";

const DEFAULT_STATUS_COLORS = {
    ready: "#10b981",
    incomplete: "#f59e0b",
    inactive_setup: "#64748b",
    inactive: "#94a3b8",
    cadastro_green: "#10b981",
    cadastro_yellow: "#fbbf24",
    cadastro_red: "#f43f5e",
    cadastro_neutral: "#cbd5e1",
    cadastro_error: "#64748b",
};

export default function createBrazilMunicipalitiesMap(markers = [], statusColors = null, options = null) {
    const colors =
        statusColors && typeof statusColors === "object"
            ? { ...DEFAULT_STATUS_COLORS, ...statusColors }
            : DEFAULT_STATUS_COLORS;

    const cadastroSnapshotUrl =
        options && typeof options.cadastroSnapshotUrl === "string" ? options.cadastroSnapshotUrl : null;

    return {
        map: null,
        layer: null,
        markerLayers: [],
        markers: Array.isArray(markers) ? markers : [],
        statusColors: colors,
        cadastroById: {},
        cadastroSnapshotUrl,
        cadastroLoading: false,
        cadastroError: null,
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
        lastTooltipEvent: null,
        yearsLoading: false,
        yearsError: null,
        schoolYears: [],

        init() {
            if (!this.$refs.map) {
                return;
            }

            for (const m of this.markers) {
                if (m?.cadastro && m.id != null) {
                    this.cadastroById[m.id] = m.cadastro;
                }
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
            this.markerLayers = [];

            const bounds = [];

            const count = this.markers.length;
            const radius = count > 40 ? 5 : count > 15 ? 6 : 8;
            const placedAt = new Map();

            this.markers.forEach((m) => {
                let lat = Number(m.lat);
                let lng = Number(m.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }

                const key = `${lat.toFixed(4)}:${lng.toFixed(4)}`;
                const hits = placedAt.get(key) ?? 0;
                if (hits > 0) {
                    const angle = (2 * Math.PI * hits) / 8;
                    const step = 0.1 + hits * 0.02;
                    lat += step * Math.cos(angle);
                    lng += step * Math.sin(angle) * 1.12;
                }
                placedAt.set(key, hits + 1);

                bounds.push([lat, lng]);

                const fillKey = this.markerFillKey(m);
                const circle = L.circleMarker([lat, lng], {
                    radius,
                    fillColor: colors[fillKey] || colors.inactive,
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
                this.markerLayers.push({ circle, marker: m });
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

            this.loadCadastroSnapshot();
        },

        markerFillKey(marker) {
            const id = marker?.id;
            const cadastro = id != null ? this.cadastroById[id] : null;
            if (cadastro?.map_fill_key) {
                if (marker.status === "ready" || marker.status === "inactive_setup") {
                    return cadastro.map_fill_key;
                }
            }
            return marker?.map_fill_key || marker?.status || "inactive";
        },

        applyMarkerColors() {
            for (const { circle, marker } of this.markerLayers) {
                const key = this.markerFillKey(marker);
                circle.setStyle({
                    fillColor: this.statusColors[key] || this.statusColors.inactive,
                });
            }
        },

        mergeCadastroSnapshot(data) {
            const byId = data?.by_city_id;
            if (!byId || typeof byId !== "object") {
                return;
            }
            this.cadastroById = { ...this.cadastroById, ...byId };
            this.markers = this.markers.map((m) => {
                const cadastro = this.cadastroById[m.id] ?? m.cadastro ?? null;
                return {
                    ...m,
                    cadastro,
                    map_fill_key:
                        m.status === "ready" || m.status === "inactive_setup"
                            ? cadastro?.map_fill_key ?? m.map_fill_key
                            : m.map_fill_key,
                };
            });
            if (this.active?.id != null) {
                const updated = this.markers.find((x) => x.id === this.active.id);
                if (updated) {
                    this.active = updated;
                }
            }
            this.applyMarkerColors();
        },

        async loadCadastroSnapshot() {
            if (!this.cadastroSnapshotUrl) {
                return;
            }
            this.cadastroLoading = true;
            this.cadastroError = null;
            try {
                const r = await fetch(this.cadastroSnapshotUrl, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!r.ok) {
                    this.cadastroError = "Não foi possível carregar o status de cadastro (RX).";
                    return;
                }
                const data = await r.json();
                this.mergeCadastroSnapshot(data);
            } catch {
                this.cadastroError = "Erro de rede ao carregar cadastro RX.";
            } finally {
                this.cadastroLoading = false;
            }
        },

        cadastroAttentionClass(level) {
            if (level === "praise") {
                return "text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800";
            }
            if (level === "watch") {
                return "text-amber-900 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800";
            }
            if (level === "urgent") {
                return "text-rose-800 dark:text-rose-200 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800";
            }
            return "text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700";
        },

        selectMarker(marker, event) {
            const enriched = this.markers.find((m) => m.id === marker.id) ?? marker;
            this.active = enriched;
            this.tooltipPinned = true;
            this.lastTooltipEvent = event;
            this.schoolYears = [];
            this.yearsError = null;
            this.positionTooltip(event);
            this.loadSchoolYears(enriched);
        },

        closeTooltip() {
            this.tooltipPinned = false;
            this.active = null;
            this.lastTooltipEvent = null;
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
                if (this.active?.id === marker.id) {
                    this.$nextTick(() => this.positionTooltip(this.lastTooltipEvent));
                }
            }
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

            // Posição provisória para medir o cartão (evita corte com altura dinâmica).
            this.tooltipStyle =
                `left:${pinX + gap}px;top:${pinY + gap}px;visibility:hidden;max-height:calc(100dvh - ${margin * 2}px);overflow-y:auto;`;

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
                if (top < margin) {
                    top = margin;
                }
                if (left < margin) {
                    left = margin;
                }
                if (left + tw + margin > vw) {
                    left = Math.max(margin, vw - tw - margin);
                }
                if (top + th + margin > vh) {
                    top = Math.max(margin, vh - th - margin);
                }

                this.tooltipStyle =
                    `left:${Math.round(left)}px;top:${Math.round(top)}px;max-height:${Math.round(maxH)}px;overflow-y:auto;visibility:visible;`;
            });
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
