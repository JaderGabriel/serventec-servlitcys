<p {{ $attributes->merge(['class' => 'text-[11px] text-gray-500 dark:text-gray-400 mt-2 leading-relaxed']) }}>
    {{ __('Enfileira a tarefa — não aguarde o resultado nesta página.') }}
    <a href="{{ route('admin.sync-queue.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Abrir fila') }}</a>
</p>
