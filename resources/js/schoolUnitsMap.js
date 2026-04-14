import L from "leaflet";
import "leaflet/dist/leaflet.css";

function toFiniteNumber(v) {
    const n = typeof v === "number" ? v : Number(String(v ?? "").trim());
    return Number.isFinite(n) ? n : null;
}

function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s ?? "");
    return d.innerHTML;
}

function safeExternalHref(url) {
    const s = String(url ?? "").trim();
    if (!s.startsWith("https://") && !s.startsWith("http://")) {
        return "#";
    }
    try {
        const u = new URL(s);
        if (u.protocol === "https:" || u.protocol === "http:") {
            return u.href;
        }
    } catch {
        /* ignore */
    }
    return "#";
}

function nf(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) {
        return "—";
    }
    return Number(n).toLocaleString("pt-BR");
}

/** Distância em metros (esfera), para divergência i-Educar × INEP ou grafo no mapa. */
function haversineMeters(lat1, lng1, lat2, lng2) {
    const a1 = Number(lat1);
    const o1 = Number(lng1);
    const a2 = Number(lat2);
    const o2 = Number(lng2);
    if (
        !Number.isFinite(a1) ||
        !Number.isFinite(o1) ||
        !Number.isFinite(a2) ||
        !Number.isFinite(o2) ||
        Math.abs(a1) > 90 ||
        Math.abs(a2) > 90 ||
        Math.abs(o1) > 180 ||
        Math.abs(o2) > 180
    ) {
        return null;
    }
    const R = 6371000;
    const p1 = (a1 * Math.PI) / 180;
    const p2 = (a2 * Math.PI) / 180;
    const dp = ((a2 - a1) * Math.PI) / 180;
    const dl = ((o2 - o1) * Math.PI) / 180;
    const s =
        Math.sin(dp / 2) ** 2 +
        Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
}

/**
 * Grafo leve: cada ponto liga-se aos k vizinhos mais próximos (arestas únicas).
 * @param {Array<{ lat: number, lng: number }>} pts
 * @returns {Array<[number, number]>}
 */
function nearestNeighborEdges(pts, kNeighbors) {
    const k = Math.max(1, Math.min(2, kNeighbors | 0));
    const n = pts.length;
    if (n < 2) {
        return [];
    }
    const edges = new Set();
    for (let i = 0; i < n; i++) {
        const dists = [];
        for (let j = 0; j < n; j++) {
            if (i === j) {
                continue;
            }
            const d = haversineMeters(
                pts[i].lat,
                pts[i].lng,
                pts[j].lat,
                pts[j].lng,
            );
            if (d != null && d > 0) {
                dists.push({ j, d });
            }
        }
        dists.sort((a, b) => a.d - b.d);
        for (let t = 0; t < Math.min(k, dists.length); t++) {
            const a = i;
            const b = dists[t].j;
            const key = a < b ? `${a}-${b}` : `${b}-${a}`;
            edges.add(key);
        }
    }
    return Array.from(edges).map((key) => {
        const parts = key.split("-").map(Number);
        return /** @type {[number, number]} */ ([parts[0], parts[1]]);
    });
}

function markerStrokeFill(mk) {
    const key = mk?.school?.status_key;
    if (key === "ativa") {
        return { color: "#15803d", fill: "#22c55e" };
    }
    if (key === "inativa") {
        return { color: "#64748b", fill: "#94a3b8" };
    }
    if (key === "unknown") {
        return { color: "#4f46e5", fill: "#a5b4fc" };
    }
    return { color: "#4f46e5", fill: "#818cf8" };
}

function buildConciliationHtml(c) {
    if (!c || typeof c !== "object") {
        return "";
    }
    const parts = [];
    if (
        c.catalogo_disponivel &&
        c.nome_local &&
        c.nome_catalogo &&
        c.nomes_coincidem === false
    ) {
        parts.push(
            `<p class="text-[11px] text-amber-900 dark:text-amber-100/95 leading-snug"><span class="font-semibold">${escapeHtml("Atenção:")}</span> ${escapeHtml("o nome na base local difere do nome no Catálogo INEP — confira antes de cruzar dados.")}</p>`,
        );
    }
    if (
        c.telefone_local &&
        c.telefone_catalogo &&
        c.telefones_coincidem === false
    ) {
        parts.push(
            `<p class="text-[11px] text-amber-900 dark:text-amber-100/95 leading-snug">${escapeHtml("Telefone local e do catálogo não coincidem textualmente.")}</p>`,
        );
    }
    if (
        c.endereco_local &&
        c.endereco_catalogo &&
        c.enderecos_coincidem === false
    ) {
        parts.push(
            `<p class="text-[11px] text-amber-900 dark:text-amber-100/95 leading-snug">${escapeHtml("Endereço local e do catálogo parecem diferentes — valide no campo.")}</p>`,
        );
    }
    if (parts.length === 0) {
        return "";
    }
    return `<div class="mb-2 rounded-md border border-amber-200/90 bg-amber-50/90 dark:border-amber-800/60 dark:bg-amber-950/40 px-2 py-1.5 space-y-1">${parts.join("")}</div>`;
}

