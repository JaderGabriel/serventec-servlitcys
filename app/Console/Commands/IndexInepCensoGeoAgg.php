<?php

namespace App\Console\Commands;

use App\Services\Inep\InepCensoEscolaGeoAggService;
use App\Support\InepMicrodadosCadastroEscolasPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:index-inep-censo-geo-agg
    {--path= : Caminho relativo ao disco public, glob ou absoluto; sobrepõe config}'
)]
#[Description('Indexa geografia agregada (município/UF/região) a partir do CSV de microdados_ed_basica na tabela inep_censo_escola_geo_agg')]
class IndexInepCensoGeoAgg extends Command
{
    public function handle(InepCensoEscolaGeoAggService $svc): int
    {
        $pathOpt = $this->option('path');
        $rel = is_string($pathOpt) && trim($pathOpt) !== ''
            ? trim($pathOpt)
            : (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $path = InepMicrodadosCadastroEscolasPath::resolve($rel);

        if ($path === null || ! is_readable($path)) {
            $this->error('Ficheiro de microdados não encontrado ou ilegível.');
            $this->line('Valor resolvido: '.$rel);

            return self::FAILURE;
        }

        $this->info('A indexar geografia Censo a partir de: '.$path);
        $this->warn('Pode demorar vários minutos em ficheiros nacionais completos.');

        $n = $svc->indexFromMicrodadosCsv($path);
        $this->info("Concluído. Linhas indexadas: {$n}.");

        return self::SUCCESS;
    }
}
