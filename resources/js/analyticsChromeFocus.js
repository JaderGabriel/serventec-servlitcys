/**
 * Modo foco (mobile): esconde cabeçalho sticky e dock de filtros
 * para libertar a área central da consultoria.
 */
const STORAGE_KEY = "serv.analytics.chromeFocus";
const ROOT_CLASS = "serv-analytics-chrome-focus";
const MOBILE_MQ = "(max-width: 1023px)";

function isMobileViewport() {
    return window.matchMedia(MOBILE_MQ).matches;
}

function readStored() {
    try {
        return window.sessionStorage.getItem(STORAGE_KEY) === "1";
    } catch {
        return false;
    }
}

function writeStored(on) {
    try {
        if (on) {
            window.sessionStorage.setItem(STORAGE_KEY, "1");
        } else {
            window.sessionStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        /* ignore quota / private mode */
    }
}

function applyDockHeightForFocus(on) {
    const root = document.documentElement;
    if (on && isMobileViewport()) {
        root.style.setProperty("--serv-analytics-dock-height", "0px");
        return;
    }

    const dock = document.querySelector(".serv-analytics-filter-dock");
    if (dock instanceof HTMLElement) {
        const height = Math.ceil(dock.getBoundingClientRect().height);
        if (height > 0) {
            root.style.setProperty("--serv-analytics-dock-height", `${height}px`);
        }
    }
}

function syncToggleUi(on) {
    document.querySelectorAll("[data-analytics-chrome-toggle]").forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }
        el.setAttribute("aria-pressed", on ? "true" : "false");
        const showLabel = el.getAttribute("data-label-show");
        const hideLabel = el.getAttribute("data-label-hide");
        if (showLabel && hideLabel) {
            el.setAttribute("title", on ? showLabel : hideLabel);
            el.setAttribute("aria-label", on ? showLabel : hideLabel);
            const text = el.querySelector("[data-analytics-chrome-label]");
            if (text) {
                text.textContent = on ? showLabel : hideLabel;
            }
        }
    });

    const restore = document.getElementById("analytics-chrome-restore");
    if (restore instanceof HTMLElement) {
        const visible = on && isMobileViewport();
        restore.hidden = !visible;
        restore.setAttribute("aria-hidden", visible ? "false" : "true");
    }
}

export function setAnalyticsChromeFocus(on) {
    const root = document.documentElement;
    const active = Boolean(on) && isMobileViewport();

    root.classList.toggle(ROOT_CLASS, active);
    writeStored(Boolean(on) && isMobileViewport());
    applyDockHeightForFocus(active);
    syncToggleUi(active);

    document.dispatchEvent(
        new CustomEvent("analytics-chrome-focus-changed", {
            detail: { focus: active },
        }),
    );
}

export function toggleAnalyticsChromeFocus() {
    const currently = document.documentElement.classList.contains(ROOT_CLASS);
    setAnalyticsChromeFocus(!currently);
}

function ensureRestoreFab() {
    if (document.getElementById("analytics-chrome-restore")) {
        return;
    }

    const template = document.getElementById("analytics-chrome-restore-template");
    if (template instanceof HTMLTemplateElement && template.content.firstElementChild) {
        const node = template.content.firstElementChild.cloneNode(true);
        if (node instanceof HTMLElement) {
            node.id = "analytics-chrome-restore";
            node.hidden = true;
            node.setAttribute("aria-hidden", "true");
            node.addEventListener("click", () => setAnalyticsChromeFocus(false));
            document.body.appendChild(node);
        }
        return;
    }

    const btn = document.createElement("button");
    btn.type = "button";
    btn.id = "analytics-chrome-restore";
    btn.className = "serv-analytics-chrome-restore";
    btn.hidden = true;
    btn.setAttribute("aria-hidden", "true");
    btn.setAttribute("data-analytics-chrome-toggle", "restore");
    btn.setAttribute("aria-label", "Mostrar menus");
    btn.title = "Mostrar menus";
    btn.textContent = "Mostrar menus";
    btn.addEventListener("click", () => setAnalyticsChromeFocus(false));
    document.body.appendChild(btn);
}

export function initAnalyticsChromeFocus() {
    if (!document.querySelector(".serv-analytics-filter-dock")) {
        return;
    }

    ensureRestoreFab();

    document.querySelectorAll("[data-analytics-chrome-toggle]").forEach((el) => {
        if (!(el instanceof HTMLElement) || el.id === "analytics-chrome-restore") {
            return;
        }
        if (el.dataset.chromeBound === "1") {
            return;
        }
        el.dataset.chromeBound = "1";
        el.addEventListener("click", (event) => {
            event.preventDefault();
            toggleAnalyticsChromeFocus();
        });
    });

    const mq = window.matchMedia(MOBILE_MQ);
    const onMq = () => {
        if (!mq.matches) {
            document.documentElement.classList.remove(ROOT_CLASS);
            applyDockHeightForFocus(false);
            syncToggleUi(false);
            return;
        }
        if (readStored()) {
            setAnalyticsChromeFocus(true);
        } else {
            syncToggleUi(false);
        }
    };

    if (typeof mq.addEventListener === "function") {
        mq.addEventListener("change", onMq);
    } else if (typeof mq.addListener === "function") {
        mq.addListener(onMq);
    }

    onMq();
}
