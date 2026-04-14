<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulUserLogin;
use App\Models\City;
use App\Models\User;
use App\Policies\CityPolicy;
use App\Policies\UserPolicy;
use App\Services\MailConfigService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Event::listen(Login::class, LogSuccessfulUserLogin::class);

        /*
         * Em produção, o ficheiro public/hot (gerado por `npm run dev`) faz o @vite apontar
         * para o servidor de desenvolvimento (ex.: [::1]:5173), causando CORS no domínio real.
         * Remove-o para forçar o uso do manifest em public/build/.
         */
        if ($this->app->environment('production')) {
            $hot = public_path('hot');
            if (is_file($hot)) {
                @unlink($hot);
            }
        }

        try {
            if (Schema::hasTable('mail_settings')) {
                app(MailConfigService::class)->applyFromDatabase();
            }
        } catch (\Throwable) {
            // Ambiente sem driver DB ou migrações pendentes — ignorar até a app estar pronta.
        }
    }
}
