function findItem(config, id) {
    for (const group of config.groups ?? []) {
        for (const item of group.items ?? []) {
            if (item.id === id) {
                return item;
            }
        }
    }

    return null;
}

function buildPostBody(config, item) {
    const body = new FormData();
    body.append('_token', config.csrf ?? '');
    const fields = config.filterFields ?? {};
    Object.entries(fields).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            body.append(key, String(value));
        }
    });
    if (item.format) {
        body.append('format', item.format);
    }
    if (item.id === 'pdf_full') {
        body.append('tab', 'municipality_health');
    }

    return body;
}

export function analyticsExportHub(config) {
    const itemsById = {};
    (config.groups ?? []).forEach((group) => {
        (group.items ?? []).forEach((item) => {
            itemsById[item.id] = item;
        });
    });

    return {
        open: false,
        config,
        itemsById,
        toast: {
            open: false,
            title: '',
            detail: '',
            ref: null,
            queueUrl: null,
        },
        showToast(title, detail, ref = null, queue = true) {
            this.toast = {
                open: true,
                title,
                detail,
                ref,
                queueUrl: queue ? (this.config.queueUrl ?? null) : null,
            };
            window.setTimeout(() => {
                if (this.toast.open && this.toast.title === title) {
                    this.toast.open = false;
                }
            }, 12000);
        },
        async run(id) {
            const item = this.itemsById[id] ?? findItem(this.config, id);
            if (!item || !item.enabled) {
                this.showToast(
                    this.config.messages?.needYear ?? '',
                    '',
                    null,
                    false,
                );
                return;
            }

            this.open = false;

            if (item.mode === 'queue') {
                await this.runQueue(item);
                return;
            }

            this.runDownload(item);
        },
        async runQueue(item) {
            window.servDataLoading?.start?.(
                this.config.messages?.queued ?? 'Enviado para a fila',
                this.config.messages?.queuedDetail ?? '',
            );

            try {
                const response = await fetch(item.url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: buildPostBody(this.config, item),
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message ?? this.config.messages?.error ?? 'Erro');
                }

                const ref = payload.export?.id ?? payload.task_id ?? null;
                this.showToast(
                    this.config.messages?.queued ?? 'Enviado para a fila',
                    payload.message ?? this.config.messages?.queuedDetail ?? '',
                    ref,
                    true,
                );
            } catch (error) {
                this.showToast(
                    this.config.messages?.error ?? 'Erro',
                    error?.message ?? '',
                    null,
                    false,
                );
            } finally {
                window.servDataLoading?.finish?.();
            }
        },
        runDownload(item) {
            window.servDataLoading?.start?.(
                this.config.messages?.download ?? 'Exportação em processamento',
                this.config.messages?.downloadDetail ?? '',
            );

            this.showToast(
                this.config.messages?.download ?? 'Exportação em processamento',
                this.config.messages?.downloadDetail ?? '',
                null,
                false,
            );

            window.setTimeout(() => {
                window.location.assign(item.url);
                window.setTimeout(() => window.servDataLoading?.finish?.(), 1500);
            }, 120);
        },
    };
}

export function registerAnalyticsExportHub(Alpine) {
    Alpine.data('analyticsExportHub', analyticsExportHub);
}
