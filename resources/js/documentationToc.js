document.addEventListener("alpine:init", () => {
    Alpine.data("documentationToc", () => ({
        activeId: "",

        init() {
            const links = this.$el.querySelectorAll('.serv-docs-toc-link[href^="#"]');
            if (links.length === 0) {
                return;
            }

            const ids = Array.from(links)
                .map((a) => a.getAttribute("href")?.slice(1) ?? "")
                .filter(Boolean);

            const headings = ids
                .map((id) => document.getElementById(id))
                .filter(Boolean);

            if (headings.length === 0) {
                return;
            }

            const observer = new IntersectionObserver(
                (entries) => {
                    const visible = entries
                        .filter((e) => e.isIntersecting)
                        .sort(
                            (a, b) =>
                                a.target.getBoundingClientRect().top -
                                b.target.getBoundingClientRect().top,
                        );
                    if (visible.length > 0) {
                        this.activeId = visible[0].target.id;
                    }
                },
                {
                    root: null,
                    rootMargin: "-20% 0px -70% 0px",
                    threshold: 0,
                },
            );

            headings.forEach((el) => observer.observe(el));

            if (window.location.hash) {
                const hash = decodeURIComponent(window.location.hash.slice(1));
                if (ids.includes(hash)) {
                    this.activeId = hash;
                }
            }
        },
    }));
});
