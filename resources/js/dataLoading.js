/**
 * Overlay global de carregamento: bloqueia cliques, mostra mensagem e barra de progresso.
 */

let progressTimer = null;

const LOADING_PRESETS = {
    filter: {
        title: "Aplicando filtros",
        message:
            "Carregando listagens e indicadores do recorte selecionado. Aguarde…",
    },
    navigate: {
        title: "Carregando página",
        message: "Preparando o conteúdo solicitado. Aguarde…",
    },
    horizonte: {
        title: "Carregando Horizonte",
        message: "Abrindo o mapa de oportunidade municipal. Aguarde…",
    },
    horizonteData: {
        title: "Montando mapa Horizonte",
        message:
            "Consultando dados públicos e posicionando municípios no mapa. Pode demorar alguns segundos.",
    },
    sync: {
        title: "Enfileirando tarefa",
        message:
            "Registrando o pedido; a página será atualizada em seguida. O processamento pesado roda na fila de sincronização.",
    },
    diagnostic: {
        title: "Executando diagnóstico",
        message:
            "Consultando a base i-Educar e montando o relatório de diagnóstico. Pode demorar alguns minutos.",
    },
    save: {
        title: "Salvando alterações",
        message: "Validando e gravando os dados. Aguarde…",
    },
    auth: {
        title: "Autenticando",
        message: "Verificando suas credenciais. Aguarde…",
    },
    prepare: {
        title: "Preparando filtros",
        message:
            "Carregando anos letivos, escolas, cursos e turnos na base do município…",
    },
    clio: {
        title: "Processando Clio",
        message: "Atualizando a coleta Educacenso. Aguarde…",
    },
    default: {
        title: "Processando pedido",
        message: "Aguarde enquanto a operação é concluída.",
    },
};

function clampProgress(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return 0;
    }

    return Math.max(0, Math.min(100, n));
}

function clearProgressTimer() {
    if (progressTimer !== null) {
        window.clearInterval(progressTimer);
        progressTimer = null;
    }
}

function startProgressSimulation(store) {
    clearProgressTimer();
    store.progress = 8;
    progressTimer = window.setInterval(() => {
        if (!store.active) {
            clearProgressTimer();

            return;
        }
        const current = store.progress ?? 0;
        if (current >= 92) {
            return;
        }
        const step = current < 40 ? 6 : current < 70 ? 3 : 1.5;
        store.progress = Math.min(92, current + step);
    }, 280);
}

function lockBody(active) {
    document.documentElement.classList.toggle("serv-data-loading-lock", active);
    document.body.classList.toggle("serv-data-loading-lock", active);
}

function presetCopy(presetKey) {
    const key = String(presetKey ?? "default");
    return LOADING_PRESETS[key] ?? LOADING_PRESETS.default;
}

/**
 * @param {HTMLFormElement} form
 */
function formActionPath(form) {
    const raw = form.getAttribute("action") || window.location.pathname;

    try {
        return new URL(raw, window.location.origin).pathname;
    } catch {
        return raw;
    }
}

/**
 * @param {HTMLFormElement} form
 * @returns {string|null}
 */
function inferLoadingPreset(form) {
    if (form.dataset.servLoadingSkip === "1") {
        return null;
    }

    const path = formActionPath(form);
    const method = (form.method || "get").toLowerCase();

    if (/\/destroy|update-status|sessions\/.*\/destroy|logout/i.test(path)) {
        return null;
    }

    const writeMethod = ["post", "put", "patch"].includes(method);

    if (writeMethod) {
        if (
            /\/admin\/(geo-sync|pedagogical-sync|dados-publicos|horizonte\/abastecimento)(\/|$)|fundeb-sync|fundeb-import|sync-queue\/.*\/resume/i.test(
                path,
            )
        ) {
            return "sync";
        }
        if (/\/dashboard\/analytics\/pdf-export/i.test(path)) {
            return "default";
        }
        if (
            /\/login|forgot-password|reset-password|confirm-password|email\/verification-notification/i.test(
                path,
            )
        ) {
            return "auth";
        }
        if (
            /\/cities|\/users|\/profile|first-access|\/settings\/mail|password(\/|$)/i.test(
                path,
            )
        ) {
            return "save";
        }

        return null;
    }

    if (method === "get") {
        if (/\/admin\/analytics-diagnostics/i.test(path)) {
            return "diagnostic";
        }
        if (
            /\/admin\/(conexoes|sync-queue|ieducar-compatibility|geo-sync|pedagogical-sync|dados-publicos|horizonte\/abastecimento)/i.test(
                path,
            )
        ) {
            return "filter";
        }
        if (/^\/cities\/?$/.test(path) && form.querySelector("[name]")) {
            return "filter";
        }
        if (/^\/users\/?$/.test(path) && form.querySelector("[name]")) {
            return "filter";
        }
        if (/\/dashboard\/analytics/i.test(path)) {
            return "navigate";
        }
        if (/\/dashboard\/rx/i.test(path)) {
            return "navigate";
        }
        if (/\/clio\//i.test(path)) {
            return "clio";
        }
    }

    return null;
}

