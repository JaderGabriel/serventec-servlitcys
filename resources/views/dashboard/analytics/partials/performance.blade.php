@props(['performanceData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Área reservada a médias, notas e indicadores de avaliação (boletim, avaliações). Quando as tabelas do iEducar estiverem mapeadas em config/ieducar.php ou em um serviço dedicado, os gráficos poderão ser ligados aqui.') }}
    </p>
    @if (! empty($performanceData['message']))
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $performanceData['message'] }}</p>
    @endif
    <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center text-sm text-gray-400 dark:text-gray-500">
        {{ __('Área para médias, notas e indicadores de avaliação.') }}
    </div>
</div>
