<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Versão Laravel/PHP, ambiente, locale — contexto operacional.
 */
#[Lazy]
class ApplicationInsightsCard extends Card
{
    public function render(): Renderable
    {
        [$data, $time, $runAt] = $this->remember(function () {
            return [
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'env' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'timezone' => (string) config('app.timezone'),
                'locale' => (string) app()->getLocale(),
                'url' => (string) config('app.url'),
                'cache' => (string) config('cache.default'),
                'session' => (string) config('session.driver'),
                'queue' => (string) config('queue.default'),
            ];
        }, 'app');

        return View::make('livewire.pulse.application-insights-card', [
            'data' => $data,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
