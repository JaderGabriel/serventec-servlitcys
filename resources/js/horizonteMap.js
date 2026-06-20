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
        return "#be123c";
    }
    if (t >= 0.33) {
        return "#b45309";
    }
    return "#fde68a";
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

const APPROX_COORD_SOURCES = new Set(["uf_spread", "overview"]);

function isApproxCoord(marker) {
    if (!marker) {
        return false;
    }
    if (marker.coord_approximate) {
        return true;
    }
    return APPROX_COORD_SOURCES.has(String(marker.coord_source ?? ""));
}

function matchesHighPressure(marker, threshold) {
    if (!marker || marker.consultoria_active) {
        return false;
    }
    const tier = String(marker.tier ?? "");
    if (!tier.startsWith("prospect_")) {
        return false;
    }

    return tier === "prospect_high" || Number(marker.financial_pressure ?? 0) >= threshold;
}

function markerVisualStyle(marker, colors) {
    const tier = String(marker?.tier ?? "");
    const fill = colors[tier] || "#64748b";
    const approx = isApproxCoord(marker);
    const sparse = tier === "data_sparse";

    return {
        radius: sparse ? 10 : approx ? 9 : 8,
        fillColor: fill,
        color: approx ? "#d97706" : sparse ? "#475569" : "#ffffff",
        weight: approx ? 2.5 : 2,
        dashArray: approx ? "4 3" : null,
        fillOpacity: sparse ? 0.98 : 0.92,
    };
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
        pendingRegionalUf: "",
        mapRefreshTimer: null,
        mapRefreshGeneration: 0,
        mapRendering: false,
        renderProgress: 0,
        loadingMessage: "",
        pageError: null,
        mapView: options.defaultViewFilter?.map_view ?? "heat",
        mapMode: "overview",
        scopeUf: "",
        meta: {},
        active: null,
        tooltipPinned: false,
        tooltipStyle: "",
        searchQuery: "",
        viewPreset: options.defaultViewFilter?.preset ?? "high_pressure",
        filterTier: options.defaultViewFilter?.tier ?? "prospects",
        displayPolicy: null,
        defaultViewFilter:
            options.defaultViewFilter && typeof options.defaultViewFilter === "object"
                ? options.defaultViewFilter
                : {},
        pressureThreshold: Number(
            options.defaultViewFilter?.pressure_min ??
                options.defaultViewFilter?.min_financial ??
                60,
        ),
        hideApproxOnMap: options.defaultViewFilter?.hide_approximate_on_map !== false,
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
        requireFundeb: options.defaultViewFilter?.require_fundeb === true,
        requireCenso: false,
        requireSaeb: false,
        requireCadunico: false,
        onlyMissingSge: false,
        hideConsultoria: options.defaultViewFilter?.hide_consultoria !== false,
        ufList: Array.isArray(options.ufList) ? options.ufList : [],
        ufNames:
            options.ufNames && typeof options.ufNames === "object" ? options.ufNames : {},
        prospectSort: "success_score",
        canRefreshData: Boolean(options.canRefreshData),
        canManageSge: Boolean(options.canManageSge),
        sgeShowUrl:
            typeof options.sgeShowUrl === "string" ? options.sgeShowUrl : "",
        sgeRegistryUrl:
            typeof options.sgeRegistryUrl === "string" ? options.sgeRegistryUrl : "",
        sgeFormOpen: false,
        sgeFormReadOnly: false,
        sgeFormSaving: false,
        sgeFormError: null,
        filterPanelOpen: true,
        methodologyPanelOpen: false,
        highlightIbge: "",
        methodology:
            options.methodology && typeof options.methodology === "object"
                ? options.methodology
                : {},
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
            let list = this.filteredMarkers.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            if (this.hideApproxOnMap) {
                list = list.filter((m) => !isApproxCoord(m));
            }
            const limit = Number(this.mapRenderLimit) || 400;
            let rendered = [];
            if (this.showAllOnMap || list.length <= limit) {
                rendered = list;
            } else {
                rendered = [...list]
                    .sort(
                        (a, b) =>
                            Number(b.success_score ?? 0) - Number(a.success_score ?? 0) ||
                            String(a.name).localeCompare(String(b.name), "pt-BR"),
                    )
                    .slice(0, limit);
            }

            const allValid = this.filteredMarkers.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            const pinnedIbge = String(this.highlightIbge ?? "").trim();
            if (pinnedIbge !== "") {
                const pinned = allValid.find((m) => String(m.ibge) === pinnedIbge);
                if (pinned && !rendered.some((m) => String(m.ibge) === pinnedIbge)) {
                    rendered = [pinned, ...rendered.slice(0, Math.max(0, limit - 1))];
                }
            }

            return rendered;
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

        get ufSelectOptions() {
            const codes =
                this.ufList.length > 0
                    ? this.ufList
                    : this.ufRankings.length > 0
                      ? this.ufRankings.map((r) => r.uf)
                      : this.ufMapPoints.map((p) => p.uf);
            return [...new Set(codes.map((uf) => String(uf ?? "").trim()).filter(Boolean))]
                .sort((a, b) => this.ufLabel(a).localeCompare(this.ufLabel(b), "pt-BR"))
                .map((uf) => ({
                    uf,
                    name: this.ufName(uf),
                    label: this.ufLabel(uf),
                }));
        },

        get approxHiddenOnMapCount() {
            if (!this.isRegionalMode || !this.hideApproxOnMap) {
                return 0;
            }

            return this.filteredMarkers.filter(
                (m) =>
                    isValidCoord(Number(m.lat), Number(m.lng)) && isApproxCoord(m),
            ).length;
        },

        get decisionViewBanner() {
            const df = this.defaultViewFilter || {};
            if (this.isOverviewMode) {
                const totalPressure = this.ufMapPoints.reduce(
                    (sum, p) => sum + Number(p.high_pressure ?? 0),
                    0,
                );
                return {
                    kind: "overview",
                    title: "Visão executiva — alta pressão por UF",
                    message:
                        "Bolhas = estados · intensidade = municípios de alta pressão FUNDEB. Clique num estado para abrir a camada municipal filtrada.",
                    count: totalPressure,
                    unit: "municípios de alta pressão (BR)",
                };
            }
            if (this.viewPreset === "high_pressure") {
                return {
                    kind: "regional",
                    title: df.label || "Alta pressão FUNDEB",
                    message:
                        df.description ||
                        `Prospectos com pressão financeira ≥ ${this.pressureThreshold} ou propensão alta — camada inicial para decisão.`,
                    count: this.filteredCount,
                    total: this.markers.length,
                    unit: "no recorte filtrado",
                };
            }

            return null;
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
                if (this.viewPreset === "high_pressure") {
                    if (!matchesHighPressure(m, this.pressureThreshold)) {
                        return false;
                    }
                } else if (this.filterTier === "prospects") {
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

        get scoreThresholds() {
            const t = this.methodology?.thresholds;
            return {
                high: Number(t?.high ?? 70),
                medium: Number(t?.medium ?? 40),
            };
        },

        get activeFilterCount() {
            let count = 0;
            if (this.viewPreset === "high_pressure") {
                count += 1;
            } else if (this.filterTier !== "prospects" && this.filterTier !== "all") {
                count += 1;
            }
            if (this.minSuccessScore > 0) {
                count += 1;
            }
            if (this.minBenefitScore > 0) {
                count += 1;
            }
            if (this.minMatriculas > 0) {
                count += 1;
            }
            if (this.minFinancial > 0) {
                count += 1;
            }
            if (this.minPedagogical > 0) {
                count += 1;
            }
            if (this.minReadiness > 0) {
                count += 1;
            }
            if (this.minSocialDemand > 0) {
                count += 1;
            }
            if (this.requireFundeb) {
                count += 1;
            }
            if (this.requireCenso) {
                count += 1;
            }
            if (this.requireSaeb) {
                count += 1;
            }
            if (this.requireCadunico) {
                count += 1;
            }
            if (this.onlyMissingSge) {
                count += 1;
            }
            if (this.hideConsultoria) {
                count += 1;
            }
            if (this.searchQuery.trim() !== "") {
                count += 1;
            }
            return count;
        },

        get mapInteractionStats() {
            if (!this.isRegionalMode) {
                return { onMap: 0, approximate: 0, sparse: 0 };
            }
            const valid = this.filteredMarkers.filter((m) =>
                isValidCoord(Number(m.lat), Number(m.lng)),
            );
            return {
                onMap: this.mapMarkersForRender.length,
                total: valid.length,
                approximate: valid.filter((m) => isApproxCoord(m)).length,
                sparse: valid.filter((m) => m.tier === "data_sparse").length,
            };
        },

        get activeFilterChips() {
            const chips = [];
            const tierLabels = {
                high_pressure: `Alta pressão FUNDEB ≥ ${this.pressureThreshold}`,
                prospects: "Prospectos",
                prospect_high: "Alta propensão",
                all: "Todos",
                consultoria_active: "Consultoria",
                catalog_pending: "Catálogo pendente",
            };
            if (this.viewPreset === "high_pressure") {
                chips.push({
                    key: "preset",
                    label: tierLabels.high_pressure,
                });
            } else if (this.filterTier !== "prospects" && this.filterTier !== "all") {
                chips.push({
                    key: "tier",
                    label: tierLabels[this.filterTier] || this.filterTier,
                });
            }
            if (this.minSuccessScore > 0) {
                chips.push({
                    key: "success",
                    label: `Propensão ≥ ${this.minSuccessScore}`,
                });
            }
            if (this.minBenefitScore > 0) {
                chips.push({
                    key: "benefit",
                    label: `Benefício ≥ ${this.minBenefitScore}`,
                });
            }
            if (this.minMatriculas > 0) {
                chips.push({
                    key: "matriculas",
                    label: `Matrículas ≥ ${nf(this.minMatriculas)}`,
                });
            }
            if (this.minFinancial > 0) {
                chips.push({
                    key: "financial",
                    label: `Pressão FUNDEB ≥ ${this.minFinancial}`,
                });
            }
            if (this.minPedagogical > 0) {
                chips.push({
                    key: "pedagogical",
                    label: `Déficit SAEB ≥ ${this.minPedagogical}`,
                });
            }
            if (this.minReadiness > 0) {
                chips.push({
                    key: "readiness",
                    label: `Prontidão ≥ ${this.minReadiness}`,
                });
            }
            if (this.minSocialDemand > 0) {
                chips.push({
                    key: "social",
                    label: `Demanda social ≥ ${this.minSocialDemand}`,
                });
            }
            if (this.requireFundeb) {
                chips.push({ key: "fundeb", label: "FUNDEB" });
            }
            if (this.requireCenso) {
                chips.push({ key: "censo", label: "Censo" });
            }
            if (this.requireSaeb) {
                chips.push({ key: "saeb", label: "SAEB" });
            }
            if (this.requireCadunico) {
                chips.push({ key: "cadunico", label: "CadÚnico" });
            }
            if (this.onlyMissingSge) {
                chips.push({ key: "sge", label: "Sem SGE" });
            }
            if (this.hideConsultoria) {
                chips.push({ key: "hide_consultoria", label: "Ocultar consultoria" });
            }
            const q = this.searchQuery.trim();
            if (q !== "") {
                chips.push({ key: "search", label: `«${q}»` });
            }
            return chips;
        },

        async init() {
            this.initMap();
            if (this.loadUrl) {
                await this.fetchOverview();
                await this.applyInitialNavigation();
            }
        },

        applyDefaultDecisionView() {
            const df =
                (this.meta?.default_filter &&
                typeof this.meta.default_filter === "object"
                    ? this.meta.default_filter
                    : null) ||
                (this.defaultViewFilter && typeof this.defaultViewFilter === "object"
                    ? this.defaultViewFilter
                    : {});

            this.viewPreset = df.preset || "high_pressure";
            this.filterTier = df.tier || "prospects";
            this.hideConsultoria = df.hide_consultoria !== false;
            this.requireFundeb = df.require_fundeb === true;
            this.pressureThreshold = Number(
                df.pressure_min ?? df.min_financial ?? 60,
            );
            this.hideApproxOnMap = df.hide_approximate_on_map !== false;
            this.mapView = df.map_view || "heat";
            this.minSuccessScore = 0;
            this.minBenefitScore = 0;
            this.minMatriculas = 0;
            this.minFinancial = 0;
            this.minPedagogical = 0;
            this.minReadiness = 0;
            this.minSocialDemand = 0;
            this.requireCenso = false;
            this.requireSaeb = false;
            this.requireCadunico = false;
            this.onlyMissingSge = false;
            this.searchQuery = "";
            this.showAllOnMap = false;
            this.renderCapDismissed = false;
        },

        async applyInitialNavigation() {
            this.applyDefaultDecisionView();
            const queryUf = String(options.initialUf ?? "").trim().toUpperCase();
            const policyUf = String(this.displayPolicy?.initial_uf ?? "")
                .trim()
                .toUpperCase();
            const uf = queryUf || policyUf;
            if (uf !== "" && !this.pageError) {
                await this.selectUf(uf, false);
            }
        },

        setViewPreset(preset) {
            this.viewPreset = preset;
            if (preset === "high_pressure") {
                this.applyDefaultDecisionView();
                return;
            }
            if (preset === "prospects") {
                this.applyDefaultDecisionView();
                this.viewPreset = "prospects";
                this.minFinancial = 0;
                this.requireFundeb = false;
                return;
            }
            if (preset === "prospect_high") {
                this.clearSecondaryFilters();
                this.viewPreset = "custom";
                this.filterTier = "prospect_high";
                this.hideConsultoria = true;
                return;
            }
            if (preset === "all") {
                this.resetFiltersToAll();
            }
        },

        clearSecondaryFilters() {
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
            this.searchQuery = "";
        },

        markFiltersCustom() {
            if (this.viewPreset === "high_pressure") {
                this.viewPreset = "custom";
            }
        },

        ufName(uf) {
            const code = String(uf ?? "").trim().toUpperCase();
            if (code === "") {
                return "";
            }
            const fromPoint = this.ufMapPoints.find((p) => p.uf === code);
            if (fromPoint?.uf_name) {
                return String(fromPoint.uf_name);
            }
            const fromRank = this.ufRankings.find((r) => r.uf === code);
            if (fromRank?.uf_name) {
                return String(fromRank.uf_name);
            }
            return String(this.ufNames?.[code] ?? code);
        },

        ufLabel(uf) {
            const code = String(uf ?? "").trim().toUpperCase();
            if (code === "") {
                return "";
            }
            const name = this.ufName(code);
            return name !== code ? `${code} — ${name}` : code;
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
            if (this.regionalLoading && this.pendingRegionalUf === scoped) {
                return;
            }
            this.pendingRegionalUf = scoped;
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
                if (this.pendingRegionalUf === scoped) {
                    this.pendingRegionalUf = "";
                }
                await this.scheduleMapRefresh();
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
            this.markers = Array.isArray(data.markers) ? data.markers : [];
            this.ufMapPoints = [];
            this.ufList = uniqueSortedUfs(this.markers).sort((a, b) =>
                this.ufLabel(a).localeCompare(this.ufLabel(b), "pt-BR"),
            );
            this.applyCommonPayload(data);
            this.initialViewNotice = {
                kind: "regional",
                message: `${this.markers.length.toLocaleString("pt-BR")} municípios com dados em ${this.ufLabel(uf)} · ${Number(this.summary?.prospect_count ?? 0).toLocaleString("pt-BR")} prospectos.`,
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
            if (this.meta.default_filter && typeof this.meta.default_filter === "object") {
                this.defaultViewFilter = this.meta.default_filter;
            }
            this.displayPolicy =
                this.meta.display_policy && typeof this.meta.display_policy === "object"
                    ? this.meta.display_policy
                    : null;
            this.mapRenderLimit = Number(this.displayPolicy?.max_render_markers) || 400;
            if (this.ufList.length === 0 && Array.isArray(this.ufMapPoints)) {
                this.ufList = this.ufMapPoints
                    .map((p) => p.uf)
                    .filter(Boolean)
                    .sort((a, b) => this.ufLabel(a).localeCompare(this.ufLabel(b), "pt-BR"));
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
                maxClusterRadius: 36,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                disableClusteringAtZoom: 11,
                zoomToBoundsOnClick: true,
            });
            this.map.addLayer(this.clusterGroup);

            this.map.setView([-14.2, -51.9], 4);

            this.map.on("click", () => {
                if (!this.sgeFormOpen) {
                    this.closeTooltip();
                }
            });

            this.$watch("filteredMarkers", () => {
                if (!this.isRegionalMode || this.regionalLoading || this.pageLoading) {
                    return;
                }
                this.showAllOnMap = false;
                this.renderCapDismissed = false;
                void this.scheduleMapRefresh();
            });
            this.$watch("mapView", () => void this.scheduleMapRefresh());
        },

        scheduleMapRefresh() {
            this.mapRefreshGeneration += 1;
            const generation = this.mapRefreshGeneration;

            if (this.mapRefreshTimer !== null) {
                clearTimeout(this.mapRefreshTimer);
            }

            return new Promise((resolve) => {
                this.mapRefreshTimer = window.setTimeout(async () => {
                    this.mapRefreshTimer = null;
                    if (generation !== this.mapRefreshGeneration) {
                        resolve();
                        return;
                    }
                    await this.refreshMapLayers();
                    resolve();
                }, 32);
            });
        },

        async refreshMapLayers() {
            if (!this.map) {
                return;
            }

            const pinnedMarker =
                this.tooltipPinned && this.active ? { ...this.active } : null;
            const pinnedIbge = pinnedMarker ? String(pinnedMarker.ibge ?? "") : "";
            const pinnedStillVisible =
                pinnedIbge !== "" &&
                (this.isOverviewMode ||
                    this.filteredMarkers.some((m) => String(m.ibge) === pinnedIbge));

            if (!pinnedStillVisible) {
                this.closeTooltip();
            }

            this.mapRendering = true;
            this.renderProgress = 0;

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

                if (pinnedStillVisible && pinnedMarker && this.isRegionalMode) {
                    const latest =
                        this.markers.find((m) => String(m.ibge) === pinnedIbge) ||
                        this.mapMarkersForRender.find((m) => String(m.ibge) === pinnedIbge) ||
                        pinnedMarker;
                    this.active = latest;
                    this.tooltipPinned = true;
                    const lat = Number(latest.lat);
                    const lng = Number(latest.lng);
                    if (isValidCoord(lat, lng)) {
                        this.positionTooltip({
                            containerPoint: this.map.latLngToContainerPoint([lat, lng]),
                        });
                    }
                }
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
                const ufLabel = escapeHtml(p.uf_name ? `${p.uf} — ${p.uf_name}` : this.ufLabel(p.uf));
                circle.bindTooltip(
                    `<strong>${ufLabel}</strong><br>` +
                        `${nf(p.high_pressure ?? 0)} alta pressão · ${nf(p.high_prospect ?? 0)} alta propensão<br>` +
                        `${nf(p.total)} com dados · ${nf(p.prospect_count)} prospectos<br>` +
                        `<span class="text-slate-500">Clique para abrir camada municipal filtrada</span>`,
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
                const style = markerVisualStyle(m, this.colors);
                const marker = L.circleMarker([lat, lng], {
                    ...style,
                    className: isApproxCoord(m) ? "serv-horizonte-marker--approx" : "",
                });
                marker.bindTooltip(
                    `<span class="font-medium">${escapeHtml(m.name)}</span> — ${escapeHtml(m.uf)}` +
                        (isApproxCoord(m) ? `<br><span class="text-amber-700">${escapeHtml("Coordenada aproximada")}</span>` : ""),
                    { direction: "top", opacity: 0.95 },
                );
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

            const list = this.mapMarkersForRender.filter((m) => !m.consultoria_active);

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

        onScopeUfPick(event) {
            const uf = String(event?.target?.value ?? "")
                .trim()
                .toUpperCase();
            if (uf === "") {
                void this.backToOverview();
                return;
            }
            void this.selectUf(uf, true);
        },

        async selectUf(uf, userInitiated = true, force = false) {
            const scoped = String(uf ?? "").trim().toUpperCase();
            if (!scoped) {
                return;
            }
            if (
                !force &&
                this.isRegionalMode &&
                this.scopeUf === scoped &&
                this.markers.length > 0 &&
                !this.regionalLoading
            ) {
                return;
            }
            if (this.regionalLoading && this.pendingRegionalUf === scoped) {
                return;
            }
            this.highlightIbge = "";
            if (userInitiated) {
                this.applyDefaultDecisionView();
            }
            await this.fetchRegional(scoped);
        },

        async backToOverview() {
            this.scopeUf = "";
            this.markers = [];
            this.mapMode = "overview";
            this.showAllOnMap = false;
            await this.fetchOverview();
        },

        selectMarker(m, ev = null) {
            this.active = m;
            this.tooltipPinned = true;
            let containerPoint = ev?.containerPoint ?? null;
            if (!containerPoint && this.map && m) {
                const lat = Number(m.lat);
                const lng = Number(m.lng);
                if (isValidCoord(lat, lng)) {
                    containerPoint = this.map.latLngToContainerPoint([lat, lng]);
                }
            }
            if (containerPoint) {
                this.positionTooltip({ containerPoint });
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
            void this.flyToMarker(m);
        },

        async flyToMarker(m) {
            if (!m) {
                return;
            }
            const lat = Number(m.lat);
            const lng = Number(m.lng);
            if (!this.map || !isValidCoord(lat, lng)) {
                return;
            }
            const targetUf = String(m.uf ?? "").trim().toUpperCase();
            if (this.isOverviewMode && targetUf) {
                await this.selectUf(targetUf, false);
            } else if (
                this.isRegionalMode &&
                targetUf &&
                this.scopeUf !== targetUf
            ) {
                await this.selectUf(targetUf, false);
            }
            this.highlightIbge = String(m.ibge ?? "");
            await this.scheduleMapRefresh();
            const zoom = isApproxCoord(m) ? 9 : 10;
            this.map.flyTo([lat, lng], zoom, { duration: 0.75 });
            window.setTimeout(() => {
                if (!this.map) {
                    return;
                }
                const point = this.map.latLngToContainerPoint([lat, lng]);
                this.selectMarker(m, { containerPoint: point });
            }, 400);
        },

        setFilterTier(tier) {
            this.viewPreset = "custom";
            this.filterTier = tier;
        },

        setMapView(view) {
            this.mapView = view;
        },

        resetFilters() {
            this.applyDefaultDecisionView();
        },

        resetFiltersToAll() {
            this.viewPreset = "all";
            this.filterTier = "all";
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
            this.mapView = "markers";
        },

        enableFullMapRender() {
            this.showAllOnMap = true;
            this.renderCapDismissed = true;
            void this.scheduleMapRefresh();
        },

        dismissInitialNotice() {
            this.initialViewNotice = null;
        },

        async applyFocusSegment(segment) {
            if (!segment?.filter) {
                return;
            }
            const f = segment.filter;
            this.resetFiltersToAll();
            this.viewPreset = "custom";
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
            this.hideConsultoria = true;
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
            const th = this.scoreThresholds;
            const lines = [];
            if (isApproxCoord(m)) {
                lines.push(
                    `<p class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1.5 text-[11px] text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">${escapeHtml("Posição indicativa (centroide IBGE ou dispersão na UF) — use a lista para localizar o município.")}</p>`,
                );
            }
            if (m.tier === "data_sparse") {
                lines.push(
                    `<p class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 text-[11px] text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-200">${escapeHtml("Sem dados públicos importados — score e tier indicativos. Importe FUNDEB, Censo ou SAEB para enriquecer.")}</p>`,
                );
            }
            lines.push(
                `<dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">`,
                `<dt class="text-gray-500">${escapeHtml("Propensão")}</dt><dd class="font-semibold tabular-nums">${nf(m.success_score)}/100 <span class="text-[10px] font-normal text-slate-400">(≥${th.high} alta)</span></dd>`,
                `<dt class="text-gray-500">${escapeHtml("Benefício")}</dt><dd class="font-semibold tabular-nums">${nf(m.benefit_score)}/100</dd>`,
            );
            if (m.matriculas_censo != null) {
                lines.push(
                    `<dt class="text-gray-500">${escapeHtml("Matrículas Censo")}</dt><dd class="tabular-nums">${nf(m.matriculas_censo)}</dd>`,
                );
            }
            const sources = [
                m.has_fundeb ? "FUNDEB" : null,
                m.has_censo ? "Censo" : null,
                m.has_saeb ? "SAEB" : null,
                m.has_cadunico ? "CadÚnico" : null,
            ].filter(Boolean);
            if (sources.length > 0) {
                lines.push(
                    `<dt class="text-gray-500">${escapeHtml("Fontes")}</dt><dd>${escapeHtml(sources.join(" · "))}</dd>`,
                );
            }
            const sge = m.sge && typeof m.sge === "object" ? m.sge : null;
            if (sge) {
                lines.push(
                    `<dt class="text-gray-500">${escapeHtml("SGE")}</dt><dd>${escapeHtml(sge.system_label || sge.system || "—")}</dd>`,
                );
            }
            lines.push(`</dl>`);

            const dims = [
                { key: "financial_pressure", label: "Financeira" },
                { key: "pedagogical_gap", label: "Pedagógica" },
                { key: "scale_score", label: "Escala" },
                { key: "social_demand", label: "Social" },
                { key: "transfer_dependency", label: "Transfer." },
                { key: "data_readiness", label: "Prontidão" },
            ];
            lines.push(
                `<p class="mt-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">${escapeHtml("Dimensões (0–100)")}</p>`,
            );
            lines.push(`<div class="mt-1 space-y-1.5">`);
            for (const d of dims) {
                const val = Math.max(0, Math.min(100, Number(m[d.key] ?? 0)));
                lines.push(
                    `<div class="flex items-center gap-2 text-[11px]">` +
                        `<span class="w-[4.5rem] shrink-0 text-slate-500">${escapeHtml(d.label)}</span>` +
                        `<span class="flex-1 h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">` +
                        `<span class="block h-full rounded-full bg-teal-600" style="width:${val}%"></span>` +
                        `</span>` +
                        `<span class="w-6 text-right tabular-nums font-medium">${val}</span>` +
                        `</div>`,
                );
            }
            lines.push(`</div>`);

            if (m.analytics_url) {
                lines.push(
                    `<a href="${escapeHtml(m.analytics_url)}" class="mt-3 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">${escapeHtml("Abrir consultoria")}</a>`,
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
            if (m.in_catalog || m.consultoria_active) {
                return false;
            }
            const status = String(m.sge_status ?? m.sge?.status ?? "");
            return status === "not_found" || status === "registry";
        },

        sgeHasRegistry(m) {
            if (!m) {
                return false;
            }
            const status = String(m.sge_status ?? m.sge?.status ?? "");
            return status === "registry";
        },

        sgeRegistryActionLabel(m) {
            return this.sgeHasRegistry(m) ? "Actualizar" : "Cadastrar";
        },

        sgeShowUrlFor(ibge) {
            return this.sgeShowUrl.replace("__IBGE__", encodeURIComponent(String(ibge)));
        },

        sgeUrlFor(ibge) {
            return this.sgeRegistryUrl.replace("__IBGE__", encodeURIComponent(String(ibge)));
        },

        handleSgeCellClick(m) {
            if (this.canEditSgeFor(m)) {
                this.openSgeForm(m);
                return;
            }
            if (m?.cities_url) {
                window.open(m.cities_url, "_blank", "noopener,noreferrer");
                return;
            }
            void this.flyToMarker(m);
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
        },

        openSgeForm(m, options = {}) {
            if (!this.canManageSge || !m?.ibge || !this.canEditSgeFor(m)) {
                return;
            }
            const readOnly = Boolean(options?.readOnly);
            this.sgeFormError = null;
            this.sgeFormReadOnly = readOnly;
            this.sgeForm = {
                ibge: String(m.ibge),
                name: String(m.name ?? ""),
                uf: String(m.uf ?? ""),
                system: String(m.sge?.system ?? ""),
                vendor: "",
                notes: String(m.sge?.detail ?? ""),
                app_url: String(m.sge?.app_url ?? ""),
                has_entry: this.sgeHasRegistry(m),
            };
            this.closeTooltip();
            this.sgeFormOpen = true;
            void this.loadSgeFormEntry(String(m.ibge));
        },

        enableSgeFormEdit() {
            if (!this.canManageSge || !this.sgeForm.ibge) {
                return;
            }
            this.sgeFormReadOnly = false;
        },

        closeSgeForm() {
            this.sgeFormOpen = false;
            this.sgeFormReadOnly = false;
            this.sgeFormError = null;
            this.sgeFormSaving = false;
        },

        async loadSgeFormEntry(ibge) {
            if (!this.canManageSge) {
                return;
            }
            try {
                const response = await fetch(this.sgeShowUrlFor(ibge), {
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
                sge_editable:
                    prev.sge_editable ??
                    (!prev.in_catalog && !prev.consultoria_active),
            };
            if (String(this.active?.ibge ?? "") === target) {
                this.active = this.markers[idx];
            }
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
                    await this.scheduleMapRefresh();
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
