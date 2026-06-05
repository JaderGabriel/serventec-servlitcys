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

function toFiniteNumber(v) {
    const n = typeof v === "number" ? v : Number(String(v ?? "").trim());
    return Number.isFinite(n) ? n : null;
}

function haversineKm(lat1, lng1, lat2, lng2) {
    const a1 = toFiniteNumber(lat1);
    const o1 = toFiniteNumber(lng1);
    const a2 = toFiniteNumber(lat2);
    const o2 = toFiniteNumber(lng2);
    if (
        a1 === null ||
        o1 === null ||
        a2 === null ||
        o2 === null ||
        Math.abs(a1) > 90 ||
        Math.abs(a2) > 90 ||
        Math.abs(o1) > 180 ||
        Math.abs(o2) > 180
    ) {
        return null;
    }
    const R = 6371;
    const p1 = (a1 * Math.PI) / 180;
    const p2 = (a2 * Math.PI) / 180;
    const dp = ((a2 - a1) * Math.PI) / 180;
    const dl = ((o2 - o1) * Math.PI) / 180;
    const s =
        Math.sin(dp / 2) ** 2 +
        Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
}

function formatKm(km) {
    const n = toFiniteNumber(km);
    if (n === null) {
        return "—";
    }
    if (n < 1) {
        return `${Math.round(n * 1000)} m`;
    }
    return `${n.toLocaleString("pt-BR", { maximumFractionDigits: 1 })} km`;
}

function territoryKey(t) {
    const c = String(t?.codigo ?? "").trim();
    if (c !== "") {
        return c;
    }
    return `${t?.label ?? ""}|${t?.lat ?? ""}|${t?.lng ?? ""}`;
}

function linkColorForKm(km) {
    const d = toFiniteNumber(km);
    if (d === null) {
        return "#94a3b8";
    }
    if (d <= 2) {
        return "#16a34a";
    }
    if (d <= 5) {
        return "#ca8a04";
    }
    if (d <= 10) {
        return "#ea580c";
    }
    return "#dc2626";
}

function schoolMarkerStyle(mk, maxMat) {
    const mat = toFiniteNumber(mk?.school?.matriculas);
    const vagas = toFiniteNumber(mk?.school?.vagas_disponiveis);
    const cap = toFiniteNumber(mk?.school?.capacidade_declarada);
    let color = "#1e40af";
    let fill = "#2563eb";
    let state = "rede";
    if (vagas !== null && vagas > 0) {
        color = "#166534";
        fill = "#22c55e";
        state = "vagas";
    } else if (
        cap !== null &&
        mat !== null &&
        cap > 0 &&
        mat / cap >= 0.9
    ) {
        color = "#5b21b6";
        fill = "#7c3aed";
        state = "quase_lotada";
    }
    let radius = 7;
    if (mat !== null && mat > 0 && maxMat > 0) {
        radius = Math.round(6 + 10 * Math.sqrt(mat / maxMat));
    }
    return { color, fill, radius, state };
}

/** Escala de pressão: amarelo → laranja → vermelho (maior contraste entre faixas). */
function pressureCircleStyle(pressao, maxPressao, gap, maxGap) {
    const p = Math.min(1, Number(pressao ?? 0) / Math.max(1, maxPressao));
    const g = Math.min(1, Number(gap ?? 0) / Math.max(1, maxGap));
    const intensity = 0.65 * p + 0.35 * g;
    if (intensity >= 0.66) {
        return {
            fillColor: "#dc2626",
            color: "#7f1d1d",
            fillOpacity: 0.42,
            weight: 2.5,
            tier: "alta",
        };
    }
    if (intensity >= 0.33) {
        return {
            fillColor: "#f97316",
            color: "#9a3412",
            fillOpacity: 0.34,
            weight: 2,
            tier: "media",
        };
    }
    return {
        fillColor: "#fde047",
        color: "#a16207",
        fillOpacity: 0.28,
        weight: 2,
        tier: "baixa",
    };
}

function bindDistanceTooltip(polyline, text) {
    polyline
        .bindTooltip(escapeHtml(text), {
            permanent: true,
            direction: "center",
            className: "serv-cadunico-map-dist-tooltip",
            opacity: 1,
        })
        .openTooltip();
}

