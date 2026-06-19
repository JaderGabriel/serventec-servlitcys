import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "leaflet.markercluster";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";

const BRAZIL_BOUNDS = L.latLngBounds(
    L.latLng(-33.75, -74.5),
    L.latLng(5.5, -32.0),
);

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

function isValidCoord(lat, lng) {
    return (
        Number.isFinite(lat) &&
        Number.isFinite(lng) &&
        lat >= -34.0 &&
        lat <= 5.5 &&
        lng >= -74.5 &&
        lng <= -32.0
    );
}

export default function createHorizonteMap(markers = [], colors = {}, options = {}) {
    return {
        map: null,
        layer: null,
        clusterGroup: null,
        ufLayer: null,
        heatLayer: null,
        markerLayers: [],
        markers: Array.isArray(markers) ? markers : [],
        ufMapPoints: [],
        colors: colors && typeof colors === "object" ? colors : {},
        legend: Array.isArray(options.legend) ? options.legend : [],
        heatLegend: Array.isArray(options.heatLegend) ? options.heatLegend : [],
        summary: options.summary && typeof options.summary === "object" ? options.summary : {},
        ufRankings: Array.isArray(options.ufRankings) ? options.ufRankings : [],
        topProspects: Array.isArray(options.topProspects) ? options.topProspects : [],
        focusSegments: Array.isArray(options.focusSegments) ? options.focusSegments : [],
        sgeSummary: options.sgeSummary && typeof options.sgeSummary === "object" ? options.sgeSummary : {},
        refYear: Number(options.refYear) || new Date().getFullYear() - 1,
        loadUrl: typeof options.loadUrl === "string" ? options.loadUrl : "",
        pageLoading: Boolean(options.loadUrl),
        regionalLoading: false,
        mapRendering: false,
        renderProgress: 0,
        loadingMessage: "",
        pageError: null,
        mapView: "markers",
        mapMode: "overview",
        scopeUf: "",
        meta: {},
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
        searchQuery: "",
        filterTier: options.initialFilter ?? "prospects",
        filterUf: options.initialUf ?? "",
        displayPolicy: null,
        initialViewNotice: null,
        showAllOnMap: false,
        renderCapDismissed: false,
        mapRenderLimit: 400,
        minSuccessScore: 0,
        minBenefitScore: 0,
        minMatriculas: 0,
        minFinancial: 0,
        minPedagogical: 0,
        minReadiness: 0,
        minSocialDemand: 0,
        requireFundeb: false,
        requireCenso: false,
        requireSaeb: false,
        requireCadunico: false,
        onlyMissingSge: false,
        hideConsultoria: false,
        ufList: Array.isArray(options.ufList) ? options.ufList : [],
        prospectSort: "success_score",
        canRefreshData: Boolean(options.canRefreshData),
        canManageSge: Boolean(options.canManageSge),
        sgeRegistryUrl:
            typeof options.sgeRegistryUrl === "string" ? options.sgeRegistryUrl : "",
        sgeFormOpen: false,
        sgeFormSaving: false,
        sgeFormError: null,
        filterPanelOpen: true,
        sgeForm: {
            ibge: "",
            name: "",
            uf: "",
            system: "",
            vendor: "",
            notes: "",
            app_url: "",
            has_entry: false,
        },

        get totalMarkers() {
            return Number(this.summary?.total ?? this.meta?.marker_count ?? 0);
        },

        get filteredCount() {
            return this.filteredMarkers.length;
        },

        get isOverviewMode() {
            return this.mapMode === "overview";
        },

        get isRegionalMode() {
            return this.mapMode === "regional";
        },

        get mapMarkersForRender() {
            if (this.isOverviewMode) {
                return [];
            }
            const list = this.filteredMarkers.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            const limit = Number(this.mapRenderLimit) || 400;
            if (this.showAllOnMap || list.length <= limit) {
                return list;
            }
            return [...list]
                .sort(
                    (a, b) =>
                        Number(b.success_score ?? 0) - Number(a.success_score ?? 0) ||
                        String(a.name).localeCompare(String(b.name), "pt-BR"),
                )
                .slice(0, limit);
        },

        get mapRenderTruncated() {
            if (this.isOverviewMode) {
                return false;
            }
            const limit = Number(this.mapRenderLimit) || 400;
            const valid = this.filteredMarkers.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            return !this.showAllOnMap && valid.length > limit;
        },

        get mapRenderShownCount() {
            return this.isOverviewMode
                ? this.ufMapPoints.length
                : this.mapMarkersForRender.length;
        },

        get mapHeavyDataset() {
            return Boolean(this.displayPolicy?.heavy_dataset);
        },

        get mapHiddenByFilters() {
            return (
                !this.pageLoading &&
                !this.regionalLoading &&
                this.isRegionalMode &&
                this.markers.length > 0 &&
                this.filteredMarkers.length === 0
            );
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
                if (Number(m.social_demand ?? 0) < this.minSocialDemand) {
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
                if (this.requireCadunico && !m.has_cadunico) {
                    return false;
                }
                if (this.onlyMissingSge && (m.sge_found ?? false)) {
                    return false;
                }
                if (this.onlyMissingSge && (m.in_catalog ?? false)) {
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
            if (q.length < 2 || this.isOverviewMode) {
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
            this.initMap();
            if (this.loadUrl) {
                await this.fetchOverview();
            }
            if (this.pageError) {
                return;
            }
            const initialUf = String(options.initialUf ?? "").trim();
            if (initialUf !== "") {
                await this.selectUf(initialUf, false);
            } else if (this.displayPolicy?.initial_uf) {
                await this.selectUf(this.displayPolicy.initial_uf, false);
            }
        },

        dataUrl(scope, uf = "") {
            const url = new URL(this.loadUrl, window.location.origin);
            url.searchParams.set("scope", scope);
            if (uf) {
                url.searchParams.set("uf", uf);
            }
            return url.toString();
        },

        async fetchOverview() {
            this.pageLoading = true;
            this.pageError = null;
            this.loadingMessage = "A carregar painel nacional…";

            const preset = window.servDataLoading?.presets?.horizonteData;
            window.servDataLoading?.start?.(
                preset?.title ?? "Montando mapa Horizonte",
                preset?.message ?? "A agregar indicadores por UF. Aguarde…",
            );

            try {
                const response = await fetch(this.dataUrl("overview"), {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                this.applyOverviewPayload(data);
            } catch (error) {
                console.error("horizonte overview", error);
                this.pageError =
                    error instanceof Error ? error.message : "Erro ao carregar o Horizonte.";
            } finally {
                this.pageLoading = false;
                window.servDataLoading?.finish?.();
                await this.refreshMapLayers();
            }
        },

        async fetchRegional(uf) {
            const scoped = String(uf ?? "").trim().toUpperCase();
            if (!scoped) {
                return;
            }
            this.regionalLoading = true;
            this.pageError = null;
            this.loadingMessage = `A carregar municípios de ${scoped}…`;

            try {
                const response = await fetch(this.dataUrl("regional", scoped), {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                this.applyRegionalPayload(data, scoped);
            } catch (error) {
                console.error("horizonte regional", error);
                this.pageError =
                    error instanceof Error
                        ? error.message
                        : `Erro ao carregar UF ${scoped}.`;
            } finally {
                this.regionalLoading = false;
                await this.refreshMapLayers();
            }
        },

        applyOverviewPayload(data) {
            if (!data || typeof data !== "object") {
                return;
            }
            this.mapMode = "overview";
            this.scopeUf = "";
            this.markers = [];
            this.ufMapPoints = Array.isArray(data.uf_map_points) ? data.uf_map_points : [];
            this.applyCommonPayload(data);
            this.setOverviewNotice();
        },

        applyRegionalPayload(data, uf) {
            if (!data || typeof data !== "object") {
                return;
            }
            this.mapMode = "regional";
            this.scopeUf = uf;
            this.filterUf = uf;
            this.markers = Array.isArray(data.markers) ? data.markers : [];
            this.ufMapPoints = [];
            this.ufList = uniqueSortedUfs(this.markers);
            this.applyCommonPayload(data);
            this.initialViewNotice = {
                kind: "regional",
                message: `${this.markers.length.toLocaleString("pt-BR")} municípios em ${uf} · ${this.totalMarkers.toLocaleString("pt-BR")} na base nacional.`,
                uf,
            };
        },

        applyCommonPayload(data) {
            this.colors =
                data.colors && typeof data.colors === "object" ? data.colors : this.colors;
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
            this.sgeSummary =
                data.sge_summary && typeof data.sge_summary === "object"
                    ? data.sge_summary
                    : this.sgeSummary;
            this.refYear = Number(data.reference_year) || this.refYear;
            this.meta = data.meta && typeof data.meta === "object" ? data.meta : {};
            this.displayPolicy =
                this.meta.display_policy && typeof this.meta.display_policy === "object"
                    ? this.meta.display_policy
                    : null;
            this.mapRenderLimit = Number(this.displayPolicy?.max_render_markers) || 400;
            if (this.ufList.length === 0 && Array.isArray(this.ufMapPoints)) {
                this.ufList = this.ufMapPoints.map((p) => p.uf).filter(Boolean).sort();
            }
        },

        setOverviewNotice() {
            const policy = this.displayPolicy;
            if (!policy?.heavy_dataset) {
                this.initialViewNotice = null;
                return;
            }
            this.initialViewNotice = {
                kind: "overview",
                message:
                    policy.reason ||
                    "Selecione uma UF no mapa ou na barra lateral para ver municípios.",
                total: Number(policy.marker_count_total ?? this.totalMarkers),
            };
        },

        initMap() {
            if (!this.$refs.map) {
                return;
            }

            this.map = L.map(this.$refs.map, {
                zoomControl: true,
                scrollWheelZoom: true,
                preferCanvas: true,
                maxBounds: BRAZIL_BOUNDS,
                maxBoundsViscosity: 0.85,
                minZoom: 3,
            });

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 14,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            }).addTo(this.map);

            this.layer = L.layerGroup().addTo(this.map);
            this.ufLayer = L.layerGroup().addTo(this.map);
            this.heatLayer = L.layerGroup().addTo(this.map);
            this.clusterGroup = L.markerClusterGroup({
                chunkedLoading: true,
                chunkInterval: 120,
                maxClusterRadius: 42,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                disableClusteringAtZoom: 11,
            });
            this.map.addLayer(this.clusterGroup);

            this.map.setView([-14.2, -51.9], 4);

            this.$watch("filteredMarkers", () => {
                if (this.isRegionalMode) {
                    this.showAllOnMap = false;
                    this.renderCapDismissed = false;
                    void this.refreshMapLayers();
                }
            });
            this.$watch("mapView", () => void this.refreshMapLayers());
        },

        async refreshMapLayers() {
            if (!this.map) {
                return;
            }

            this.mapRendering = true;
            this.renderProgress = 0;
            this.closeTooltip();

            try {
                if (this.isOverviewMode) {
                    await this.renderUfOverview();
                } else if (this.mapView === "heat") {
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

        async renderUfOverview() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.ufLayer.clearLayers();
            this.markerLayers = [];

            const points = this.ufMapPoints;
            const bounds = [];

            for (const p of points) {
                const lat = Number(p.lat);
                const lng = Number(p.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const intensity = Math.max(0.15, Number(p.heat_intensity ?? 0));
                const radius = 12 + Math.sqrt(Number(p.total ?? 1)) * 1.8;
                const circle = L.circleMarker([lat, lng], {
                    radius: Math.min(36, radius),
                    fillColor: heatColor(intensity),
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.72,
                });
                circle.bindTooltip(
                    `<strong>${escapeHtml(p.uf)}</strong><br>` +
                        `${nf(p.total)} municípios · ${nf(p.high_prospect)} alta propensão<br>` +
                        `Propensão média: ${nf(p.avg_success)}/100`,
                    { direction: "top", sticky: true },
                );
                circle.on("click", () => this.selectUf(p.uf));
                circle.addTo(this.ufLayer);
            }

            this.fitMapBounds(bounds, 4);
        },

        async renderMarkers() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.ufLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.markerLayers = [];

            const list = this.mapMarkersForRender;
            const total = list.length;

            for (let i = 0; i < list.length; i++) {
                const m = list[i];
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                const fill = this.colors[m.tier] || "#64748b";
                const marker = L.circleMarker([lat, lng], {
                    radius: 7,
                    fillColor: fill,
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.92,
                });
                marker.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });
                this.clusterGroup.addLayer(marker);
                this.markerLayers.push({ circle: marker, marker: m });

                if (i > 0 && i % 80 === 0) {
                    this.renderProgress = Math.round((i / total) * 100);
                    await new Promise((r) => requestAnimationFrame(r));
                }
            }

            const bounds = list
                .map((m) => [Number(m.lat), Number(m.lng)])
                .filter(([la, ln]) => isValidCoord(la, ln));
            this.fitMapBounds(bounds, this.scopeUf ? 8 : 6);
        },

        async renderHeatLayer() {
            this.layer.clearLayers();
            this.heatLayer.clearLayers();
            this.ufLayer.clearLayers();
            this.clusterGroup.clearLayers();
            this.markerLayers = [];

            const list = this.mapMarkersForRender.filter((m) => {
                if (m.consultoria_active) {
                    return false;
                }
                return Number(m.heat_intensity ?? 0) > 0 || String(m.tier).startsWith("prospect_");
            });

            const bounds = [];
            for (const m of list) {
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (!isValidCoord(lat, lng)) {
                    continue;
                }
                bounds.push([lat, lng]);
                const intensity = Math.max(
                    0.08,
                    Number(m.heat_intensity ?? m.success_score / 100) || 0,
                );
                const circle = L.circleMarker([lat, lng], {
                    radius: 6 + intensity * 16,
                    fillColor: heatColor(intensity),
                    color: "transparent",
                    weight: 0,
                    fillOpacity: 0.15 + intensity * 0.55,
                });
                circle.on("click", (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.selectMarker(m, e);
                });
                circle.addTo(this.heatLayer);
            }

            this.fitMapBounds(bounds, this.scopeUf ? 8 : 6);
        },

        fitMapBounds(bounds, fallbackZoom = 4) {
            if (!this.map) {
                return;
            }
            const valid = bounds.filter(([la, ln]) => isValidCoord(la, ln));
            if (valid.length > 0) {
                const maxZoom = this.scopeUf ? 10 : valid.length > 80 ? 6 : 7;
                this.map.fitBounds(valid, { padding: [40, 40], maxZoom });
            } else {
                this.map.setView([-14.2, -51.9], fallbackZoom);
            }
        },

        async selectUf(uf, userInitiated = true) {
            const scoped = String(uf ?? "").trim().toUpperCase();
            if (!scoped) {
                return;
            }
            this.filterTier = this.filterTier || "prospects";
            if (userInitiated) {
                this.filterTier = "prospects";
            }
            await this.fetchRegional(scoped);
        },

        async backToOverview() {
            this.scopeUf = "";
            this.filterUf = "";
            this.markers = [];
            this.mapMode = "overview";
            this.showAllOnMap = false;
            await this.fetchOverview();
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
            this.tooltipStyle = `left:${pinX + gap}px;top:${pinY + gap}px;visibility:hidden;`;
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
                left = Math.max(margin, Math.min(left, vw - tw - margin));
                top = Math.max(margin, Math.min(top, vh - th - margin));
                this.tooltipStyle = `left:${Math.round(left)}px;top:${Math.round(top)}px;max-height:${Math.round(maxH)}px;overflow-y:auto;visibility:visible;`;
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
            if (!this.map || !isValidCoord(lat, lng)) {
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
            this.filterTier = this.isRegionalMode ? "prospects" : "all";
            this.minSuccessScore = 0;
            this.minBenefitScore = 0;
            this.minMatriculas = 0;
            this.minFinancial = 0;
            this.minPedagogical = 0;
            this.minReadiness = 0;
            this.minSocialDemand = 0;
            this.requireFundeb = false;
            this.requireCenso = false;
            this.requireSaeb = false;
            this.requireCadunico = false;
            this.onlyMissingSge = false;
            this.hideConsultoria = false;
            this.showAllOnMap = false;
            this.renderCapDismissed = false;
        },

        enableFullMapRender() {
            this.showAllOnMap = true;
            this.renderCapDismissed = true;
            void this.refreshMapLayers();
        },

        dismissInitialNotice() {
            this.initialViewNotice = null;
        },

        async applyFocusSegment(segment) {
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
            if (f.require_cadunico) {
                this.requireCadunico = true;
            }
            if (f.only_missing_sge) {
                this.onlyMissingSge = true;
                this.filterTier = f.tier || "prospects";
            }
            if (f.min_social != null) {
                this.minSocialDemand = Number(f.min_social);
            }
            this.mapView = "heat";
            if (this.isOverviewMode) {
                const uf =
                    this.displayPolicy?.initial_uf ||
                    this.ufRankings?.[0]?.uf ||
                    this.ufMapPoints?.[0]?.uf;
                if (uf) {
                    await this.selectUf(uf, false);
                }
            }
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
            const sge = m.sge && typeof m.sge === "object" ? m.sge : null;
            if (sge) {
                lines.push(
                    `<dt class="text-gray-500">${escapeHtml("SGE")}</dt><dd>${escapeHtml(sge.system_label || sge.system || "—")}</dd>`,
                );
            }
            lines.push(`</dl>`);
            if (m.analytics_url) {
                lines.push(
                    `<a href="${escapeHtml(m.analytics_url)}" class="mt-2 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">${escapeHtml("Abrir consultoria")}</a>`,
                );
            }
            return lines.join("");
        },

        canEditSgeFor(m) {
            if (!this.canManageSge || !m) {
                return false;
            }
            if (m.sge_editable === false) {
                return false;
            }
            if (m.sge_editable === true) {
                return true;
            }
            const status = String(m.sge_status ?? m.sge?.status ?? "");
            return status === "not_found" || status === "registry";
        },

        sgeUrlFor(ibge) {
            return this.sgeRegistryUrl.replace("__IBGE__", encodeURIComponent(String(ibge)));
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
        },

        openSgeForm(m) {
            if (!this.canManageSge || !m?.ibge || !this.canEditSgeFor(m)) {
                return;
            }
            this.sgeFormError = null;
            this.sgeForm = {
                ibge: String(m.ibge),
                name: String(m.name ?? ""),
                uf: String(m.uf ?? ""),
                system: String(m.sge?.system ?? ""),
                vendor: "",
                notes: "",
                app_url: String(m.sge?.app_url ?? ""),
                has_entry: m.sge_status === "registry",
            };
            this.sgeFormOpen = true;
            this.loadSgeFormEntry(String(m.ibge));
        },

        closeSgeForm() {
            this.sgeFormOpen = false;
            this.sgeFormError = null;
            this.sgeFormSaving = false;
        },

        async loadSgeFormEntry(ibge) {
            if (!this.canManageSge) {
                return;
            }
            try {
                const response = await fetch(this.sgeUrlFor(ibge), {
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                const entry = data.entry;
                if (entry && typeof entry === "object") {
                    this.sgeForm.system = String(entry.system ?? this.sgeForm.system);
                    this.sgeForm.vendor = String(entry.vendor ?? "");
                    this.sgeForm.notes = String(entry.notes ?? "");
                    this.sgeForm.app_url = String(entry.app_url ?? "");
                    this.sgeForm.has_entry = true;
                }
            } catch (error) {
                console.debug("horizonte sge load", error);
            }
        },

        applyMarkerSgeUpdate(ibge, sge) {
            const target = String(ibge);
            const idx = this.markers.findIndex((m) => String(m.ibge) === target);
            if (idx < 0) {
                return;
            }
            const prev = this.markers[idx];
            this.markers[idx] = {
                ...prev,
                sge,
                sge_found: Boolean(sge?.found),
                sge_status: sge?.status ?? "not_found",
                sge_system: sge?.system ?? null,
            };
        },

        async saveSgeEntry() {
            if (!this.canManageSge || !this.sgeForm.ibge) {
                return;
            }
            this.sgeFormSaving = true;
            this.sgeFormError = null;
            try {
                const response = await fetch(this.sgeUrlFor(this.sgeForm.ibge), {
                    method: "PUT",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.csrfToken(),
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        system: this.sgeForm.system,
                        vendor: this.sgeForm.vendor,
                        notes: this.sgeForm.notes,
                        app_url: this.sgeForm.app_url || null,
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || `HTTP ${response.status}`);
                }
                if (data.sge) {
                    this.applyMarkerSgeUpdate(this.sgeForm.ibge, data.sge);
                    await this.refreshMapLayers();
                }
                this.closeSgeForm();
            } catch (error) {
                this.sgeFormError =
                    error instanceof Error ? error.message : "Erro ao gravar registo SGE.";
            } finally {
                this.sgeFormSaving = false;
            }
        },

        async deleteSgeEntry() {
            if (!this.canManageSge || !this.sgeForm.ibge || !this.sgeForm.has_entry) {
                return;
            }
            if (!window.confirm("Remover registo SGE deste município?")) {
                return;
            }
            this.sgeFormSaving = true;
            try {
                const response = await fetch(this.sgeUrlFor(this.sgeForm.ibge), {
                    method: "DELETE",
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": this.csrfToken(),
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || `HTTP ${response.status}`);
                }
                this.closeSgeForm();
                if (this.scopeUf) {
                    await this.fetchRegional(this.scopeUf);
                }
            } catch (error) {
                this.sgeFormError =
                    error instanceof Error ? error.message : "Erro ao remover registo SGE.";
            } finally {
                this.sgeFormSaving = false;
            }
        },
    };
}
