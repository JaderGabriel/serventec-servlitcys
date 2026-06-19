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

function heatColor(intensity) {
    const t = Math.max(0, Math.min(1, Number(intensity) || 0));
    if (t >= 0.66) {
        return "#dc2626";
    }
    if (t >= 0.33) {
        return "#f97316";
    }
    return "#fde047";
}

export default function createHorizonteMap(markers = [], colors = {}, options = {}) {
    return {
        map: null,
        layer: null,
        heatLayer: null,
        markerLayers: [],
        markers: Array.isArray(markers) ? markers : [],
        colors: colors && typeof colors === "object" ? colors : {},
        legend: Array.isArray(options.legend) ? options.legend : [],
        heatLegend: Array.isArray(options.heatLegend) ? options.heatLegend : [],
        summary: options.summary && typeof options.summary === "object" ? options.summary : {},
        ufRankings: Array.isArray(options.ufRankings) ? options.ufRankings : [],
        topProspects: Array.isArray(options.topProspects) ? options.topProspects : [],
        focusSegments: Array.isArray(options.focusSegments) ? options.focusSegments : [],
        refYear: Number(options.refYear) || new Date().getFullYear() - 1,
        loadUrl: typeof options.loadUrl === "string" ? options.loadUrl : "",
        pageLoading: Boolean(options.loadUrl),
        mapRendering: false,
        renderProgress: 0,
        loadingMessage: "",
        pageError: null,
        mapView: "markers",
        meta: {},
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
        searchQuery: "",
        filterTier: options.initialFilter ?? "all",
        filterUf: options.initialUf ?? "",
        minSuccessScore: 0,
        minBenefitScore: 0,
        minMatriculas: 0,
        minFinancial: 0,
        minPedagogical: 0,
        minReadiness: 0,
        requireFundeb: false,
        requireCenso: false,
        requireSaeb: false,
        hideConsultoria: false,
        ufList: Array.isArray(options.ufList) ? options.ufList : [],
        prospectSort: "success_score",
        canRefreshData: Boolean(options.canRefreshData),

        get totalMarkers() {
            return this.markers.length;
        },

        get mapHiddenByFilters() {
            return !this.pageLoading && this.totalMarkers > 0 && this.filteredMarkers.length === 0;
        },

        get filteredMarkers() {
            const q = this.searchQuery.trim().toLowerCase();
            return this.markers.filter((m) => {
                if (this.hideConsultoria && m.consultoria_active) {
                    return false;
                }
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
                if (Number(m.success_score ?? 0) < this.minSuccessScore) {
                    return false;
                }
                if (Number(m.benefit_score ?? 0) < this.minBenefitScore) {
                    return false;
                }
                if (Number(m.matriculas_censo ?? 0) < this.minMatriculas) {
                    return false;
                }
                if (Number(m.financial_pressure ?? 0) < this.minFinancial) {
                    return false;
                }
                if (Number(m.pedagogical_gap ?? 0) < this.minPedagogical) {
                    return false;
                }
                if (Number(m.data_readiness ?? 0) < this.minReadiness) {
                    return false;
                }
                if (this.requireFundeb && !m.has_fundeb) {
                    return false;
                }
                if (this.requireCenso && !m.has_censo) {
                    return false;
                }
                if (this.requireSaeb && !m.has_saeb) {
                    return false;
                }
                if (q === "") {
                    return true;
                }
                const hay = `${m.name} ${m.uf} ${m.ibge}`.toLowerCase();
                return hay.includes(q);
            });
        },

        get sortedProspects() {
            const key = this.prospectSort;
            return [...this.filteredMarkers]
                .filter((m) => String(m.tier || "").startsWith("prospect_"))
                .sort((a, b) => {
                    const av = Number(a[key] ?? 0);
                    const bv = Number(b[key] ?? 0);
                    return bv - av || String(a.name).localeCompare(String(b.name), "pt-BR");
                })
                .slice(0, 50);
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

        get coverage() {
            return this.summary?.coverage ?? {};
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
            this.loadingMessage = "Consultando FUNDEB, Censo, SAEB e catálogo IBGE…";

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
                this.loadingMessage = `A posicionar ${Number(data.summary?.total ?? 0).toLocaleString("pt-BR")} municípios no mapa…`;
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
            this.heatLegend = Array.isArray(data.heat_legend)
                ? data.heat_legend
                : this.heatLegend;
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
            this.focusSegments = Array.isArray(data.focus_segments)
                ? data.focus_segments
                : this.focusSegments;
            this.refYear = Number(data.reference_year) || this.refYear;
            this.ufList = uniqueSortedUfs(this.markers);
            this.meta = data.meta && typeof data.meta === "object" ? data.meta : {};
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
            this.heatLayer = L.layerGroup().addTo(this.map);
            void this.refreshMapLayers();

            this.$watch("filteredMarkers", () => void this.refreshMapLayers());
            this.$watch("mapView", () => void this.refreshMapLayers());
        },

        async refreshMapLayers() {
            if (!this.map || !this.layer || !this.heatLayer) {
                return;
            }

            this.mapRendering = true;
            this.renderProgress = 0;
            this.closeTooltip();

            try {
                if (this.mapView === "heat") {
                    await this.renderHeatLayer();
                } else {
                    await this.renderMarkers();
                }
            } finally {
                this.mapRendering = false;
                this.renderProgress = 100;
                this.loadingMessage = "";
            }
        },

        async renderMarkers() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.markerLayers = [];
            const bounds = [];
            const list = this.filteredMarkers;
            const total = list.length;
            const radius = total > 300 ? 4 : total > 80 ? 5 : total > 30 ? 6 : 8;
            const batch = total > 200 ? 80 : total;

            for (let i = 0; i < list.length; i++) {
                const m = list[i];
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const fill = this.colors[m.tier] || "#64748b";
                const circle = L.circleMarker([lat, lng], {
                    radius,
                    fillColor: fill,
                    color: "#ffffff",
                    weight: total > 300 ? 1 : 2,
                    opacity: 1,
                    fillOpacity: 0.9,
                });
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });
                circle.addTo(this.layer);
                this.markerLayers.push({ circle, marker: m });

                if (batch > 0 && i > 0 && i % batch === 0) {
                    this.renderProgress = Math.round((i / total) * 100);
                    this.loadingMessage = `A desenhar ${i.toLocaleString("pt-BR")} / ${total.toLocaleString("pt-BR")} municípios…`;
                    await new Promise((resolve) => requestAnimationFrame(resolve));
                }
            }

            this.fitMapBounds(bounds);
        },

        async renderHeatLayer() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.markerLayers = [];
            const bounds = [];
            const list = this.filteredMarkers.filter((m) => {
                if (m.consultoria_active) {
                    return false;
                }
                const heat = Number(m.heat_intensity ?? 0);
                return heat > 0 || m.tier === "catalog_pending" || m.tier === "data_sparse";
            });
            const total = list.length;
            const batch = total > 150 ? 60 : total;

            for (let i = 0; i < list.length; i++) {
                const m = list[i];
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const intensity = Math.max(
                    0.08,
                    Number(m.heat_intensity ?? m.success_score / 100) || 0,
                );
                const circle = L.circleMarker([lat, lng], {
                    radius: 6 + intensity * 18,
                    fillColor: heatColor(intensity),
                    color: "transparent",
                    weight: 0,
                    opacity: 1,
                    fillOpacity: 0.12 + intensity * 0.55,
                });
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });
                circle.addTo(this.heatLayer);
                this.markerLayers.push({ circle, marker: m });

                if (batch > 0 && i > 0 && i % batch === 0) {
                    this.renderProgress = Math.round((i / total) * 100);
                    this.loadingMessage = `Mapa de calor: ${i.toLocaleString("pt-BR")} / ${total.toLocaleString("pt-BR")}…`;
                    await new Promise((resolve) => requestAnimationFrame(resolve));
                }
            }

            this.fitMapBounds(bounds);
        },

        fitMapBounds(bounds) {
            if (!this.map) {
                return;
            }
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
                this.positionTooltip(ev);
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

        setMapView(view) {
            this.mapView = view;
        },

        resetFilters() {
            this.filterTier = "all";
            this.filterUf = "";
            this.minSuccessScore = 0;
            this.minBenefitScore = 0;
            this.minMatriculas = 0;
            this.minFinancial = 0;
            this.minPedagogical = 0;
            this.minReadiness = 0;
            this.requireFundeb = false;
            this.requireCenso = false;
            this.requireSaeb = false;
            this.hideConsultoria = false;
        },

        applyFocusSegment(segment) {
            if (!segment?.filter) {
                return;
            }
            const f = segment.filter;
            this.resetFilters();
            if (f.tier) {
                this.filterTier = f.tier;
            }
            if (f.min_success != null) {
                this.minSuccessScore = Number(f.min_success);
            }
            if (f.min_benefit != null) {
                this.minBenefitScore = Number(f.min_benefit);
            }
            if (f.min_matriculas != null) {
                this.minMatriculas = Number(f.min_matriculas);
            }
            if (f.min_financial != null) {
                this.minFinancial = Number(f.min_financial);
            }
            if (f.min_pedagogical != null) {
                this.minPedagogical = Number(f.min_pedagogical);
            }
            if (f.min_readiness != null) {
                this.minReadiness = Number(f.min_readiness);
            }
            if (f.require_fundeb) {
                this.requireFundeb = true;
            }
            if (f.require_censo) {
                this.requireCenso = true;
            }
            if (f.require_saeb) {
                this.requireSaeb = true;
            }
            this.mapView = "heat";
        },

        tierLabel(m) {
            return m?.tier_label || m?.tier || "";
        },

        tooltipBodyHtml(m) {
            if (!m) {
                return "";
            }
            const lines = [
                `<dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">`,
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
            const sources = [
                m.has_fundeb ? "FUNDEB" : null,
                m.has_censo ? "Censo" : null,
                m.has_saeb ? "SAEB" : null,
            ].filter(Boolean);
            if (sources.length > 0) {
                lines.push(
                    `<dt class="text-gray-500">${escapeHtml("Fontes")}</dt><dd>${escapeHtml(sources.join(" · "))}</dd>`,
                );
            }
            lines.push(`</dl>`);
            if (m.analytics_url) {
                lines.push(
                    `<a href="${escapeHtml(m.analytics_url)}" class="mt-2 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">${escapeHtml("Abrir consultoria")}</a>`,
                );
            } else if (m.cities_url) {
                lines.push(
                    `<a href="${escapeHtml(m.cities_url)}" class="mt-2 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">${escapeHtml("Cadastrar / ver catálogo")}</a>`,
                );
            }
            return lines.join("");
        },
    };
}
