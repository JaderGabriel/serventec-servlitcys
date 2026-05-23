<x-profile.section
    id="perfil-foto"
    icon="user-circle"
    :title="__('Foto de perfil')"
    :description="__('JPG, PNG ou WebP — máximo 2 MB. A prévia atualiza o cartão acima ao escolher o arquivo.')"
>
    <form
        method="post"
        action="{{ route('profile.photo.update') }}"
        enctype="multipart/form-data"
        class="space-y-4"
        x-on:change="if ($event.target.name === 'photo' && $event.target.files?.[0]) {
            photoPreview = URL.createObjectURL($event.target.files[0]);
        }"
    >
        @csrf

        <div class="serv-profile-photo-row">
            <div class="serv-profile-photo-preview" aria-hidden="true">
                <template x-if="photoPreview">
                    <img :src="photoPreview" alt="" />
                </template>
                <template x-if="!photoPreview">
                    <x-user-avatar :user="$user" size="lg" class="!h-16 !w-16 !text-lg !rounded-xl" />
                </template>
            </div>

            <label for="photo" class="serv-profile-upload">
                <span class="serv-profile-upload__inner">
                    <x-ui.icon name="user-circle" class="h-7 w-7 text-teal-600/80 dark:text-teal-400/90" />
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        {{ __('Escolher imagem') }}
                    </span>
                    <span class="serv-profile-upload__hint">{{ __('Clique ou arraste aqui') }}</span>
                </span>
                <input
                    id="photo"
                    name="photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="sr-only"
                />
            </label>
        </div>

        <x-input-error :messages="$errors->get('photo')" />

        @if ($user->hasProfilePhoto())
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer">
                <input
                    type="checkbox"
                    name="remove_photo"
                    value="1"
                    class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-900"
                    x-on:change="if ($event.target.checked) { photoPreview = null; }"
                />
                {{ __('Remover foto atual') }}
            </label>
        @endif

        <div class="serv-profile-actions">
            <x-primary-button>{{ __('Salvar foto') }}</x-primary-button>
            <x-profile.save-hint status="profile-photo-updated">{{ __('Foto atualizada.') }}</x-profile.save-hint>
        </div>
    </form>
</x-profile.section>
