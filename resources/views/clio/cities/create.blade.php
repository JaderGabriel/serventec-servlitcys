<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="serv-eyebrow">{{ __('Clio') }}</p>
            <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                {{ __('Novo município — ficha leve') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
                {{ __('Cadastro sem credenciais i-Educar, para coleta e análise de relatórios. Pode vincular a base depois.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="post" action="{{ route('clio.cities.store') }}" class="serv-panel space-y-4 p-6">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium">{{ __('Nome do município') }}</label>
                    <input id="name" name="name" value="{{ old('name') }}" required maxlength="255"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    @error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="uf" class="block text-sm font-medium">{{ __('UF') }}</label>
                        <input id="uf" name="uf" value="{{ old('uf', 'BA') }}" required maxlength="2"
                               class="mt-1 block w-full uppercase rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        @error('uf')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="ibge_municipio" class="block text-sm font-medium">{{ __('IBGE (7 dígitos)') }}</label>
                        <input id="ibge_municipio" name="ibge_municipio" value="{{ old('ibge_municipio') }}" maxlength="7" inputmode="numeric"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        @error('ibge_municipio')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="contact_name" class="block text-sm font-medium">{{ __('Contacto (opcional)') }}</label>
                    <input id="contact_name" name="contact_name" value="{{ old('contact_name') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div>
                    <label for="contact_email" class="block text-sm font-medium">{{ __('E-mail (opcional)') }}</label>
                    <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    @error('contact_email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('clio.campaigns.index') }}" class="text-sm text-slate-600 hover:underline">{{ __('Cancelar') }}</a>
                    <button type="submit" class="serv-btn-primary text-sm">{{ __('Salvar ficha leve') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
