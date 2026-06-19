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

function uniqueSortedUfs(markers) {
    return [...new Set(markers.map((m) => String(m.uf ?? "").trim()).filter(Boolean))].sort();
}

export default function createHorizonteMap(markers = [], colors = {}, options = {}) {
    return {
        map: null,
        layer: null,
        markerLayers: [],
        markers: Array.isArray(markers) ? markers : [],
        colors: colors && typeof colors === "object" ? colors : {},
        legend: Array.isArray(options.legend) ? options.legend : [],
        summary: options.summary && typeof options.summary === "object" ? options.summary : {},
        ufRankings: Array.isArray(options.ufRankings) ? options.ufRankings : [],
        topProspects: Array.isArray(options.topProspects) ? options.topProspects : [],
        refYear: Number(options.refYear) || new Date().getFullYear() - 1,
        loadUrl: typeof options.loadUrl === "string" ? options.loadUrl : "",
        pageLoading: Boolean(options.loadUrl),
        pageError: null,
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
        searchQuery: "",
        filterTier: options.initialFilter ?? "all",
        filterUf: options.initialUf ?? "",
        ufList: Array.isArray(options.ufList) ? options.ufList : [],

        get filteredMarkers() {
            const q = this.searchQuery.trim().toLowerCase();
            return this.markers.filter((m) => {
                if (this.filterTier === "prospects") {
                    if (!String(m.tier || "").startsWith("prospect_")) {
                        return false;
                    }
                } else if (this.filterTier !== "all" && m.tier !== this.filterTier) {
                    return false;
                }
                if (this.filterUf !== "" && String(m.uf) !== this.filterUf) {
                    return false;
                }
                if (q === "") {
                    return true;
                }
                const hay = `${m.name} ${m.uf} ${m.ibge}`.toLowerCase();
                return hay.includes(q);
            });
        },

        get searchSuggestions() {
            const q = this.searchQuery.trim().toLowerCase();
            if (q.length < 2) {
                return [];
            }
            return this.markers
                .filter((m) => {
                    const hay = `${m.name} ${m.uf} ${m.ibge}`.toLowerCase();
                    return hay.includes(q);
                })
                .slice(0, 12);
        },

        async init() {
            if (this.loadUrl) {
                await this.fetchPayload();
            }

            if (this.pageError) {
                return;
            }

            this.initMap();
        },

        async fetchPayload() {
            this.pageLoading = true;
            this.pageError = null;

            const preset = window.servDataLoading?.presets?.horizonteData;
            window.servDataLoading?.start?.(
                preset?.title ?? "Montando mapa Horizonte",
                preset?.message ??
                    "Consultando dados públicos e posicionando municípios no mapa. Aguarde…",
            );

            try {
                const response = await fetch(this.loadUrl, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });

                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(text || `HTTP ${response.status}`);
                }

                const data = await response.json();
                this.applyPayload(data);
            } catch (error) {
                console.error("horizonte map-data", error);
                let message =
                    error instanceof Error ? error.message : "Erro ao carregar o mapa Horizonte.";
                if (message.length > 400) {
                    message = message.replace(/<[^>]+>/g, " ").slice(0, 400);
                }
                this.pageError = message;
            } finally {
                this.pageLoading = false;
                window.servDataLoading?.finish?.();
            }
        },

        applyPayload(data) {
            if (!data || typeof data !== "object") {
                return;
            }

            this.markers = Array.isArray(data.markers) ? data.markers : [];
            this.colors =
                data.colors && typeof data.colors === "object"
                    ? data.colors
                    : this.colors;
            this.legend = Array.isArray(data.legend) ? data.legend : this.legend;
            this.summary =
                data.summary && typeof data.summary === "object"
                    ? data.summary
                    : this.summary;
            this.ufRankings = Array.isArray(data.uf_rankings)
                ? data.uf_rankings
                : this.ufRankings;
            this.topProspects = Array.isArray(data.top_prospects)
                ? data.top_prospects
                : this.topProspects;
            this.refYear = Number(data.reference_year) || this.refYear;
            this.ufList = uniqueSortedUfs(this.markers);
        },

        initMap() {
            if (!this.$refs.map) {
                return;
            }

            this.map = L.map(this.$refs.map, {
                zoomControl: true,
                scrollWheelZoom: true,
            });

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 12,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            }).addTo(this.map);

            this.layer = L.layerGroup().addTo(this.map);
            this.renderMarkers();

            this.$watch("filteredMarkers", () => this.renderMarkers());
        },

        renderMarkers() {
            if (!this.map || !this.layer) {
                return;
            }

            this.layer.clearLayers();
            this.markerLayers = [];
            const bounds = [];
            const list = this.filteredMarkers;
            const radius = list.length > 80 ? 5 : list.length > 30 ? 6 : 8;

            list.forEach((m) => {
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }
                bounds.push([lat, lng]);
                const fill = this.colors[m.tier] || "#64748b";
                const circle = L.circleMarker([lat, lng], {
                    radius,
                    fillColor: fill,
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.9,
                });
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });
                circle.addTo(this.layer);
                this.markerLayers.push({ circle, marker: m });
            });

            if (bounds.length > 0) {
                this.map.fitBounds(bounds, { padding: [32, 32], maxZoom: 8 });
            } else {
                this.map.setView([-14.2, -51.9], 4);
            }
        },

        selectMarker(m, ev = null) {
            this.active = m;
            this.tooltipPinned = true;
            if (ev?.containerPoint) {
                this.tooltipStyle = `left:${ev.containerPoint.x}px;top:${ev.containerPoint.y}px;`;
            }
        },

        closeTooltip() {
            this.active = null;
            this.tooltipPinned = false;
        },

        pickSearch(m) {
            this.searchQuery = `${m.name} — ${m.uf}`;
            this.flyToMarker(m);
        },

        flyToMarker(m) {
            const lat = Number(m.lat);
            const lng = Number(m.lng);
            if (!this.map || !Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }
            this.map.flyTo([lat, lng], 10, { duration: 0.8 });
            this.selectMarker(m);
        },

        setFilterTier(tier) {
            this.filterTier = tier;
        },

        setFilterUf(uf) {
            this.filterUf = uf;
        },

        tierLabel(m) {
            return m?.tier_label || m?.tier || "";
        },

        tooltipHtml(m) {
            if (!m) {
                return "";
            }
            const lines = [
                `<p class="font-semibold text-gray-900 dark:text-gray-100">${escapeHtml(m.name)} — ${escapeHtml(m.uf)}</p>`,
                `<p class="text-xs text-gray-500">IBGE ${escapeHtml(m.ibge)} · ${escapeHtml(this.tierLabel(m))}</p>`,
                `<dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">`,
                `<dt class="text-gray-500">${escapeHtml("Propensão")}</dt><dd class="font-semibold tabular-nums">${nf(m.success_score)}/100</dd>`,
                `<dt class="text-gray-500">${escapeHtml("Benefício")}</dt><dd class="font-semibold tabular-nums">${nf(m.benefit_score)}/100</dd>`,
            ];
            if (m.matriculas_censo != null) {
                lines.push(
                    `<dt class="text-gray-500">${escapeHtml("Matrículas Censo")}</dt><dd class="tabular-nums">${nf(m.matriculas_censo)}</dd>`,
                );
            }
            if (m.saeb_lp != null || m.saeb_mat != null) {
                lines.push(
                    `<dt class="text-gray-500">SAEB</dt><dd class="tabular-nums">LP ${nf(m.saeb_lp)} · MAT ${nf(m.saeb_mat)}</dd>`,
                );
            }
            if (m.complementacao_fundeb != null) {
                lines.push(
                    `<dt class="text-gray-500">${escapeHtml("Compl. FUNDEB")}</dt><dd class="tabular-nums">${nf(m.complementacao_fundeb)}</dd>`,
                );
            }
            lines.push(`</dl>`);
            if (m.analytics_url) {
                lines.push(
                    `<a href="${escapeHtml(m.analytics_url)}" class="mt-2 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">${escapeHtml("Abrir consultoria")}</a>`,
                );
            } else if (m.cities_url) {
                lines.push(
                    `<a href="${escapeHtml(m.cities_url)}" class="mt-2 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">${escapeHtml("Ver no catálogo")}</a>`,
                );
            }
            return lines.join("");
        },
    };
}
