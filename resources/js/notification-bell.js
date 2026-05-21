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
        criticalUnread: 0,
        filterCritical: false,
        loading: false,
        indexUrl: config.indexUrl,
        readUrlTemplate: config.readUrlTemplate,
        readAllUrl: config.readAllUrl,
        pollMs: config.pollMs ?? 30000,
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
        bellTitle() {
            const base = "Notificações";
            if (this.unread === 0) {
                return base;
            }
            if (this.criticalUnread > 0) {
                return `${base} (${this.unread}, ${this.criticalUnread} críticas)`;
            }
            return `${base} (${this.unread})`;
        },
        rowClass(item) {
            if (item.read) {
                return "opacity-75";
            }
            if (item.is_critical) {
                return "bg-rose-50/80 dark:bg-rose-950/40 border-s-2 border-s-rose-500";
            }
            if (item.priority === "high") {
                return "bg-amber-50/50 dark:bg-amber-950/25";
            }
            return "bg-indigo-50/50 dark:bg-indigo-950/30";
        },
        dotClass(item) {
            if (item.is_critical || item.icon === "error") {
                return "bg-rose-500";
            }
            if (item.priority === "high" || item.icon === "warning") {
                return "bg-amber-500";
            }
            if (item.icon === "success") {
                return "bg-emerald-500";
            }
            return "bg-sky-500";
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
        setFilter(critical) {
            this.filterCritical = critical;
            this.fetch();
        },
        readUrl(id) {
            return this.readUrlTemplate.replace("__ID__", encodeURIComponent(id));
        },
        fetchUrl() {
            const url = new URL(this.indexUrl, window.location.origin);
            if (this.filterCritical) {
                url.searchParams.set("critical", "1");
            }
            return url.toString();
        },
        async fetch() {
            this.loading = true;
            try {
                const r = await fetch(this.fetchUrl(), {
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
                this.criticalUnread = Number(j.critical_unread_count) || 0;
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
                    this.criticalUnread = Number(j.critical_unread_count) || 0;
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
                    this.criticalUnread = 0;
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
