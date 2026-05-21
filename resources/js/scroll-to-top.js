/**
 * Botão «voltar ao topo» (Alpine.data scrollToTop).
 */
export function registerScrollToTopData(Alpine) {
    Alpine.data("scrollToTop", () => ({
        visible: false,
        init() {
            const update = () => {
                this.visible = window.scrollY > 400;
            };
            update();
            window.addEventListener("scroll", update, { passive: true });
        },
        goTop() {
            window.scrollTo({ top: 0, behavior: "smooth" });
        },
    }));
}
