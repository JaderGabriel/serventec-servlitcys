<x-pulse cols="12">
    <x-pulse-dashboard.section
        :title="__('Negócio & tráfego')"
        :subtitle="__('Pedidos agregados por cidade e totais — contexto i-Educar / instituições.')"
        accent="emerald"
        icon="chart-bar"
    />
    <livewire:pulse.institution-traffic-card cols="full" rows="2" />

    <x-pulse-dashboard.section
        :title="__('Infraestrutura & recursos')"
        :subtitle="__('Servidor, CPU/RAM, versões de runtime, filas, disco e saúde da base de dados.')"
        accent="slate"
        icon="server"
    />
    <livewire:pulse.servers cols="full" />
    <livewire:pulse.usage cols="4" rows="2" />
    <livewire:pulse.application-insights-card cols="6" rows="1" />
    <livewire:pulse.database-health-card cols="6" rows="1" />
    <livewire:pulse.disk-space-card cols="6" rows="1" />
    <livewire:pulse.queue-and-failures-card cols="6" rows="1" />

    <x-pulse-dashboard.section
        :title="__('Cache & armazenamento chave-valor')"
        :subtitle="__('Interações de cache da aplicação e estado do Redis (pulse / sessão / filas).')"
        accent="violet"
        icon="circle-stack"
    />
    <livewire:pulse.cache cols="4" />
    <livewire:pulse.redis-overview-card cols="full" rows="2" />

    <x-pulse-dashboard.section
        :title="__('HTTP & redes')"
        :subtitle="__('Latência de pedidos à aplicação e pedidos HTTP de saída (APIs, ArcGIS, INEP, etc.).')"
        accent="sky"
        icon="globe-alt"
    />
    <livewire:pulse.slow-requests cols="6" />
    <livewire:pulse.slow-outgoing-requests cols="6" />

    <x-pulse-dashboard.section
        :title="__('Base de dados & consultas lentas')"
        :subtitle="__('Consultas SQL acima do limiar configurado (`PULSE_SLOW_QUERIES_THRESHOLD`).')"
        accent="rose"
        icon="circle-stack"
    />
    <livewire:pulse.slow-queries cols="full" rows="2" />

    <x-pulse-dashboard.section
        :title="__('Filas & jobs')"
        :subtitle="__('Processamento em segundo plano: jobs lentos e cargas de trabalho pesadas.')"
        accent="amber"
        icon="queue"
    />
    <livewire:pulse.slow-jobs cols="full" rows="2" />

    <x-pulse-dashboard.section
        :title="__('Erros & estabilidade')"
        :subtitle="__('Excepções não tratadas registadas pelos recorders do Pulse.')"
        accent="red"
        icon="exclamation"
    />
    <livewire:pulse.exceptions cols="full" rows="2" />
</x-pulse>
