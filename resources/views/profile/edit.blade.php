<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('Perfil') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-panel p-4 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-photo-form')
                </div>
            </div>

            <div class="serv-panel p-4 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="serv-panel p-4 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="serv-panel serv-panel--rose p-4 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
