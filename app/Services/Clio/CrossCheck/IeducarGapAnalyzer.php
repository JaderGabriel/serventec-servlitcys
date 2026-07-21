<?php

namespace App\Services\Clio\CrossCheck;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignInference;
use App\Services\CityDataConnection;
use App\Services\Educacenso\EducacensoIeducarSnapshot;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * INF-GAP — cruza escolas da coleta Clio com snapshot i-Educar (somente leitura).
 * Não usa EducacensoFileReader (TXT / CEN-01).
 */
final class IeducarGapAnalyzer
{
    public function __construct(
        private readonly CityDataConnection $cityData,
        private readonly EducacensoIeducarSnapshot $snapshot,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   message: ?string,
     *   only_in_clio: int,
     *   only_in_ieducar: int,
     *   matched: int,
     *   ieducar_matriculas: int
     * }
     */
    public function analyze(ClioCampaign $campaign): array
    {
        $city = $campaign->city;
        if ($city === null || ! $city->hasDataSetup()) {
            return [
                'ok' => false,
                'message' => __('Município sem credenciais i-Educar. Vincule a base antes do cruzamento.'),
                'only_in_clio' => 0,
                'only_in_ieducar' => 0,
                'matched' => 0,
                'ieducar_matriculas' => 0,
            ];
        }

        try {
            $this->cityData->configure($city);
            $conn = DB::connection($this->cityData->connectionName($city));
            $filters = new IeducarFilterState((string) $campaign->year, null, null, null);
            $snap = $this->snapshot->capture($conn, $city, $filters);
            $this->cityData->purge($city);
        } catch (Throwable $e) {
            $this->cityData->purge($city);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'only_in_clio' => 0,
                'only_in_ieducar' => 0,
                'matched' => 0,
                'ieducar_matriculas' => 0,
            ];
        }

        if (! ($snap['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $snap['note'] ?? __('Falha ao ler escolas i-Educar.'),
                'only_in_clio' => 0,
                'only_in_ieducar' => 0,
                'matched' => 0,
                'ieducar_matriculas' => 0,
            ];
        }

        $campaign->load('schools');
        $clioIneps = $campaign->schools->pluck('inep_code')->filter()->map(fn ($i) => $this->normalizeInep((string) $i))->filter()->unique()->values()->all();
        $ieducarByInep = $snap['schools_by_inep'] ?? [];
        $ieducarIneps = array_keys($ieducarByInep);

        $clioSet = array_fill_keys($clioIneps, true);
        $ieducarSet = array_fill_keys($ieducarIneps, true);

        $onlyClio = array_values(array_filter($clioIneps, fn (string $i) => ! isset($ieducarSet[$i])));
        $onlyIeducar = array_values(array_filter($ieducarIneps, fn (string $i) => ! isset($clioSet[$i])));
        $matched = count(array_filter($clioIneps, fn (string $i) => isset($ieducarSet[$i])));

        DB::transaction(function () use ($campaign, $onlyClio, $onlyIeducar, $matched, $snap, $ieducarByInep): void {
            ClioCampaignFinding::query()
                ->where('campaign_id', $campaign->id)
                ->where('code', 'like', 'CLIO-GAP-%')
                ->delete();

            foreach (array_slice($onlyClio, 0, 80) as $inep) {
                $school = $campaign->schools->firstWhere('inep_code', $inep);
                ClioCampaignFinding::query()->create([
                    'campaign_id' => $campaign->id,
                    'school_id' => $school?->id,
                    'code' => 'CLIO-GAP-CLIO',
                    'severity' => ClioCampaignFinding::SEVERITY_WARNING,
                    'message' => __('Escola na coleta Clio sem INEP correspondente no i-Educar.'),
                    'meta' => ['inep' => $inep],
                ]);
            }

            foreach (array_slice($onlyIeducar, 0, 80) as $inep) {
                $nome = $ieducarByInep[$inep]['nome'] ?? $inep;
                ClioCampaignFinding::query()->create([
                    'campaign_id' => $campaign->id,
                    'code' => 'CLIO-GAP-IEDUCAR',
                    'severity' => ClioCampaignFinding::SEVERITY_INFO,
                    'message' => __('Escola no i-Educar sem pasta/linha na coleta: :n', ['n' => $nome]),
                    'meta' => ['inep' => $inep, 'nome' => $nome],
                ]);
            }

            ClioCampaignInference::query()->updateOrCreate(
                ['campaign_id' => $campaign->id, 'code' => 'INF-GAP'],
                [
                    'summary' => __('Gap: :c só Clio · :i só i-Educar · :m em ambos.', [
                        'c' => count($onlyClio),
                        'i' => count($onlyIeducar),
                        'm' => $matched,
                    ]),
                    'payload' => [
                        'only_in_clio' => count($onlyClio),
                        'only_in_ieducar' => count($onlyIeducar),
                        'matched' => $matched,
                        'ieducar_matriculas' => (int) ($snap['total_matriculas'] ?? 0),
                        'only_in_clio_sample' => array_slice($onlyClio, 0, 20),
                        'only_in_ieducar_sample' => array_slice($onlyIeducar, 0, 20),
                    ],
                ],
            );

            $campaign->update(['status' => ClioCampaign::STATUS_CROSS_CHECKED]);
        });

        return [
            'ok' => true,
            'message' => null,
            'only_in_clio' => count($onlyClio),
            'only_in_ieducar' => count($onlyIeducar),
            'matched' => $matched,
            'ieducar_matriculas' => (int) ($snap['total_matriculas'] ?? 0),
        ];
    }

    private function normalizeInep(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }

        return substr($digits, 0, 8);
    }
}
