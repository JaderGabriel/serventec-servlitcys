import './bootstrap';

import Alpine from 'alpinejs';

document.addEventListener('alpine:init', () => {
    Alpine.data('cityDbStatus', (fetchUrl, hasSetup) => ({
        fetchUrl,
        hasSetup,
        loading: !!hasSetup,
        status: null,
        ms: null,
        message: '',
        init() {
            this.refresh();
        },
        async refresh() {
            if (!this.hasSetup) {
                this.status = 'setup_missing';

                return;
            }
            this.loading = true;
            this.status = null;
            try {
                const r = await fetch(this.fetchUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!r.ok) {
                    const t = await r.text();
                    throw new Error(t ? `HTTP ${r.status}` : `HTTP ${r.status}`);
                }
                const d = await r.json();
                this.status = d.status;
                this.ms = d.ms;
                this.message = d.message || '';
            } catch (e) {
                this.status = 'error';
                this.message = e instanceof Error ? e.message : String(e);
            } finally {
                this.loading = false;
            }
        },
        titleText() {
            if (this.loading) {
                return 'Verificando…';
            }
            if (this.status === 'setup_missing') {
                return 'Credenciais do banco incompletas.';
            }
            if (this.status === 'ok' && this.ms != null) {
                return `Conexão OK (${this.ms} ms). Clique para atualizar.`;
            }
            if (this.status === 'slow' && this.ms != null) {
                return `Resposta lenta (${this.ms} ms). Clique para atualizar.`;
            }
            if (this.status === 'error') {
                return this.message ? `Erro: ${this.message}` : 'Erro de conexão. Clique para tentar de novo.';
            }

            return '';
        },
    }));
});

window.Alpine = Alpine;

Alpine.start();