/**
 * Mapa de pressão territorial CadÚnico com filtros, escolas e distâncias.
 */
export default function createCadunicoTerritoryMap(
    territoryMarkers = [],
    schoolMarkers = [],
    footnote = null,
    ranking = [],
) {
    const territories = Array.isArray(territoryMarkers) ? territoryMarkers : [];
    const schools = Array.isArray(schoolMarkers) ? schoolMarkers : [];
    const rankingRows = Array.isArray(ranking) ? ranking : [];

    const tipoSet = new Set();
    for (const t of territories) {
        const tipo = String(t?.tipo ?? "").trim();
        if (tipo !== "") {
            tipoSet.add(tipo);
        }
    }
    const territoryTypes = {};
    for (const tipo of tipoSet) {
        territoryTypes[tipo] = true;
    }

    const territoryVisible = {};
    for (const t of territories) {
        territoryVisible[territoryKey(t)] = true;
    }

    return {
        territories,
        schools,
        rankingRows,
        footnote: typeof footnote === "string" ? footnote : null,
        map: null,
        booted: false,
        _boundsFitted: false,
        _onTab: null,
        territoryLayer: null,
        schoolLayer: null,
        allocationLayer: null,
        zoneMeshLayer: null,
        filters: {
            showTerritories: true,
            showSchools: true,
            showAllocationLinks: true,
            showZoneSchoolMesh: false,
            minGap: 0,
            highlightPressureOnly: false,
        },
        territoryTypes,
        territoryVisible,

        init() {
            this._onTab = (e) => {
                if (e?.detail?.tab === "cadunico_previsao") {
                    setTimeout(() => this.tryBoot(), 450);
                }
            };
            window.addEventListener("analytics-tab-changed", this._onTab);
            this.$watch("filters", () => this.renderLayers(), { deep: true });
            this.$watch("territoryTypes", () => this.renderLayers(), {
                deep: true,
            });
            this.$watch("territoryVisible", () => this.renderLayers(), {
                deep: true,
            });
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

        territoryKey(t) {
            return territoryKey(t);
        },

        tipoList() {
            return Object.keys(this.territoryTypes).sort((a, b) =>
                a.localeCompare(b, "pt-BR"),
            );
        },

        topTerritoriesForFilter() {
            const rows = [...this.territories].sort(
                (a, b) => (b.pressao ?? 0) - (a.pressao ?? 0),
            );
            return rows.slice(0, 20);
        },

        toggleAllTerritories(visible) {
            for (const key of Object.keys(this.territoryVisible)) {
                this.territoryVisible[key] = visible;
            }
        },

        filteredTerritories() {
            const minGap = Number(this.filters.minGap) || 0;
            const pressureCut = this.filters.highlightPressureOnly
                ? this.pressureThreshold()
                : 0;

            return this.territories.filter((t) => {
                const key = territoryKey(t);
                if (this.territoryVisible[key] === false) {
                    return false;
                }
                const tipo = String(t?.tipo ?? "").trim();
                if (tipo !== "" && this.territoryTypes[tipo] === false) {
                    return false;
                }
                const gap = Number(t?.gap ?? 0);
                if (gap < minGap) {
                    return false;
                }
                if (pressureCut > 0 && Number(t?.pressao ?? 0) < pressureCut) {
                    return false;
                }
                return true;
            });
        },

        pressureThreshold() {
            const sorted = [...this.territories]
                .map((t) => Number(t?.pressao ?? 0))
                .filter((n) => n > 0)
                .sort((a, b) => b - a);
            if (sorted.length < 4) {
                return 0;
            }
            const idx = Math.max(0, Math.floor(sorted.length * 0.35) - 1);
            return sorted[idx] ?? 0;
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
            this.map = L.map(el, { scrollWheelZoom: true }).setView(
                [-14.2, -51.9],
                12,
            );
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 18,
            }).addTo(this.map);

            this.territoryLayer = L.layerGroup().addTo(this.map);
            this.schoolLayer = L.layerGroup().addTo(this.map);
            this.allocationLayer = L.layerGroup().addTo(this.map);
            this.zoneMeshLayer = L.layerGroup().addTo(this.map);

            this.renderLayers();

            setTimeout(() => {
                this.map?.invalidateSize();
            }, 120);
        },

        renderLayers() {
            if (!this.map) {
                return;
            }

            this.territoryLayer?.clearLayers();
            this.schoolLayer?.clearLayers();
            this.allocationLayer?.clearLayers();
            this.zoneMeshLayer?.clearLayers();

            const bounds = [];
            const visibleTerritories = this.filteredTerritories();
            const maxGap = Math.max(
                1,
                ...visibleTerritories.map((t) => Number(t?.gap ?? 0)),
            );
            const maxPressao = Math.max(
                1,
                ...visibleTerritories.map((t) => Number(t?.pressao ?? 0)),
            );

            if (this.filters.showTerritories) {
                for (const t of visibleTerritories) {
                    const lat = toFiniteNumber(t.lat);
                    const lng = toFiniteNumber(t.lng);
                    if (lat === null || lng === null) {
                        continue;
                    }
                    const gap = Number(t?.gap ?? 0);
                    const pressao = Number(t?.pressao ?? 0);
                    const r = Math.max(8, Number(t.radius) || 14);
                    const pStyle = pressureCircleStyle(
                        pressao,
                        maxPressao,
                        gap,
                        maxGap,
                    );
                    const nearest = t.nearest_school ?? null;
                    const distLabel =
                        t.distancia_escola_km ?? nearest?.km ?? null;
                    const popup = [
                        `<strong>${escapeHtml(t.label ?? "")}</strong>`,
                        `<span class="block text-xs mt-1">${escapeHtml(t.tipo ?? "")}</span>`,
                        `<span class="block text-xs">${escapeHtml(t.meta ?? "")}</span>`,
                        `<span class="block text-xs mt-1">${escapeHtml("Pressão")}: <strong>${nf(pressao)}</strong></span>`,
                    ];
                    if (nearest?.label) {
                        popup.push(
                            `<span class="block text-xs mt-1">${escapeHtml("Escola mais próxima")}: ${escapeHtml(nearest.label)} (${formatKm(distLabel)})</span>`,
                        );
                    }
                    L.circle([lat, lng], {
                        radius: r * 80,
                        color: pStyle.color,
                        fillColor: pStyle.fillColor,
                        fillOpacity: pStyle.fillOpacity,
                        weight: pStyle.weight,
                    })
                        .bindPopup(popup.join(""))
                        .addTo(this.territoryLayer);
                    bounds.push([lat, lng]);
                }
            }

            if (this.filters.showSchools && this.schools.length > 0) {
                let maxMat = 0;
                for (const mk of this.schools) {
                    const m = toFiniteNumber(mk?.school?.matriculas);
                    if (m !== null && m > maxMat) {
                        maxMat = m;
                    }
                }
                for (const s of this.schools) {
                    const lat = toFiniteNumber(s.lat);
                    const lng = toFiniteNumber(s.lng);
                    if (lat === null || lng === null) {
                        continue;
                    }
                    const { color, fill, radius } = schoolMarkerStyle(s, maxMat);
                    const mat = s?.school?.matriculas;
                    const vagas = s?.school?.vagas_disponiveis;
                    const cap = s?.school?.capacidade_declarada;
                    const lines = [
                        `<strong>${escapeHtml(s.label ?? "Escola")}</strong>`,
                    ];
                    if (mat != null) {
                        lines.push(
                            `<span class="block text-xs mt-1">${escapeHtml("Matrículas")}: ${nf(mat)}</span>`,
                        );
                    }
                    if (cap != null) {
                        lines.push(
                            `<span class="block text-xs">${escapeHtml("Capacidade")}: ${nf(cap)}</span>`,
                        );
                    }
                    if (vagas != null) {
                        lines.push(
                            `<span class="block text-xs">${escapeHtml("Vagas")}: ${nf(vagas)}</span>`,
                        );
                    }
                    L.circleMarker([lat, lng], {
                        radius,
                        color,
                        fillColor: fill,
                        fillOpacity: 0.9,
                        weight: 2,
                    })
                        .bindPopup(lines.join(""))
                        .addTo(this.schoolLayer);
                    bounds.push([lat, lng]);
                }
            }

            if (
                this.filters.showAllocationLinks &&
                this.schools.length > 0 &&
                visibleTerritories.length > 0
            ) {
                for (const t of visibleTerritories) {
                    const lat = toFiniteNumber(t.lat);
                    const lng = toFiniteNumber(t.lng);
                    const nearest = t.nearest_school ?? null;
                    const slat = toFiniteNumber(nearest?.lat);
                    const slng = toFiniteNumber(nearest?.lng);
                    const km =
                        toFiniteNumber(t.distancia_escola_km) ??
                        toFiniteNumber(nearest?.km);
                    if (
                        lat === null ||
                        lng === null ||
                        slat === null ||
                        slng === null ||
                        km === null
                    ) {
                        continue;
                    }
                    const color = linkColorForKm(km);
                    const line = L.polyline(
                        [
                            [lat, lng],
                            [slat, slng],
                        ],
                        {
                            color,
                            weight: 2,
                            opacity: 0.75,
                            dashArray: "6 8",
                        },
                    )
                        .bindTooltip(
                            `${escapeHtml(t.label ?? "")} → ${escapeHtml(nearest?.label ?? "")}: ${formatKm(km)}`,
                            { sticky: true },
                        );
                    bindDistanceTooltip(line, formatKm(km));
                    line.addTo(this.allocationLayer);
                }
            }

            if (
                this.filters.showZoneSchoolMesh &&
                this.schools.length >= 2 &&
                visibleTerritories.length > 0
            ) {
                const schoolPts = this.schools
                    .map((s, idx) => {
                        const lat = toFiniteNumber(s.lat);
                        const lng = toFiniteNumber(s.lng);
                        if (lat === null || lng === null) {
                            return null;
                        }
                        return { idx, lat, lng, label: s.label ?? "" };
                    })
                    .filter(Boolean);

                const meshTerritories = [...visibleTerritories]
                    .filter((t) => Number(t?.gap ?? 0) > 0)
                    .sort((a, b) => (b.pressao ?? 0) - (a.pressao ?? 0))
                    .slice(0, 12);

                const drawnEdges = new Set();

                for (const t of meshTerritories) {
                    const tlat = toFiniteNumber(t.lat);
                    const tlng = toFiniteNumber(t.lng);
                    if (tlat === null || tlng === null) {
                        continue;
                    }
                    const nearest = schoolPts
                        .map((p) => ({
                            ...p,
                            km: haversineKm(tlat, tlng, p.lat, p.lng),
                        }))
                        .filter((p) => p.km !== null && p.km > 0)
                        .sort((a, b) => a.km - b.km)
                        .slice(0, 3);

                    for (let i = 0; i < nearest.length; i++) {
                        for (let j = i + 1; j < nearest.length; j++) {
                            const a = nearest[i];
                            const b = nearest[j];
                            const edgeKey =
                                a.idx < b.idx
                                    ? `${a.idx}-${b.idx}`
                                    : `${b.idx}-${a.idx}`;
                            if (drawnEdges.has(edgeKey)) {
                                continue;
                            }
                            drawnEdges.add(edgeKey);
                            const km = haversineKm(a.lat, a.lng, b.lat, b.lng);
                            if (km === null) {
                                continue;
                            }
                            const meshLine = L.polyline(
                                [
                                    [a.lat, a.lng],
                                    [b.lat, b.lng],
                                ],
                                {
                                    color: "#6366f1",
                                    weight: 1.5,
                                    opacity: 0.45,
                                    dashArray: "3 6",
                                },
                            )
                                .bindTooltip(
                                    `${escapeHtml(a.label)} ↔ ${escapeHtml(b.label)}: ${formatKm(km)}`,
                                    { sticky: true },
                                );
                            bindDistanceTooltip(meshLine, formatKm(km));
                            meshLine.addTo(this.zoneMeshLayer);
                        }
                    }
                }
            }

            if (bounds.length > 0 && !this._boundsFitted) {
                this.map.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });
                this._boundsFitted = true;
            }
        },
    };
}
