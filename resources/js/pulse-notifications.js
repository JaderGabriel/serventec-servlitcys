/**
 * Regista o sino de notificações no Alpine do Livewire/Pulse (sem carregar app.js).
 */
import { registerNotificationBellData } from "./notification-bell.js";

function boot() {
    const Alpine = window.Alpine;
    if (!Alpine) {
        return;
    }

    registerNotificationBellData(Alpine);

    if (typeof Alpine.initTree === "function") {
        document
            .querySelectorAll('[x-data*="notificationBell"]')
            .forEach((el) => Alpine.initTree(el));
    }
}

document.addEventListener("alpine:init", boot);

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
