<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }} · {{ __('Vincular i-Educar') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ $campaign->municipality_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ __('Preencha as credenciais da base. Após o teste OK, a coleta passa a perfil Consultoria.') }}
                </p>
            </div>
            <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Voltar') }}</a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('warning'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            <form method="post" action="{{ route('clio.campaigns.link.store', $campaign) }}" class="serv-panel space-y-5 p-6">
                @csrf
                <div>
                    <label for="db_driver" class="block text-sm font-medium">{{ __('Motor') }}</label>
                    <select id="db_driver" name="db_driver" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" required>
                        <option value="pgsql" @selected(old('db_driver', $city->db_driver ?? 'pgsql') === 'pgsql')>PostgreSQL</option>
                        <option value="mysql" @selected(old('db_driver', $city->db_driver ?? '') === 'mysql')>MySQL / MariaDB</option>
                    </select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="db_host" class="block text-sm font-medium">{{ __('Host') }}</label>
                        <input id="db_host" name="db_host" type="text" required value="{{ old('db_host', $city->db_host) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        @error('db_host')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="db_port" class="block text-sm font-medium">{{ __('Porta') }}</label>
                        <input id="db_port" name="db_port" type="number" value="{{ old('db_port', $city->db_port) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                </div>
                <div>
                    <label for="db_database" class="block text-sm font-medium">{{ __('Banco de dados') }}</label>
                    <input id="db_database" name="db_database" type="text" required value="{{ old('db_database', $city->db_database) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="db_username" class="block text-sm font-medium">{{ __('Usuário') }}</label>
                        <input id="db_username" name="db_username" type="text" required value="{{ old('db_username', $city->db_username) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div>
                        <label for="db_password" class="block text-sm font-medium">{{ __('Senha') }}</label>
                        <input id="db_password" name="db_password" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" placeholder="{{ $city->db_password ? '••••••••' : '' }}" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Deixe em branco para manter a senha atual.') }}</p>
                    </div>
                </div>
                <div>
                    <label for="ieducar_schema" class="block text-sm font-medium">{{ __('Schema (PostgreSQL)') }}</label>
                    <input id="ieducar_schema" name="ieducar_schema" type="text" value="{{ old('ieducar_schema', $city->ieducar_schema) }}" placeholder="pmieducar" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div>
                    <label for="ieducar_app_url" class="block text-sm font-medium">{{ __('URL do i-Educar') }}</label>
                    <input id="ieducar_app_url" name="ieducar_app_url" type="url" value="{{ old('ieducar_app_url', $city->ieducar_app_url) }}" placeholder="https://…" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('cities.edit', $city) }}" class="serv-link text-sm">{{ __('Editar ficha completa do município') }} →</a>
                    <button type="submit" class="serv-btn-primary text-sm">{{ __('Salvar e testar conexão') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
