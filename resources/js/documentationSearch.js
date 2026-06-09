document.addEventListener("alpine:init", () => {
    Alpine.data("documentationSearch", (searchUrl) => ({
        searchUrl: typeof searchUrl === "string" ? searchUrl : "",
        query: "",
        results: [],
        loading: false,
        open: false,
        activeIndex: -1,
        _requestId: 0,

        onInput() {
            const q = this.query.trim();
            if (q.length < 2) {
                this.results = [];
                this.open = false;
                this.activeIndex = -1;
                return;
            }
            void this.fetchResults(q);
        },

        onFocus() {
            if (this.results.length > 0) {
                this.open = true;
            }
        },

        closeResults() {
            this.open = false;
            this.activeIndex = -1;
        },

        moveActive(delta) {
            if (!this.open || this.results.length === 0) {
                return;
            }
            const n = this.results.length;
            let next = this.activeIndex + delta;
            if (next < 0) {
                next = n - 1;
            }
            if (next >= n) {
                next = 0;
            }
            this.activeIndex = next;
        },

        goActive() {
            if (!this.open || this.activeIndex < 0) {
                return;
            }
            const item = this.results[this.activeIndex];
            if (item?.url) {
                window.location.href = item.url;
            }
        },

        async fetchResults(q) {
            if (!this.searchUrl) {
                return;
            }
            const requestId = ++this._requestId;
            this.loading = true;
            try {
                const u = new URL(this.searchUrl, window.location.origin);
                u.searchParams.set("q", q);
                const response = await fetch(u.toString(), {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                if (requestId !== this._requestId) {
                    return;
                }
                this.results = Array.isArray(data.results) ? data.results : [];
                this.open = this.query.trim().length >= 2;
                this.activeIndex = this.results.length > 0 ? 0 : -1;
            } catch (error) {
                console.warn("documentationSearch", error);
                if (requestId === this._requestId) {
                    this.results = [];
                    this.open = this.query.trim().length >= 2;
                    this.activeIndex = -1;
                }
            } finally {
                if (requestId === this._requestId) {
                    this.loading = false;
                }
            }
        },
    }));
});
