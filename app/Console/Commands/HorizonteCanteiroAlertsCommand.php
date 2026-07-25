<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\MunicipalEducationWork;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class HorizonteCanteiroAlertsCommand extends Command
{
    protected $signature = 'horizonte:canteiro-alerts
                            {--dry-run : Simular sem gravar snapshot}
                            {--pdf : Gerar PDF resumido das consultorias com alerta}';

    protected $description = 'Gera snapshot de alertas Canteiro (obras paralisadas/em execução/inacabadas) para municípios com consultoria activa';

    public function handle(): int
    {
        if (! filter_var(config('horizonte.obras.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn(__('Obras (Obrasgov) desactivado.'));

            return self::SUCCESS;
        }

        if (! filter_var(config('horizonte.obras.alerts.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn(__('Alertas Canteiro desactivados.'));

            return self::SUCCESS;
        }

        $this->info(__('Canteiro — snapshot mensal de alertas (só consultoria activa)'));

        $cities = City::query()
            ->active()
            ->whereNotNull('ibge_municipio')
            ->orderBy('name')
            ->get();

        $snapshot = [];
        $totalAlerts = 0;
        $skippedNoSetup = 0;

        foreach ($cities as $city) {
            if (! $city->hasDataSetup()) {
                $skippedNoSetup++;

                continue;
            }

            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                continue;
            }

            $paralisadas = MunicipalEducationWork::query()
                ->where('ibge_municipio', $ibge)
                ->where('situacao', 'Paralisada')
                ->count();

            $emExecucao = MunicipalEducationWork::query()
                ->where('ibge_municipio', $ibge)
                ->where('situacao', 'Em execução')
                ->count();

            $inacabadas = MunicipalEducationWork::query()
                ->where('ibge_municipio', $ibge)
                ->where('situacao', 'Inacabada')
                ->count();

            if ($paralisadas === 0 && $emExecucao === 0 && $inacabadas === 0) {
                continue;
            }

            $works = MunicipalEducationWork::query()
                ->where('ibge_municipio', $ibge)
                ->whereIn('situacao', ['Paralisada', 'Em execução', 'Inacabada'])
                ->orderByRaw("CASE WHEN situacao = 'Paralisada' THEN 1 WHEN situacao = 'Inacabada' THEN 2 ELSE 3 END")
                ->limit(5)
                ->get([
                    'id_projeto_investimento',
                    'desc_nome',
                    'situacao',
                    'sistema_resp',
                    'percentual_execucao_fisica',
                    'valor_pago',
                    'latitude',
                    'longitude',
                ])
                ->map(fn ($w) => [
                    'id' => (string) ($w->id_projeto_investimento ?? ''),
                    'nome' => mb_substr(trim((string) ($w->desc_nome ?? '')), 0, 80),
                    'situacao' => (string) ($w->situacao ?? ''),
                    'sistema' => (string) ($w->sistema_resp ?? ''),
                    'percentual' => $w->percentual_execucao_fisica !== null ? (float) $w->percentual_execucao_fisica : null,
                    'valor_pago' => $w->valor_pago !== null ? (float) $w->valor_pago : null,
                    'lat' => $w->latitude !== null ? (float) $w->latitude : null,
                    'lng' => $w->longitude !== null ? (float) $w->longitude : null,
                ])
                ->all();

            $updated = MunicipalEducationWork::query()
                ->where('ibge_municipio', $ibge)
                ->max('imported_at');

            $snapshot[$ibge] = [
                'ibge' => $ibge,
                'city_id' => (int) $city->id,
                'city_name' => (string) $city->name,
                'uf' => (string) $city->uf,
                'paralisadas' => $paralisadas,
                'em_execucao' => $emExecucao,
                'inacabadas' => $inacabadas,
                'works' => $works,
                'updated_at' => $updated !== null ? (string) $updated : null,
            ];

            $totalAlerts += $paralisadas + $emExecucao + $inacabadas;
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'total_cities' => count($snapshot),
            'total_alerts' => $totalAlerts,
            'skipped_no_setup' => $skippedNoSetup,
            'simec_painel_url' => (string) config('horizonte.obras.alerts.simec_painel_url', 'https://simec.mec.gov.br/painelObras/'),
            'note' => __('Alertas apenas para municípios com consultoria activa (i-Educar configurado). Valores financeiros são de empenho/pago Obrasgov — indicativos.'),
            'snapshot' => $snapshot,
        ];

        if ((bool) $this->option('dry-run')) {
            $this->line(__('Dry-run: :n cidade(s) com alertas, :total obra(s) relevantes (:skip sem setup).', [
                'n' => (string) count($snapshot),
                'total' => (string) $totalAlerts,
                'skip' => (string) $skippedNoSetup,
            ]));

            return self::SUCCESS;
        }

        $path = trim((string) config('horizonte.obras.alerts.snapshot_path', 'horizonte/canteiro_alerts_snapshot.json'));
        if ($path === '') {
            $this->error(__('Caminho de snapshot não configurado.'));

            return self::FAILURE;
        }

        Storage::disk('local')->put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->info(__('Snapshot gravado em storage/app/:path — :n município(s), :total obra(s).', [
            'path' => $path,
            'n' => (string) count($snapshot),
            'total' => (string) $totalAlerts,
        ]));

        if ((bool) $this->option('pdf')) {
            $this->writeConsultoriaPdf($payload);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeConsultoriaPdf(array $payload): void
    {
        $pdfPath = 'horizonte/canteiro_alerts_consultoria.pdf';
        $html = view('pdf.canteiro-alerts', [
            'payload' => $payload,
            'generatedAt' => (string) ($payload['generated_at'] ?? now()->toIso8601String()),
            'simecUrl' => (string) ($payload['simec_painel_url'] ?? ''),
            'cities' => array_values(is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : []),
        ])->render();

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            Storage::disk('local')->put($pdfPath, $pdf->output());
            $this->info(__('PDF consultoria gravado em storage/app/:path', ['path' => $pdfPath]));
        } catch (\Throwable $e) {
            $this->warn(__('PDF não gerado: :msg', ['msg' => $e->getMessage()]));
        }
    }
}
