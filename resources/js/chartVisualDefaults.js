/**
 * Zoom/pan ([chartjs-plugin-zoom](https://www.chartjs.org/chartjs-plugin-zoom/));
 * anotações manuais via payload em options.plugins.annotation (sem linha de média automática).
 */
import annotationPlugin from "chartjs-plugin-annotation";
import zoomPlugin from "chartjs-plugin-zoom";

/**
 * Registo global dos plugins (chamar uma vez na entrada da app).
 *
 * @param {import("chart.js").Chart} ChartConstructor
 */
export function registerChartVisualPlugins(ChartConstructor) {
    ChartConstructor.register(annotationPlugin, zoomPlugin);
}

/**
 * Zoom com roda apenas com Ctrl (evita capturar scroll da página); pinça no telemóvel.
 */
export function defaultZoomPluginOptions() {
    return {
        pan: {
            enabled: true,
            mode: "xy",
        },
        zoom: {
            wheel: {
                enabled: true,
                modifierKey: "ctrl",
            },
            pinch: {
                enabled: true,
            },
            mode: "xy",
        },
        limits: {
            x: { min: "original", max: "original" },
            y: { min: "original", max: "original" },
        },
    };
}

function shallowMergeZoom(base, user) {
    if (!user || typeof user !== "object") {
        return base;
    }
    return {
        ...base,
        ...user,
        pan: { ...base.pan, ...user.pan },
        zoom: {
            ...base.zoom,
            ...user.zoom,
            wheel: { ...base.zoom.wheel, ...user.zoom?.wheel },
            pinch: { ...base.zoom.pinch, ...user.zoom?.pinch },
        },
        limits: {
            x: { ...base.limits.x, ...user.limits?.x },
            y: { ...base.limits.y, ...user.limits?.y },
        },
    };
}

/**
 * Junta zoom e anotações opcionais do servidor (sem média automática).
 */
export function mergeAnnotationAndZoomPlugins(mergedPlugins, payload, _extra) {
    const type = payload?.type;
    const cartesian =
        type === "bar" || type === "line" || type === "scatter";

    if (!cartesian) {
        return mergedPlugins;
    }

    const userAnn = mergedPlugins.annotation;
    const mergedAnnotations = {
        ...(userAnn?.annotations &&
        typeof userAnn.annotations === "object"
            ? userAnn.annotations
            : {}),
    };

    mergedPlugins.annotation = {
        ...(typeof userAnn === "object" ? userAnn : {}),
        annotations: mergedAnnotations,
    };

    const z0 = defaultZoomPluginOptions();
    mergedPlugins.zoom = shallowMergeZoom(z0, mergedPlugins.zoom);

    return mergedPlugins;
}

/**
 * Opções de interação / aspecto para gráficos cartesianos.
 */
export function cartesianInteractionDefaults() {
    return {
        interaction: {
            mode: "index",
            intersect: false,
        },
        hover: {
            mode: "index",
            intersect: false,
        },
    };
}

/** Velocidade alinhada à roda do plugin (chartjs-plugin-zoom, wheel.speed ≈ 0.1). */
const ZOOM_BUTTON_STEP = 0.12;

/**
 * Ponto focal ao centro da área do gráfico (zoom programático).
 *
 * @param {import("chart.js").Chart} chart
 * @returns {{ x: number, y: number }}
 */
export function chartZoomFocalPoint(chart) {
    const ca = chart?.chartArea;
    if (ca && ca.width > 0 && ca.height > 0) {
        return {
            x: ca.left + ca.width / 2,
            y: ca.top + ca.height / 2,
        };
    }
    const w = chart?.width ?? 0;
    const h = chart?.height ?? 0;

    return { x: w / 2 || 200, y: h / 2 || 150 };
}

/**
 * @param {import("chart.js").Chart} chart
 */
export function chartZoomInButton(chart) {
    if (typeof chart?.zoom !== "function") {
        return;
    }
    const speed = ZOOM_BUTTON_STEP;
    const factor = 1 + speed;
    chart.zoom({
        x: factor,
        y: factor,
        focalPoint: chartZoomFocalPoint(chart),
    });
}

/**
 * @param {import("chart.js").Chart} chart
 */
export function chartZoomOutButton(chart) {
    if (typeof chart?.zoom !== "function") {
        return;
    }
    const speed = ZOOM_BUTTON_STEP;
    const factor = 2 - 1 / (1 - speed);
    chart.zoom({
        x: factor,
        y: factor,
        focalPoint: chartZoomFocalPoint(chart),
    });
}

/**
 * @param {import("chart.js").Chart} chart
 */
export function chartResetZoomView(chart) {
    if (typeof chart?.resetZoom !== "function") {
        return;
    }
    chart.resetZoom();
}
