@props(['attendanceData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Área reservada a frequência escolar, faltas e alertas (diário de classe, etc.). Depende de mapear as tabelas correspondentes na base do município.') }}
    </p>
    @if (! empty($attendanceData['message']))
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $attendanceData['message'] }}</p>
    @endif
    <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center text-sm text-gray-400 dark:text-gray-500">
        {{ __('Área para frequência, faltas e alertas.') }}
    </div>
</div>
