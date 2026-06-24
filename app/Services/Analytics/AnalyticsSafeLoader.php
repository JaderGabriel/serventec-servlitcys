<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Log;
use Throwable;

final class AnalyticsSafeLoader
{
    /**
     * @template T
     *
     * @param  callable(): T  $load
     * @param  T  $fallback
     * @param  list<string>  $warnings
     * @return T
     */
    public function load(callable $load, mixed $fallback, string $section, array &$warnings): mixed
    {
        try {
            return $load();
        } catch (Throwable $e) {
            Log::warning('analytics.section_load_failed', [
                'section' => $section,
                'message' => $e->getMessage(),
            ]);
            $warnings[] = __(':section: não foi possível carregar os dados (:msg).', [
                'section' => $section,
                'msg' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }
}