function buildCatalogSection(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
        return "";
    }
    let block = `<div class="mt-2 border-t border-slate-200 dark:border-slate-600 pt-2">`;
    block += `<h5 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">${escapeHtml("Catálogo INEP (ArcGIS)")}</h5>`;
    block += `<dl class="mt-1.5 space-y-1 text-xs text-slate-800 dark:text-slate-200">`;
    for (const row of rows) {
        const lab = escapeHtml(row?.label ?? "");
        const val = escapeHtml(row?.value ?? "");
        block += `<div class="flex gap-2 justify-between"><dt class="text-slate-500 dark:text-slate-400 shrink-0 max-w-[40%]">${lab}</dt><dd class="text-right min-w-0 break-words">${val}</dd></div>`;
    }
    block += `</dl></div>`;
    return block;
}

function buildLinksSection(links) {
    if (!Array.isArray(links) || links.length === 0) {
        return "";
    }
    let block = `<div class="mt-2 flex flex-col gap-1.5">`;
    for (const ln of links) {
        const href = safeExternalHref(ln?.url);
        const label = escapeHtml(ln?.label ?? "Link");
        block += `<a href="${href}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-2.5 py-1.5 text-center text-[11px] font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">${label}</a>`;
    }
    block += `</div>`;
    return block;
}

function buildSchoolPopupHtml(mk, footnote) {
    const meta = escapeHtml(mk.meta || "");
    const s = mk.school;
    const conc = buildConciliationHtml(mk.conciliation);

    if (!s || typeof s !== "object") {
        const label = escapeHtml(mk.label || "—");
        let body = `<div class="text-sm font-medium">${label}</div>`;
        if (meta) {
            body += `<p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400 leading-snug">${meta}</p>`;
        }
        body += buildCatalogSection(mk.inep_catalog);
        body += buildLinksSection(mk.inep_links);
        if (footnote) {
            body += `<p class="mt-2 border-t border-gray-200 dark:border-gray-600 pt-2 text-[10px] text-gray-500 dark:text-gray-400 leading-snug">${escapeHtml(footnote)}</p>`;
        }
        return body;
    }

    const nome = escapeHtml(s.nome || mk.label || "—");
    const status = escapeHtml(s.status_label || "");
    const inep =
        s.inep != null && s.inep !== ""
            ? escapeHtml(String(s.inep))
            : "—";
    const mat = nf(s.matriculas);
    const cap =
        s.capacidade_declarada != null ? nf(s.capacidade_declarada) : "—";
    const vag =
        s.vagas_disponiveis != null ? nf(s.vagas_disponiveis) : "—";
    const tel = s.telefone ? escapeHtml(s.telefone) : "—";
    const em = s.email ? escapeHtml(s.email) : "—";
    const gest = s.gestor ? escapeHtml(s.gestor) : "—";
    const end = s.endereco ? escapeHtml(s.endereco) : "—";

    const localRows = [
        ["INEP", inep],
        ["Matrículas (filtros)", mat],
        ["Capacidade (turmas)", cap],
        ["Vagas disponíveis", vag],
        ["Telefone (base)", tel],
        ["E-mail", em],
        ["Gestor", gest],
        ["Endereço (base)", end],
    ];

    let body = `<div class="text-sm font-semibold text-gray-900 dark:text-gray-100">${nome}</div>`;
    if (status) {
        body += `<p class="mt-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">${status}</p>`;
    }
    body += conc;
    body += `<h5 class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">${escapeHtml("Base local (i-Educar)")}</h5>`;
    body += `<dl class="mt-1 space-y-1 text-xs text-gray-800 dark:text-gray-200">`;
    for (const [k, v] of localRows) {
        body += `<div class="flex gap-2 justify-between"><dt class="text-gray-500 dark:text-gray-400 shrink-0">${escapeHtml(k)}</dt><dd class="text-right min-w-0 break-words">${v}</dd></div>`;
    }
    body += `</dl>`;

    body += buildCatalogSection(mk.inep_catalog);
    body += buildLinksSection(mk.inep_links);

    if (meta) {
        body += `<p class="mt-2 border-t border-gray-200 dark:border-gray-600 pt-2 text-[11px] text-gray-500 dark:text-gray-400 leading-snug">${meta}</p>`;
    }
    if (footnote) {
        body += `<p class="mt-2 text-[10px] text-gray-500 dark:text-gray-400 leading-snug">${escapeHtml(footnote)}</p>`;
    }
    return body;
}

