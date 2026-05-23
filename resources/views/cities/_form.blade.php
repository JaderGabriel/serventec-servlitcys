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
                <x-input-label for="ibge_municipio" :value="__('Código IBGE do município (7 dígitos)')" />
                <x-text-input id="ibge_municipio" class="block mt-1 w-full font-mono" type="text" name="ibge_municipio" inputmode="numeric" maxlength="7" pattern="[0-9]{7}" :value="old('ibge_municipio', $city?->ibge_municipio)" placeholder="2910800" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Obrigatório para sincronizar séries SAEB oficiais por município. Consulte o código no IBGE ou no Portal IDEB.') }}</p>
                <x-input-error :messages="$errors->get('ibge_municipio')" class="mt-2" />
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
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600 pb-2 mb-4">{{ __('Contato de referência') }}</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ __('Pessoa responsável ou ponto focal do município. Exibido na Consultoria e no RX com atalhos para telefone, WhatsApp e e-mail.') }}</p>
        <div class="space-y-6">
            <div>
                <x-input-label for="contact_name" :value="__('Nome')" />
                <x-text-input id="contact_name" class="block mt-1 w-full" type="text" name="contact_name" :value="old('contact_name', $city?->contact_name)" placeholder="{{ __('Ex.: Maria Silva — Secretaria de Educação') }}" />
                <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="contact_phone" :value="__('Telefone')" />
                    <x-text-input id="contact_phone" class="block mt-1 w-full" type="tel" name="contact_phone" inputmode="tel" :value="old('contact_phone', $city?->contact_phone)" placeholder="(00) 00000-0000" />
                    <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="contact_whatsapp" :value="__('WhatsApp')" />
                    <x-text-input id="contact_whatsapp" class="block mt-1 w-full" type="tel" name="contact_whatsapp" inputmode="tel" :value="old('contact_whatsapp', $city?->contact_whatsapp)" placeholder="(00) 00000-0000" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Com DDD. Se omitir o código do país, assume-se Brasil (+55).') }}</p>
                    <x-input-error :messages="$errors->get('contact_whatsapp')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="contact_email" :value="__('E-mail')" />
                <x-text-input id="contact_email" class="block mt-1 w-full" type="email" name="contact_email" :value="old('contact_email', $city?->contact_email)" placeholder="contato@municipio.gov.br" />
                <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
            </div>
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
                <x-input-label for="ieducar_app_url" :value="__('URL do i-Educar (portal web)')" />
                <x-text-input id="ieducar_app_url" class="block mt-1 w-full" type="url" name="ieducar_app_url" maxlength="512" :value="old('ieducar_app_url', $city?->ieducar_app_url)" placeholder="https://municipio.exemplo.gov.br" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Link público da instalação i-Educar do município. Usado no mapa do Início (botão «i-Educar»). Pode omitir https:// — será adicionado automaticamente.') }}</p>
                <x-input-error :messages="$errors->get('ieducar_app_url')" class="mt-2" />
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
