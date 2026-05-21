/**
 * Tema claro/escuro via classe `dark` em <html> e localStorage.theme (light | dark | system).
 * Compatível com Laravel Pulse (`setDarkClass`).
 */
export function applyTheme() {
    const stored = localStorage.getItem("theme");
    const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    const useDark =
        stored === "dark" ||
        (stored !== "light" && prefersDark);

    document.documentElement.classList.toggle("dark", useDark);
    document.documentElement.style.colorScheme = useDark ? "dark" : "light";
}

if (typeof window !== "undefined") {
    window.setDarkClass = applyTheme;
    applyTheme();
    window
        .matchMedia("(prefers-color-scheme: dark)")
        .addEventListener("change", () => {
            if (!["light", "dark"].includes(localStorage.getItem("theme") ?? "")) {
                applyTheme();
                window.dispatchEvent(new CustomEvent("serv:theme-changed"));
            }
        });
}

export function setTheme(mode) {
    if (mode === "light" || mode === "dark") {
        localStorage.setItem("theme", mode);
    } else {
        localStorage.removeItem("theme");
    }
    applyTheme();
    window.dispatchEvent(new CustomEvent("serv:theme-changed"));
}

export function currentThemePreference() {
    const stored = localStorage.getItem("theme");
    return stored === "light" || stored === "dark" ? stored : null;
}
