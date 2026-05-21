<section>
    <header>
        <h2 class="text-lg font-medium text-slate-900 dark:text-slate-100">
            {{ __('Foto de perfil') }}
        </h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            {{ __('Aparece ao lado do seu nome no menu. Formatos: JPG, PNG ou WebP (máx. 2 MB).') }}
        </p>
    </header>

    <form
        method="post"
        action="{{ route('profile.photo.update') }}"
        enctype="multipart/form-data"
        class="mt-6 space-y-5"
        x-data="{ preview: @js($user->profilePhotoUrl()) }"
    >
        @csrf

        <div class="flex flex-col sm:flex-row items-start gap-5">
            <div class="shrink-0">
                <template x-if="preview">
                    <img :src="preview" alt="" class="h-24 w-24 rounded-full object-cover ring-2 ring-teal-200 dark:ring-teal-700" />
                </template>
                <template x-if="!preview">
                    <x-user-avatar :user="$user" size="xl" class="ring-2" />
                </template>
            </div>

            <div class="flex-1 min-w-0 space-y-3 w-full">
                <div>
                    <x-input-label for="photo" :value="__('Escolher imagem')" />
                    <input
                        id="photo"
                        name="photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        class="mt-1 block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-teal-800 hover:file:bg-teal-100 dark:file:bg-teal-950/50 dark:file:text-teal-200"
                        @change="preview = $event.target.files?.[0] ? URL.createObjectURL($event.target.files[0]) : preview"
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('photo')" />
                </div>

                @if ($user->hasProfilePhoto())
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                        <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-900" />
                        {{ __('Remover foto actual') }}
                    </label>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Guardar foto') }}</x-primary-button>

            @if (session('status') === 'profile-photo-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm text-slate-600 dark:text-slate-400"
                >{{ __('Foto actualizada.') }}</p>
            @endif
        </div>
    </form>
</section>
