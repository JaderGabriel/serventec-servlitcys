<?php

namespace App\Support\Funding;

/**
 * CSV de extrato BB em storage (download automático ou upload manual).
 */
final class BbExtratoStoragePaths
{
    public static function storageRoot(): string
    {
        $rel = trim((string) config('ieducar.funding.transfers.extrato_sources.bb_extrato.storage_path', 'funding/bb_extrato'), '/');

        return storage_path('app/'.$rel);
    }

    public static function csvFile(string $ibge, int $ano): string
    {
        return self::storageRoot().'/'.preg_replace('/\D/', '', $ibge).'_'.$ano.'.csv';
    }
}
