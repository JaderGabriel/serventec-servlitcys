<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Nova cidade') }}
            </h2>
        </div>
    </x-slot>

    <x-admin.screen-shell
        group="municipalities"
        active="cities"
        accent="violet"
        narrow
        :eyebrow="__('Municípios')"
        :title="__('Cadastrar cidade')"
        :description="__('Defina identificação, IBGE e credenciais do banco i-Educar.')"
    >
        <div class="rounded-xl border border-gray-200/90 dark:border-gray-700 bg-white/80 dark:bg-gray-900/40 p-6">
            <form method="post" action="{{ route('cities.store') }}">
                @csrf
                @include('cities._form', ['city' => null])
                <div class="flex items-center justify-end mt-6 gap-3">
                    <a href="{{ route('cities.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('Cancelar') }}</a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">{{ __('Salvar') }}</button>
                </div>
            </form>
        </div>
    </x-admin.screen-shell>
</x-app-layout>
