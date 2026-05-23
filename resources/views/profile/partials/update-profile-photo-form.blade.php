<x-profile.section
    id="perfil-foto"
    icon="user-circle"
    :title="__('Foto de perfil')"
    :description="__('Aparece no menu e na identificação da sua conta. JPG, PNG ou WebP — máx. 2 MB.')"
>
    <form
        method="post"
        action="{{ route('profile.photo.update') }}"
        enctype="multipart/form-data"
        class="space-y-5"
        x-data="{ preview: @js($user->profilePhotoUrl()) }"
    >
        @csrf

        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
            <div class="shrink-0 relative">
                <template x-if="preview">
                    <img :src="preview" alt="" class="h-28 w-28 rounded-2xl object-cover ring-2 ring-teal-200/80 shadow-md dark:ring-teal-700/80" />
                </template>
                <template x-if="!preview">
                    <x-user-avatar :user="$user" size="xl" class="!h-28 !w-28 !text-3xl rounded-2xl ring-2 shadow-md" />
                </template>
            </div>

            <div class="flex-1 w-full min-w-0 space-y-4">
                <label
                    for="photo"
                    class="serv-profile-upload flex flex-col items-center justify-center gap-2 px-4 py-8 cursor-pointer"
                >
                    <x-ui.icon name="user-circle" class="h-8 w-8 text-teal-600/70 dark:text-teal-400/80" />
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200 text-center">
                        {{ __('Clique para escolher uma imagem') }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('ou arraste para esta área') }}</span>
                    <input
                        id="photo"
                        name="photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        class="sr-only"
                        @change="preview = $event.target.files?.[0] ? URL.createObjectURL($event.target.files[0]) : preview"
                    />
                </label>
                <x-input-error :messages="$errors->get('photo')" />

                @if ($user->hasProfilePhoto())
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer">
                        <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-900" />
                        {{ __('Remover foto atual') }}
                    </label>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
            <x-primary-button>{{ __('Salvar foto') }}</x-primary-button>
            <x-profile.save-hint status="profile-photo-updated">{{ __('Foto atualizada.') }}</x-profile.save-hint>
        </div>
    </form>
</x-profile.section>
