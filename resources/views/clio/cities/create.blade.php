<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="serv-eyebrow">{{ __('Clio') }}</p>
            <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                {{ __('Novo município') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
                {{ __('Cadastre para coleta Educacenso — só análise de relatórios, ou já com credenciais i-Educar (consultoria).') }}
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
                x-data="{ mode: '{{ old('setup_mode', 'catalog') }}' }"
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
                                <span class="mt-0.5 block text-xs text-slate-500">{{ __('Sem i-Educar — análise de CSV/ZIP do portal.') }}</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer gap-3 rounded-lg border p-3 transition"
                               :class="mode === 'consultancy' ? 'border-sky-400 bg-sky-50 dark:border-sky-600 dark:bg-sky-950/40' : 'border-slate-200 dark:border-slate-700'">
                            <input type="radio" class="mt-1" name="setup_mode" value="consultancy" x-model="mode">
                            <span>
                                <span class="block text-sm font-medium">{{ __('Consultoria') }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ __('Com credenciais i-Educar — cruzamento e lacunas.') }}</span>
                            </span>
                        </label>
                    </div>
                    @error('setup_mode')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </fieldset>

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

                <div class="border-t border-slate-200 pt-5 dark:border-slate-700">
                    <label for="clio_drive_url" class="block text-sm font-medium">{{ __('Pasta Google Drive (dados da coleta)') }}</label>
                    <input id="clio_drive_url" name="clio_drive_url" type="url" value="{{ old('clio_drive_url') }}"
                           placeholder="https://drive.google.com/drive/folders/…"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Link da pasta com CSV/ZIP do Educacenso (partilha «qualquer pessoa com o link»). Depois da coleta, use Verificar e Importar.') }}
                    </p>
                    @error('clio_drive_url')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div x-show="mode === 'consultancy'" x-cloak class="space-y-4 border-t border-slate-200 pt-5 dark:border-slate-700">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Credenciais i-Educar') }}</p>
                    <div>
                        <label for="db_driver" class="block text-sm font-medium">{{ __('Motor') }}</label>
                        <select id="db_driver" name="db_driver" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                                :required="mode === 'consultancy'">
                            <option value="pgsql" @selected(old('db_driver', 'pgsql') === 'pgsql')>PostgreSQL</option>
                            <option value="mysql" @selected(old('db_driver') === 'mysql')>MySQL / MariaDB</option>
                        </select>
                        @error('db_driver')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="db_host" class="block text-sm font-medium">{{ __('Host') }}</label>
                            <input id="db_host" name="db_host" type="text" value="{{ old('db_host') }}"
                                   class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                                   :required="mode === 'consultancy'" />
                            @error('db_host')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="db_port" class="block text-sm font-medium">{{ __('Porta') }}</label>
                            <input id="db_port" name="db_port" type="number" value="{{ old('db_port') }}"
                                   class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        </div>
                    </div>
                    <div>
                        <label for="db_database" class="block text-sm font-medium">{{ __('Banco de dados') }}</label>
                        <input id="db_database" name="db_database" type="text" value="{{ old('db_database') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                               :required="mode === 'consultancy'" />
                        @error('db_database')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="db_username" class="block text-sm font-medium">{{ __('Usuário') }}</label>
                            <input id="db_username" name="db_username" type="text" value="{{ old('db_username') }}"
                                   class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                                   :required="mode === 'consultancy'" />
                            @error('db_username')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="db_password" class="block text-sm font-medium">{{ __('Senha') }}</label>
                            <input id="db_password" name="db_password" type="password" autocomplete="new-password"
                                   class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                                   :required="mode === 'consultancy'" />
                            @error('db_password')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="ieducar_schema" class="block text-sm font-medium">{{ __('Schema (PostgreSQL)') }}</label>
                        <input id="ieducar_schema" name="ieducar_schema" type="text" value="{{ old('ieducar_schema') }}" placeholder="pmieducar"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div>
                        <label for="ieducar_app_url" class="block text-sm font-medium">{{ __('URL do i-Educar') }}</label>
                        <input id="ieducar_app_url" name="ieducar_app_url" type="url" value="{{ old('ieducar_app_url') }}" placeholder="https://…"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        @error('ieducar_app_url')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('clio.home') }}" class="text-sm text-slate-600 hover:underline">{{ __('Cancelar') }}</a>
                    <button type="submit" class="serv-btn-primary text-sm"
                            x-text="mode === 'consultancy' ? '{{ __('Salvar consultoria') }}' : '{{ __('Salvar só coleta') }}'">
                        {{ __('Salvar') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