function buildSchoolModalPayload(mk, qeduBaseFallback) {
    const s = mk?.school && typeof mk.school === "object" ? mk.school : null;
    const nome = String(s?.nome || mk?.label || "—");
    const status = String(s?.status_label || "");
    const inep = s?.inep != null && s?.inep !== "" ? String(s.inep) : "";

    const qeduLink = Array.isArray(mk?.inep_links)
        ? mk.inep_links.find((ln) => ln && ln.id === "qedu")
        : null;
    const pageUrl = qeduLink?.url ? safeExternalHref(qeduLink.url) : "";
    const baseCfg = String(
        mk?.qedu_escola_base_url || qeduBaseFallback || "",
    ).replace(/\/$/, "");

    return {
        title: nome,
        status,
        fonte_coordenada: String(mk?.fonte_coordenada || ""),
        fonte_coordenada_label: String(mk?.fonte_coordenada_label || ""),
        geo_divergence:
            mk?.geo_divergence && typeof mk.geo_divergence === "object"
                ? { ...mk.geo_divergence }
                : null,
        meta: String(mk?.meta || ""),
        inep,
        contato: {
            telefone: String(s?.telefone || ""),
            email: String(s?.email || ""),
            gestor: String(s?.gestor || ""),
        },
        base: {
            matriculas: s?.matriculas ?? null,
            capacidade_declarada: s?.capacidade_declarada ?? null,
            vagas_disponiveis: s?.vagas_disponiveis ?? null,
            endereco: String(s?.endereco || ""),
        },
        oferta: Array.isArray(s?.oferta_curso_serie) ? s.oferta_curso_serie : [],
        qedu: {
            base_url: baseCfg,
            page_url: pageUrl,
        },
        conciliation: mk?.conciliation ?? null,
        inep_catalog: Array.isArray(mk?.inep_catalog) ? mk.inep_catalog : [],
        inep_links: Array.isArray(mk?.inep_links) ? mk.inep_links : [],
    };
}

/**
 * Mapa OSM (Leaflet) para unidades escolares com coordenadas.
 * @param {unknown} markersInput
 * @param {unknown} footnoteInput
 * @param {unknown} optionsInput
 */
