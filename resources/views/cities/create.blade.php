<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nova cidade') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700 p-6">
                <form method="post" action="{{ route('cities.store') }}">
                    @csrf
                    @include('cities._form', ['city' => null])
                    <div class="flex items-center justify-end mt-6 gap-2">
                        <a href="{{ route('cities.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('Cancelar') }}</a>
                        <x-primary-button>{{ __('Salvar') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
