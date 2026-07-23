<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="serv-eyebrow">{{ __('Clio') }}</p>
            <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                {{ __('Novo município') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
                {{ __('Só coleta: cadastre uma ficha leve. Consultoria: escolha um município já ligado ao i-Educar na plataforma.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if (session('warning'))
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            <form
                method="post"
                action="{{ route('clio.cities.store') }}"
                class="serv-panel space-y-5 p-6"
                data-serv-loading-on-submit
                data-serv-loading-title="{{ __('Cadastrando município') }}"
                data-serv-loading-message="{{ __('Salvando a ficha no Clio. Aguarde…') }}"
                x-data="{
                    mode: '{{ old('setup_mode', 'catalog') }}',
                    cityId: '{{ old('city_id', '') }}',
                    drives: @js($consultancyCities->mapWithKeys(fn ($c) => [(string) $c->id => $c->clio_drive_url])->all()),
                    driveUrl: '{{ old('clio_drive_url', '') }}',
                    syncDrive() {
                        if (this.mode !== 'consultancy' || ! this.cityId) return;
                        const fromCity = this.drives[String(this.cityId)] ?? '';
                        if (! this.driveUrl) this.driveUrl = fromCity || '';
                    }
                }"
                x-init="syncDrive()"
            >
                @csrf

                <fieldset>
                    <legend class="block text-sm font-medium">{{ __('Tipo de cadastro') }}</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer gap-3 rounded-lg border p-3 transition"
                               :class="mode === 'catalog' ? 'border-sky-400 bg-sky-50 dark:border-sky-600 dark:bg-sky-950/40' : 'border-slate-200 dark:border-slate-700'">
                            <input type="radio" class="mt-1" name="setup_mode" value="catalog" x-model="mode">
                            <span>
                                <span class="block text-sm font-medium">{{ __('Só coleta') }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ __('Nova ficha sem i-Educar — análise de CSV/ZIP do portal.') }}</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer gap-3 rounded-lg border p-3 transition"
                               :class="mode === 'consultancy' ? 'border-sky-400 bg-sky-50 dark:border-sky-600 dark:bg-sky-950/40' : 'border-slate-200 dark:border-slate-700'">
                            <input type="radio" class="mt-1" name="setup_mode" value="consultancy" x-model="mode">
                            <span>
                                <span class="block text-sm font-medium">{{ __('Consultoria') }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ __('Usar município já cadastrado com i-Educar.') }}</span>
                            </span>
                        </label>
                    </div>
                    @error('setup_mode')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </fieldset>

                <div x-show="mode === 'catalog'" x-cloak class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium">{{ __('Nome do município') }}</label>
                        <input id="name" name="name" value="{{ old('name') }}" maxlength="255"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                               :disabled="mode !== 'catalog'"
                               :required="mode === 'catalog'" />
                        @error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="uf" class="block text-sm font-medium">{{ __('UF') }}</label>
                            <input id="uf" name="uf" value="{{ old('uf', 'BA') }}" maxlength="2"
                                   class="mt-1 block w-full uppercase rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                                   :disabled="mode !== 'catalog'"
                                   :required="mode === 'catalog'" />
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
                        <label for="contact_name" class="block text-sm font-medium">{{ __('Contato (opcional)') }}</label>
                        <input id="contact_name" name="contact_name" value="{{ old('contact_name') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div>
                        <label for="contact_email" class="block text-sm font-medium">{{ __('E-mail (opcional)') }}</label>
                        <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        @error('contact_email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div x-show="mode === 'consultancy'" x-cloak class="space-y-4 border-t border-slate-200 pt-5 dark:border-slate-700">
                    @if ($consultancyCities->isEmpty())
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            @if (! empty($consultancyAllLinked))
                                {{ __('Todos os municípios com i-Educar activo já estão vinculados ao Clio. Abra a coleta existente ou cadastre um novo na plataforma.') }}
                            @else
                                {{ __('Nenhum município com i-Educar activo. Cadastre-o em Municípios da plataforma e volte aqui.') }}
                            @endif
                        </p>
                        <a href="{{ route('cities.create') }}" class="serv-link text-sm font-medium">{{ __('Cadastrar município na consultoria') }} →</a>
                    @else
                        <div>
                            <label for="city_id" class="block text-sm font-medium">{{ __('Município da consultoria') }}</label>
                            <select id="city_id" name="city_id"
                                    class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                                    x-model="cityId"
                                    @change="driveUrl = drives[String(cityId)] || ''"
                                    :disabled="mode !== 'consultancy'"
                                    :required="mode === 'consultancy'">
                                <option value="">{{ __('Selecione…') }}</option>
                                @foreach ($consultancyCities as $city)
                                    <option value="{{ $city->id }}" @selected((string) old('city_id') === (string) $city->id)>
                                        {{ $city->name }} / {{ $city->uf }}
                                        @if ($city->ibge_municipio)
                                            · {{ $city->ibge_municipio }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('city_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                            <p class="mt-2 text-xs text-slate-500">
                                {{ __('Só aparecem municípios com i-Educar que ainda não têm coleta no Clio. Credenciais já estão na ficha.') }}
                                <a href="{{ route('cities.index') }}" class="serv-link font-medium">{{ __('Gerir municípios') }}</a>
                            </p>
                        </div>
                    @endif
                </div>

                <div class="border-t border-slate-200 pt-5 dark:border-slate-700"
                     x-show="mode === 'catalog' || (mode === 'consultancy' && {{ $consultancyCities->isNotEmpty() ? 'true' : 'false' }})"
                     x-cloak>
                    <label for="clio_drive_url" class="block text-sm font-medium">{{ __('Pasta Google Drive (dados da coleta)') }}</label>
                    <input id="clio_drive_url" name="clio_drive_url" type="url" x-model="driveUrl"
                           placeholder="https://drive.google.com/drive/folders/…"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Opcional. Em consultoria, se já houver link na ficha do município, ele é preenchido ao selecionar.') }}
                    </p>
                    @error('clio_drive_url')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('clio.home') }}" class="text-sm text-slate-600 hover:underline">{{ __('Cancelar') }}</a>
                    <button type="submit" class="serv-btn-primary text-sm"
                            x-bind:disabled="mode === 'consultancy' && {{ $consultancyCities->isEmpty() ? 'true' : 'false' }}"
                            x-text="mode === 'consultancy' ? '{{ __('Continuar com consultoria') }}' : '{{ __('Salvar só coleta') }}'">
                        {{ __('Salvar') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