/**
 * @param {HTMLFormElement} form
 */
function resolveLoadingCopy(form) {
    const presetKey =
        form.dataset.servLoadingPreset ||
        inferLoadingPreset(form) ||
        (form.hasAttribute("data-serv-loading-on-submit") ? "default" : null);

    if (!presetKey) {
        return null;
    }

    const preset = presetCopy(presetKey);

    return {
        title: form.dataset.servLoadingTitle ?? preset.title,
        message: form.dataset.servLoadingMessage ?? preset.message,
    };
}

/**
 * @param {HTMLFormElement} form
 */
function bindLoadingOnSubmit(form) {
    if (form.dataset.servLoadingBound === "1") {
        return;
    }

    const copy = resolveLoadingCopy(form);
    if (!copy) {
        return;
    }

    form.dataset.servLoadingBound = "1";
    form.addEventListener("submit", () => {
        servDataLoadingStart(copy.title, copy.message);
    });
}

export function registerDataLoadingStore(Alpine) {
    Alpine.store("dataLoading", {
        active: false,
        title: "",
        message: "",
        progress: null,
        start(title = "", message = "") {
            this.active = true;
            this.title = String(title ?? "");
            this.message = String(message ?? "");
            this.progress = 0;
            lockBody(true);
            startProgressSimulation(this);
        },
        setProgress(value) {
            if (!this.active) {
                return;
            }
            this.progress = clampProgress(value);
        },
        setMessage(message) {
            this.message = String(message ?? "");
        },
        finish() {
            if (!this.active) {
                return;
            }
            clearProgressTimer();
            this.progress = 100;
            window.setTimeout(() => this.reset(), 350);
        },
        reset() {
            clearProgressTimer();
            this.active = false;
            this.title = "";
            this.message = "";
            this.progress = null;
            lockBody(false);
        },
    });
}

export function servDataLoadingStart(title, message) {
    const store = window.Alpine?.store?.("dataLoading");
    if (store) {
        store.start(title, message);

        return;
    }
    lockBody(true);
}

export function servDataLoadingFinish() {
    const store = window.Alpine?.store?.("dataLoading");
    if (store) {
        store.finish();

        return;
    }
    lockBody(false);
}

export function servDataLoadingReset() {
    const store = window.Alpine?.store?.("dataLoading");
    if (store) {
        store.reset();

        return;
    }
    lockBody(false);
}

export function initDataLoadingForms(root = document) {
    root.querySelectorAll("form").forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        bindLoadingOnSubmit(form);
    });
}

const NAV_LINK_LOADING = [
    { pattern: /^\/dashboard\/horizonte\/?$/, preset: "horizonte" },
    { pattern: /^\/dashboard\/rx\/?$/, preset: "navigate" },
    { pattern: /^\/dashboard\/analytics\/?$/, preset: "navigate" },
    { pattern: /^\/clio(\/|$)/, preset: "clio" },
];

function linkPathname(link) {
    try {
        return new URL(link.href, window.location.origin).pathname;
    } catch {
        return null;
    }
}

/**
 * @param {string|null} disposition
 * @param {string} fallback
 */
function filenameFromContentDisposition(disposition, fallback) {
    if (!disposition) {
        return fallback;
    }

    const utf8 = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(disposition);
    if (utf8?.[1]) {
        try {
            return decodeURIComponent(utf8[1].trim().replace(/^"+|"+$/g, ""));
        } catch {
            /* keep fallback */
        }
    }

    const plain = /filename\s*=\s*"((?:\\.|[^"])*)"|filename\s*=\s*([^;]+)/i.exec(
        disposition,
    );
    if (plain) {
        const raw = (plain[1] ?? plain[2] ?? "").trim().replace(/^"+|"+$/g, "");
        if (raw !== "") {
            return raw;
        }
    }

    return fallback;
}

