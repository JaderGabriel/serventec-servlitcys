/**
 * Sino de notificações (Alpine). Carregado em app.js e em pulse-notifications.js (Monitorização).
 */
let registered = false;

export function registerNotificationBellData(Alpine) {
    if (!Alpine || registered) {
        return;
    }

    registered = true;

    Alpine.data("notificationBell", (config) => ({
        open: false,
        items: [],
        unread: 0,
        loading: false,
        indexUrl: config.indexUrl,
        readUrlTemplate: config.readUrlTemplate,
        readAllUrl: config.readAllUrl,
        pollMs: config.pollMs ?? 45000,
        _timer: null,
        init() {
            this.fetch();
            this._timer = setInterval(() => this.fetch(), this.pollMs);
        },
        destroy() {
            if (this._timer) {
                clearInterval(this._timer);
            }
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.fetch();
            }
        },
        close() {
            this.open = false;
        },
        readUrl(id) {
            return this.readUrlTemplate.replace("__ID__", encodeURIComponent(id));
        },
        async fetch() {
            this.loading = true;
            try {
                const r = await fetch(this.indexUrl, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    credentials: "same-origin",
                });
                if (!r.ok) {
                    return;
                }
                const j = await r.json();
                this.items = Array.isArray(j.items) ? j.items : [];
                this.unread = Number(j.unread_count) || 0;
            } catch (e) {
                console.error("notifications", e);
            } finally {
                this.loading = false;
            }
        },
        async markRead(id) {
            try {
                const r = await fetch(this.readUrl(id), {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN":
                            document.querySelector('meta[name="csrf-token"]')
                                ?.content ?? "",
                    },
                    credentials: "same-origin",
                });
                if (r.ok) {
                    const j = await r.json();
                    this.unread = Number(j.unread_count) || 0;
                    this.items = this.items.map((item) =>
                        item.id === id ? { ...item, read: true } : item,
                    );
                }
            } catch (e) {
                console.error("notification read", e);
            }
        },
        async markAllRead() {
            try {
                const r = await fetch(this.readAllUrl, {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN":
                            document.querySelector('meta[name="csrf-token"]')
                                ?.content ?? "",
                    },
                    credentials: "same-origin",
                });
                if (r.ok) {
                    this.unread = 0;
                    this.items = this.items.map((item) => ({
                        ...item,
                        read: true,
                    }));
                }
            } catch (e) {
                console.error("notifications read all", e);
            }
        },
    }));
}

function bootNotificationBell() {
    if (window.Alpine) {
        registerNotificationBellData(window.Alpine);
    }
}

document.addEventListener("alpine:init", bootNotificationBell);

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootNotificationBell);
} else {
    bootNotificationBell();
}
