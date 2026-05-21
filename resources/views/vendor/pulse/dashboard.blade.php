<x-pulse cols="12">
    <livewire:pulse.monitoring-executive-strip />

    <x-pulse-dashboard.section
        :title="__('Municípios e decisão operacional')"
        :subtitle="__('Inventário das bases i-Educar, volume de uso por cidade e fila de sincronização — priorize municípios incompletos ou com falhas.')"
        accent="cyan"
        icon="chart-bar"
    />
    <livewire:pulse.municipal-infrastructure-card cols="full" rows="2" />
    <livewire:pulse.institution-traffic-card cols="full" rows="2" />

    <x-pulse-dashboard.section
        :title="__('Sincronização administrativa')"
        :subtitle="__('Rotas de geo e pedagógico: volume próprio e pedidos lentos nas mesmas URLs.')"
        accent="teal"
        icon="cloud-arrow-up"
    />
    <livewire:pulse.sync-admin-pulse-card cols="full" rows="1" />

    <x-pulse-dashboard.section
        :title="__('Infraestrutura de TI')"
        :subtitle="__('Runtime, filas, disco, base de dados e servidor — métricas em tempo real e série temporal.')"
        accent="slate"
        icon="server"
    />
    <x-pulse-dashboard.merged-server-panel />
    <livewire:pulse.usage cols="3" rows="2" />
    <livewire:pulse.application-insights-card cols="3" rows="1" />
    <livewire:pulse.database-health-card cols="3" rows="1" />
    <livewire:pulse.disk-space-card cols="3" rows="1" />
    <livewire:pulse.queue-and-failures-card cols="3" rows="1" />

    <x-pulse-dashboard.section
        :title="__('Cache e Redis')"
        :subtitle="__('Interações de cache e estado das ligações chave-valor (sessão, filas, Pulse).')"
        accent="violet"
        icon="circle-stack"
    />
    <livewire:pulse.cache cols="3" rows="2" />
    <livewire:pulse.redis-overview-card cols="9" rows="2" />

    <x-pulse-dashboard.section
        :title="__('Latência HTTP e saída')"
        :subtitle="__('Pedidos lentos à aplicação e chamadas HTTP externas (APIs, ArcGIS, INEP, etc.).')"
        accent="sky"
        icon="globe-alt"
    />
    <livewire:pulse.slow-requests cols="4" />
    <livewire:pulse.slow-outgoing-requests cols="4" />
    <livewire:pulse.slow-queries cols="4" rows="2" />

    <x-pulse-dashboard.section
        :title="__('Jobs em segundo plano')"
        :subtitle="__('Jobs lentos na fila (`PULSE_SLOW_JOBS_THRESHOLD`).')"
        accent="amber"
        icon="queue"
    />
    <livewire:pulse.slow-jobs cols="full" rows="2" />

    <x-pulse-dashboard.section
        :title="__('Estabilidade e erros')"
        :subtitle="__('Excepções não tratadas — investigar antes de escalar análises municipais.')"
        accent="rose"
        icon="exclamation"
    />
    <livewire:pulse.exceptions cols="full" rows="2" />
</x-pulse>
