/**
 * Reserva espaço no rodapé e no scroll para o dock fixo de filtros do analytics.
 */
export function initAnalyticsFilterDockLayout() {
    const dock = document.querySelector(".serv-analytics-filter-dock");
    if (!(dock instanceof HTMLElement)) {
        return;
    }

    const root = document.documentElement;
    let frame = null;

    const apply = () => {
        const height = Math.ceil(dock.getBoundingClientRect().height);
        if (height > 0) {
            root.style.setProperty("--serv-analytics-dock-height", `${height}px`);
        }
    };

    const schedule = () => {
        if (frame !== null) {
            cancelAnimationFrame(frame);
        }
        frame = requestAnimationFrame(() => {
            frame = null;
            apply();
        });
    };

    schedule();

    if (typeof ResizeObserver !== "undefined") {
        const observer = new ResizeObserver(schedule);
        observer.observe(dock);
    }

    window.addEventListener("resize", schedule);
    document.addEventListener("analytics-tab-changed", schedule);
}
