<?php

namespace App\Providers;

use App\Models\City;
use App\Models\User;
use App\Policies\CityPolicy;
use App\Policies\UserPolicy;
use App\Services\MailConfigService;
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

        try {
            if (Schema::hasTable('mail_settings')) {
                app(MailConfigService::class)->applyFromDatabase();
            }
        } catch (\Throwable) {
            // Ambiente sem driver DB ou migrações pendentes — ignorar até a app estar pronta.
        }
    }
}
