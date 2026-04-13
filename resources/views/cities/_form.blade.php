<div class="space-y-8">
    <div>
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600 pb-2 mb-4">{{ __('Identificação') }}</h3>
        <div class="space-y-6">
            <div>
                <x-input-label for="name" :value="__('Nome da cidade')" />
                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $city?->name)" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="uf" :value="__('UF (estado)')" />
                <x-text-input id="uf" class="block mt-1 w-full uppercase" type="text" name="uf" maxlength="2" :value="old('uf', $city?->uf)" required placeholder="SP" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Duas letras, ex.: SP, RJ.') }}</p>
                <x-input-error :messages="$errors->get('uf')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="country" :value="__('País')" />
                <x-text-input id="country" class="block mt-1 w-full" type="text" name="country" :value="old('country', $city?->country ?? 'Brasil')" />
                <x-input-error :messages="$errors->get('country')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3 pt-2">
                <input type="hidden" name="is_active" value="0" />
                <input id="is_active" type="checkbox" name="is_active" value="1" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" @checked(old('is_active', $city?->is_active ?? true)) />
                <x-input-label for="is_active" :value="__('Cidade ativa (incluir em painéis e listagens)')" class="!mb-0" />
            </div>
            <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
        </div>
    </div>

    <div>
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600 pb-2 mb-4">{{ __('Base de dados (consultas do painel)') }}</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ __('Escolha o motor e as credenciais para conectar ao banco desta cidade. A senha é armazenada de forma criptografada.') }}</p>
        <div class="space-y-6">
            <div>
                <x-input-label for="db_driver" :value="__('Motor da base de dados')" />
                <select id="db_driver" name="db_driver" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="mysql" @selected(old('db_driver', $city?->dataDriver() ?? \App\Models\City::DRIVER_MYSQL) === 'mysql')>{{ __('MySQL / MariaDB') }}</option>
                    <option value="pgsql" @selected(old('db_driver', $city?->dataDriver() ?? \App\Models\City::DRIVER_MYSQL) === 'pgsql')>{{ __('PostgreSQL') }}</option>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('As consultas ao iEducar usam o mesmo esquema; o Laravel adapta a conexão ao motor escolhido.') }}</p>
                <x-input-error :messages="$errors->get('db_driver')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="ieducar_schema" :value="__('Schema PostgreSQL (iEducar)')" />
                <x-text-input id="ieducar_schema" class="block mt-1 w-full" type="text" name="ieducar_schema" maxlength="63" :value="old('ieducar_schema', $city?->ieducar_schema)" placeholder="pmieducar" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Opcional. Em bases Portabilis o padrão é «pmieducar» (aplicado automaticamente se deixar vazio e o motor for PostgreSQL). Preencha só se o schema for outro.') }}</p>
                <x-input-error :messages="$errors->get('ieducar_schema')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_host" :value="__('Host')" />
                <x-text-input id="db_host" class="block mt-1 w-full" type="text" name="db_host" :value="old('db_host', $city?->db_host)" required placeholder="ex.: db.exemplo.com" />
                <x-input-error :messages="$errors->get('db_host')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_port" :value="__('Porta')" />
                <x-text-input id="db_port" class="block mt-1 w-full" type="number" name="db_port" min="1" max="65535" :value="old('db_port', $city?->db_port ?? 3306)" placeholder="{{ old('db_driver', $city?->dataDriver() ?? 'mysql') === 'pgsql' ? '5432' : '3306' }}" />
                <x-input-error :messages="$errors->get('db_port')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_database" :value="__('Nome da base de dados')" />
                <x-text-input id="db_database" class="block mt-1 w-full" type="text" name="db_database" :value="old('db_database', $city?->db_database)" required />
                <x-input-error :messages="$errors->get('db_database')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_username" :value="__('Usuário do banco de dados')" />
                <x-text-input id="db_username" class="block mt-1 w-full" type="text" name="db_username" :value="old('db_username', $city?->db_username)" required autocomplete="username" />
                <x-input-error :messages="$errors->get('db_username')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_password" :value="__('Senha')" />
                <x-text-input id="db_password" class="block mt-1 w-full" type="password" name="db_password" value="" autocomplete="new-password" placeholder="{{ $city ? __('Deixe em branco para manter a atual') : '' }}" />
                <x-input-error :messages="$errors->get('db_password')" class="mt-2" />
            </div>
        </div>
    </div>
</div>
