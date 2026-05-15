<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulUserLogin;
use App\Livewire\Pulse\ApplicationInsightsCard;
use App\Livewire\Pulse\DatabaseHealthCard;
use App\Livewire\Pulse\DiskSpaceCard;
use App\Livewire\Pulse\InstitutionTrafficCard;
use App\Livewire\Pulse\QueueAndFailuresCard;
use App\Livewire\Pulse\RedisOverviewCard;
use App\Livewire\Pulse\ServerStatusStrip;
use App\Livewire\Pulse\SyncAdminPulseCard;
use App\Models\City;
use App\Models\User;
use App\Observers\CityFundebSyncObserver;
use App\Policies\CityPolicy;
use App\Policies\UserPolicy;
use App\Services\MailConfigService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Se o ambiente estiver configurado com `REDIS_CLIENT=phpredis` mas a extensão
        // não estiver disponível, o Laravel falha com "Class \"Redis\" not found".
        // Fazemos fallback para Predis (via composer) para manter a app funcional.
        if (config('database.redis.client') === 'phpredis' && ! class_exists(\Redis::class)) {
            config(['database.redis.client' => 'predis']);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        City::observe(CityFundebSyncObserver::class);

        Livewire::component('pulse.institution-traffic-card', InstitutionTrafficCard::class);
        Livewire::component('pulse.redis-overview-card', RedisOverviewCard::class);
        Livewire::component('pulse.application-insights-card', ApplicationInsightsCard::class);
        Livewire::component('pulse.database-health-card', DatabaseHealthCard::class);
        Livewire::component('pulse.queue-and-failures-card', QueueAndFailuresCard::class);
        Livewire::component('pulse.disk-space-card', DiskSpaceCard::class);
        Livewire::component('pulse.server-status-strip', ServerStatusStrip::class);
        Livewire::component('pulse.sync-admin-pulse-card', SyncAdminPulseCard::class);

        /*
         * O Pulse regista o componente anónimo <x-pulse> com prefixo "pulse" (hash xxh128).
         * Esse caminho aponta primeiro para vendor/laravel/pulse/.../components/pulse.blade.php.
         * As cópias em resources/views/vendor/pulse/ só substituem vistas do namespace pulse::*
         * (ex.: pulse::dashboard), não o componente anónimo — por isso o layout publicado era ignorado.
         * Antecedemos o mesmo hash de namespace com a pasta da app para <x-pulse> usar a vista publicada.
         */
        $pulseAnonymousNs = hash('xxh128', 'pulse');
        $pulseLayoutDir = resource_path('views/vendor/pulse/components');
        if (is_dir($pulseLayoutDir)) {
            View::getFinder()->prependNamespace($pulseAnonymousNs, $pulseLayoutDir);
        }

        Event::listen(Login::class, LogSuccessfulUserLogin::class);

        $this->app->booted(function (): void {
            Gate::define('viewPulse', fn (?User $user): bool => $user !== null && $user->isAdmin());
            Gate::define('manageUserAudit', fn (?User $user): bool => $user !== null && $user->isAdmin());
        });

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