/**
 * @param {string} pathname
 */
function isAttachmentExportPath(pathname) {
    return /\/export\/(pdf|xlsx|csv)(\/|$)/i.test(pathname);
}

/**
 * Download via fetch: o browser não navega, então o overlay precisa fechar no finally.
 *
 * @param {HTMLAnchorElement} link
 * @param {string} title
 * @param {string} message
 */
async function downloadHrefWithLoading(link, title, message) {
    servDataLoadingStart(title, message);
    let keepLoadingForNavigation = false;

    try {
        const response = await fetch(link.href, {
            credentials: "same-origin",
            headers: {
                Accept: "*/*",
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        const contentType = (response.headers.get("Content-Type") || "").toLowerCase();
        if (!response.ok || contentType.includes("text/html")) {
            keepLoadingForNavigation = true;
            window.location.assign(link.href);

            return;
        }

        const blob = await response.blob();
        const pathname = linkPathname(link) || "download";
        const extMatch = /\.(pdf|xlsx|csv)$/i.exec(pathname);
        const fallback =
            (extMatch ? `download.${extMatch[1].toLowerCase()}` : null) ||
            (contentType.includes("pdf")
                ? "download.pdf"
                : contentType.includes("sheet") || contentType.includes("excel")
                  ? "download.xlsx"
                  : "download.bin");
        const filename = filenameFromContentDisposition(
            response.headers.get("Content-Disposition"),
            fallback,
        );

        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement("a");
        anchor.href = objectUrl;
        anchor.download = filename;
        anchor.rel = "noopener";
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 2_000);
    } catch (error) {
        console.error("serv download", error);
        keepLoadingForNavigation = true;
        window.location.assign(link.href);
    } finally {
        if (!keepLoadingForNavigation) {
            servDataLoadingFinish();
        }
    }
}

/**
 * @param {HTMLAnchorElement} link
 */
function bindLoadingOnLink(link) {
    if (link.dataset.servLoadingBound === "1") {
        return;
    }

    if (link.target === "_blank") {
        return;
    }

    const href = linkPathname(link);
    if (!href) {
        return;
    }

    const explicit = link.hasAttribute("data-serv-loading-on-click");
    const forceDownload =
        link.hasAttribute("data-serv-loading-download") ||
        (explicit && isAttachmentExportPath(href));
    const match = NAV_LINK_LOADING.find(({ pattern }) => pattern.test(href));

    if (link.hasAttribute("download") && !forceDownload && !explicit) {
        return;
    }

    if (!explicit && !match && !forceDownload) {
        return;
    }

    if (!explicit && !forceDownload && href === window.location.pathname) {
        return;
    }

    link.dataset.servLoadingBound = "1";
    link.addEventListener("click", (event) => {
        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        const presetKey = explicit || forceDownload
            ? (link.dataset.servLoadingPreset || "clio")
            : match.preset;
        const preset = presetCopy(presetKey);
        const title = link.dataset.servLoadingTitle ?? preset.title;
        const message = link.dataset.servLoadingMessage ?? preset.message;

        if (forceDownload || link.hasAttribute("download")) {
            event.preventDefault();
            void downloadHrefWithLoading(link, title, message);

            return;
        }

        servDataLoadingStart(title, message);
    });
}

export function initDataLoadingLinks(root = document) {
    root.querySelectorAll("a[href]").forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }
        bindLoadingOnLink(link);
    });
}

let pageshowBound = false;

export function initDataLoadingPageshow() {
    if (pageshowBound) {
        return;
    }
    pageshowBound = true;
    window.addEventListener("pageshow", () => {
        servDataLoadingFinish();
    });
}

/**
 * Submete o formulário disparando o evento `submit` (para o overlay global).
 * `HTMLFormElement.submit()` não dispara `submit` — evitar em selects com onchange.
 *
 * @param {HTMLFormElement|null|undefined} form
 */
export function servFormRequestSubmit(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (typeof form.requestSubmit === "function") {
        form.requestSubmit();

        return;
    }

    const copy = resolveLoadingCopy(form);
    if (copy) {
        servDataLoadingStart(copy.title, copy.message);
    }

    HTMLFormElement.prototype.submit.call(form);
}

window.servDataLoading = {
    start: servDataLoadingStart,
    finish: servDataLoadingFinish,
    reset: servDataLoadingReset,
    requestSubmit: servFormRequestSubmit,
    presets: LOADING_PRESETS,
};
