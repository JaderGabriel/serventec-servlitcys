<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="serv-eyebrow">{{ __('Clio') }}</p>
            <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                {{ __('Nova coleta') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ __('Associe um município do catálogo Clio (com ou sem i-Educar) ao exercício da 1ª etapa.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            <form method="post" action="{{ route('clio.campaigns.store') }}" class="serv-panel space-y-5 p-6">
                @csrf
                <div>
                    <label for="city_id" class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Município') }}</label>
                    <select id="city_id" name="city_id" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                        <option value="">{{ __('Selecione…') }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}" @selected((string) old('city_id', request('city_id')) === (string) $city->id)>
                                {{ $city->name }} / {{ $city->uf }}
                                @if ($city->hasDataSetup())
                                    — {{ __('consultoria') }}
                                @else
                                    — {{ __('só coleta') }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('city_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    @can('createCatalogCity', App\Models\Clio\ClioCampaign::class)
                        <p class="mt-2 text-xs text-slate-500">
                            {{ __('Município ainda não cadastrado?') }}
                            <a href="{{ route('clio.cities.create') }}" class="serv-link font-medium">{{ __('Cadastrar município') }}</a>
                        </p>
                    @endcan
                </div>

                <div>
                    <label for="year" class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Ano letivo / exercício') }}</label>
                    <input id="year" type="number" name="year" value="{{ old('year', $defaultYear) }}" min="2020" max="2100" required
                           class="mt-1 block w-40 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    @error('year')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Notas (opcional)') }}</label>
                    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('clio.home') }}" class="text-sm text-slate-600 hover:underline dark:text-slate-400">{{ __('Cancelar') }}</a>
                    <button type="submit" class="serv-btn-primary text-sm">{{ __('Criar coleta') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
