<?php

namespace App\Console\Commands;

use App\Services\Inep\SaebMicrodadosInepDownloader;
use App\Services\Inep\SaebMicrodadosOpenDataImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('saeb:sync-microdados
    {--year= : Ano dos microdados SAEB (ex.: 2023)}
    {--url= : URL de CSV público (dados.gov / link directo) em vez do ZIP INEP}
    {--no-merge : Substituir historico.json em vez de fundir}
    {--no-resolve-inep : Não mapear INEP→cod_escola}
    {--keep-cache : Manter pasta extraída do ZIP em storage (só aplica ao ZIP INEP)}')]
#[Description('Descarrega microdados SAEB (INEP ou CSV por URL), filtra por municípios cadastrados e grava historico.json.')]
class SaebSyncMicrodadosCommand extends Command
{
    public function handle(SaebMicrodadosOpenDataImportService $service): int
    {
        $merge = ! $this->option('no-merge');
        $resolveInep = ! $this->option('no-resolve-inep');
        $purgeExtract = ! $this->option('keep-cache');

        $url = trim((string) $this->option('url'));
        if ($url !== '') {
            $yearOpt = $this->option('year');
            $fallbackYear = is_numeric($yearOpt)
                ? max(1995, min(2100, (int) $yearOpt))
                : max(1995, (int) date('Y') - 1);
            if (SaebMicrodadosInepDownloader::isZipUrl($url)) {
                $this->info(__('A descarregar ZIP de microdados…'));
            } else {
                $this->info(__('A processar CSV remoto…'));
            }
            $result = $service->syncFromMicrodadosFormUrl($url, $merge, $resolveInep, $purgeExtract, $fallbackYear);
        } else {
            $yearOpt = $this->option('year');
            $year = is_numeric($yearOpt)
                ? max(1995, min(2100, (int) $yearOpt))
                : max(1995, (int) date('Y') - 1);
            $this->info(__('A descarregar microdados INEP :year (ZIP) …', ['year' => (string) $year]));
            $result = $service->syncFromInepZip($year, $merge, $resolveInep, $purgeExtract, null);
        }

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
