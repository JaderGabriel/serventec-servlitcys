/**
 * Melhorias visuais partilhadas: zoom/pan ([chartjs-plugin-zoom](https://www.chartjs.org/chartjs-plugin-zoom/)),
 * linha de referência da média ([chartjs-plugin-annotation](https://www.chartjs.org/chartjs-plugin-annotation/3.1.0/)).
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

function averageOfFirstDataset(payload) {
    const ds0 = payload?.datasets?.[0];
    const data = ds0?.data;
    if (!Array.isArray(data) || data.length === 0) {
        return null;
    }
    const nums = data.map((v) => Number(v)).filter((n) => Number.isFinite(n));
    if (nums.length < 2) {
        return null;
    }
    const sum = nums.reduce((a, b) => a + b, 0);
    return sum / nums.length;
}

/**
 * Linha tracejada na média do 1.º dataset (um dataset apenas).
 * Barras verticais: eixo Y. Barras horizontais (indexAxis 'y'): eixo X.
 */
export function buildAverageLineAnnotations(payload, extra) {
    if (extra?.plugins?.annotation?.disableAutoAverage === true) {
        return {};
    }
    if (!payload?.datasets || payload.datasets.length !== 1) {
        return {};
    }
    const avg = averageOfFirstDataset(payload);
    if (avg === null) {
        return {};
    }

    const isDark =
        typeof document !== "undefined" &&
        document.documentElement.classList.contains("dark");
    const lineCol = isDark
        ? "rgba(129, 140, 248, 0.85)"
        : "rgba(79, 70, 229, 0.75)";
    const labelBg = isDark
        ? "rgba(55, 48, 163, 0.92)"
        : "rgba(79, 70, 229, 0.9)";
    const labelText = isDark ? "#e0e7ff" : "#ffffff";

    const horizontal = extra?.indexAxis === "y";
    const scaleID = horizontal ? "x" : "y";
    const rounded =
        Math.abs(avg) >= 1000
            ? Math.round(avg).toLocaleString("pt-PT")
            : avg >= 10
              ? avg.toFixed(1)
              : avg.toFixed(2);

    return {
        servlitcysAvgLine: {
            type: "line",
            scaleID,
            value: avg,
            borderColor: lineCol,
            borderWidth: 2,
            borderDash: [6, 4],
            label: {
                display: true,
                content: `Média: ${rounded}`,
                position: horizontal ? "50%" : "end",
                backgroundColor: labelBg,
                color: labelText,
                font: { size: 10, weight: "600" },
                padding: 4,
                borderRadius: 4,
            },
        },
    };
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
 * Junta plugins padrão (zoom + média) com o que vier do servidor.
 */
export function mergeAnnotationAndZoomPlugins(mergedPlugins, payload, extra) {
    const type = payload?.type;
    const cartesian =
        type === "bar" || type === "line" || type === "scatter";

    if (!cartesian) {
        return mergedPlugins;
    }

    const userAnn = mergedPlugins.annotation;
    const autoAvg = buildAverageLineAnnotations(payload, extra);
    const mergedAnnotations = {
        ...(userAnn?.annotations &&
        typeof userAnn.annotations === "object"
            ? userAnn.annotations
            : {}),
        ...autoAvg,
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
