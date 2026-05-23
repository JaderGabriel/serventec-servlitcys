<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulUserLogin;
use App\Livewire\Pulse\ApplicationInsightsCard;
use App\Livewire\Pulse\DatabaseHealthCard;
use App\Livewire\Pulse\DiskSpaceCard;
use App\Livewire\Pulse\InstitutionTrafficCard;
use App\Livewire\Pulse\MonitoringExecutiveStrip;
use App\Livewire\Pulse\MunicipalInfrastructureCard;
use App\Livewire\Pulse\QueueAndFailuresCard;
use App\Livewire\Pulse\RedisOverviewCard;
use App\Livewire\Pulse\ServerStatusStrip;
use App\Livewire\Pulse\SyncAdminPulseCard;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use App\Observers\CityFundebSyncObserver;
use App\Policies\AnalyticsReportExportPolicy;
use App\Policies\CityPolicy;
use App\Policies\UserPolicy;
use App\Services\CityDataConnection;
use App\Services\Ieducar\IeducarCityDataService;
use App\Services\MailConfigService;
use App\Support\Admin\WeeklyMassSyncCheckpoint;
use App\Support\Performance\AuthRouteRegistry;
use App\Support\Performance\RedisProbe;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
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
        RedisProbe::applyClientConfig();

        if (! class_exists(WeeklyMassSyncCheckpoint::class, false)) {
            class_alias(
                \App\Support\AdminSync\WeeklyMassSyncCheckpoint::class,
                WeeklyMassSyncCheckpoint::class,
            );
        }

        if (! class_exists(IeducarCityDataService::class, false)) {
            class_alias(
                CityDataConnection::class,
                IeducarCityDataService::class,
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AnalyticsReportExport::class, AnalyticsReportExportPolicy::class);

        City::observe(CityFundebSyncObserver::class);

        Livewire::component('pulse.institution-traffic-card', InstitutionTrafficCard::class);
        Livewire::component('pulse.redis-overview-card', RedisOverviewCard::class);
        Livewire::component('pulse.application-insights-card', ApplicationInsightsCard::class);
        Livewire::component('pulse.database-health-card', DatabaseHealthCard::class);
        Livewire::component('pulse.queue-and-failures-card', QueueAndFailuresCard::class);
        Livewire::component('pulse.disk-space-card', DiskSpaceCard::class);
        Livewire::component('pulse.server-status-strip', ServerStatusStrip::class);
        Livewire::component('pulse.sync-admin-pulse-card', SyncAdminPulseCard::class);
        Livewire::component('pulse.monitoring-executive-strip', MonitoringExecutiveStrip::class);
        Livewire::component('pulse.municipal-infrastructure-card', MunicipalInfrastructureCard::class);

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
         * Em produção, o arquivo public/hot (gerado por `npm run dev`) faz o @vite apontar
         * para o servidor de desenvolvimento (ex.: [::1]:5173), causando CORS no domínio real.
         * Remove-o para forçar o uso do manifest em public/build/.
         */
        if ($this->app->environment('production')) {
            $hot = public_path('hot');
            if (is_file($hot)) {
                @unlink($hot);
            }
        }

        if ($this->shouldApplyMailFromDatabase()) {
            try {
                $hasMailSettings = Cache::remember(
                    'bootstrap:has_mail_settings_table',
                    86400,
                    static fn (): bool => Schema::hasTable('mail_settings'),
                );

                if ($hasMailSettings) {
                    app(MailConfigService::class)->applyFromDatabase();
                }
            } catch (\Throwable) {
                // Ambiente sem driver DB ou migrações pendentes — ignorar até a app estar pronta.
            }
        }
    }

    private function shouldApplyMailFromDatabase(): bool
    {
        if (! config('performance.skip_mail_on_auth_routes', true)) {
            return true;
        }

        if (! $this->app->runningInConsole() && $this->app->bound('request')) {
            $request = $this->app->make('request');
            if ($request instanceof \Illuminate\Http\Request && AuthRouteRegistry::matches($request)) {
                return false;
            }
        }

        return true;
    }
}
