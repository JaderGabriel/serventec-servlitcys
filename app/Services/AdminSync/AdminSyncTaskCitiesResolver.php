<?php

namespace App\Services\AdminSync;

use App\Enums\AdminSyncDomain;
use App\Models\AdminSyncTask;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;

final class AdminSyncTaskCitiesResolver
{
    /**
     * Grava city_ids e city_names no payload para exibição na fila.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function enrichPayload(
        array $payload,
        ?int $cityId,
        AdminSyncDomain $domain,
        string $taskKey,
    ): array {
        $ids = self::resolveCityIds($payload, $cityId, $domain, $taskKey);
        if ($ids === []) {
            return $payload;
        }

        $payload['city_ids'] = $ids;
        $payload['city_names'] = self::namesForIds($ids);

        if (self::targetsAllCities($payload, $cityId, $domain, $taskKey)) {
            $payload['all_cities'] = true;
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public static function cityNamesForTask(AdminSyncTask $task): array
    {
        $payload = $task->payload ?? [];
        if (isset($payload['city_names']) && is_array($payload['city_names']) && $payload['city_names'] !== []) {
            return array_values(array_map('strval', $payload['city_names']));
        }

        return self::namesForIds(self::resolveCityIdsForTask($task));
    }

    /**
     * @return list<int>
     */
    public static function resolveCityIdsForTask(AdminSyncTask $task): array
    {
        return self::resolveCityIds(
            $task->payload ?? [],
            $task->city_id !== null ? (int) $task->city_id : null,
            $task->domainEnum(),
            $task->task_key,
        );
    }

    public static function targetsAllCitiesForTask(AdminSyncTask $task): bool
    {
        return self::targetsAllCities(
            $task->payload ?? [],
            $task->city_id !== null ? (int) $task->city_id : null,
            $task->domainEnum(),
            $task->task_key,
        );
    }

    public static function citiesLabelForTask(AdminSyncTask $task): string
    {
        $names = self::cityNamesForTask($task);
        if ($names === []) {
            if ($task->city_id !== null) {
                return __('Cidade #:id', ['id' => (string) $task->city_id]);
            }

            return '—';
        }

        return implode(', ', $names);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private static function resolveCityIds(
        array $payload,
        ?int $cityId,
        AdminSyncDomain $domain,
        string $taskKey,
    ): array {
        if (isset($payload['city_ids']) && is_array($payload['city_ids']) && $payload['city_ids'] !== []) {
            return array_values(array_unique(array_map('intval', $payload['city_ids'])));
        }

        if (isset($payload['city_id']) && (int) $payload['city_id'] > 0) {
            return [(int) $payload['city_id']];
        }

        if ($cityId !== null && $cityId > 0) {
            return [$cityId];
        }

        if (! self::targetsAllCities($payload, $cityId, $domain, $taskKey)) {
            return [];
        }

        return self::allCityIdsForDomain($domain);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function targetsAllCities(
        array $payload,
        ?int $cityId,
        AdminSyncDomain $domain,
        string $taskKey,
    ): bool {
        if ($cityId !== null && $cityId > 0) {
            return false;
        }

        if (! empty($payload['all_cities'])) {
            return true;
        }

        if (isset($payload['city_ids']) && is_array($payload['city_ids']) && $payload['city_ids'] !== []) {
            return false;
        }

        if (isset($payload['city_id']) && (int) $payload['city_id'] > 0) {
            return false;
        }

        $key = $domain->value.'::'.$taskKey;

        return match ($key) {
            'fundeb::sync_all_years' => true,
            'fundeb::import_bulk_year' => true,
            'geo::ieducar', 'geo::microdados', 'geo::official', 'geo::pipeline' => true,
            'geo::probe' => false,
            'pedagogical::import_official', 'pedagogical::import_urls', 'pedagogical::import_csv', 'pedagogical::import_microdados' => true,
            default => false,
        };
    }

    /**
     * @return list<int>
     */
    private static function allCityIdsForDomain(AdminSyncDomain $domain): array
    {
        if ($domain === AdminSyncDomain::Fundeb) {
            return City::query()
                ->orderBy('name')
                ->get(['id', 'ibge_municipio'])
                ->filter(static fn (City $c): bool => FundebMunicipioReferenceRepository::normalizeIbge($c->ibge_municipio) !== null)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return City::query()
            ->forAnalytics()
            ->orderBy('name')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private static function namesForIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return City::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn ($name): string => (string) $name)
            ->values()
            ->all();
    }
}