export default function createSchoolUnitsMap(
    markersInput,
    footnoteInput,
    optionsInput,
) {
    const markers = Array.isArray(markersInput) ? markersInput : [];
    const footnote =
        typeof footnoteInput === "string" && footnoteInput.trim() !== ""
            ? footnoteInput
            : null;
    const options =
        optionsInput && typeof optionsInput === "object" ? optionsInput : {};
    const mode = String(options.mode || "default");
    const qeduEscolaBaseUrl =
        typeof options.qeduEscolaBaseUrl === "string"
            ? options.qeduEscolaBaseUrl.trim()
            : "";

    return {
        markers,
        footnote,
        mode,
        qeduEscolaBaseUrl,
        map: null,
        group: null,
        graphLayer: null,
        booted: false,
        modalOpen: false,
        modal: null,
        _onTab: null,
        _bootAttempts: 0,

        init() {
            this._onTab = (e) => {
                if (e?.detail?.tab === "school_units") {
                    // Painel estava display:none (outro tab): offsetWidth=0 esgotava retries; ao voltar ao tab, re-tentar.
                    this._bootAttempts = 0;
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
            if (this.graphLayer) {
                this.graphLayer.remove();
                this.graphLayer = null;
            }
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },

        openSchoolModal(mk) {
            this.modal = buildSchoolModalPayload(mk, this.qeduEscolaBaseUrl);
            const gd = this.modal?.geo_divergence;
            if (
                gd &&
                (gd.meters == null || gd.meters === "") &&
                gd.ieducar_lat != null &&
                gd.ieducar_lng != null &&
                gd.official_lat != null &&
                gd.official_lng != null
            ) {
                const m = haversineMeters(
                    gd.ieducar_lat,
                    gd.ieducar_lng,
                    gd.official_lat,
                    gd.official_lng,
                );
                if (m != null) {
                    gd.meters = m;
                }
            }
            this.modalOpen = true;
        },

        closeSchoolModal() {
            this.modalOpen = false;
        },

        tryBoot() {
            if (this.booted || this.markers.length === 0) {
                return;
            }
            const el = this.$refs.mapContainer;
            if (!el) {
                return;
            }
            if (el.offsetWidth < 20) {
                if (this._bootAttempts < 12) {
                    this._bootAttempts += 1;
                    setTimeout(
                        () => this.tryBoot(),
                        120 * this._bootAttempts,
                    );
                }
                return;
            }
            this._bootAttempts = 0;

            const latlngs = this.markers
                .filter(
                    (m) =>
                        toFiniteNumber(m.lat) !== null &&
                        toFiniteNumber(m.lng) !== null &&
                        Math.abs(toFiniteNumber(m.lat)) <= 90 &&
                        Math.abs(toFiniteNumber(m.lng)) <= 180,
                )
                .map((m) => [toFiniteNumber(m.lat), toFiniteNumber(m.lng)]);
            const graphPts = this.markers
                .map((m) => {
                    const la = toFiniteNumber(m.lat);
                    const ln = toFiniteNumber(m.lng);
                    if (
                        la === null ||
                        ln === null ||
                        Math.abs(la) > 90 ||
                        Math.abs(ln) > 180
                    ) {
                        return null;
                    }
                    return { lat: la, lng: ln };
                })
                .filter(Boolean);

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

            this.graphLayer = L.layerGroup().addTo(this.map);
            this.group = L.layerGroup().addTo(this.map);

            const maxGraphUnits = 50;
            if (
                graphPts.length >= 2 &&
                graphPts.length <= maxGraphUnits &&
                this.graphLayer
            ) {
                const k = graphPts.length > 28 ? 1 : 2;
                const ptsSimple = graphPts.map((p) => ({
                    lat: p.lat,
                    lng: p.lng,
                }));
                for (const [ia, ib] of nearestNeighborEdges(ptsSimple, k)) {
                    const a = graphPts[ia];
                    const b = graphPts[ib];
                    L.polyline(
                        [
                            [a.lat, a.lng],
                            [b.lat, b.lng],
                        ],
                        {
                            color: "#94a3b8",
                            weight: 1,
                            opacity: 0.3,
                            dashArray: "4 7",
                            interactive: false,
                        },
                    ).addTo(this.graphLayer);
                }
            }

            // “Cobertura”: raio ~ proporcional às matrículas no filtro (quando existir).
            let maxMat = 0;
            if (this.mode === "coverage") {
                for (const mk of this.markers) {
                    const m = mk?.school?.matriculas;
                    const v = toFiniteNumber(m);
                    if (v !== null && v > maxMat) {
                        maxMat = v;
                    }
                }
            }

            this.markers.forEach((mk) => {
                const lat = toFiniteNumber(mk.lat);
                const lng = toFiniteNumber(mk.lng);
                if (
                    lat === null ||
                    lng === null ||
                    Math.abs(lat) > 90 ||
                    Math.abs(lng) > 180
                ) {
                    return;
                }
                const { color, fill } = markerStrokeFill(mk);
                const baseRadius = 8;
                let radius = baseRadius;
                if (this.mode === "coverage") {
                    const v = toFiniteNumber(mk?.school?.matriculas);
                    if (v !== null && v > 0 && maxMat > 0) {
                        // 6..18
                        radius = Math.round(6 + 12 * Math.sqrt(v / maxMat));
                    } else {
                        radius = 6;
                    }
                }
                L.circleMarker([lat, lng], {
                    radius,
                    color,
                    weight: 2,
                    fillColor: fill,
                    fillOpacity: this.mode === "coverage" ? 0.65 : 0.92,
                })
                    .on("click", () => this.openSchoolModal(mk))
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
