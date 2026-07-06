<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Educacenso\EducacensoStage1ConferenceService;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('censo:analyze-educacenso-file {file : Caminho do arquivo Educacenso} {--city= : ID da cidade} {--ano= : Ano letivo} {--output=json : json|table}')]
#[Description('Analisa arquivo Educacenso (portal INEP) cruzando com i-Educar read-only.')]
final class EducacensoAnalyzeStage1Command extends Command
{
    public function handle(EducacensoStage1ConferenceService $service): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));
        if ($path === null) {
            $this->error(__('Arquivo não encontrado.'));

            return self::FAILURE;
        }

        $cityId = (int) $this->option('city');
        if ($cityId <= 0) {
            $this->error(__('Informe --city=ID'));

            return self::FAILURE;
        }

        $city = City::query()->whereKey($cityId)->first();
        if ($city === null) {
            $this->error(__('Cidade não encontrada.'));

            return self::FAILURE;
        }

        $ano = (int) ($this->option('ano') ?: date('Y'));
        $filters = new IeducarFilterState((string) $ano, null, null, null);

        $report = $service->analyze($city, $filters, $path, basename($path));

        if (($this->option('output') ?? 'json') === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->info(__('Estado: :s', ['s' => $report['status_label'] ?? '—']));
        $this->line(__('Achados: :n', ['n' => (string) ($report['findings_count'] ?? 0)]));

        return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePath(string $file): ?string
    {
        if (is_file($file) && is_readable($file)) {
            return $file;
        }

        $rel = base_path($file);
        if (is_file($rel) && is_readable($rel)) {
            return $rel;
        }

        return null;
    }
}
